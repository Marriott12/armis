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

// Check if user has access to operations module
requireModuleAccess('operations');

// Log access
logAccess('operations', 'dashboard_view', true);

$pageTitle = "Operations";
$moduleName = "Operations";
$moduleIcon = "shield-alt";
$currentPage = "dashboard";

$sidebarLinks = [
    ['title' => 'Dashboard', 'url' => '/Armis2/operations/index.php', 'icon' => 'tachometer-alt', 'page' => 'dashboard'],
    ['title' => 'Mission Planning', 'url' => '/Armis2/operations/missions.php', 'icon' => 'map-marked-alt', 'page' => 'missions'],
    ['title' => 'Deployments', 'url' => '/Armis2/operations/deployments.php', 'icon' => 'plane', 'page' => 'deployments'],
    ['title' => 'Resource Allocation', 'url' => '/Armis2/operations/resources.php', 'icon' => 'boxes', 'page' => 'resources'],
    ['title' => 'Status Reports', 'url' => '/Armis2/operations/reports.php', 'icon' => 'clipboard-list', 'page' => 'reports'],
    ['title' => 'Field Operations', 'url' => '/Armis2/operations/field.php', 'icon' => 'crosshairs', 'page' => 'field']
];

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . dirname($_SERVER['PHP_SELF']) . '/../login.php');
    exit();
}

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
                            <i class="fas fa-shield-alt"></i> Operations Dashboard
                        </h1>
                        <span class="badge status-badge bg-success">OPERATIONAL</span>
                    </div>
                </div>
            </div>
            
            <div class="row g-4">
                <div class="col-md-6 col-lg-3">
                    <div class="card module-card">
                        <div class="card-body text-center">
                            <div class="mb-3">
                                <i class="fas fa-map-marked-alt fa-2x text-primary"></i>
                            </div>
                            <h5 class="card-title">Mission Planning</h5>
                            <p class="card-text">Plan and coordinate operational missions</p>
                            <a href="/operations/missions" class="btn btn-armis">Plan Missions</a>
                        </div>
                    </div>
                </div>

                <div class="col-md-6 col-lg-3">
                    <div class="card module-card">
                        <div class="card-body text-center">
                            <div class="mb-3">
                                <i class="fas fa-plane fa-2x text-success"></i>
                            </div>
                            <h5 class="card-title">Deployments</h5>
                            <p class="card-text">Track deployment status and logistics</p>
                            <a href="/operations/deployments" class="btn btn-armis">View Deployments</a>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6 col-lg-3">
                    <div class="card module-card">
                        <div class="card-body text-center">
                            <div class="mb-3">
                                <i class="fas fa-boxes fa-2x text-success"></i>
                            </div>
                            <h5 class="card-title">Resource Allocation</h5>
                            <p class="card-text">Manage and allocate operational resources</p>
                            <a href="/operations/resources" class="btn btn-armis">Manage Resources</a>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6 col-lg-3">
                    <div class="card module-card">
                        <div class="card-body text-center">
                            <div class="mb-3">
                                <i class="fas fa-clipboard-list fa-2x text-warning"></i>
                            </div>
                            <h5 class="card-title">Status Reports</h5>
                            <p class="card-text">Monitor operational status and reporting</p>
                            <a href="/operations/reports" class="btn btn-armis">View Reports</a>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6 col-lg-3">
                    <div class="card module-card">
                        <div class="card-body text-center">
                            <div class="mb-3">
                                <i class="fas fa-crosshairs fa-2x text-danger"></i>
                            </div>
                            <h5 class="card-title">Field Operations</h5>
                            <p class="card-text">Real-time field operation coordination</p>
                            <a href="/operations/field" class="btn btn-armis">Field Ops</a>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Operational Status -->
            <div class="row mt-5">
                <div class="col-12">
                    <h3 class="section-title mb-4">Operational Status</h3>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="card bg-success text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h5>Active Missions</h5>
                                    <h2>7</h2>
                                </div>
                                <i class="fas fa-rocket fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="card bg-primary text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h5>Resources</h5>
                                    <h2>94%</h2>
                                </div>
                                <i class="fas fa-boxes fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="card bg-warning text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h5>Priority Alerts</h5>
                                    <h2>2</h2>
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
                                    <h5>Field Units</h5>
                                    <h2>23</h2>
                                </div>
                                <i class="fas fa-users fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Mission Timeline -->
            <div class="row mt-5">
                <div class="col-12">
                    <h3 class="section-title mb-4">Recent Operations</h3>
                    <div class="card">
                        <div class="card-body">
                            <div class="list-group list-group-flush">
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-1">Operation Phoenix</h6>
                                        <p class="mb-1">Strategic reconnaissance mission - Sector 7</p>
                                        <small class="text-muted">Active - Started 6 hours ago</small>
                                    </div>
                                    <span class="badge bg-success">Active</span>
                                </div>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-1">Operation Shield</h6>
                                        <p class="mb-1">Defensive perimeter establishment</p>
                                        <small class="text-muted">Completed 2 days ago</small>
                                    </div>
                                    <span class="badge bg-primary">Completed</span>
                                </div>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-1">Operation Thunder</h6>
                                        <p class="mb-1">Equipment deployment and testing</p>
                                        <small class="text-muted">Scheduled for next week</small>
                                    </div>
                                    <span class="badge bg-warning">Planned</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include dirname(__DIR__) . '/shared/footer.php'; ?>