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
    error_log("Database connection failed in admin/database.php: " . $e->getMessage());
    die("Database connection failed. Please check your configuration.");
}

$pageTitle = "Database Management";
$moduleName = "Database Admin";
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

// Handle database operations
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'optimize_tables':
                $message = "Table optimization would be performed in a production system.";
                $messageType = "info";
                logAccess('admin', 'database_optimize', true);
                break;
            case 'backup_database':
                $message = "Database backup would be created in a production system.";
                $messageType = "info";
                logAccess('admin', 'database_backup', true);
                break;
        }
    }
}

// Get database information
function getDatabaseInfo() {
    global $pdo;
    try {
        // Get database size
        $stmt = $pdo->query("SELECT 
            SUM(data_length + index_length) as database_size,
            COUNT(*) as table_count
            FROM information_schema.TABLES 
            WHERE table_schema = DATABASE()");
        $dbInfo = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get table information
        $stmt = $pdo->query("SELECT 
            table_name,
            table_rows,
            data_length,
            index_length,
            (data_length + index_length) as total_size
            FROM information_schema.TABLES 
            WHERE table_schema = DATABASE()
            ORDER BY total_size DESC");
        $tables = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'size' => $dbInfo['database_size'] ?? 0,
            'table_count' => $dbInfo['table_count'] ?? 0,
            'tables' => $tables
        ];
    } catch (Exception $e) {
        return [
            'size' => 0,
            'table_count' => 0,
            'tables' => []
        ];
    }
}

$dbInfo = getDatabaseInfo();

// Helper function to format bytes
function formatBytes($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
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
                            <p class="text-muted mb-0">Monitor and maintain database performance and integrity</p>
                        </div>
                        <div>
                            <div class="btn-group" role="group">
                                <button class="btn btn-success" onclick="createBackup()">
                                    <i class="fas fa-download"></i> Create Backup
                                </button>
                                <button class="btn btn-warning" onclick="optimizeDatabase()">
                                    <i class="fas fa-tools"></i> Optimize
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Alert Messages -->
            <?php if ($message): ?>
            <div class="alert alert-<?= $messageType ?> alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <!-- Database Overview -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card bg-primary text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="card-title">Database Size</h6>
                                    <h4 class="mb-0"><?= formatBytes($dbInfo['size']) ?></h4>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-hdd fa-2x opacity-75"></i>
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
                                    <h6 class="card-title">Tables</h6>
                                    <h4 class="mb-0"><?= number_format($dbInfo['table_count']) ?></h4>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-table fa-2x opacity-75"></i>
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
                                    <h6 class="card-title">Connection Status</h6>
                                    <h4 class="mb-0">Active</h4>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-plug fa-2x opacity-75"></i>
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
                                    <h6 class="card-title">Last Backup</h6>
                                    <h4 class="mb-0">2 days ago</h4>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-clock fa-2x opacity-75"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Database Tables -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-list"></i> Database Tables
                    </h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Table Name</th>
                                    <th>Rows</th>
                                    <th>Data Size</th>
                                    <th>Index Size</th>
                                    <th>Total Size</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($dbInfo['tables'] as $table): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($table['table_name']) ?></strong>
                                    </td>
                                    <td><?= number_format($table['table_rows'] ?? 0) ?></td>
                                    <td><?= formatBytes($table['data_length'] ?? 0) ?></td>
                                    <td><?= formatBytes($table['index_length'] ?? 0) ?></td>
                                    <td><?= formatBytes($table['total_size'] ?? 0) ?></td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <button class="btn btn-outline-primary" onclick="viewTable('<?= $table['table_name'] ?>')">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="btn btn-outline-warning" onclick="optimizeTable('<?= $table['table_name'] ?>')">
                                                <i class="fas fa-tools"></i>
                                            </button>
                                            <button class="btn btn-outline-info" onclick="analyzeTable('<?= $table['table_name'] ?>')">
                                                <i class="fas fa-chart-line"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Database Operations -->
            <div class="row">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-tools"></i> Maintenance Operations
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="list-group list-group-flush">
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-1">Optimize Tables</h6>
                                        <small class="text-muted">Improve database performance</small>
                                    </div>
                                    <button class="btn btn-outline-warning btn-sm" onclick="optimizeDatabase()">
                                        Run
                                    </button>
                                </div>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-1">Check Tables</h6>
                                        <small class="text-muted">Verify table integrity</small>
                                    </div>
                                    <button class="btn btn-outline-info btn-sm" onclick="checkTables()">
                                        Run
                                    </button>
                                </div>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-1">Repair Tables</h6>
                                        <small class="text-muted">Fix corrupted tables</small>
                                    </div>
                                    <button class="btn btn-outline-danger btn-sm" onclick="repairTables()">
                                        Run
                                    </button>
                                </div>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-1">Update Statistics</h6>
                                        <small class="text-muted">Refresh query optimizer data</small>
                                    </div>
                                    <button class="btn btn-outline-success btn-sm" onclick="updateStats()">
                                        Run
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
                                <i class="fas fa-save"></i> Backup & Restore
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="list-group list-group-flush">
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-1">Full Database Backup</h6>
                                        <small class="text-muted">Complete database export</small>
                                    </div>
                                    <button class="btn btn-outline-success btn-sm" onclick="createBackup()">
                                        Create
                                    </button>
                                </div>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-1">Schema Only Backup</h6>
                                        <small class="text-muted">Structure without data</small>
                                    </div>
                                    <button class="btn btn-outline-info btn-sm" onclick="createSchemaBackup()">
                                        Create
                                    </button>
                                </div>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-1">Restore Database</h6>
                                        <small class="text-muted">Import from backup file</small>
                                    </div>
                                    <button class="btn btn-outline-warning btn-sm" onclick="restoreDatabase()">
                                        Restore
                                    </button>
                                </div>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-1">Schedule Backups</h6>
                                        <small class="text-muted">Automated backup setup</small>
                                    </div>
                                    <button class="btn btn-outline-primary btn-sm" onclick="scheduleBackups()">
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

<!-- Hidden Forms for Operations -->
<form id="optimizeForm" method="POST" style="display: none;">
    <input type="hidden" name="action" value="optimize_tables">
</form>

<form id="backupForm" method="POST" style="display: none;">
    <input type="hidden" name="action" value="backup_database">
</form>

<script>
function createBackup() {
    if (confirm('This will create a full database backup. Continue?')) {
        document.getElementById('backupForm').submit();
    }
}

function optimizeDatabase() {
    if (confirm('This will optimize all database tables. This may take some time. Continue?')) {
        document.getElementById('optimizeForm').submit();
    }
}

function viewTable(tableName) {
    // In a real system, this would open a table viewer
    alert('Table viewer would open for: ' + tableName);
}

function optimizeTable(tableName) {
    if (confirm('Optimize table: ' + tableName + '?')) {
        // In a real system, this would optimize the specific table
        alert('Table optimization would be performed for: ' + tableName);
    }
}

function analyzeTable(tableName) {
    // In a real system, this would show table analysis
    alert('Table analysis would be displayed for: ' + tableName);
}

function checkTables() {
    if (confirm('Check all tables for integrity issues?')) {
        alert('Table integrity check would be performed.');
    }
}

function repairTables() {
    if (confirm('WARNING: This will attempt to repair potentially corrupted tables. Continue?')) {
        alert('Table repair operation would be performed.');
    }
}

function updateStats() {
    if (confirm('Update table statistics for query optimizer?')) {
        alert('Statistics update would be performed.');
    }
}

function createSchemaBackup() {
    if (confirm('Create a schema-only backup (structure without data)?')) {
        alert('Schema backup would be created.');
    }
}

function restoreDatabase() {
    alert('Database restore interface would be displayed.');
}

function scheduleBackups() {
    alert('Backup scheduling interface would be displayed.');
}
</script>

<?php include dirname(__DIR__) . '/shared/footer.php'; ?>
