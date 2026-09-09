<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include configuration and database
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/shared/database_connection.php';

// Include RBAC system
require_once dirname(__DIR__) . '/shared/rbac.php';

// Initialize global database connection
try {
    $pdo = getDbConnection();
} catch (Exception $e) {
    error_log("Database connection failed in admin/security.php: " . $e->getMessage());
    die("Database connection failed. Please check your configuration.");
}

$pageTitle = "Security Center";
$moduleName = "Security Admin";
$moduleIcon = "shield-alt";
$currentPage = "security";

require_once __DIR__ . '/includes/sidebar_nav.php';

// Check if user is logged in and has admin privileges
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . dirname($_SERVER['PHP_SELF']) . '/../login.php');
    exit();
}

// Check if user has access to admin module
requireModuleAccess('admin');
require_once dirname(__DIR__) . '/shared/csrf.php';

// --- Action handlers (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    require_csrf();
    $securityMessage = '';
    $securityMessageType = 'success';

    switch ($_POST['action']) {
        case 'toggle_lockdown':
            $newState = ($_POST['state'] ?? '') === '1' ? '1' : '0';
            try {
                $pdo->prepare(
                    "INSERT INTO system_settings (setting_key, setting_value, updated_by) VALUES ('EMERGENCY_LOCKDOWN', ?, ?)
                     ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = VALUES(updated_by)"
                )->execute([$newState, $_SESSION['user_id'] ?? null]);
                $securityMessage = $newState === '1'
                    ? 'Emergency lockdown ACTIVATED. Only administrators can log in until this is lifted.'
                    : 'Emergency lockdown lifted. Normal login is restored.';
                logAccess('admin', 'security_lockdown_toggle', true);
            } catch (PDOException $e) {
                $securityMessage = 'Could not update lockdown state — has the system_settings migration been run?';
                $securityMessageType = 'danger';
            }
            break;

        case 'block_ip':
            $ip = trim($_POST['ip'] ?? '');
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                try {
                    $pdo->prepare('INSERT IGNORE INTO blocked_ips (ip_address, reason, blocked_by) VALUES (?, ?, ?)')
                        ->execute([$ip, trim($_POST['reason'] ?? '') ?: null, $_SESSION['user_id'] ?? null]);
                    $securityMessage = "IP $ip blocked.";
                    logAccess('admin', 'security_ip_blocked', true);
                } catch (PDOException $e) {
                    $securityMessage = 'Could not block IP — has the blocked_ips migration been run?';
                    $securityMessageType = 'danger';
                }
            } else {
                $securityMessage = 'Invalid IP address.';
                $securityMessageType = 'danger';
            }
            break;

        case 'unblock_ip':
            $pdo->prepare('DELETE FROM blocked_ips WHERE id = ?')->execute([(int) ($_POST['id'] ?? 0)]);
            $securityMessage = 'IP unblocked.';
            logAccess('admin', 'security_ip_unblocked', true);
            break;

        case 'terminate_session':
            $svcNo = trim($_POST['svcNo'] ?? '');
            if ($svcNo !== '') {
                try {
                    $pdo->prepare(
                        'INSERT INTO forced_logouts (svcNo, forced_by) VALUES (?, ?)
                         ON DUPLICATE KEY UPDATE forced_at = NOW(), forced_by = VALUES(forced_by)'
                    )->execute([$svcNo, $_SESSION['user_id'] ?? null]);
                    $securityMessage = "Session for $svcNo will be terminated on their next request.";
                    logAccess('admin', 'security_session_terminated', true);
                } catch (PDOException $e) {
                    $securityMessage = 'Could not terminate session — has the forced_logouts migration been run?';
                    $securityMessageType = 'danger';
                }
            }
            break;
    }
    $_SESSION['security_flash'] = ['message' => $securityMessage, 'type' => $securityMessageType];
    header('Location: /Armis2/admin/security.php');
    exit;
}
$securityFlash = $_SESSION['security_flash'] ?? null;
unset($_SESSION['security_flash']);

// --- Security check (GET, returns JSON) ---
// Real checks, not a fake "scan complete" — every number here comes
// from a genuine query.
if (isset($_GET['action']) && $_GET['action'] === 'scan') {
    header('Content-Type: application/json');
    require_once dirname(__DIR__) . '/shared/access_log_reader.php';
    $failedLoginCount24h = count(array_filter(
        readAccessLogEntries(time() - 86400),
        fn($e) => empty($e['success'])
    ));
    // Usernames seen anywhere in the app in the last 90 days — used
    // below to find accounts with no activity in 90+ days. Built from
    // access.log (app-wide), not the activity_log DB table (which only
    // reflects admin_branch usage and would flag nearly every account
    // as "stale" regardless of real activity elsewhere).
    $recentlyActiveUsernames = array_unique(array_filter(array_map(
        fn($e) => $e['username'] ?? null,
        array_filter(readAccessLogEntries(time() - (90 * 86400)), fn($e) => !empty($e['success']))
    )));
    $staleCount = 0;
    $activeAccountsStmt = $pdo->query("SELECT username FROM staff WHERE accStatus = 'Active' AND username IS NOT NULL AND username != ''");
    foreach ($activeAccountsStmt->fetchAll(PDO::FETCH_COLUMN) as $username) {
        if (!in_array($username, $recentlyActiveUsernames, true)) {
            $staleCount++;
        }
    }
    echo json_encode([
        'first_login_pending' => (int) $pdo->query("SELECT COUNT(*) FROM staff WHERE isFirstLogin = 1")->fetchColumn(),
        'suspended_accounts' => (int) $pdo->query("SELECT COUNT(*) FROM staff WHERE accStatus = 'Suspended'")->fetchColumn(),
        'stale_accounts' => $staleCount,
        'failed_logins_24h' => $failedLoginCount24h,
    ]);
    logAccess('admin', 'security_scan', true);
    exit;
}

// Log access
logAccess('admin', 'security_view', true);

// FIX: previously all hardcoded fake data ("would come from actual
// security monitoring in production"). Real sources now:
//   - failed_logins/recent alerts: logs/access.log (JSON lines,
//     success:false entries) — login failures aren't in the DB.
//   - locked_accounts: staff.accStatus = 'Suspended' (a real enum value).
//   - active_sessions/active users: distinct users active in
//     activity_log within the last SESSION_TIMEOUT window — the same
//     definition the app itself uses for "still logged in" (there's
//     no per-session DB table; PHP's sessions are file-based).
//
// One function, used both for the full page render below and for the
// ?action=stats polling endpoint (real live auto-refresh, same
// pattern as the rest of the app's dashboards) — so both always agree
// instead of two copies of this logic drifting apart.
function computeSecurityData(PDO $pdo): array {
    // See shared/access_log_reader.php for why logs/access.log (not
    // the activity_log DB table) is the right source here — that table
    // is only ever written to by admin_branch's logActivity(), so
    // querying it for "who's active right now" undercounts (or shows
    // 0) since it misses every other module.
    require_once dirname(__DIR__) . '/shared/access_log_reader.php';

    $failCutoff = time() - 86400; // last 24h
    $failedLogins = array_values(array_filter(
        readAccessLogEntries($failCutoff),
        fn($e) => empty($e['success'])
    ));
    $failedLogins = array_reverse($failedLogins); // most recent first

    $activeUserRows = getActiveUsersFromAccessLog((int) SESSION_TIMEOUT);

    $lockedAccountsCount = (int) $pdo->query("SELECT COUNT(*) FROM staff WHERE accStatus = 'Suspended'")->fetchColumn();

    // Repeated failures (3+) from the same username in the window count
    // as a higher-severity "alert" — a genuine, if simple, brute-force signal.
    $attemptCounts = [];
    foreach ($failedLogins as $entry) {
        $u = $entry['username'] ?? 'unknown';
        $attemptCounts[$u] = ($attemptCounts[$u] ?? 0) + 1;
    }

    return compact('failedLogins', 'lockedAccountsCount', 'activeUserRows', 'attemptCounts');
}

// --- Live stats (GET, returns JSON) ---
// Backs the auto-refreshing stat cards at the top of the page.
if (isset($_GET['action']) && $_GET['action'] === 'stats') {
    header('Content-Type: application/json');
    $d = computeSecurityData($pdo);
    echo json_encode([
        'failed_logins' => count($d['failedLogins']),
        'locked_accounts' => $d['lockedAccountsCount'],
        'active_sessions' => count($d['activeUserRows']),
        'security_alerts' => count(array_filter($d['attemptCounts'], fn($c) => $c >= 3)) + $d['lockedAccountsCount'],
    ]);
    exit;
}

$sec = computeSecurityData($pdo);
$failedLogins = $sec['failedLogins'];
$lockedAccountsCount = $sec['lockedAccountsCount'];
$activeUserRows = $sec['activeUserRows'];
$attemptCounts = $sec['attemptCounts'];

$securityStats = [
    'failed_logins' => count($failedLogins),
    'locked_accounts' => $lockedAccountsCount,
    'active_sessions' => count($activeUserRows),
    'security_alerts' => count(array_filter($attemptCounts, fn($c) => $c >= 3)) + $lockedAccountsCount,
];

$recentAlerts = [];
foreach (array_slice($failedLogins, 0, 20) as $entry) {
    $u = $entry['username'] ?? 'unknown';
    $recentAlerts[] = [
        'time' => $entry['timestamp'] ?? '',
        'type' => 'Failed Login',
        'user' => $u,
        'ip' => $entry['ip'] ?? '',
        'severity' => ($attemptCounts[$u] ?? 0) >= 3 ? 'high' : 'medium',
    ];
}

$activeSessions = array_map(function ($row) {
    return [
        'svcNo' => $row['user_id'],
        'user' => $row['username'],
        'login_time' => $row['last_seen'],
        'ip' => $row['last_ip'] ?? '',
        'status' => (strtotime($row['last_seen']) > time() - 300) ? 'active' : 'idle',
    ];
}, $activeUserRows);

// Currently blocked IPs, for the "Manage Blocked IPs" panel.
$blockedIps = [];
try {
    $blockedIps = $pdo->query("
        SELECT bi.id, bi.ip_address, bi.reason, bi.created_at, s.fName, s.lName
        FROM blocked_ips bi LEFT JOIN staff s ON bi.blocked_by = s.svcNo
        ORDER BY bi.created_at DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('blocked_ips read failed (has the migration been run?): ' . $e->getMessage());
}
$lockdownActive = false;
try {
    $lockdownActive = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'EMERGENCY_LOCKDOWN'")->fetchColumn() === '1';
} catch (PDOException $e) {
    error_log('EMERGENCY_LOCKDOWN read failed: ' . $e->getMessage());
}

include dirname(__DIR__) . '/shared/header.php';
include dirname(__DIR__) . '/shared/sidebar.php';
?>

<!-- Main Content -->
<div class="content-wrapper with-sidebar">
    <div class="container-fluid">
        <div class="main-content">
            <!-- Header Section -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h1 class="admin-section-title">
                                <i class="fas fa-shield-alt text-primary"></i> Security Center
                            </h1>
                            <p class="text-muted mb-0">Monitor system security and manage access controls</p>
                            <small class="text-muted" id="statsLastUpdated"></small>
                        </div>
                        <div>
                            <div class="btn-group" role="group">
                                <button class="btn btn-warning" onclick="viewSecurityLog()">
                                    <i class="fas fa-file-alt"></i> Security Log
                                </button>
                                <button class="btn <?= $lockdownActive ? 'btn-outline-danger' : 'btn-danger' ?>" onclick="lockdownMode()">
                                    <i class="fas fa-lock"></i> <?= $lockdownActive ? 'Lift Lockdown' : 'Emergency Lockdown' ?>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($securityFlash): ?>
            <div class="alert alert-<?= htmlspecialchars($securityFlash['type']) ?> alert-dismissible fade show">
                <?= htmlspecialchars($securityFlash['message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <?php if ($lockdownActive): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle"></i> <strong>Emergency lockdown is ACTIVE.</strong> Only administrators can currently log in.
            </div>
            <?php endif; ?>

            <!-- Security Statistics -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card bg-warning text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="card-title">Failed Logins</h6>
                                    <h4 class="mb-0" id="stat-failed_logins"><?= number_format($securityStats['failed_logins']) ?></h4>
                                    <small>Last 24 hours</small>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-exclamation-triangle fa-2x opacity-75"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-danger text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="card-title">Locked Accounts</h6>
                                    <h4 class="mb-0" id="stat-locked_accounts"><?= number_format($securityStats['locked_accounts']) ?></h4>
                                    <small>Currently locked</small>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-user-lock fa-2x opacity-75"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-success text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="card-title">Active Sessions</h6>
                                    <h4 class="mb-0" id="stat-active_sessions"><?= number_format($securityStats['active_sessions']) ?></h4>
                                    <small>Online users</small>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-users fa-2x opacity-75"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-info text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="card-title">Security Alerts</h6>
                                    <h4 class="mb-0" id="stat-security_alerts"><?= number_format($securityStats['security_alerts']) ?></h4>
                                    <small>Requires attention</small>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-bell fa-2x opacity-75"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Security Alerts -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-exclamation-circle"></i> Recent Security Alerts
                    </h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Time</th>
                                    <th>Alert Type</th>
                                    <th>User</th>
                                    <th>IP Address</th>
                                    <th>Severity</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentAlerts as $alert): ?>
                                <tr>
                                    <td><?= date('M j, Y H:i', strtotime($alert['time'])) ?></td>
                                    <td><?= htmlspecialchars($alert['type']) ?></td>
                                    <td>
                                        <code><?= htmlspecialchars($alert['user']) ?></code>
                                    </td>
                                    <td><?= htmlspecialchars($alert['ip']) ?></td>
                                    <td>
                                        <?php
                                        $badgeClass = $alert['severity'] === 'high' ? 'danger' : 
                                                     ($alert['severity'] === 'medium' ? 'warning' : 'info');
                                        ?>
                                        <span class="badge bg-<?= $badgeClass ?>"><?= ucfirst($alert['severity']) ?></span>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <form method="POST" style="display:inline;">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="block_ip">
                                                <input type="hidden" name="ip" value="<?= htmlspecialchars($alert['ip']) ?>">
                                                <input type="hidden" name="reason" value="Repeated failed logins as <?= htmlspecialchars($alert['user']) ?>">
                                                <button type="submit" class="btn btn-outline-warning" title="Block this IP" onclick="return confirm('Block IP <?= htmlspecialchars($alert['ip']) ?>?');">
                                                    <i class="fas fa-ban"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($recentAlerts)): ?>
                                <tr><td colspan="6" class="text-center text-muted">No failed login attempts in the last 24 hours.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Active Sessions -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-users"></i> Active User Sessions
                    </h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Last Activity</th>
                                    <th>IP Address</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($activeSessions as $session): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($session['user'] ?? $session['svcNo']) ?></strong>
                                    </td>
                                    <td><?= date('M j, Y H:i', strtotime($session['login_time'])) ?></td>
                                    <td><?= htmlspecialchars($session['ip']) ?></td>
                                    <td>
                                        <?php
                                        $statusClass = $session['status'] === 'active' ? 'success' : 'warning';
                                        ?>
                                        <span class="badge bg-<?= $statusClass ?>"><?= ucfirst($session['status']) ?></span>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('Terminate this session? They will be logged out on their next request.');">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="terminate_session">
                                                <input type="hidden" name="svcNo" value="<?= htmlspecialchars($session['svcNo']) ?>">
                                                <button type="submit" class="btn btn-outline-danger" title="Terminate session">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($activeSessions)): ?>
                                <tr><td colspan="5" class="text-center text-muted">No users active in the last <?= (int) (SESSION_TIMEOUT / 60) ?> minutes.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Blocked IPs -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-ban"></i> Blocked IP Addresses
                    </h5>
                </div>
                <div class="card-body">
                    <form method="POST" class="row g-2 mb-3">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="block_ip">
                        <div class="col-md-4">
                            <input type="text" name="ip" class="form-control" placeholder="IP address (e.g. 192.168.1.100)" required>
                        </div>
                        <div class="col-md-5">
                            <input type="text" name="reason" class="form-control" placeholder="Reason (optional)">
                        </div>
                        <div class="col-md-3">
                            <button type="submit" class="btn btn-warning w-100"><i class="fas fa-ban"></i> Block IP</button>
                        </div>
                    </form>
                    <div class="table-responsive">
                        <table class="table table-sm table-striped">
                            <thead><tr><th>IP Address</th><th>Reason</th><th>Blocked By</th><th>Blocked At</th><th>Actions</th></tr></thead>
                            <tbody>
                                <?php foreach ($blockedIps as $bip): ?>
                                <tr>
                                    <td><code><?= htmlspecialchars($bip['ip_address']) ?></code></td>
                                    <td><?= htmlspecialchars($bip['reason'] ?? '') ?></td>
                                    <td><?= htmlspecialchars(trim(($bip['fName'] ?? '') . ' ' . ($bip['lName'] ?? '')) ?: 'Unknown') ?></td>
                                    <td><?= date('M j, Y H:i', strtotime($bip['created_at'])) ?></td>
                                    <td>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Unblock this IP?');">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="unblock_ip">
                                            <input type="hidden" name="id" value="<?= (int) $bip['id'] ?>">
                                            <button type="submit" class="btn btn-outline-secondary btn-sm">Unblock</button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($blockedIps)): ?>
                                <tr><td colspan="5" class="text-center text-muted">No IPs currently blocked.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Security Tools -->
            <div class="row">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-tools"></i> Security Tools
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="list-group list-group-flush">
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-1">Password Policy Audit</h6>
                                        <small class="text-muted">Check password compliance</small>
                                    </div>
                                    <button class="btn btn-outline-primary btn-sm" onclick="auditPasswords()">
                                        Run Audit
                                    </button>
                                </div>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-1">Access Log Analysis</h6>
                                        <small class="text-muted">Analyze user access patterns</small>
                                    </div>
                                    <button class="btn btn-outline-info btn-sm" onclick="analyzeAccessLogs()">
                                        Analyze
                                    </button>
                                </div>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-1">Permission Review</h6>
                                        <small class="text-muted">Review user permissions</small>
                                    </div>
                                    <button class="btn btn-outline-warning btn-sm" onclick="reviewPermissions()">
                                        Review
                                    </button>
                                </div>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-1">Security Scan</h6>
                                        <small class="text-muted">Account &amp; activity health check</small>
                                    </div>
                                    <button class="btn btn-outline-danger btn-sm" onclick="securityScan()">
                                        Scan
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-cog"></i> Security Configuration
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="list-group list-group-flush">
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-1">IP Whitelist <span class="badge bg-secondary">Not built</span></h6>
                                        <small class="text-muted">A much higher-risk change than IP blocking (below) — misconfiguring it can lock everyone out. Needs a deliberate decision before building.</small>
                                    </div>
                                    <button class="btn btn-outline-secondary btn-sm" onclick="manageWhitelist()">
                                        Details
                                    </button>
                                </div>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-1">Firewall Rules <span class="badge bg-secondary">Not applicable</span></h6>
                                        <small class="text-muted">Network-level, outside what this PHP app can configure — use your server/host's firewall.</small>
                                    </div>
                                    <button class="btn btn-outline-secondary btn-sm" onclick="configureFirewall()">
                                        Details
                                    </button>
                                </div>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-1">Two-Factor Auth <span class="badge bg-secondary">Not built</span></h6>
                                        <small class="text-muted">Needs a real enrollment/verification flow — a project of its own.</small>
                                    </div>
                                    <button class="btn btn-outline-secondary btn-sm" onclick="configure2FA()">
                                        Details
                                    </button>
                                </div>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-1">Session Management</h6>
                                        <small class="text-muted">Timeout is live and enforced app-wide — configure it here.</small>
                                    </div>
                                    <button class="btn btn-outline-warning btn-sm" onclick="configureSessions()">
                                        Configure
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Live-refreshing stat cards — same pattern as the rest of the app's
// dashboards (e.g. admin_branch). Polls ?action=stats, which shares
// the exact same computeSecurityData() the initial page render uses.
async function refreshSecurityStats() {
    try {
        const res = await fetch('/Armis2/admin/security.php?action=stats');
        if (!res.ok) return;
        const data = await res.json();
        document.getElementById('stat-failed_logins').textContent = data.failed_logins.toLocaleString();
        document.getElementById('stat-locked_accounts').textContent = data.locked_accounts.toLocaleString();
        document.getElementById('stat-active_sessions').textContent = data.active_sessions.toLocaleString();
        document.getElementById('stat-security_alerts').textContent = data.security_alerts.toLocaleString();
        document.getElementById('statsLastUpdated').textContent = 'Updated ' + new Date().toLocaleTimeString();
    } catch (e) {
        // Silent — a failed poll shouldn't interrupt the page, just retry next interval.
    }
}
refreshSecurityStats();
setInterval(refreshSecurityStats, 30000);

function viewSecurityLog() {
    window.location.href = '/Armis2/admin/reports.php?export=access_log&format=csv&range=30days';
}

async function lockdownMode() {
    const activating = <?= $lockdownActive ? 'false' : 'true' ?>;
    const msg = activating
        ? 'WARNING: This will prevent all non-administrator logins until lifted. Continue?'
        : 'Lift the emergency lockdown and restore normal login?';
    if (!confirm(msg)) return;
    const res = await fetch('/Armis2/admin/security.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ action: 'toggle_lockdown', state: activating ? '1' : '0' }),
    });
    location.reload();
}

function auditPasswords() {
    window.location.href = '/Armis2/admin/reports.php?export=password_audit&format=csv&range=1year';
}

function analyzeAccessLogs() {
    window.location.href = '/Armis2/admin/reports.php?export=access_log&format=csv&range=30days';
}

function reviewPermissions() {
    window.location.href = '/Armis2/admin/reports.php?export=user_permissions&format=csv&range=30days';
}

async function securityScan() {
    if (!confirm('Run a security check across accounts and recent activity?')) return;
    armisNotifications.info('Security check', 'Running...');
    try {
        const res = await fetch('/Armis2/admin/security.php?action=scan');
        const data = await res.json();
        armisNotifications.warning(
            'Security check complete',
            `${data.first_login_pending} account(s) never completed first-login password change. ` +
            `${data.suspended_accounts} suspended account(s). ` +
            `${data.stale_accounts} active account(s) with no activity in 90+ days. ` +
            `${data.failed_logins_24h} failed login attempt(s) in the last 24h.`
        );
    } catch (e) {
        armisNotifications.error('Security check failed', 'Could not complete the check.');
    }
}

function manageWhitelist() {
    armisNotifications.info('Not yet available', 'IP allow-listing (restricting access to only approved IPs) is a larger, higher-risk change than blocking — not built yet. IP blocking (the "Block IP" button on each alert) is available now.');
}

function configureFirewall() {
    armisNotifications.info('Not applicable here', 'Firewall rules are network/server-level infrastructure, outside what this PHP application can configure. Use your server or hosting provider\'s firewall.');
}

function configure2FA() {
    armisNotifications.info('Not yet available', 'Two-factor authentication requires a new enrollment and verification flow — not built yet.');
}

function configureSessions() {
    window.location.href = '/Armis2/admin/settings.php#session_timeout';
}
</script>

<?php include dirname(__DIR__) . '/shared/footer.php'; ?>
