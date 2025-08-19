<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include RBAC system
require_once dirname(__DIR__) . '/shared/rbac.php';
require_once dirname(__DIR__) . '/shared/database_connection.php';

$pageTitle = "Database Management";
$moduleName = "System Admin";
$moduleIcon = "database";
$currentPage = "database";

$sidebarLinks = [
    ['title' => 'Dashboard', 'url' => '/Armis2/admin/index.php', 'icon' => 'tachometer-alt', 'page' => 'dashboard'],
    ['title' => 'User Management', 'url' => '/Armis2/admin/users.php', 'icon' => 'users', 'page' => 'users'],
    ['title' => 'System Settings', 'url' => '/Armis2/admin/settings.php', 'icon' => 'cogs', 'page' => 'settings'],
    ['title' => 'Database Management', 'url' => '/Armis2/admin/database.php', 'icon' => 'database', 'page' => 'database'],
    ['title' => 'Security Center', 'url' => '/Armis2/admin/security.php', 'icon' => 'shield-alt', 'page' => 'security'],
    ['title' => 'System Reports', 'url' => '/Armis2/admin/reports.php', 'icon' => 'chart-bar', 'page' => 'reports']
];

// Check if user is logged in and has admin privileges
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . dirname($_SERVER['PHP_SELF']) . '/../login.php');
    exit();
}

// Check if user has access to admin module
requireModuleAccess('admin');

// Log access
logAccess('admin', 'database_view', true);

// Initialize variables
$message = '';
$messageType = '';
$dbStats = [];
$tables = [];
$connectionStatus = false;

try {
    $pdo = getDbConnection();
    $connectionStatus = true;
    
    // Get database statistics
    $stmt = $pdo->query("SELECT 
        COUNT(*) as total_tables
        FROM information_schema.tables 
        WHERE table_schema = DATABASE()");
    $result = $stmt->fetch();
    $dbStats['total_tables'] = $result['total_tables'];
    
    // Get database size
    $stmt = $pdo->query("SELECT 
        ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS db_size_mb
        FROM information_schema.tables 
        WHERE table_schema = DATABASE()");
    $result = $stmt->fetch();
    $dbStats['size_mb'] = $result['db_size_mb'] ?? 0;
    
    // Get table information
    $stmt = $pdo->query("SELECT 
        table_name,
        table_rows,
        ROUND(((data_length + index_length) / 1024 / 1024), 2) AS size_mb,
        table_comment
        FROM information_schema.tables 
        WHERE table_schema = DATABASE()
        ORDER BY (data_length + index_length) DESC");
    $tables = $stmt->fetchAll();
    
    // Get record counts for key tables
    $keyTables = ['staff', 'ranks', 'units', 'corps'];
    $recordCounts = [];
    
    foreach ($keyTables as $table) {
        try {
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM `{$table}`");
            $result = $stmt->fetch();
            $recordCounts[$table] = $result['count'];
        } catch (Exception $e) {
            $recordCounts[$table] = 'N/A';
        }
    }
    
} catch (Exception $e) {
    $message = "Database connection failed: " . $e->getMessage();
    $messageType = "danger";
    error_log("Database management error: " . $e->getMessage());
}

// Handle database actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $connectionStatus) {
    try {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'optimize_table':
                    $tableName = $_POST['table_name'];
                    if (preg_match('/^[a-zA-Z0-9_]+$/', $tableName)) {
                        $pdo->exec("OPTIMIZE TABLE `{$tableName}`");
                        $message = "Table '{$tableName}' optimized successfully.";
                        $messageType = "success";
                        logAccess('admin', "table_optimize_{$tableName}", true);
                    }
                    break;
                    
                case 'check_table':
                    $tableName = $_POST['table_name'];
                    if (preg_match('/^[a-zA-Z0-9_]+$/', $tableName)) {
                        $stmt = $pdo->query("CHECK TABLE `{$tableName}`");
                        $result = $stmt->fetch();
                        $message = "Table check completed: " . $result['Msg_text'];
                        $messageType = "info";
                        logAccess('admin', "table_check_{$tableName}", true);
                    }
                    break;
            }
        }
    } catch (Exception $e) {
        $message = "Database operation failed: " . $e->getMessage();
        $messageType = "danger";
        error_log("Database operation error: " . $e->getMessage());
    }
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
                                <i class="fas fa-database text-primary"></i> Database Management
                            </h1>
                            <p class="text-muted mb-0">Monitor and manage database health and performance</p>
                        </div>
                        <div>
                            <span class="badge bg-<?php echo $connectionStatus ? 'success' : 'danger'; ?> fs-6">
                                <i class="fas fa-<?php echo $connectionStatus ? 'check-circle' : 'times-circle'; ?>"></i>
                                <?php echo $connectionStatus ? 'Connected' : 'Disconnected'; ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Alert Messages -->
            <?php if ($message): ?>
            <div class="row mb-3">
                <div class="col-12">
                    <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($connectionStatus): ?>
            <!-- Database Overview -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card bg-primary text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <div class="h4 mb-0"><?php echo number_format($dbStats['total_tables']); ?></div>
                                    <div>Total Tables</div>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-table fa-2x opacity-50"></i>
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
                                    <div class="h4 mb-0"><?php echo number_format($dbStats['size_mb'], 1); ?> MB</div>
                                    <div>Database Size</div>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-hdd fa-2x opacity-50"></i>
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
                                    <div class="h4 mb-0"><?php echo number_format($recordCounts['staff'] ?? 0); ?></div>
                                    <div>Staff Records</div>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-users fa-2x opacity-50"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-warning text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <div class="h4 mb-0"><?php echo PHP_VERSION; ?></div>
                                    <div>PHP Version</div>
                                </div>
                                <div class="align-self-center">
                                    <i class="fab fa-php fa-2x opacity-50"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Record Counts -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-chart-bar"></i> Key Table Statistics</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <?php foreach ($recordCounts as $table => $count): ?>
                                <div class="col-md-3 mb-3">
                                    <div class="text-center">
                                        <div class="h4 text-primary"><?php echo is_numeric($count) ? number_format($count) : $count; ?></div>
                                        <div class="text-muted"><?php echo ucfirst($table); ?></div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Table Management -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-table"></i> Database Tables</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($tables)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-database fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">No tables found</h5>
                            </div>
                            <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Table Name</th>
                                            <th>Rows</th>
                                            <th>Size (MB)</th>
                                            <th>Comment</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($tables as $table): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($table['table_name']); ?></strong>
                                            </td>
                                            <td>
                                                <?php echo number_format($table['table_rows']); ?>
                                            </td>
                                            <td>
                                                <?php echo number_format($table['size_mb'], 2); ?>
                                            </td>
                                            <td>
                                                <small class="text-muted">
                                                    <?php echo htmlspecialchars($table['table_comment'] ?: 'No comment'); ?>
                                                </small>
                                            </td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <button type="button" class="btn btn-outline-primary btn-sm" 
                                                            onclick="optimizeTable('<?php echo $table['table_name']; ?>')" 
                                                            title="Optimize Table">
                                                        <i class="fas fa-cog"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-outline-info btn-sm" 
                                                            onclick="checkTable('<?php echo $table['table_name']; ?>')" 
                                                            title="Check Table">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Database Tools -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-tools"></i> Database Tools</h5>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <button class="btn btn-outline-primary" onclick="showBackupInfo()">
                                    <i class="fas fa-download"></i> Database Backup
                                </button>
                                <button class="btn btn-outline-warning" onclick="showOptimizeInfo()">
                                    <i class="fas fa-cog"></i> Optimize All Tables
                                </button>
                                <button class="btn btn-outline-info" onclick="showMaintenanceInfo()">
                                    <i class="fas fa-wrench"></i> Maintenance Mode
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-chart-line"></i> Performance Metrics</h5>
                        </div>
                        <div class="card-body">
                            <div class="row text-center">
                                <div class="col-6">
                                    <div class="h6 text-success"><?php echo ini_get('memory_limit'); ?></div>
                                    <small class="text-muted">Memory Limit</small>
                                </div>
                                <div class="col-6">
                                    <div class="h6 text-info"><?php echo ini_get('max_execution_time'); ?>s</div>
                                    <small class="text-muted">Max Execution</small>
                                </div>
                            </div>
                            <hr>
                            <div class="text-center">
                                <a href="/Armis2/admin/health.php" class="btn btn-outline-success btn-sm">
                                    <i class="fas fa-heartbeat"></i> Full Health Check
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <?php else: ?>
            <!-- Connection Error -->
            <div class="row">
                <div class="col-12">
                    <div class="card border-danger">
                        <div class="card-header bg-danger text-white">
                            <h5 class="mb-0"><i class="fas fa-exclamation-triangle"></i> Database Connection Error</h5>
                        </div>
                        <div class="card-body">
                            <p>Unable to connect to the database. Please check:</p>
                            <ul>
                                <li>Database server is running</li>
                                <li>Database credentials are correct</li>
                                <li>Database exists and is accessible</li>
                                <li>Network connectivity to database server</li>
                            </ul>
                            <div class="mt-3">
                                <button class="btn btn-outline-primary" onclick="location.reload()">
                                    <i class="fas fa-redo"></i> Retry Connection
                                </button>
                                <a href="/Armis2/admin/health.php" class="btn btn-outline-info">
                                    <i class="fas fa-heartbeat"></i> System Health Check
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Hidden forms for actions -->
<form id="optimizeForm" method="POST" style="display: none;">
    <input type="hidden" name="action" value="optimize_table">
    <input type="hidden" name="table_name" id="optimizeTableName">
</form>

<form id="checkForm" method="POST" style="display: none;">
    <input type="hidden" name="action" value="check_table">
    <input type="hidden" name="table_name" id="checkTableName">
</form>

<script>
function optimizeTable(tableName) {
    if (confirm('Are you sure you want to optimize the table "' + tableName + '"? This may take a few moments.')) {
        document.getElementById('optimizeTableName').value = tableName;
        document.getElementById('optimizeForm').submit();
    }
}

function checkTable(tableName) {
    if (confirm('Check the table "' + tableName + '" for errors?')) {
        document.getElementById('checkTableName').value = tableName;
        document.getElementById('checkForm').submit();
    }
}

function showBackupInfo() {
    alert('Database backup functionality would be implemented here. This typically involves:\n\n' +
          '• Creating mysqldump exports\n' +
          '• Scheduling automated backups\n' +
          '• Storing backups securely\n' +
          '• Providing restore capabilities\n\n' +
          'This is a demonstration interface.');
}

function showOptimizeInfo() {
    alert('Optimize all tables functionality would:\n\n' +
          '• Run OPTIMIZE TABLE on all database tables\n' +
          '• Defragment table data and indexes\n' +
          '• Reclaim unused space\n' +
          '• May take several minutes to complete\n\n' +
          'This is a demonstration interface.');
}

function showMaintenanceInfo() {
    alert('Maintenance mode functionality would:\n\n' +
          '• Put the system in maintenance mode\n' +
          '• Prevent user access during updates\n' +
          '• Display maintenance message to users\n' +
          '• Allow admin access for system updates\n\n' +
          'This is a demonstration interface.');
}
</script>

<?php include dirname(__DIR__) . '/shared/footer.php'; ?>