<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include RBAC system
require_once dirname(__DIR__) . '/shared/rbac.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . dirname($_SERVER['PHP_SELF']) . '/../login.php');
    exit();
}

// Check if user has access to command module
requireModuleAccess('command');

// Log access
logAccess('command', 'dashboard_view', true);

$pageTitle = "Command";
$moduleName = "Command";
$moduleIcon = "chess-king";
$currentPage = "dashboard";

$sidebarLinks = [
    ['title' => 'Dashboard', 'url' => '/Armis2/command/index.php', 'icon' => 'tachometer-alt', 'page' => 'dashboard'],
    ['title' => 'Operational Reports', 'url' => '/Armis2/command/operations.php', 'icon' => 'file-alt', 'page' => 'operations'],
    ['title' => 'Staff Profiles', 'url' => '/Armis2/command/profiles.php', 'icon' => 'id-card', 'page' => 'profiles'],
    ['title' => 'Command Reports', 'url' => '/Armis2/command/reports.php', 'icon' => 'chart-line', 'page' => 'reports']
];

include dirname(__DIR__) . '/shared/header.php';
include dirname(__DIR__) . '/shared/sidebar.php';
?>

<!-- Main Content -->
<div class="content-wrapper with-sidebar">
    <div class="container-fluid">
        <div class="main-content">
            <div class="row">
                <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h1 class="section-title">
                            <i class="fas fa-chess-king"></i> Command Dashboard
                        </h1>
                        <span class="badge status-badge">Command Center</span>
                    </div>
                </div>
            </div>
            
            <div class="row g-4">
                <div class="col-md-6 col-lg-3">
                    <div class="card module-card">
                        <div class="card-body text-center">
                            <div class="mb-3">
                                <i class="fas fa-sitemap fa-2x text-primary"></i>
                            </div>
                            <h5 class="card-title">Command Structure</h5>
                            <p class="card-text">View and manage command hierarchy</p>
                            <a href="/command/operations" class="btn btn-armis">View Structure</a>
                        </div>
                    </div>
                </div>

                <div class="col-md-6 col-lg-3">
                    <div class="card module-card">
                        <div class="card-body text-center">
                            <div class="mb-3">
                                <i class="fas fa-file-alt fa-2x text-primary"></i>
                            </div>
                            <h5 class="card-title">Operational Reports</h5>
                            <p class="card-text">View and manage operational status reports</p>
                            <a href="/command/operations" class="btn btn-armis">View Reports</a>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6 col-lg-3">
                    <div class="card module-card">
                        <div class="card-body text-center">
                            <div class="mb-3">
                                <i class="fas fa-id-card fa-2x text-success"></i>
                            </div>
                            <h5 class="card-title">Staff Profiles</h5>
                            <p class="card-text">Access detailed personnel profiles and records</p>
                            <a href="profiles.php" class="btn btn-armis">View Profiles</a>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6 col-lg-3">
                    <div class="card module-card">
                        <div class="card-body text-center">
                            <div class="mb-3">
                                <i class="fas fa-chart-line fa-2x text-warning"></i>
                            </div>
                            <h5 class="card-title">Command Reports</h5>
                            <p class="card-text">Generate strategic and tactical reports</p>
                            <a href="/command/reports" class="btn btn-armis">Generate Reports</a>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6 col-lg-3">
                    <div class="card module-card">
                        <div class="card-body text-center">
                            <div class="mb-3">
                                <i class="fas fa-search fa-2x text-info"></i>
                            </div>
                            <h5 class="card-title">Search & Analytics</h5>
                            <p class="card-text">Advanced search and data analytics tools</p>
                            <a href="/command/search" class="btn btn-armis">Search</a>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Command Overview -->
            <div class="row mt-5">
                <div class="col-12">
                    <h3 class="section-title mb-4">Command Overview</h3>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="card bg-primary text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h5>Active Operations</h5>
                                    <h2>15</h2>
                                </div>
                                <i class="fas fa-rocket fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="card bg-success text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h5>Personnel Ready</h5>
                                    <h2>92%</h2>
                                </div>
                                <i class="fas fa-users fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="card bg-warning text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h5>Alerts</h5>
                                    <h2>3</h2>
                                </div>
                                <i class="fas fa-exclamation-triangle fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="card bg-info text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h5>Mission Status</h5>
                                    <h2>GREEN</h2>
                                </div>
                                <i class="fas fa-check-circle fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include dirname(__DIR__) . '/shared/footer.php'; ?>