<?php
/**
 * ARMIS System Administration Dashboard
 * Enhanced with dynamic role management, system reports, and security monitoring
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once dirname(__DIR__) . '/shared/csrf.php';

// Security headers
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('X-Content-Type-Options: nosniff');

// Enable error reporting in development only
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Include necessary files
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/shared/database_connection.php';
require_once dirname(__DIR__) . '/shared/rbac.php';

// Enhanced logging with IP tracking
$user_ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
error_log("Admin access attempt by user: " . ($_SESSION['username'] ?? 'unknown') . " from IP: " . $user_ip);

// Basic session check before RBAC
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    error_log("Admin access denied: No user_id in session from IP: " . $user_ip);
    header('Location: /Armis2/login.php');
    exit();
}

// Use RBAC system for proper access control
requireModuleAccess('admin');

// Log successful access with enhanced details
error_log("Admin access GRANTED to user: " . ($_SESSION['username'] ?? 'unknown') . 
          " (ID: " . $_SESSION['user_id'] . ") with role: " . ($_SESSION['role'] ?? 'unknown') . 
          " from IP: " . $user_ip);

// Initialize database connection
try {
    $pdo = getDbConnection();
    if (!$pdo) {
        throw new Exception("Failed to establish database connection");
    }
} catch (Exception $e) {
    error_log("Database connection failed in admin/index.php: " . $e->getMessage());
    die("System temporarily unavailable. Please contact administrator.");
}

// Handle AJAX requests for dynamic functionality
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action'])) {
    header('Content-Type: application/json');
    require_csrf();
    
    switch ($_POST['action']) {
        case 'assign_role':
            echo handleRoleAssignment($pdo, $_POST);
            exit;
        case 'generate_report':
            echo handleReportGeneration($pdo, $_POST);
            exit;
        case 'security_scan':
            echo handleSecurityScan($pdo);
            exit;
        case 'get_user_list':
            echo getUserList($pdo);
            exit;
        case 'backup_database':
            echo handleDatabaseBackup($pdo);
            exit;
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            exit;
    }
}

$pageTitle = "System Admin";
$moduleName = "System Admin";
$moduleIcon = "cogs";
$currentPage = "dashboard";

require_once __DIR__ . '/includes/sidebar_nav.php';

// Log successful access
error_log("Admin dashboard accessed by admin user: " . $_SESSION['username']);

// Log access if function exists
if (function_exists('logAccess')) {
    try {
        logAccess('admin', 'dashboard_view', true);
    } catch (Exception $e) {
        error_log("Error in logAccess: " . $e->getMessage());
    }
}

// Get real system statistics with error handling
function getSystemStats() {
    global $pdo;
    $stats = [
        'login_accounts' => 0,
        'active_logins' => 0,
        'login_attempts' => 0,
        'successful_logins' => 0,
        'failed_logins' => 0,
        'db_health' => 'Unknown',
        'db_size' => 0,
        'load' => 'N/A'
    ];

    try {
        // ARMIS credentials live on staff; there is no canonical users table.
        $stmt = $pdo->query("SELECT COUNT(*) FROM staff
                             WHERE accStatus = 'Active'
                               AND username IS NOT NULL AND username <> ''
                               AND password IS NOT NULL AND password <> ''");
        $stats['login_accounts'] = (int)$stmt->fetchColumn();

        // A true PHP session registry is not currently part of the canonical schema.
        // Use successful authentication events in the last 30 minutes as an honest
        // indicator of recently active authenticated users, rather than inventing sessions.
        $stmt = $pdo->query("SELECT COUNT(DISTINCT COALESCE(NULLIF(user_id, 0), username))
                             FROM activity_log
                             WHERE action = 'login_success'
                               AND createdAt >= DATE_SUB(NOW(), INTERVAL 30 MINUTE)");
        $stats['active_logins'] = (int)$stmt->fetchColumn();

        $stmt = $pdo->query("SELECT
                                COUNT(*) AS total_attempts,
                                SUM(action = 'login_success') AS successful_logins,
                                SUM(action = 'login_failed') AS failed_logins
                             FROM activity_log
                             WHERE action IN ('login_success', 'login_failed')");
        $loginStats = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $stats['login_attempts'] = (int)($loginStats['total_attempts'] ?? 0);
        $stats['successful_logins'] = (int)($loginStats['successful_logins'] ?? 0);
        $stats['failed_logins'] = (int)($loginStats['failed_logins'] ?? 0);

        // Calculate actual database size from the current database, not a hardcoded value.
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(data_length + index_length), 0)
                               FROM information_schema.tables
                               WHERE table_schema = DATABASE()");
        $stmt->execute();
        $stats['db_size'] = (int)$stmt->fetchColumn();

        $stmt = $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()");
        $stats['db_health'] = ((int)$stmt->fetchColumn() > 0) ? 'Good' : 'Poor';

        // Windows/WAMP does not reliably expose a Linux-style load average.
        // If PHP provides it, use it; otherwise display N/A rather than fake CPU data.
        if (function_exists('sys_getloadavg')) {
            $load = sys_getloadavg();
            if (is_array($load) && isset($load[0])) {
                $stats['load'] = number_format((float)$load[0], 2);
            }
        }
    } catch (Throwable $e) {
        error_log('Error in getSystemStats: ' . $e->getMessage());
    }

    return $stats;
}

function getRecentActivity() {
    global $pdo;
    try {
        // Recent activity must come from the real audit/activity stream.
        $stmt = $pdo->query("SELECT username, action, details, ip_address, createdAt
                             FROM activity_log
                             ORDER BY createdAt DESC, id DESC
                             LIMIT 10");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        error_log('Recent activity error: ' . $e->getMessage());
        return [];
    }
}

function activityLabel($action) {
    $labels = [
        'login_success' => 'System Login',
        'login_failed' => 'Failed Login Attempt',
        'admin_branch_dashboard_access' => 'Admin Branch Access',
        'dashboard_view' => 'Dashboard Access',
        'backup_database' => 'Database Backup',
        'settings_update' => 'Settings Update'
    ];
    return $labels[$action] ?? ucwords(str_replace(['_', '-'], ' ', (string)$action));
}

function activityBadgeClass($action) {
    if ($action === 'login_failed') return 'bg-danger';
    if ($action === 'login_success') return 'bg-success';
    return 'bg-info';
}

function formatBytes($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}

function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    if ($time < 60) return 'just now';
    elseif ($time < 3600) return floor($time/60) . ' minutes ago';
    elseif ($time < 86400) return floor($time/3600) . ' hours ago';
    else return floor($time/86400) . ' days ago';
}

// Get dynamic data
$systemStats = getSystemStats();
$recentActivity = getRecentActivity();

// Ensure all required dependencies are available
$requiredFiles = [
    dirname(__DIR__) . '/shared/header.php',
    dirname(__DIR__) . '/shared/sidebar.php'
];

foreach ($requiredFiles as $file) {
    if (!file_exists($file)) {
        error_log("Critical error: Required file not found: $file");
        die("Critical error: Missing required files. Please contact system administrator.");
    }
}

// Include UI components with error handling
try {
    include dirname(__DIR__) . '/shared/header.php';
} catch (Exception $e) {
    error_log("Error including header: " . $e->getMessage());
    echo "<h1>Admin Dashboard</h1>";
    echo "<p>Header failed to load but we're continuing.</p>";
}

try {
    include dirname(__DIR__) . '/shared/sidebar.php';
} catch (Exception $e) {
    error_log("Error including sidebar: " . $e->getMessage());
    echo "<p>Sidebar failed to load but we're continuing.</p>";
}
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
                                <i class="fas fa-cogs text-primary"></i> System Administration Dashboard
                            </h1>
                            <p class="text-muted mb-0">Complete system oversight and management</p>
                        </div>
                        <div class="d-flex gap-2">
                            <span class="badge bg-success fs-6">System Online</span>
                            <span class="badge bg-info fs-6">Admin Level Access</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- System Status Cards -->
            <div class="row g-4 mb-5">
                <div class="col-xl-3 col-lg-6">
                    <div class="card bg-primary text-white h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title text-white-50">Login Accounts</h6>
                                    <h2 class="mb-0"><?= number_format($systemStats['login_accounts']) ?></h2>
                                    <small class="text-white-75">Active accounts with credentials</small>
                                </div>
                                <div class="text-white-50"><i class="fas fa-user-shield fa-2x"></i></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-lg-6">
                    <div class="card bg-success text-white h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title text-white-50">Active Users</h6>
                                    <h2 class="mb-0"><?= number_format($systemStats['active_logins']) ?></h2>
                                    <small class="text-white-75">Successful logins in last 30 min</small>
                                </div>
                                <div class="text-white-50"><i class="fas fa-user-check fa-2x"></i></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-lg-6">
                    <div class="card bg-dark text-white h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title text-white-50">Login Attempts</h6>
                                    <h2 class="mb-0"><?= number_format($systemStats['login_attempts']) ?></h2>
                                    <small class="text-white-75"><?= number_format($systemStats['successful_logins']) ?> successful · <?= number_format($systemStats['failed_logins']) ?> failed</small>
                                </div>
                                <div class="text-white-50"><i class="fas fa-sign-in-alt fa-2x"></i></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-lg-6">
                    <div class="card bg-info text-white h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title text-white-50">Database Health</h6>
                                    <h2 class="mb-0"><?= htmlspecialchars($systemStats['db_health']) ?></h2>
                                    <small class="text-white-75"><?= formatBytes($systemStats['db_size']) ?></small>
                                </div>
                                <div class="text-white-50"><i class="fas fa-database fa-2x"></i></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-lg-6">
                    <div class="card bg-secondary text-white h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title text-white-50">Database Size</h6>
                                    <h2 class="mb-0"><?= formatBytes($systemStats['db_size']) ?></h2>
                                    <small class="text-white-75">Current database footprint</small>
                                </div>
                                <div class="text-white-50"><i class="fas fa-server fa-2x"></i></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-lg-6">
                    <div class="card bg-warning text-dark h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title text-dark-50">System Load</h6>
                                    <h2 class="mb-0"><?= htmlspecialchars($systemStats['load']) ?></h2>
                                    <small class="text-dark-50">Load average when supported</small>
                                </div>
                                <div class="text-dark-50"><i class="fas fa-tachometer-alt fa-2x"></i></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Administration Modules -->
            <div class="row mb-5">
                <div class="col-12">
                    <h3 class="admin-section-title mb-4">
                        <i class="fas fa-tools"></i> System Administration Modules
                    </h3>
                </div>
                <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6 mb-4">
                    <div class="card admin-module-card h-100">
                        <div class="card-body text-center">
                            <div class="mb-3">
                                <i class="fas fa-users fa-3x text-primary"></i>
                            </div>
                            <h6 class="card-title">User Management</h6>
                            <p class="card-text small">Manage system users, roles, and permissions</p>
                            <a href="/Armis2/admin/users.php" class="btn btn-outline-primary btn-sm admin-btn">Manage Users</a>
                        </div>
                    </div>
                </div>
                <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6 mb-4">
                    <div class="card admin-module-card h-100">
                        <div class="card-body text-center">
                            <div class="mb-3">
                                <i class="fas fa-cogs fa-3x text-success"></i>
                            </div>
                            <h6 class="card-title">System Settings</h6>
                            <p class="card-text small">Configure system-wide settings and preferences</p>
                            <a href="/Armis2/admin/settings.php" class="btn btn-outline-success btn-sm admin-btn">Settings</a>
                        </div>
                    </div>
                </div>
                <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6 mb-4">
                    <div class="card admin-module-card h-100">
                        <div class="card-body text-center">
                            <div class="mb-3">
                                <i class="fas fa-database fa-3x text-info"></i>
                            </div>
                            <h6 class="card-title">Database Management</h6>
                            <p class="card-text small">Database maintenance, backups, and optimization</p>
                            <a href="/Armis2/admin/database.php" class="btn btn-outline-info btn-sm admin-btn">Database</a>
                        </div>
                    </div>
                </div>
                <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6 mb-4">
                    <div class="card admin-module-card h-100">
                        <div class="card-body text-center">
                            <div class="mb-3">
                                <i class="fas fa-shield-alt fa-3x text-danger"></i>
                            </div>
                            <h6 class="card-title">Security Center</h6>
                            <p class="card-text small">Security monitoring, audit logs, and policies</p>
                            <a href="/Armis2/admin/security.php" class="btn btn-outline-danger btn-sm admin-btn">Security</a>
                        </div>
                    </div>
                </div>
                <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6 mb-4">
                    <div class="card admin-module-card h-100">
                        <div class="card-body text-center">
                            <div class="mb-3">
                                <i class="fas fa-chart-bar fa-3x text-warning"></i>
                            </div>
                            <h6 class="card-title">System Reports</h6>
                            <p class="card-text small">Generate comprehensive system reports</p>
                            <a href="/Armis2/admin/reports.php" class="btn btn-outline-warning btn-sm admin-btn">Reports</a>
                        </div>
                    </div>
                </div>
                <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6 mb-4">
                    <div class="card admin-module-card h-100">
                        <div class="card-body text-center">
                            <div class="mb-3">
                                <i class="fas fa-users-cog fa-3x text-secondary"></i>
                            </div>
                            <h6 class="card-title">Admin Branch</h6>
                            <p class="card-text small">Access personnel administration functions</p>
                            <a href="/Armis2/admin_branch/" class="btn btn-outline-secondary btn-sm admin-btn">Admin Branch</a>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Quick Actions and Recent Activity -->
            <div class="row">
                <div class="col-lg-8">
                    <div class="card admin-card">
                        <div class="card-header admin-card-header bg-light">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-clock"></i> Recent System Activity
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="list-group list-group-flush">
                                <?php if (empty($recentActivity)): ?>
                                <div class="list-group-item admin-list-group-item text-center">
                                    <p class="text-muted mb-0">No recent activity to display</p>
                                </div>
                                <?php else: ?>
                                <?php foreach ($recentActivity as $activity): ?>
                                <div class="list-group-item admin-list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-1"><?= htmlspecialchars(activityLabel($activity['action'] ?? 'activity')) ?></h6>
                                        <p class="mb-1 text-muted small">User: <?= htmlspecialchars($activity['username'] ?? 'unknown') ?></p>
                                        <small class="text-muted"><?= timeAgo($activity['createdAt']) ?></small>
                                    </div>
                                    <span class="badge admin-badge <?= activityBadgeClass($activity['action'] ?? '') ?>">
                                        <?= ($activity['action'] ?? '') === 'login_failed' ? 'Failed' : (($activity['action'] ?? '') === 'login_success' ? 'Success' : 'Activity') ?>
                                    </span>
                                </div>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="card admin-card">
                        <div class="card-header admin-card-header bg-light">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-bolt"></i> Quick Actions
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <a href="/Armis2/admin_branch/create_staff.php" class="btn admin-btn btn-primary">
                                    <i class="fas fa-user-plus"></i> Create New User
                                </a>
                                <a href="/Armis2/admin/database.php#backup" class="btn admin-btn btn-success">
                                    <i class="fas fa-download"></i> Database Backup
                                </a>
                                <a href="/Armis2/admin/security.php" class="btn admin-btn btn-warning">
                                    <i class="fas fa-shield-alt"></i> Security Scan
                                </a>
                                <a href="/Armis2/admin/reports.php" class="btn admin-btn btn-info">
                                    <i class="fas fa-chart-line"></i> Generate Report
                                </a>
                                <a href="/Armis2/admin/settings.php" class="btn admin-btn btn-secondary">
                                    <i class="fas fa-cogs"></i> System Settings
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card admin-card mt-4">
                        <div class="card-header admin-card-header bg-light">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-exclamation-triangle"></i> System Alerts
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-info alert-sm">
                                <strong>Authentication:</strong> <?= number_format($systemStats['failed_logins']) ?> failed login attempts are recorded in the activity log.
                            </div>
                            <div class="alert <?= $systemStats['db_health'] === 'Good' ? 'alert-success' : 'alert-danger' ?> alert-sm">
                                <strong>Database:</strong> <?= htmlspecialchars($systemStats['db_health']) ?> — <?= formatBytes($systemStats['db_size']) ?> currently in use.
                            </div>
                            <div class="alert alert-secondary alert-sm">
                                <strong>System Load:</strong> <?= htmlspecialchars($systemStats['load']) ?><?= $systemStats['load'] === 'N/A' ? ' (not exposed by this Windows/PHP environment)' : '' ?>.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
     
<?php include dirname(__DIR__) . '/shared/footer.php'; ?>
