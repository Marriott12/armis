<?php
declare(strict_types=1);

/**
 * ARMIS System Health & Scalability Console
 * URL: /Armis2/admin/health.php
 */
require_once dirname(__DIR__) . '/config/scalability.php';
require_once dirname(__DIR__) . '/shared/database_connection.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . dirname($_SERVER['PHP_SELF']) . '/../login.php');
    exit;
}

$role = strtolower((string)($_SESSION['role'] ?? ''));
if (!in_array($role, ['admin'], true)) {
    http_response_code(403);
    exit('Forbidden');
}

function healthStatus(bool $ok, string $okMessage, string $failMessage, string $failLevel = 'WARNING'): array
{
    return ['status' => $ok ? 'OK' : $failLevel, 'message' => $ok ? $okMessage : $failMessage];
}

$healthChecks = [];
$scalability = [];

// 1. Database / real schema health
try {
    $db = getDbConnection();
    $db->query('SELECT 1')->fetchColumn();
    $tables = ['staff','rank','unit','appointment','appointment_type','staff_appointment','staff_promotion','branches','roles','role_modules','activity_log','honors','staff_awards','staff_medical_records','training_records'];
    $missing = [];
    $stmt = $db->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?');
    foreach ($tables as $table) {
        $stmt->execute([$table]);
        if ((int)$stmt->fetchColumn() !== 1) $missing[] = $table;
    }
    $healthChecks['database'] = healthStatus(!$missing, 'Primary database connected; all core ARMIS tables are present.', 'Missing core tables: ' . implode(', ', $missing), 'ERROR');
} catch (Throwable $e) {
    $healthChecks['database'] = ['status' => 'ERROR', 'message' => 'Database connection failed. See ARMIS logs for the exception.'];
}

// 2. PHP/runtime
$healthChecks['php_version'] = healthStatus(version_compare(PHP_VERSION, '8.0.0', '>='), 'PHP ' . PHP_VERSION, 'PHP ' . PHP_VERSION . ' is below the supported runtime target.');
$healthChecks['pdo_mysql'] = healthStatus(extension_loaded('pdo_mysql'), 'PDO MySQL extension loaded.', 'PDO MySQL extension is not loaded.', 'ERROR');
$memoryLimit = ini_get('memory_limit') ?: 'unknown';
$healthChecks['memory_limit'] = ['status' => 'OK', 'message' => 'Memory limit: ' . $memoryLimit];

// 3. Writable application directories
// The cache directory is part of the ARMIS runtime contract. Create it on
// first deployment when possible so a fresh WAMP extraction does not make
// the entire health console report ERROR merely because the directory was
// not included in the deployment archive.
$applicationDirectories = [
    dirname(__DIR__) . '/logs' => 'logs',
    dirname(__DIR__) . '/cache' => 'cache',
    dirname(__DIR__) . '/uploads' => 'uploads',
];
foreach ($applicationDirectories as $path => $name) {
    if ($name === 'cache' && !is_dir($path)) {
        @mkdir($path, 0775, true);
    }
    $existsAndWritable = is_dir($path) && is_writable($path);
    $healthChecks['writable_' . $name] = healthStatus(
        $existsAndWritable,
        "$name directory is writable.",
        "$name directory is missing or not writable.",
        'ERROR'
    );
}

// 4. Redis: real connectivity check, with file cache fallback.
$redisEnabled = ScalabilityConfig::redisEnabled();
$redisExtension = class_exists('Redis');
$redisReachable = false;
if ($redisEnabled && $redisExtension) {
    try {
        $r = new Redis();
        $r->connect(ScalabilityConfig::redisHost(), ScalabilityConfig::redisPort(), 1.5);
        if (ScalabilityConfig::redisPassword()) $r->auth(ScalabilityConfig::redisPassword());
        $redisReachable = (bool)$r->ping();
        $r->close();
    } catch (Throwable $e) {
        $redisReachable = false;
    }
}
$cacheBackend = ARMISCache::backend();
$healthChecks['cache'] = [
    'status' => $redisReachable ? 'OK' : 'INFO',
    'message' => $redisReachable
        ? 'Redis caching is active (' . ScalabilityConfig::redisHost() . ':' . ScalabilityConfig::redisPort() . ').'
        : 'Redis is not active; ARMIS is using the local file-cache fallback. Enable REDIS_ENABLED=1 after installing Redis + PHP Redis.'
];

// 5. Gzip / HTTP compression
$gzipFunction = function_exists('ob_gzhandler');
$gzipEnabled = ScalabilityConfig::ENABLE_GZIP_COMPRESSION && $gzipFunction;
$healthChecks['gzip'] = healthStatus($gzipEnabled, 'PHP gzip compression is available; Apache/mod_deflate may also compress responses.', 'PHP gzip handler is unavailable. Configure Apache mod_deflate for production compression.', 'WARNING');

// 6. CDN configuration
$cdnEnabled = ScalabilityConfig::cdnEnabled() && ScalabilityConfig::cdnBaseUrl() !== '';
$healthChecks['cdn'] = $cdnEnabled
    ? ['status' => 'OK', 'message' => 'Static asset CDN is configured: ' . ScalabilityConfig::cdnBaseUrl()]
    : ['status' => 'INFO', 'message' => 'CDN integration is implemented but disabled locally. Set CDN_ENABLED=1 and CDN_BASE_URL on the production host.'];

// 7. Read replicas
$replicas = ScalabilityConfig::readReplicaHosts();
$replicaResults = [];
foreach ($replicas as $host) {
    try {
        $replica = armisCreatePdo($host);
        $replica->query('SELECT 1')->fetchColumn();
        $replicaResults[$host] = true;
    } catch (Throwable $e) {
        $replicaResults[$host] = false;
    }
}
$healthyReplicaCount = count(array_filter($replicaResults));
$scalability['read_replicas'] = $replicaResults;
$healthChecks['read_replicas'] = !$replicas
    ? ['status' => 'INFO', 'message' => 'No read replicas configured; ARMIS safely uses the primary database.']
    : healthStatus($healthyReplicaCount === count($replicas), "$healthyReplicaCount/" . count($replicas) . ' configured read replicas are reachable.', "$healthyReplicaCount/" . count($replicas) . ' configured read replicas are reachable. Failed replicas will automatically fall back to the primary.', 'WARNING');

// 8. Load balancer readiness
$lbEnabled = ScalabilityConfig::loadBalancerEnabled();
$lbReady = !$lbEnabled || ($redisReachable && $redisEnabled);
$healthChecks['load_balancer'] = $lbEnabled
    ? healthStatus($lbReady, 'Load-balancer mode enabled and shared Redis infrastructure is reachable.', 'Load-balancer mode is enabled but shared Redis session/cache infrastructure is not ready.', 'ERROR')
    : ['status' => 'INFO', 'message' => 'Load balancing is implemented as a deployment-ready configuration but disabled for this single WAMP instance.'];

// 9. Actual DB telemetry
$dbStats = ['staff' => null, 'active_staff' => null];
if (isset($db) && $db instanceof PDO) {
    try {
        $dbStats['staff'] = (int)$db->query('SELECT COUNT(*) FROM staff')->fetchColumn();
        $dbStats['active_staff'] = (int)$db->query("SELECT COUNT(*) FROM staff WHERE svcStatus='Active'")->fetchColumn();
    } catch (Throwable $e) {}
}

// 10. Disk
$root = dirname(__DIR__);
$diskFree = @disk_free_space($root);
$diskTotal = @disk_total_space($root);
$diskUsedPercent = ($diskFree !== false && $diskTotal) ? (($diskTotal - $diskFree) / $diskTotal * 100) : null;
$healthChecks['disk_space'] = $diskUsedPercent === null
    ? ['status' => 'INFO', 'message' => 'Disk statistics unavailable.']
    : ['status' => $diskUsedPercent < 80 ? 'OK' : ($diskUsedPercent < 90 ? 'WARNING' : 'ERROR'), 'message' => sprintf('Disk usage %.1f%%; %.1f GB free.', $diskUsedPercent, $diskFree / 1073741824)];

$overallStatus = 'OK';
foreach ($healthChecks as $check) {
    if ($check['status'] === 'ERROR') { $overallStatus = 'ERROR'; break; }
    if ($check['status'] === 'WARNING') $overallStatus = 'WARNING';
}

$pageTitle = 'System Health & Scalability';
$moduleName = 'System Admin';
$moduleIcon = 'heartbeat';
$currentPage = 'health';
require_once __DIR__ . '/includes/sidebar_nav.php';
include dirname(__DIR__) . '/shared/header.php';
include dirname(__DIR__) . '/shared/sidebar.php';
?>
<div class="content-wrapper with-sidebar">
<div class="container-fluid"><div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="section-title"><i class="fas fa-heartbeat"></i> System Health & Scalability</h1>
        <div>
            <span class="badge bg-<?php echo $overallStatus === 'OK' ? 'success' : ($overallStatus === 'WARNING' ? 'warning' : 'danger'); ?> fs-6">System: <?php echo $overallStatus; ?></span>
            <button onclick="location.reload()" class="btn btn-outline-secondary ms-2"><i class="fas fa-sync"></i> Refresh</button>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-3 col-md-6 mb-4"><div class="card dashboard-card h-100"><div class="card-body text-center"><i class="fas fa-database fa-3x text-primary"></i><h5 class="mt-3">Staff Records</h5><h3><?php echo $dbStats['staff'] === null ? '—' : number_format($dbStats['staff']); ?></h3><small class="text-muted">Actual database count</small></div></div></div>
        <div class="col-lg-3 col-md-6 mb-4"><div class="card dashboard-card h-100"><div class="card-body text-center"><i class="fas fa-user-check fa-3x text-success"></i><h5 class="mt-3">Active Staff</h5><h3><?php echo $dbStats['active_staff'] === null ? '—' : number_format($dbStats['active_staff']); ?></h3><small class="text-muted">svcStatus = Active</small></div></div></div>
        <div class="col-lg-3 col-md-6 mb-4"><div class="card dashboard-card h-100"><div class="card-body text-center"><i class="fas fa-memory fa-3x text-warning"></i><h5 class="mt-3">Memory</h5><h3><?php echo number_format(memory_get_usage(true) / 1048576, 1); ?> MB</h3><small class="text-muted">Current PHP request</small></div></div></div>
        <div class="col-lg-3 col-md-6 mb-4"><div class="card dashboard-card h-100"><div class="card-body text-center"><i class="fas fa-server fa-3x text-info"></i><h5 class="mt-3">Cache Backend</h5><h3><?php echo strtoupper(htmlspecialchars($cacheBackend)); ?></h3><small class="text-muted">Redis preferred, file fallback</small></div></div></div>
    </div>

    <div class="card dashboard-card mb-4"><div class="card-header"><h5 class="mb-0"><i class="fas fa-stethoscope"></i> Live Health Checks</h5></div><div class="card-body table-responsive"><table class="table table-hover align-middle"><thead><tr><th>Component</th><th>Status</th><th>Details</th></tr></thead><tbody>
    <?php foreach ($healthChecks as $component => $check): ?>
    <tr><td><i class="fas fa-cog me-2"></i><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $component))); ?></td><td><span class="badge bg-<?php echo $check['status']==='OK'?'success':($check['status']==='WARNING'?'warning':($check['status']==='ERROR'?'danger':'info')); ?>"><?php echo $check['status']; ?></span></td><td><?php echo htmlspecialchars($check['message']); ?></td></tr>
    <?php endforeach; ?>
    </tbody></table></div></div>

    <div class="row">
      <div class="col-lg-6 mb-4"><div class="card dashboard-card h-100"><div class="card-header"><h5 class="mb-0"><i class="fas fa-rocket"></i> Implemented Optimizations</h5></div><div class="card-body">
        <ul class="list-group list-group-flush">
          <li class="list-group-item"><i class="fas fa-check-circle text-success me-2"></i>Persistent PDO connections with primary/read-replica routing hooks</li>
          <li class="list-group-item"><i class="fas fa-check-circle text-success me-2"></i>Redis cache integration with secure file-cache fallback</li>
          <li class="list-group-item"><i class="fas fa-check-circle text-success me-2"></i>Gzip compression support and HTTP security headers</li>
          <li class="list-group-item"><i class="fas fa-check-circle text-success me-2"></i>CDN asset URL integration via environment configuration</li>
          <li class="list-group-item"><i class="fas fa-check-circle text-success me-2"></i>Performance logging with real request time and memory data</li>
        </ul>
      </div></div></div>
      <div class="col-lg-6 mb-4"><div class="card dashboard-card h-100"><div class="card-header"><h5 class="mb-0"><i class="fas fa-cloud-upload-alt"></i> Production Scale Readiness</h5></div><div class="card-body">
        <div class="alert alert-info"><strong>Local WAMP:</strong> Redis, CDN, replicas and load balancing remain optional and are intentionally not enabled until the supporting infrastructure exists.</div>
        <ol class="mb-0">
          <li>Install Redis server + PHP Redis extension, then set <code>REDIS_ENABLED=1</code>.</li>
          <li>Point <code>CDN_BASE_URL</code> to the production static-asset host and enable <code>CDN_ENABLED=1</code>.</li>
          <li>Add MySQL hosts to <code>DB_READ_REPLICAS</code>; read paths can use <code>getReadDbConnection()</code>.</li>
          <li>Place ARMIS behind a load balancer only after shared Redis/session infrastructure is ready.</li>
        </ol>
      </div></div></div>
    </div>

    <div class="card dashboard-card mb-4"><div class="card-header"><h5 class="mb-0"><i class="fas fa-code"></i> Runtime Configuration</h5></div><div class="card-body"><div class="table-responsive"><table class="table table-sm"><tbody>
      <tr><th>Redis</th><td><?php echo $redisEnabled ? 'Enabled' : 'Disabled'; ?></td><td><?php echo htmlspecialchars(ScalabilityConfig::redisHost() . ':' . ScalabilityConfig::redisPort()); ?></td></tr>
      <tr><th>CDN</th><td><?php echo $cdnEnabled ? 'Enabled' : 'Disabled'; ?></td><td><?php echo htmlspecialchars(ScalabilityConfig::cdnBaseUrl() ?: 'Local assets'); ?></td></tr>
      <tr><th>Read replicas</th><td><?php echo count($replicas); ?></td><td><?php echo $replicas ? htmlspecialchars(implode(', ', $replicas)) : 'Primary database only'; ?></td></tr>
      <tr><th>Load balancer</th><td><?php echo $lbEnabled ? 'Enabled' : 'Disabled'; ?></td><td>Shared-session requirement enforced</td></tr>
      <tr><th>Compression</th><td><?php echo $gzipEnabled ? 'Available' : 'Apache recommended'; ?></td><td>Use mod_deflate for static assets</td></tr>
    </tbody></table></div></div></div>
</div></div></div>
<script>setTimeout(function(){ location.reload(); }, 30000);</script>
<?php include dirname(__DIR__) . '/shared/footer.php'; ?>
