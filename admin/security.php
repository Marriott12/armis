<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include RBAC system
require_once dirname(__DIR__) . '/shared/rbac.php';
require_once dirname(__DIR__) . '/shared/database_connection.php';

$pageTitle = "Security Center";
$moduleName = "System Admin";
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
logAccess('admin', 'security_center_view', true);

// Security metrics and data
$securityMetrics = [];
$recentAccessLogs = [];
$roleAnalysis = [];

try {
    $pdo = getDbConnection();
    
    // Get security metrics
    $securityMetrics = [
        'total_users' => 0,
        'active_users' => 0,
        'admin_users' => 0,
        'failed_logins_today' => 0,
        'successful_logins_today' => 0
    ];
    
    // Total users
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM staff");
    $result = $stmt->fetch();
    $securityMetrics['total_users'] = $result['count'];
    
    // Active users
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM staff WHERE accStatus = 'active'");
    $result = $stmt->fetch();
    $securityMetrics['active_users'] = $result['count'];
    
    // Admin users
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM staff WHERE role = 'admin'");
    $result = $stmt->fetch();
    $securityMetrics['admin_users'] = $result['count'];
    
    // Role distribution analysis
    $stmt = $pdo->query("SELECT role, COUNT(*) as count FROM staff GROUP BY role ORDER BY count DESC");
    $roleAnalysis = $stmt->fetchAll();
    
    // Recent access logs (simulated data since we don't have a comprehensive log table)
    $recentAccessLogs = [
        ['timestamp' => date('Y-m-d H:i:s', strtotime('-5 minutes')), 'user' => 'admin', 'action' => 'login_success', 'ip' => '192.168.1.100'],
        ['timestamp' => date('Y-m-d H:i:s', strtotime('-15 minutes')), 'user' => 'commander', 'action' => 'page_access', 'ip' => '192.168.1.101'],
        ['timestamp' => date('Y-m-d H:i:s', strtotime('-1 hour')), 'user' => 'unknown', 'action' => 'login_failed', 'ip' => '10.0.0.50'],
        ['timestamp' => date('Y-m-d H:i:s', strtotime('-2 hours')), 'user' => 'admin', 'action' => 'user_created', 'ip' => '192.168.1.100'],
        ['timestamp' => date('Y-m-d H:i:s', strtotime('-3 hours')), 'user' => 'sergeant', 'action' => 'profile_update', 'ip' => '192.168.1.105']
    ];
    
} catch (Exception $e) {
    error_log("Security center error: " . $e->getMessage());
}

// Security recommendations
$securityRecommendations = [
    [
        'level' => 'high',
        'title' => 'Enable Two-Factor Authentication',
        'description' => 'Implement 2FA for all admin accounts to enhance security',
        'status' => 'pending'
    ],
    [
        'level' => 'medium', 
        'title' => 'Password Policy Enforcement',
        'description' => 'Enforce strong password requirements (8+ chars, special chars)',
        'status' => 'implemented'
    ],
    [
        'level' => 'medium',
        'title' => 'Session Timeout Configuration',
        'description' => 'Review and optimize session timeout settings',
        'status' => 'implemented'
    ],
    [
        'level' => 'low',
        'title' => 'Regular Security Audits',
        'description' => 'Schedule monthly security audits and penetration testing',
        'status' => 'pending'
    ]
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
                            <p class="text-muted mb-0">Monitor system security and access controls</p>
                        </div>
                        <div>
                            <span class="badge bg-success fs-6">
                                <i class="fas fa-shield-check"></i> System Secure
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Security Metrics -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card bg-primary text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <div class="h4 mb-0"><?php echo number_format($securityMetrics['total_users']); ?></div>
                                    <div>Total Users</div>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-users fa-2x opacity-50"></i>
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
                                    <div class="h4 mb-0"><?php echo number_format($securityMetrics['active_users']); ?></div>
                                    <div>Active Users</div>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-user-check fa-2x opacity-50"></i>
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
                                    <div class="h4 mb-0"><?php echo number_format($securityMetrics['admin_users']); ?></div>
                                    <div>Admin Users</div>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-user-shield fa-2x opacity-50"></i>
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
                                    <div class="h4 mb-0">0</div>
                                    <div>Security Alerts</div>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-exclamation-triangle fa-2x opacity-50"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Security Dashboard -->
            <div class="row mb-4">
                <div class="col-md-8">
                    <!-- Recent Security Events -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-list"></i> Recent Security Events</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Timestamp</th>
                                            <th>User</th>
                                            <th>Action</th>
                                            <th>IP Address</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recentAccessLogs as $log): ?>
                                        <tr>
                                            <td>
                                                <small><?php echo date('M j, H:i', strtotime($log['timestamp'])); ?></small>
                                            </td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($log['user']); ?></strong>
                                            </td>
                                            <td>
                                                <?php
                                                $actionClass = '';
                                                $actionIcon = '';
                                                switch ($log['action']) {
                                                    case 'login_success':
                                                        $actionClass = 'text-success';
                                                        $actionIcon = 'sign-in-alt';
                                                        break;
                                                    case 'login_failed':
                                                        $actionClass = 'text-danger';
                                                        $actionIcon = 'times-circle';
                                                        break;
                                                    case 'user_created':
                                                        $actionClass = 'text-info';
                                                        $actionIcon = 'user-plus';
                                                        break;
                                                    default:
                                                        $actionClass = 'text-muted';
                                                        $actionIcon = 'info-circle';
                                                }
                                                ?>
                                                <span class="<?php echo $actionClass; ?>">
                                                    <i class="fas fa-<?php echo $actionIcon; ?>"></i>
                                                    <?php echo str_replace('_', ' ', ucfirst($log['action'])); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <code><?php echo htmlspecialchars($log['ip']); ?></code>
                                            </td>
                                            <td>
                                                <?php if (strpos($log['action'], 'failed') !== false): ?>
                                                <span class="badge bg-danger">Failed</span>
                                                <?php else: ?>
                                                <span class="badge bg-success">Success</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <div class="text-center mt-3">
                                <button class="btn btn-outline-primary btn-sm" onclick="showFullAuditLog()">
                                    <i class="fas fa-eye"></i> View Full Audit Log
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <!-- Role Distribution -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-chart-pie"></i> Role Distribution</h5>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($roleAnalysis)): ?>
                            <?php foreach ($roleAnalysis as $role): ?>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <div>
                                    <span class="fw-bold"><?php echo ucfirst($role['role'] ?? 'Unknown'); ?></span>
                                    <small class="text-muted d-block">
                                        <?php 
                                        $roleInfo = ARMIS_ROLES[$role['role']] ?? null;
                                        echo $roleInfo ? $roleInfo['description'] : 'No description';
                                        ?>
                                    </small>
                                </div>
                                <div>
                                    <span class="badge bg-secondary"><?php echo $role['count']; ?></span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            <?php else: ?>
                            <div class="text-center text-muted py-3">
                                <i class="fas fa-users fa-2x mb-2"></i>
                                <div>No role data available</div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Security Recommendations -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-lightbulb"></i> Security Recommendations</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <?php foreach ($securityRecommendations as $recommendation): ?>
                                <div class="col-md-6 mb-3">
                                    <div class="card border-<?php echo $recommendation['level'] === 'high' ? 'danger' : ($recommendation['level'] === 'medium' ? 'warning' : 'info'); ?>">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div>
                                                    <h6 class="card-title">
                                                        <i class="fas fa-<?php echo $recommendation['level'] === 'high' ? 'exclamation-triangle text-danger' : ($recommendation['level'] === 'medium' ? 'exclamation-circle text-warning' : 'info-circle text-info'); ?>"></i>
                                                        <?php echo htmlspecialchars($recommendation['title']); ?>
                                                    </h6>
                                                    <p class="card-text small text-muted">
                                                        <?php echo htmlspecialchars($recommendation['description']); ?>
                                                    </p>
                                                </div>
                                                <div>
                                                    <?php if ($recommendation['status'] === 'implemented'): ?>
                                                    <span class="badge bg-success">
                                                        <i class="fas fa-check"></i> Done
                                                    </span>
                                                    <?php else: ?>
                                                    <span class="badge bg-secondary">
                                                        <i class="fas fa-clock"></i> Pending
                                                    </span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Security Tools -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-tools"></i> Security Tools</h5>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <button class="btn btn-outline-primary" onclick="runSecurityScan()">
                                    <i class="fas fa-search"></i> Run Security Scan
                                </button>
                                <button class="btn btn-outline-warning" onclick="showPasswordPolicy()">
                                    <i class="fas fa-key"></i> Password Policy
                                </button>
                                <button class="btn btn-outline-info" onclick="showPermissionMatrix()">
                                    <i class="fas fa-table"></i> Permission Matrix
                                </button>
                                <button class="btn btn-outline-success" onclick="exportSecurityReport()">
                                    <i class="fas fa-download"></i> Export Security Report
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-cog"></i> Security Settings</h5>
                        </div>
                        <div class="card-body">
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" role="switch" id="enableAuditLog" checked disabled>
                                <label class="form-check-label" for="enableAuditLog">
                                    Audit Logging
                                </label>
                            </div>
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" role="switch" id="enableBruteForceProtection" checked disabled>
                                <label class="form-check-label" for="enableBruteForceProtection">
                                    Brute Force Protection
                                </label>
                            </div>
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" role="switch" id="enableSessionTimeout" checked disabled>
                                <label class="form-check-label" for="enableSessionTimeout">
                                    Session Timeout
                                </label>
                            </div>
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" role="switch" id="enableIpWhitelist" disabled>
                                <label class="form-check-label" for="enableIpWhitelist">
                                    IP Whitelist
                                </label>
                            </div>
                            <div class="text-muted small">
                                <i class="fas fa-info-circle"></i> Security settings are configured in the main configuration.
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-bolt"></i> Quick Security Actions</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-3">
                                    <button class="btn btn-outline-danger w-100 mb-2" onclick="showLockAllSessions()">
                                        <i class="fas fa-lock"></i> Lock All Sessions
                                    </button>
                                </div>
                                <div class="col-md-3">
                                    <button class="btn btn-outline-warning w-100 mb-2" onclick="showForcePasswordReset()">
                                        <i class="fas fa-key"></i> Force Password Reset
                                    </button>
                                </div>
                                <div class="col-md-3">
                                    <button class="btn btn-outline-info w-100 mb-2" onclick="showActiveSessionsReport()">
                                        <i class="fas fa-users"></i> Active Sessions
                                    </button>
                                </div>
                                <div class="col-md-3">
                                    <button class="btn btn-outline-success w-100 mb-2" onclick="showSystemHealthCheck()">
                                        <i class="fas fa-heartbeat"></i> Health Check
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
function runSecurityScan() {
    alert('Security scan functionality would:\n\n' +
          '• Check for security vulnerabilities\n' +
          '• Validate user permissions\n' +
          '• Scan for suspicious activity\n' +
          '• Generate security report\n\n' +
          'This is a demonstration interface.');
}

function showPasswordPolicy() {
    alert('Password Policy:\n\n' +
          '• Minimum 8 characters\n' +
          '• At least one uppercase letter\n' +
          '• At least one lowercase letter\n' +
          '• At least one number\n' +
          '• At least one special character\n' +
          '• Cannot reuse last 5 passwords\n' +
          '• Must change every 90 days');
}

function showPermissionMatrix() {
    alert('Permission Matrix would show:\n\n' +
          '• Role-based access controls\n' +
          '• Module permissions per role\n' +
          '• User-specific overrides\n' +
          '• Access level hierarchies\n\n' +
          'This is a demonstration interface.');
}

function exportSecurityReport() {
    alert('Security report would include:\n\n' +
          '• User access summary\n' +
          '• Failed login attempts\n' +
          '• Permission changes\n' +
          '• Security recommendations\n\n' +
          'This is a demonstration interface.');
}

function showFullAuditLog() {
    alert('Full audit log would show:\n\n' +
          '• Complete user activity history\n' +
          '• Login/logout events\n' +
          '• Data modifications\n' +
          '• Administrative actions\n\n' +
          'This is a demonstration interface.');
}

function showLockAllSessions() {
    if (confirm('This will log out ALL users except administrators. Continue?')) {
        alert('All user sessions would be terminated.');
    }
}

function showForcePasswordReset() {
    alert('Force password reset would:\n\n' +
          '• Require all users to change passwords\n' +
          '• Send notification emails\n' +
          '• Log security action\n\n' +
          'This is a demonstration interface.');
}

function showActiveSessionsReport() {
    alert('Active sessions report would show:\n\n' +
          '• Currently logged in users\n' +
          '• Session start times\n' +
          '• IP addresses\n' +
          '• User activity status\n\n' +
          'This is a demonstration interface.');
}

function showSystemHealthCheck() {
    window.location.href = '/Armis2/admin/health.php';
}
</script>

<?php include dirname(__DIR__) . '/shared/footer.php'; ?>