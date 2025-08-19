<?php
/**
 * ARMIS Main Dashboard
 * Army Resource Management Information System
 * Central hub for all military operations and administration
 * MODERNIZED WITH PWA SUPPORT AND ENHANCED SECURITY
 */

// Include enhanced configuration
require_once __DIR__ . '/config/enhanced_config.php';

// Include scalability configuration
require_once __DIR__ . '/config/scalability.php';

// Include military formatting functions
require_once __DIR__ . '/shared/military_formatting.php';

// Include security audit service
require_once __DIR__ . '/shared/security_audit_service.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Security check - prevent accessing deleted files through URL manipulation
if (isset($_GET['page'])) {
    require_once __DIR__ . '/config/file_restoration_prevention.php';
    $requestedFile = $_GET['page'];
    if (isFileBlockedFromRestoration($requestedFile)) {
        error_log("Attempt to access blocked file via URL: " . $requestedFile);
        http_response_code(403);
        die("Access denied: This file has been removed from the system.");
    }
}

// Authentication check - redirect to login if not authenticated
if (!isset($_SESSION['user_id'])) {
    header('Location: /Armis2/login.php');
    exit();
}

// Include RBAC system for access control
require_once __DIR__ . '/shared/rbac.php';

// Restrict central dashboard to System Administrators only
requireModuleAccess('admin');

// Log access to central dashboard
logAccess('admin', 'central_dashboard_view', true);

// Page configuration for shared components
$pageTitle = "System Administrator Central Dashboard";
$moduleName = "System Administration";
$moduleIcon = "user-shield";
$currentPage = "dashboard";

$sidebarLinks = [
    ['title' => 'Dashboard', 'url' => '/Armis2/index.php', 'icon' => 'tachometer-alt', 'page' => 'dashboard'],
    ['title' => 'System Admin', 'url' => '/Armis2/system_admin/index.php', 'icon' => 'user-shield', 'page' => 'system_admin'],
    ['title' => 'Admin Branch', 'url' => '/Armis2/admin_branch/index.php', 'icon' => 'users-cog', 'page' => 'admin_branch'],
    ['title' => 'Command', 'url' => '/Armis2/command/index.php', 'icon' => 'chess-king', 'page' => 'command'],
    ['title' => 'Operations', 'url' => '/Armis2/operations/index.php', 'icon' => 'map-marked-alt', 'page' => 'operations'],
    ['title' => 'Training', 'url' => '/Armis2/training/index.php', 'icon' => 'graduation-cap', 'page' => 'training'],
    ['title' => 'Finance', 'url' => '/Armis2/finance/index.php', 'icon' => 'dollar-sign', 'page' => 'finance'],
    ['title' => 'Ordinance', 'url' => '/Armis2/ordinance/index.php', 'icon' => 'tools', 'page' => 'ordinance'],
    ['title' => 'Users', 'url' => '/Armis2/users/index.php', 'icon' => 'users', 'page' => 'users']
];

// Get user information with proper military formatting
$userName = $_SESSION['username'] ?? $_SESSION['name'] ?? 'System User';
$userRank = $_SESSION['rank'] ?? 'Staff';
$userRankAbbr = $_SESSION['rank_abbr'] ?? getRankAbbreviation($userRank);
$userFirstName = $_SESSION['first_name'] ?? $_SESSION['fname'] ?? '';
$userLastName = $_SESSION['last_name'] ?? $_SESSION['lname'] ?? '';
$userCategory = $_SESSION['category'] ?? '';
$userUnit = $_SESSION['unit'] ?? $_SESSION['unit_name'] ?? 'Central Command';
$userSvcNo = $_SESSION['svcNo'] ?? $_SESSION['service_number'] ?? '';
$userLastLogin = $_SESSION['last_login'] ?? date('Y-m-d H:i:s');

// Format the user's name according to military conventions
$formattedUserName = formatMilitaryName($userRank, $userRankAbbr, $userFirstName, $userLastName, $userCategory);

// Set additional session variables for header use
$_SESSION['formatted_name'] = $formattedUserName;
if (!empty($userRankAbbr)) $_SESSION['rank_abbr'] = $userRankAbbr;
if (!empty($userFirstName)) $_SESSION['first_name'] = $userFirstName;
if (!empty($userLastName)) $_SESSION['last_name'] = $userLastName;
if (!empty($userCategory)) $_SESSION['category'] = $userCategory;

$userInfo = [
    'name' => $formattedUserName,
    'rank' => $userRank,
    'unit' => $userUnit,
    'service_number' => $userSvcNo,
    'last_login' => $userLastLogin
];

include __DIR__ . '/shared/header.php';
include __DIR__ . '/shared/sidebar.php';
?>

<!-- Main Content -->
<div class="content-wrapper with-sidebar">
    <div class="container-fluid">
        <div class="main-content">
            <!-- Welcome Header -->
            <div class="row">
                <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <h1 class="section-title">
                                <i class="fas fa-user-shield"></i> System Administrator Central Dashboard
                            </h1>
                            <p class="text-muted">
                                Welcome back, <?php echo htmlspecialchars($userInfo['name']); ?>
                                <?php if (!empty($userInfo['service_number'])): ?>
                                    <span class="badge bg-secondary ms-2"><?php echo htmlspecialchars($userInfo['service_number']); ?></span>
                                <?php endif; ?>
                                <br>
                                <small>Unit: <?php echo htmlspecialchars($userInfo['unit']); ?> | System Administrator Access</small>
                            </p>
                        </div>
                        <div class="text-end">
                            <div class="badge bg-success mb-2">System Online</div><br>
                            <small class="text-muted">Last login: <?php echo date('d M Y, H:i', strtotime($userInfo['last_login'])); ?></small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- System Status Cards -->
            <div class="row">
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="card dashboard-card h-100 border-left-primary">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Active Personnel</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">15,247</div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-users fa-2x text-primary"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="card dashboard-card h-100 border-left-success">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Units Deployed</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">42</div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-map-marked-alt fa-2x text-success"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="card dashboard-card h-100 border-left-info">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Training Programs</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">128</div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-graduation-cap fa-2x text-info"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="card dashboard-card h-100 border-left-warning">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Active Missions</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">7</div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-crosshairs fa-2x text-warning"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Access Modules -->
            <div class="row">
                <div class="col-12">
                    <div class="card dashboard-card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-user-shield"></i> System Administrator - Module Access</h5>
                            <small class="text-muted">Full system access granted - Use responsibly</small>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-lg-3 col-md-6 mb-3">
                                    <a href="/Armis2/admin/" class="btn btn-outline-secondary w-100 p-3">
                                        <i class="fas fa-cogs fa-2x d-block mb-2"></i>
                                        <strong>System Admin</strong><br>
                                        <small>User & System Management</small>
                                    </a>
                                </div>
                                <div class="col-lg-3 col-md-6 mb-3">
                                    <a href="/Armis2/admin_branch/" class="btn btn-outline-primary w-100 p-3">
                                        <i class="fas fa-users-cog fa-2x d-block mb-2"></i>
                                        <strong>Admin Branch</strong><br>
                                        <small>Army Administration</small>
                                    </a>
                                </div>
                                <div class="col-lg-3 col-md-6 mb-3">
                                    <a href="/Armis2/command/" class="btn btn-outline-success w-100 p-3">
                                        <i class="fas fa-chess-king fa-2x d-block mb-2"></i>
                                        <strong>Command</strong><br>
                                        <small>Command Operations</small>
                                    </a>
                                </div>
                                <div class="col-lg-3 col-md-6 mb-3">
                                    <a href="/Armis2/operations/" class="btn btn-outline-danger w-100 p-3">
                                        <i class="fas fa-map-marked-alt fa-2x d-block mb-2"></i>
                                        <strong>Operations</strong><br>
                                        <small>Field Operations</small>
                                    </a>
                                </div>
                                <div class="col-lg-3 col-md-6 mb-3">
                                    <a href="/Armis2/training/" class="btn btn-outline-info w-100 p-3">
                                        <i class="fas fa-graduation-cap fa-2x d-block mb-2"></i>
                                        <strong>Training</strong><br>
                                        <small>Education & Certification</small>
                                    </a>
                                </div>
                                <div class="col-lg-3 col-md-6 mb-3">
                                    <a href="/Armis2/finance/" class="btn btn-outline-warning w-100 p-3">
                                        <i class="fas fa-calculator fa-2x d-block mb-2"></i>
                                        <strong>Finance</strong><br>
                                        <small>Budget & Procurement</small>
                                    </a>
                                </div>
                                <div class="col-lg-3 col-md-6 mb-3">
                                    <a href="/Armis2/ordinance/" class="btn btn-outline-secondary w-100 p-3">
                                        <i class="fas fa-shield-alt fa-2x d-block mb-2"></i>
                                        <strong>Ordinance</strong><br>
                                        <small>Weapons & Equipment</small>
                                    </a>
                                </div>
                                <div class="col-lg-3 col-md-6 mb-3">
                                    <a href="/Armis2/users/" class="btn btn-outline-dark w-100 p-3">
                                        <i class="fas fa-user fa-2x d-block mb-2"></i>
                                        <strong>My Profile</strong><br>
                                        <small>Personal Information</small>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Activity & Alerts -->
            <div class="row">
                <div class="col-lg-8 mb-4">
                    <div class="card dashboard-card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-clock"></i> Recent System Activity</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Time</th>
                                            <th>Module</th>
                                            <th>Activity</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td><?php echo date('H:i'); ?></td>
                                            <td><span class="badge bg-primary">Admin Branch</span></td>
                                            <td>New staff member registered</td>
                                            <td><span class="badge bg-success">Completed</span></td>
                                        </tr>
                                        <tr>
                                            <td><?php echo date('H:i', strtotime('-15 minutes')); ?></td>
                                            <td><span class="badge bg-info">Training</span></td>
                                            <td>Course completion certificate issued</td>
                                            <td><span class="badge bg-success">Completed</span></td>
                                        </tr>
                                        <tr>
                                            <td><?php echo date('H:i', strtotime('-30 minutes')); ?></td>
                                            <td><span class="badge bg-warning">Finance</span></td>
                                            <td>Budget allocation approved</td>
                                            <td><span class="badge bg-success">Approved</span></td>
                                        </tr>
                                        <tr>
                                            <td><?php echo date('H:i', strtotime('-45 minutes')); ?></td>
                                            <td><span class="badge bg-danger">Operations</span></td>
                                            <td>Mission status updated</td>
                                            <td><span class="badge bg-info">Updated</span></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4 mb-4">
                    <div class="card dashboard-card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-exclamation-triangle"></i> System Alerts</h5>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-success d-flex align-items-center">
                                <i class="fas fa-check-circle me-3"></i>
                                <div>
                                    <strong>All Systems Operational</strong><br>
                                    <small>Last check: <?php echo date('H:i'); ?></small>
                                </div>
                            </div>
                            <div class="alert alert-info d-flex align-items-center">
                                <i class="fas fa-info-circle me-3"></i>
                                <div>
                                    <strong>Scheduled Maintenance</strong><br>
                                    <small>Next: <?php echo date('Y-m-d', strtotime('+7 days')); ?> 02:00</small>
                                </div>
                            </div>
                            <div class="alert alert-warning d-flex align-items-center">
                                <i class="fas fa-users me-3"></i>
                                <div>
                                    <strong>High User Load</strong><br>
                                    <small>Current: 1,247 active users</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- System Performance -->
            <div class="row">
                <div class="col-12">
                    <div class="card dashboard-card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-tachometer-alt"></i> System Performance</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-3 text-center">
                                    <h6>Server Load</h6>
                                    <div class="progress mb-2">
                                        <div class="progress-bar bg-success" style="width: 35%"></div>
                                    </div>
                                    <small class="text-muted">35% - Optimal</small>
                                </div>
                                <div class="col-md-3 text-center">
                                    <h6>Memory Usage</h6>
                                    <div class="progress mb-2">
                                        <div class="progress-bar bg-info" style="width: 58%"></div>
                                    </div>
                                    <small class="text-muted">58% - Normal</small>
                                </div>
                                <div class="col-md-3 text-center">
                                    <h6>Database</h6>
                                    <div class="progress mb-2">
                                        <div class="progress-bar bg-warning" style="width: 72%"></div>
                                    </div>
                                    <small class="text-muted">72% - Good</small>
                                </div>
                                <div class="col-md-3 text-center">
                                    <h6>Network</h6>
                                    <div class="progress mb-2">
                                        <div class="progress-bar bg-success" style="width: 28%"></div>
                                    </div>
                                    <small class="text-muted">28% - Excellent</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.border-left-primary {
    border-left: 4px solid var(--armis-primary) !important;
}

.border-left-success {
    border-left: 4px solid #28a745 !important;
}

.border-left-info {
    border-left: 4px solid #17a2b8 !important;
}

.border-left-warning {
    border-left: 4px solid #ffc107 !important;
}

.dashboard-card {
    border: none;
    box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
    transition: all 0.3s;
}

.dashboard-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 0.25rem 2rem 0 rgba(58, 59, 69, 0.2);
}

.text-xs {
    font-size: 0.7rem;
}
</style>

<?php include __DIR__ . '/shared/footer.php'; ?>
