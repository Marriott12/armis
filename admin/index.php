<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include RBAC system
require_once dirname(__DIR__) . '/shared/rbac.php';

$pageTitle = "System Admin";
$moduleName = "System Admin";
$moduleIcon = "cogs";
$currentPage = "dashboard";

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
logAccess('admin', 'dashboard_view', true);

// Add admin privilege check here if needed
// if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
//     header('Location: ' . dirname($_SERVER['PHP_SELF']) . '/../unauthorized.php');
//     exit();
// }

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
                                    <h6 class="card-title text-white-50">Total Users</h6>
                                    <h2 class="mb-0">247</h2>
                                    <small class="text-white-75">+12 this month</small>
                                </div>
                                <div class="text-white-50">
                                    <i class="fas fa-users fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-lg-6">
                    <div class="card bg-success text-white h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title text-white-50">System Uptime</h6>
                                    <h2 class="mb-0">99.8%</h2>
                                    <small class="text-white-75">Last 30 days</small>
                                </div>
                                <div class="text-white-50">
                                    <i class="fas fa-server fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-lg-6">
                    <div class="card bg-warning text-white h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title text-white-50">Pending Tasks</h6>
                                    <h2 class="mb-0">8</h2>
                                    <small class="text-white-75">3 high priority</small>
                                </div>
                                <div class="text-white-50">
                                    <i class="fas fa-tasks fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-lg-6">
                    <div class="card bg-info text-white h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title text-white-50">Database Size</h6>
                                    <h2 class="mb-0">2.4GB</h2>
                                    <small class="text-white-75">+50MB this week</small>
                                </div>
                                <div class="text-white-50">
                                    <i class="fas fa-database fa-2x"></i>
                                </div>
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
                                <div class="list-group-item admin-list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-1">Database backup completed</h6>
                                        <p class="mb-1 text-muted small">Automated backup of ARMIS database</p>
                                        <small class="text-muted">2 hours ago</small>
                                    </div>
                                    <span class="badge admin-badge bg-success">Success</span>
                                </div>
                                <div class="list-group-item admin-list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-1">New user account created</h6>
                                        <p class="mb-1 text-muted small">Staff member onboarded: Lt. Sarah Wilson</p>
                                        <small class="text-muted">4 hours ago</small>
                                    </div>
                                    <span class="badge admin-badge bg-info">Info</span>
                                </div>
                                <div class="list-group-item admin-list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-1">Security audit initiated</h6>
                                        <p class="mb-1 text-muted small">Monthly security review started</p>
                                        <small class="text-muted">6 hours ago</small>
                                    </div>
                                    <span class="badge admin-badge bg-warning">Warning</span>
                                </div>
                                <div class="list-group-item admin-list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-1">System maintenance scheduled</h6>
                                        <p class="mb-1 text-muted small">Scheduled for Sunday 2:00 AM</p>
                                        <small class="text-muted">1 day ago</small>
                                    </div>
                                    <span class="badge admin-badge bg-secondary">Scheduled</span>
                                </div>
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
                                <button class="btn admin-btn btn-primary">
                                    <i class="fas fa-user-plus"></i> Create New User
                                </button>
                                <button class="btn admin-btn btn-success">
                                    <i class="fas fa-download"></i> Database Backup
                                </button>
                                <button class="btn admin-btn btn-warning">
                                    <i class="fas fa-shield-alt"></i> Security Scan
                                </button>
                                <button class="btn admin-btn btn-info">
                                    <i class="fas fa-chart-line"></i> Generate Report
                                </button>
                                <button class="btn admin-btn btn-secondary">
                                    <i class="fas fa-cogs"></i> System Settings
                                </button>
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
                            <div class="alert alert-warning alert-sm">
                                <strong>Disk Space:</strong> 85% used on server
                            </div>
                            <div class="alert alert-info alert-sm">
                                <strong>Updates:</strong> 3 security updates available
                            </div>
                            <div class="alert alert-success alert-sm">
                                <strong>Backup:</strong> Last backup successful
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
     
<?php include dirname(__DIR__) . '/shared/footer.php'; ?>
