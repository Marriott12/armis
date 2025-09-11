<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include RBAC system
require_once dirname(__DIR__) . '/shared/rbac.php';
require_once 'operations_manager.php';
require_once 'dashboard_widgets.php';
require_once 'notifications.php';
require_once 'audit_log.php';
require_once 'search_filter.php';
require_once 'mission_history.php';

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
    ['title' => 'Field Operations', 'url' => '/Armis2/operations/field.php', 'icon' => 'crosshairs', 'page' => 'field'],
    ['title' => 'Setup Database', 'url' => '/Armis2/operations/setup_database.php', 'icon' => 'database', 'page' => 'setup']
];

// Initialize operations manager
try {
    $operationsManager = new OperationsManager($_SESSION['user_id']);
    
    // Get dashboard data
    $activeMissions = $operationsManager->getActiveMissions(5);
    $activeDeployments = $operationsManager->getActiveDeployments(5);
    $recentReports = $operationsManager->getRecentStatusReports(5);
    $resourceAllocation = $operationsManager->getResourceAllocationSummary();
    
    // Get statistics
    $missionStats = $operationsManager->getMissionStatistics();
    $deploymentStats = $operationsManager->getDeploymentStatistics();
    $resourceStats = $operationsManager->getResourceStatistics();
    
    // Get dashboard widgets
    $dashboardStats = getDashboardStats();
    $notifications = getNotifications($_SESSION['user_id']);
    $auditTrail = getAuditTrail(10);

    // Example: search/filter usage
    $searchResults = searchMissions($_GET['search'] ?? '', $_GET['status'] ?? null);
    $filteredDeployments = filterDeployments($_GET['dep_status'] ?? null, $_GET['location'] ?? null);
    
} catch (Exception $e) {
    $error = $e->getMessage();
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
                        <?php if (isset($error)): ?>
                            <div class="alert alert-danger">
                                <?php echo $error; ?>
                                <p>Please <a href="setup_database.php" class="alert-link">set up the operations database</a> to continue.</p>
                            </div>
                        <?php else: ?>
                            <span class="badge status-badge bg-success">OPERATIONAL</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <?php if (!isset($error)): ?>
            <!-- Statistics Cards -->
            <div class="row">
                <!-- Mission Statistics -->
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-primary shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                        Missions</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $missionStats['active_missions']; ?> Active</div>
                                    <div class="text-xs text-muted"><?php echo $missionStats['total_missions']; ?> Total</div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-map-marked-alt fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Deployment Statistics -->
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-success shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                        Deployments</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $deploymentStats['active_deployments']; ?> Active</div>
                                    <div class="text-xs text-muted"><?php echo $deploymentStats['total_deployments']; ?> Total</div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-plane fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Resources Statistics -->
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-info shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Resources
                                    </div>
                                    <div class="row no-gutters align-items-center">
                                        <div class="col-auto">
                                            <div class="h5 mb-0 mr-3 font-weight-bold text-gray-800"><?php echo $resourceStats['available_resources']; ?> Available</div>
                                        </div>
                                    </div>
                                    <div class="text-xs text-muted"><?php echo $resourceStats['deployed_resources']; ?> Deployed</div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-boxes fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Status Reports Count -->
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-warning shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                        Status Reports</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo count($recentReports); ?> Recent</div>
                                    <div class="text-xs text-muted">Last 30 days</div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-clipboard-list fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Content Row -->
            <div class="row">
                <!-- Active Missions -->
                <div class="col-lg-6 mb-4">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                            <h6 class="m-0 font-weight-bold text-primary">Active Missions</h6>
                            <a href="missions.php" class="btn btn-sm btn-primary">View All</a>
                        </div>
                        <div class="card-body">
                            <?php if (empty($activeMissions)): ?>
                                <div class="text-center text-muted py-4">
                                    <p>No active missions</p>
                                    <a href="missions.php?action=new" class="btn btn-primary">Create Mission</a>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Mission</th>
                                                <th>Location</th>
                                                <th>Status</th>
                                                <th>Resources</th>
                                                <th>Personnel</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($activeMissions as $mission): ?>
                                            <tr>
                                                <td>
                                                    <a href="mission_details.php?id=<?php echo $mission['mission_id']; ?>">
                                                        <?php echo htmlspecialchars($mission['mission_name']); ?>
                                                    </a>
                                                    <small class="d-block text-muted"><?php echo htmlspecialchars($mission['mission_code']); ?></small>
                                                </td>
                                                <td><?php echo htmlspecialchars($mission['location_name'] ?? 'N/A'); ?></td>
                                                <td>
                                                    <span class="badge badge-success"><?php echo ucfirst($mission['status']); ?></span>
                                                    <?php if ($mission['priority'] == 'critical'): ?>
                                                        <span class="badge badge-danger">Critical</span>
                                                    <?php elseif ($mission['priority'] == 'high'): ?>
                                                        <span class="badge badge-warning">High</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo $mission['resource_count']; ?></td>
                                                <td><?php echo $mission['personnel_count']; ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Active Deployments -->
                <div class="col-lg-6 mb-4">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                            <h6 class="m-0 font-weight-bold text-success">Active Deployments</h6>
                            <a href="deployments.php" class="btn btn-sm btn-success">View All</a>
                        </div>
                        <div class="card-body">
                            <?php if (empty($activeDeployments)): ?>
                                <div class="text-center text-muted py-4">
                                    <p>No active deployments</p>
                                    <a href="deployments.php?action=new" class="btn btn-success">Create Deployment</a>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Deployment</th>
                                                <th>Location</th>
                                                <th>Status</th>
                                                <th>Personnel</th>
                                                <th>Dates</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($activeDeployments as $deployment): ?>
                                            <tr>
                                                <td>
                                                    <a href="deployment_details.php?id=<?php echo $deployment['deployment_id']; ?>">
                                                        <?php echo htmlspecialchars($deployment['deployment_name']); ?>
                                                    </a>
                                                    <small class="d-block text-muted"><?php echo htmlspecialchars($deployment['deployment_code']); ?></small>
                                                </td>
                                                <td><?php echo htmlspecialchars($deployment['location_name'] ?? 'N/A'); ?></td>
                                                <td><span class="badge badge-success"><?php echo ucfirst($deployment['status']); ?></span></td>
                                                <td><?php echo $deployment['personnel_count']; ?></td>
                                                <td>
                                                    <small>
                                                        <?php echo date('M d, Y', strtotime($deployment['start_date'])); ?> -
                                                        <?php echo date('M d, Y', strtotime($deployment['end_date'])); ?>
                                                    </small>
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

            <!-- Second Content Row -->
            <div class="row">
                <!-- Resource Allocation Summary -->
                <div class="col-lg-6 mb-4">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                            <h6 class="m-0 font-weight-bold text-info">Resource Allocation</h6>
                            <a href="resources.php" class="btn btn-sm btn-info">Manage Resources</a>
                        </div>
                        <div class="card-body">
                            <?php if (empty($resourceAllocation)): ?>
                                <div class="text-center text-muted py-4">
                                    <p>No resource data available</p>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-bordered">
                                        <thead>
                                            <tr>
                                                <th>Resource Type</th>
                                                <th>Total</th>
                                                <th>Available</th>
                                                <th>Deployed</th>
                                                <th>Maintenance</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($resourceAllocation as $resource): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($resource['resource_type_name']); ?></td>
                                                <td><?php echo $resource['total_resources']; ?></td>
                                                <td><?php echo $resource['available']; ?></td>
                                                <td><?php echo $resource['deployed']; ?></td>
                                                <td><?php echo $resource['maintenance']; ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Recent Status Reports -->
                <div class="col-lg-6 mb-4">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                            <h6 class="m-0 font-weight-bold text-warning">Recent Status Reports</h6>
                            <a href="reports.php" class="btn btn-sm btn-warning">View All Reports</a>
                        </div>
                        <div class="card-body">
                            <?php if (empty($recentReports)): ?>
                                <div class="text-center text-muted py-4">
                                    <p>No status reports available</p>
                                    <a href="reports.php?action=new" class="btn btn-warning">Create Report</a>
                                </div>
                            <?php else: ?>
                                <div class="list-group">
                                    <?php foreach ($recentReports as $report): ?>
                                    <a href="report_details.php?id=<?php echo $report['report_id']; ?>" class="list-group-item list-group-item-action">
                                        <div class="d-flex w-100 justify-content-between">
                                            <h6 class="mb-1"><?php echo htmlspecialchars($report['report_title']); ?></h6>
                                            <small><?php echo date('M d, Y', strtotime($report['report_date'])); ?></small>
                                        </div>
                                        <p class="mb-1 text-truncate"><?php echo htmlspecialchars(substr($report['report_content'], 0, 100)); ?>...</p>
                                        <small>Mission: <?php echo htmlspecialchars($report['mission_name'] ?? 'N/A'); ?></small>
                                        <small class="d-block">By: <?php echo htmlspecialchars($report['submitted_by_name'] ?? 'Unknown'); ?></small>
                                    </a>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php include dirname(__DIR__) . '/shared/footer.php'; ?>