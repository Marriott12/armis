<?php
// Define module constants
define('ARMIS_OPERATIONS', true);
define('ARMIS_DEVELOPMENT', true);

// Include operations authentication and services
require_once __DIR__ . '/includes/auth.php';
require_once dirname(__DIR__) . '/shared/database_connection.php';
require_once __DIR__ . '/includes/operations_service.php';

// Require authentication and operations access
requireOperationsAccess();

// Log page access
logOperationsActivity('dashboard_access', 'Accessed Operations Dashboard');

// Initialize operations service
$pdo = getDbConnection();
$operationsService = null;
$dashboardData = null;

try {
    if ($pdo) {
        $operationsService = new OperationsService($pdo);
        $dashboardData = [
            'kpi' => $operationsService->getKPIData(),
            'recent_activities' => $operationsService->getRecentActivities(4),
            'mission_stats' => $operationsService->getMissionStats(),
            'deployment_overview' => $operationsService->getDeploymentOverview(),
            'resource_allocation' => $operationsService->getResourceAllocation(),
            'readiness_metrics' => $operationsService->getReadinessMetrics()
        ];
        
        error_log("Operations dashboard initialization successful");
    } else {
        throw new Exception('Database connection failed');
    }
} catch (Exception $e) {
    error_log("Operations dashboard initialization error: " . $e->getMessage());
    // Set fallback data
    $dashboardData = [
        'kpi' => ['active_missions' => 0, 'active_deployments' => 0, 'resource_utilization' => 0, 'priority_alerts' => 0, 'field_units' => 0],
        'recent_activities' => [],
        'mission_stats' => ['status_distribution' => [], 'priority_distribution' => []],
        'deployment_overview' => [],
        'resource_allocation' => [],
        'readiness_metrics' => ['equipment_readiness' => 0, 'personnel_readiness' => 0]
    ];
}

$pageTitle = "Operations Dashboard";
$moduleName = "Operations";
$moduleIcon = "shield-alt";
$currentPage = "dashboard";

$sidebarLinks = [
    ['title' => 'Dashboard', 'url' => '/operations/index.php', 'icon' => 'tachometer-alt', 'page' => 'dashboard'],
    ['title' => 'Mission Planning', 'url' => '/operations/missions.php', 'icon' => 'map-marked-alt', 'page' => 'missions'],
    ['title' => 'Deployments', 'url' => '/operations/deployments.php', 'icon' => 'plane', 'page' => 'deployments'],
    ['title' => 'Resource Allocation', 'url' => '/operations/resources.php', 'icon' => 'boxes', 'page' => 'resources'],
    ['title' => 'Status Reports', 'url' => '/operations/reports.php', 'icon' => 'clipboard-list', 'page' => 'reports'],
    ['title' => 'Field Operations', 'url' => '/operations/field.php', 'icon' => 'crosshairs', 'page' => 'field']
];

include dirname(__DIR__) . '/shared/header.php';
include dirname(__DIR__) . '/shared/sidebar.php';
?>

<!-- Operations Module CSS -->
<link href="/operations/css/operations.css" rel="stylesheet">

<!-- Main Content -->
<div class="content-wrapper with-sidebar">
    <div class="container-fluid">
        <!-- Loading Indicator -->
        <div id="dashboardLoading" class="operations-loading" style="display: none;">
            <div class="loading-spinner"></div>
            <p>Loading operations data...</p>
        </div>

        <div class="main-content">
            <!-- Header Section -->
            <div class="row">
                <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h1 class="section-title">
                            <i class="fas fa-shield-alt"></i> Operations Dashboard
                        </h1>
                        <div class="d-flex align-items-center">
                            <div class="form-check form-switch me-3">
                                <input class="form-check-input" type="checkbox" id="realTimeUpdates" checked>
                                <label class="form-check-label" for="realTimeUpdates">Real-time Updates</label>
                            </div>
                            <button class="btn btn-operations-primary refresh-dashboard">
                                <i class="fas fa-sync-alt"></i> Refresh
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- KPI Cards -->
            <div class="row g-4 mb-4">
                <div class="col-md-6 col-lg-2-4">
                    <div class="card kpi-card text-white h-100" data-target="active-missions">
                        <div class="card-body d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="card-title">Active Missions</h5>
                                <h2 class="mb-0" id="activeMissions"><?= $dashboardData['kpi']['active_missions'] ?? 0 ?></h2>
                            </div>
                            <i class="fas fa-rocket fa-2x opacity-75"></i>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-2-4">
                    <div class="card kpi-card success text-white h-100" data-target="deployments">
                        <div class="card-body d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="card-title">Deployments</h5>
                                <h2 class="mb-0" id="activeDeployments"><?= $dashboardData['kpi']['active_deployments'] ?? 0 ?></h2>
                            </div>
                            <i class="fas fa-plane fa-2x opacity-75"></i>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-2-4">
                    <div class="card kpi-card info text-white h-100" data-target="resources">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h5 class="card-title mb-0">Resource Usage</h5>
                                <i class="fas fa-boxes fa-2x opacity-75"></i>
                            </div>
                            <h2 class="mb-2" id="resourceUtilization"><?= $dashboardData['kpi']['resource_utilization'] ?? 0 ?>%</h2>
                            <div class="progress" style="height: 6px;">
                                <div class="progress-bar bg-white" id="resourceProgress" style="width: <?= $dashboardData['kpi']['resource_utilization'] ?? 0 ?>%"></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-2-4">
                    <div class="card kpi-card warning text-white h-100" data-target="alerts">
                        <div class="card-body d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="card-title">Priority Alerts</h5>
                                <h2 class="mb-0" id="priorityAlerts"><?= $dashboardData['kpi']['priority_alerts'] ?? 0 ?></h2>
                            </div>
                            <i class="fas fa-exclamation-triangle fa-2x opacity-75"></i>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-2-4">
                    <div class="card kpi-card info text-white h-100" data-target="field-units">
                        <div class="card-body d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="card-title">Field Units</h5>
                                <h2 class="mb-0" id="fieldUnits"><?= $dashboardData['kpi']['field_units'] ?? 0 ?></h2>
                            </div>
                            <i class="fas fa-users fa-2x opacity-75"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Main Dashboard Content -->
            <div class="row g-4">
                <!-- Recent Activities -->
                <div class="col-lg-6">
                    <div class="card operations-card h-100">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-clock"></i> Recent Operations</h5>
                        </div>
                        <div class="card-body">
                            <div class="list-group list-group-flush" id="recentActivities">
                                <!-- Dynamic content loaded via JavaScript -->
                                <?php if (!empty($dashboardData['recent_activities'])): ?>
                                    <?php foreach ($dashboardData['recent_activities'] as $activity): ?>
                                        <div class="list-group-item d-flex justify-content-between align-items-center">
                                            <div>
                                                <h6 class="mb-1"><?= htmlspecialchars($activity['title']) ?></h6>
                                                <p class="mb-1"><?= htmlspecialchars($activity['description'] ?? '') ?></p>
                                                <small class="text-muted"><?= date('M j, H:i', strtotime($activity['timestamp'])) ?></small>
                                            </div>
                                            <span class="badge mission-status status-<?= $activity['status'] ?>"><?= ucfirst($activity['status']) ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="text-center text-muted">No recent activities</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Active Alerts -->
                <div class="col-lg-6">
                    <div class="card operations-card h-100">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-bell"></i> Active Alerts</h5>
                        </div>
                        <div class="card-body">
                            <div id="activeAlerts">
                                <!-- Dynamic content loaded via JavaScript -->
                                <div class="text-center text-muted">Loading alerts...</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Secondary Dashboard Row -->
            <div class="row g-4 mt-4">
                <!-- Mission Statistics -->
                <div class="col-lg-6">
                    <div class="card operations-card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-chart-pie"></i> Mission Statistics</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <canvas id="missionStatusChart" height="200"></canvas>
                                </div>
                                <div class="col-md-6">
                                    <canvas id="missionPriorityChart" height="200"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Readiness Metrics -->
                <div class="col-lg-6">
                    <div class="card operations-card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-tachometer-alt"></i> Operational Readiness</h5>
                        </div>
                        <div class="card-body">
                            <div class="row text-center">
                                <div class="col-md-6">
                                    <h6>Equipment Readiness</h6>
                                    <div class="readiness-gauge" id="equipmentReadiness">
                                        <div class="readiness-circle">
                                            <div class="readiness-fill" style="height: <?= $dashboardData['readiness_metrics']['equipment_readiness'] ?? 0 ?>%"></div>
                                            <div class="readiness-text"><?= $dashboardData['readiness_metrics']['equipment_readiness'] ?? 0 ?>%</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <h6>Personnel Readiness</h6>
                                    <div class="readiness-gauge" id="personnelReadiness">
                                        <div class="readiness-circle">
                                            <div class="readiness-fill" style="height: <?= $dashboardData['readiness_metrics']['personnel_readiness'] ?? 0 ?>%"></div>
                                            <div class="readiness-text"><?= $dashboardData['readiness_metrics']['personnel_readiness'] ?? 0 ?>%</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Resource Allocation Overview -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card operations-card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-chart-bar"></i> Resource Allocation</h5>
                        </div>
                        <div class="card-body">
                            <div class="row" id="resourceAllocation">
                                <!-- Dynamic content loaded via JavaScript -->
                                <?php if (!empty($dashboardData['resource_allocation'])): ?>
                                    <?php foreach ($dashboardData['resource_allocation'] as $resource): ?>
                                        <?php 
                                            $total = (int)$resource['total_available'];
                                            $allocated = (int)$resource['total_allocated']; 
                                            $utilization = $total > 0 ? round(($allocated / $total) * 100) : 0;
                                        ?>
                                        <div class="col-md-6 col-lg-3 mb-3">
                                            <div class="card operations-card">
                                                <div class="card-body text-center">
                                                    <h6 class="card-title"><?= htmlspecialchars($resource['resource_type']) ?></h6>
                                                    <div class="resource-progress mb-2">
                                                        <div class="resource-progress-bar bg-primary" style="width: <?= $utilization ?>%">
                                                            <span class="progress-text"><?= $utilization ?>%</span>
                                                        </div>
                                                    </div>
                                                    <small class="text-muted"><?= $allocated ?>/<?= $total ?> allocated</small>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="text-center text-muted">No resource data available</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Active Deployments -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card operations-card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-map-marked-alt"></i> Active Deployments</h5>
                        </div>
                        <div class="card-body">
                            <div class="row" id="deploymentOverview">
                                <!-- Dynamic content loaded via JavaScript -->
                                <?php if (!empty($dashboardData['deployment_overview'])): ?>
                                    <?php foreach ($dashboardData['deployment_overview'] as $deployment): ?>
                                        <div class="col-md-6 col-lg-4 mb-3">
                                            <div class="card operations-card h-100">
                                                <div class="card-body">
                                                    <h6 class="card-title"><?= htmlspecialchars($deployment['deployment_name']) ?></h6>
                                                    <p class="card-text small"><?= htmlspecialchars($deployment['location'] ?? 'Location TBD') ?></p>
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <span class="badge mission-status status-<?= $deployment['status'] ?>"><?= ucfirst($deployment['status']) ?></span>
                                                        <small class="text-muted"><?= $deployment['personnel_count'] ?? 0 ?> personnel</small>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="text-center text-muted">No active deployments</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Toast Container -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index: 11">
    <div id="toastContainer"></div>
</div>

<!-- Chart.js for statistics -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<!-- Operations Dashboard JavaScript -->
<script src="/operations/js/operations_dashboard.js"></script>

<?php include dirname(__DIR__) . '/shared/footer.php'; ?>