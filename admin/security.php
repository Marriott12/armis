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
logAccess('admin', 'security_view', true);

// Security data (would come from actual security monitoring in production)
$securityStats = [
    'failed_logins' => 23,
    'locked_accounts' => 3,
    'active_sessions' => 47,
    'security_alerts' => 5
];

$recentAlerts = [
    ['time' => '2024-01-15 10:30:00', 'type' => 'Failed Login', 'user' => 'john.doe', 'ip' => '192.168.1.100', 'severity' => 'medium'],
    ['time' => '2024-01-15 09:45:00', 'type' => 'Account Locked', 'user' => 'jane.smith', 'ip' => '192.168.1.105', 'severity' => 'high'],
    ['time' => '2024-01-15 08:15:00', 'type' => 'Suspicious Activity', 'user' => 'admin', 'ip' => '10.0.0.50', 'severity' => 'high'],
    ['time' => '2024-01-15 07:30:00', 'type' => 'Password Reset', 'user' => 'mike.wilson', 'ip' => '192.168.1.110', 'severity' => 'low'],
    ['time' => '2024-01-15 06:20:00', 'type' => 'Failed Login', 'user' => 'sarah.connor', 'ip' => '192.168.1.120', 'severity' => 'medium']
];

$activeSessions = [
    ['user' => 'admin', 'login_time' => '2024-01-15 08:00:00', 'ip' => '192.168.1.50', 'location' => 'Lusaka, ZM', 'status' => 'active'],
    ['user' => 'john.doe', 'login_time' => '2024-01-15 09:30:00', 'ip' => '192.168.1.100', 'location' => 'Ndola, ZM', 'status' => 'active'],
    ['user' => 'mary.johnson', 'login_time' => '2024-01-15 10:15:00', 'ip' => '192.168.1.75', 'location' => 'Kitwe, ZM', 'status' => 'active'],
    ['user' => 'david.brown', 'login_time' => '2024-01-15 07:45:00', 'ip' => '192.168.1.90', 'location' => 'Lusaka, ZM', 'status' => 'idle'],
    ['user' => 'lisa.white', 'login_time' => '2024-01-15 11:00:00', 'ip' => '192.168.1.130', 'location' => 'Livingstone, ZM', 'status' => 'active']
];

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
                        </div>
                        <div>
                            <div class="btn-group" role="group">
                                <button class="btn btn-warning" onclick="viewSecurityLog()">
                                    <i class="fas fa-file-alt"></i> Security Log
                                </button>
                                <button class="btn btn-danger" onclick="lockdownMode()">
                                    <i class="fas fa-lock"></i> Emergency Lockdown
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Security Statistics -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card bg-warning text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="card-title">Failed Logins</h6>
                                    <h4 class="mb-0"><?= number_format($securityStats['failed_logins']) ?></h4>
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
                                    <h4 class="mb-0"><?= number_format($securityStats['locked_accounts']) ?></h4>
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
                                    <h4 class="mb-0"><?= number_format($securityStats['active_sessions']) ?></h4>
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
                                    <h4 class="mb-0"><?= number_format($securityStats['security_alerts']) ?></h4>
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
                                            <button class="btn btn-outline-primary" onclick="viewAlert('<?= $alert['time'] ?>')">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="btn btn-outline-warning" onclick="blockIP('<?= $alert['ip'] ?>')">
                                                <i class="fas fa-ban"></i>
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
                                    <th>Login Time</th>
                                    <th>IP Address</th>
                                    <th>Location</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($activeSessions as $session): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($session['user']) ?></strong>
                                    </td>
                                    <td><?= date('M j, Y H:i', strtotime($session['login_time'])) ?></td>
                                    <td><?= htmlspecialchars($session['ip']) ?></td>
                                    <td><?= htmlspecialchars($session['location']) ?></td>
                                    <td>
                                        <?php
                                        $statusClass = $session['status'] === 'active' ? 'success' : 'warning';
                                        ?>
                                        <span class="badge bg-<?= $statusClass ?>"><?= ucfirst($session['status']) ?></span>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <button class="btn btn-outline-info" onclick="viewSession('<?= $session['user'] ?>')">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="btn btn-outline-danger" onclick="terminateSession('<?= $session['user'] ?>')">
                                                <i class="fas fa-times"></i>
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
                                        <small class="text-muted">System vulnerability check</small>
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
                                        <h6 class="mb-1">IP Whitelist</h6>
                                        <small class="text-muted">Manage allowed IP addresses</small>
                                    </div>
                                    <button class="btn btn-outline-success btn-sm" onclick="manageWhitelist()">
                                        Configure
                                    </button>
                                </div>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-1">Firewall Rules</h6>
                                        <small class="text-muted">Configure access rules</small>
                                    </div>
                                    <button class="btn btn-outline-primary btn-sm" onclick="configureFirewall()">
                                        Configure
                                    </button>
                                </div>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-1">Two-Factor Auth</h6>
                                        <small class="text-muted">Manage 2FA settings</small>
                                    </div>
                                    <button class="btn btn-outline-info btn-sm" onclick="configure2FA()">
                                        Configure
                                    </button>
                                </div>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-1">Session Management</h6>
                                        <small class="text-muted">Configure session policies</small>
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
function viewSecurityLog() {
    alert('Security log viewer would be displayed.');
}

function lockdownMode() {
    if (confirm('WARNING: This will lock down the system and prevent all logins except admin. Continue?')) {
        alert('Emergency lockdown would be activated.');
    }
}

function viewAlert(time) {
    alert('Alert details would be displayed for: ' + time);
}

function blockIP(ip) {
    if (confirm('Block IP address: ' + ip + '?')) {
        alert('IP address would be blocked: ' + ip);
    }
}

function viewSession(user) {
    alert('Session details would be displayed for: ' + user);
}

function terminateSession(user) {
    if (confirm('Terminate session for user: ' + user + '?')) {
        alert('Session would be terminated for: ' + user);
    }
}

function auditPasswords() {
    alert('Password policy audit would be performed.');
}

function analyzeAccessLogs() {
    alert('Access log analysis would be displayed.');
}

function reviewPermissions() {
    alert('Permission review interface would be displayed.');
}

function securityScan() {
    if (confirm('Perform system security scan? This may take several minutes.')) {
        alert('Security scan would be initiated.');
    }
}

function manageWhitelist() {
    alert('IP whitelist management interface would be displayed.');
}

function configureFirewall() {
    alert('Firewall configuration interface would be displayed.');
}

function configure2FA() {
    alert('Two-factor authentication settings would be displayed.');
}

function configureSessions() {
    alert('Session management settings would be displayed.');
}
</script>

<?php include dirname(__DIR__) . '/shared/footer.php'; ?>
