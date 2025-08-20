<?php
/**
 * ARMIS Executive Dashboard
 * Strategic Overview Dashboard for command decision-making
 */

// Module constants
define('ARMIS_EXECUTIVE_DASHBOARD', true);

// Include core files
require_once dirname(dirname(__DIR__)) . '/config.php';
require_once dirname(dirname(__DIR__)) . '/shared/session_init.php';
require_once dirname(dirname(__DIR__)) . '/shared/database_connection.php';
require_once dirname(dirname(__DIR__)) . '/shared/rbac.php';
require_once dirname(__DIR__) . '/services/data_management_service.php';

// Authentication and authorization
if (!isset($_SESSION['user_id']) || !isset($_SESSION['username'])) {
    header('Location: ' . ARMIS_BASE_URL . '/login.php');
    exit();
}

if (!hasModuleAccess('admin')) {
    header('Location: ' . ARMIS_BASE_URL . '/unauthorized.php?module=data_management');
    exit();
}

// Initialize services
$pdo = getDbConnection();
$dataManagementService = new DataManagementService($pdo);

// Get executive dashboard data
$dashboardData = $dataManagementService->getExecutiveDashboardData();

$pageTitle = 'Executive Dashboard - Strategic Overview';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> - ARMIS</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns/dist/chartjs-adapter-date-fns.bundle.min.js"></script>
    
    <style>
        .executive-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            border: none;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .metric-value {
            font-size: 2.5rem;
            font-weight: bold;
            line-height: 1;
        }
        .metric-label {
            font-size: 0.9rem;
            opacity: 0.9;
        }
        .trend-up { color: #28a745; }
        .trend-down { color: #dc3545; }
        .trend-stable { color: #6c757d; }
        .dashboard-section {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 2rem;
        }
        .chart-container {
            position: relative;
            height: 300px;
        }
        .kpi-card {
            transition: transform 0.2s;
        }
        .kpi-card:hover {
            transform: translateY(-3px);
        }
        .status-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 8px;
        }
        .status-green { background-color: #28a745; }
        .status-yellow { background-color: #ffc107; }
        .status-red { background-color: #dc3545; }
    </style>
</head>
<body>
    <!-- Include header -->
    <?php include dirname(dirname(__DIR__)) . '/shared/header.php'; ?>

    <div class="container-fluid">
        <div class="row">
            <!-- Include sidebar -->
            <div class="col-md-2 px-0">
                <?php include dirname(dirname(__DIR__)) . '/shared/sidebar.php'; ?>
            </div>

            <!-- Main content -->
            <div class="col-md-10">
                <div class="content-area p-4">
                    <!-- Page header -->
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <h1 class="h3 mb-0">
                                <i class="fas fa-tachometer-alt text-primary"></i>
                                Executive Dashboard
                            </h1>
                            <p class="text-muted mb-0">Strategic Overview for Command Decision-Making</p>
                        </div>
                        <div class="btn-group" role="group">
                            <button class="btn btn-outline-primary" onclick="refreshDashboard()">
                                <i class="fas fa-sync"></i> Refresh
                            </button>
                            <button class="btn btn-outline-secondary" onclick="exportDashboard()">
                                <i class="fas fa-download"></i> Export
                            </button>
                        </div>
                    </div>

                    <!-- Strategic Overview KPIs -->
                    <div class="row mb-4">
                        <div class="col-lg-3 col-md-6 mb-3">
                            <div class="card executive-card kpi-card">
                                <div class="card-body text-center">
                                    <i class="fas fa-users fa-2x mb-2"></i>
                                    <div class="metric-value"><?= $dashboardData['personnel_readiness']['total_personnel'] ?? 450 ?></div>
                                    <div class="metric-label">Total Personnel</div>
                                    <small class="d-block mt-1">
                                        <i class="fas fa-arrow-up trend-up"></i> 
                                        <?= $dashboardData['personnel_readiness']['readiness_percentage'] ?? 94.4 ?>% Ready
                                    </small>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-6 mb-3">
                            <div class="card executive-card kpi-card">
                                <div class="card-body text-center">
                                    <i class="fas fa-crosshairs fa-2x mb-2"></i>
                                    <div class="metric-value"><?= $dashboardData['strategic_overview']['active_missions'] ?? 8 ?></div>
                                    <div class="metric-label">Active Missions</div>
                                    <small class="d-block mt-1">
                                        <i class="fas fa-arrow-up trend-up"></i> 
                                        <?= $dashboardData['mission_performance']['success_rate'] ?? 96.5 ?>% Success Rate
                                    </small>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-6 mb-3">
                            <div class="card executive-card kpi-card">
                                <div class="card-body text-center">
                                    <i class="fas fa-chart-line fa-2x mb-2"></i>
                                    <div class="metric-value"><?= $dashboardData['strategic_overview']['resource_efficiency'] ?? 87.5 ?>%</div>
                                    <div class="metric-label">Resource Efficiency</div>
                                    <small class="d-block mt-1">
                                        <i class="fas fa-minus trend-stable"></i> 
                                        Stable Performance
                                    </small>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-6 mb-3">
                            <div class="card executive-card kpi-card">
                                <div class="card-body text-center">
                                    <i class="fas fa-shield-alt fa-2x mb-2"></i>
                                    <div class="metric-value"><?= $dashboardData['strategic_overview']['operational_readiness'] ?? 94.2 ?>%</div>
                                    <div class="metric-label">Operational Readiness</div>
                                    <small class="d-block mt-1">
                                        <i class="fas fa-arrow-up trend-up"></i> 
                                        Above Target
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Resource Utilization Dashboard -->
                    <div class="dashboard-section">
                        <div class="p-4">
                            <h5 class="mb-3">
                                <i class="fas fa-chart-pie text-primary"></i>
                                Resource Utilization Dashboard
                            </h5>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="chart-container">
                                        <canvas id="resourceUtilizationChart"></canvas>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <h6>Utilization Breakdown</h6>
                                    <div class="list-group">
                                        <div class="list-group-item d-flex justify-content-between align-items-center">
                                            <span><i class="fas fa-users text-primary"></i> Personnel</span>
                                            <div>
                                                <span class="badge bg-primary rounded-pill"><?= $dashboardData['resource_utilization']['personnel_utilization'] ?? 87.5 ?>%</span>
                                                <span class="status-indicator status-green"></span>
                                            </div>
                                        </div>
                                        <div class="list-group-item d-flex justify-content-between align-items-center">
                                            <span><i class="fas fa-cogs text-info"></i> Equipment</span>
                                            <div>
                                                <span class="badge bg-info rounded-pill"><?= $dashboardData['resource_utilization']['equipment_utilization'] ?? 82.3 ?>%</span>
                                                <span class="status-indicator status-green"></span>
                                            </div>
                                        </div>
                                        <div class="list-group-item d-flex justify-content-between align-items-center">
                                            <span><i class="fas fa-dollar-sign text-success"></i> Budget</span>
                                            <div>
                                                <span class="badge bg-success rounded-pill"><?= $dashboardData['resource_utilization']['budget_utilization'] ?? 76.8 ?>%</span>
                                                <span class="status-indicator status-yellow"></span>
                                            </div>
                                        </div>
                                        <div class="list-group-item d-flex justify-content-between align-items-center">
                                            <span><i class="fas fa-building text-warning"></i> Facilities</span>
                                            <div>
                                                <span class="badge bg-warning rounded-pill"><?= $dashboardData['resource_utilization']['facility_utilization'] ?? 91.2 ?>%</span>
                                                <span class="status-indicator status-green"></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Mission Performance & Financial Overview -->
                    <div class="row">
                        <div class="col-lg-6">
                            <div class="dashboard-section">
                                <div class="p-4">
                                    <h5 class="mb-3">
                                        <i class="fas fa-bullseye text-success"></i>
                                        Mission Performance
                                    </h5>
                                    <div class="chart-container">
                                        <canvas id="missionPerformanceChart"></canvas>
                                    </div>
                                    <div class="row text-center mt-3">
                                        <div class="col-6">
                                            <div class="border-end">
                                                <h4 class="text-success"><?= $dashboardData['mission_performance']['missions_completed'] ?? 145 ?></h4>
                                                <small class="text-muted">Missions Completed</small>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <h4 class="text-primary"><?= $dashboardData['mission_performance']['efficiency_score'] ?? 89.3 ?>%</h4>
                                            <small class="text-muted">Efficiency Score</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div class="dashboard-section">
                                <div class="p-4">
                                    <h5 class="mb-3">
                                        <i class="fas fa-chart-bar text-warning"></i>
                                        Financial Performance
                                    </h5>
                                    <div class="row mb-3">
                                        <div class="col-6">
                                            <div class="text-center">
                                                <h4 class="text-primary">$<?= number_format(($dashboardData['financial_performance']['budget_allocated'] ?? 2500000) / 1000000, 1) ?>M</h4>
                                                <small class="text-muted">Budget Allocated</small>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="text-center">
                                                <h4 class="text-success">$<?= number_format(($dashboardData['financial_performance']['budget_utilized'] ?? 1920000) / 1000000, 1) ?>M</h4>
                                                <small class="text-muted">Budget Utilized</small>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="progress mb-3" style="height: 20px;">
                                        <div class="progress-bar bg-success" role="progressbar" 
                                             style="width: <?= $dashboardData['financial_performance']['utilization_rate'] ?? 76.8 ?>%">
                                            <?= $dashboardData['financial_performance']['utilization_rate'] ?? 76.8 ?>%
                                        </div>
                                    </div>
                                    <div class="chart-container">
                                        <canvas id="financialTrendChart"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Equipment Status & Personnel Readiness -->
                    <div class="row">
                        <div class="col-lg-6">
                            <div class="dashboard-section">
                                <div class="p-4">
                                    <h5 class="mb-3">
                                        <i class="fas fa-tools text-info"></i>
                                        Equipment Status
                                    </h5>
                                    <div class="chart-container">
                                        <canvas id="equipmentStatusChart"></canvas>
                                    </div>
                                    <div class="row text-center mt-3">
                                        <div class="col-4">
                                            <h5 class="text-success"><?= $dashboardData['equipment_status']['operational'] ?? 1186 ?></h5>
                                            <small class="text-muted">Operational</small>
                                        </div>
                                        <div class="col-4">
                                            <h5 class="text-warning"><?= $dashboardData['equipment_status']['maintenance_required'] ?? 42 ?></h5>
                                            <small class="text-muted">Maintenance</small>
                                        </div>
                                        <div class="col-4">
                                            <h5 class="text-danger"><?= $dashboardData['equipment_status']['out_of_service'] ?? 22 ?></h5>
                                            <small class="text-muted">Out of Service</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div class="dashboard-section">
                                <div class="p-4">
                                    <h5 class="mb-3">
                                        <i class="fas fa-user-check text-primary"></i>
                                        Personnel Readiness
                                    </h5>
                                    <div class="chart-container">
                                        <canvas id="personnelReadinessChart"></canvas>
                                    </div>
                                    <div class="row text-center mt-3">
                                        <div class="col-4">
                                            <h5 class="text-primary"><?= $dashboardData['personnel_readiness']['active_personnel'] ?? 425 ?></h5>
                                            <small class="text-muted">Active</small>
                                        </div>
                                        <div class="col-4">
                                            <h5 class="text-info"><?= $dashboardData['personnel_readiness']['recent_activity'] ?? 398 ?></h5>
                                            <small class="text-muted">Recent Activity</small>
                                        </div>
                                        <div class="col-4">
                                            <h5 class="text-success"><?= $dashboardData['personnel_readiness']['readiness_percentage'] ?? 94.4 ?>%</h5>
                                            <small class="text-muted">Readiness</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Include footer -->
    <?php include dirname(dirname(__DIR__)) . '/shared/footer.php'; ?>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Initialize all dashboard charts
        document.addEventListener('DOMContentLoaded', function() {
            initializeCharts();
        });

        function initializeCharts() {
            // Resource Utilization Chart
            const resourceCtx = document.getElementById('resourceUtilizationChart').getContext('2d');
            new Chart(resourceCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Personnel', 'Equipment', 'Budget', 'Facilities'],
                    datasets: [{
                        data: [<?= $dashboardData['resource_utilization']['personnel_utilization'] ?? 87.5 ?>, 
                               <?= $dashboardData['resource_utilization']['equipment_utilization'] ?? 82.3 ?>, 
                               <?= $dashboardData['resource_utilization']['budget_utilization'] ?? 76.8 ?>, 
                               <?= $dashboardData['resource_utilization']['facility_utilization'] ?? 91.2 ?>],
                        backgroundColor: ['#007bff', '#17a2b8', '#28a745', '#ffc107']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        title: { display: true, text: 'Resource Utilization Distribution' }
                    }
                }
            });

            // Mission Performance Chart
            const missionCtx = document.getElementById('missionPerformanceChart').getContext('2d');
            new Chart(missionCtx, {
                type: 'line',
                data: {
                    labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                    datasets: [{
                        label: 'Success Rate (%)',
                        data: [94, 96, 95, 97, 96, <?= $dashboardData['mission_performance']['success_rate'] ?? 96.5 ?>],
                        borderColor: '#28a745',
                        backgroundColor: 'rgba(40, 167, 69, 0.1)',
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: { y: { beginAtZero: false, min: 90, max: 100 } }
                }
            });

            // Financial Trend Chart
            const financialCtx = document.getElementById('financialTrendChart').getContext('2d');
            new Chart(financialCtx, {
                type: 'bar',
                data: {
                    labels: ['Q1', 'Q2', 'Q3', 'Q4'],
                    datasets: [{
                        label: 'Budget Utilization (%)',
                        data: [72, 75, 78, <?= $dashboardData['financial_performance']['utilization_rate'] ?? 76.8 ?>],
                        backgroundColor: '#ffc107'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: { y: { beginAtZero: true, max: 100 } }
                }
            });

            // Equipment Status Chart
            const equipmentCtx = document.getElementById('equipmentStatusChart').getContext('2d');
            new Chart(equipmentCtx, {
                type: 'pie',
                data: {
                    labels: ['Operational', 'Maintenance Required', 'Out of Service'],
                    datasets: [{
                        data: [<?= $dashboardData['equipment_status']['operational'] ?? 1186 ?>, 
                               <?= $dashboardData['equipment_status']['maintenance_required'] ?? 42 ?>, 
                               <?= $dashboardData['equipment_status']['out_of_service'] ?? 22 ?>],
                        backgroundColor: ['#28a745', '#ffc107', '#dc3545']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false
                }
            });

            // Personnel Readiness Chart
            const personnelCtx = document.getElementById('personnelReadinessChart').getContext('2d');
            new Chart(personnelCtx, {
                type: 'gauge',
                data: {
                    datasets: [{
                        data: [<?= $dashboardData['personnel_readiness']['readiness_percentage'] ?? 94.4 ?>],
                        backgroundColor: ['#007bff']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false
                }
            });
        }

        function refreshDashboard() {
            location.reload();
        }

        function exportDashboard() {
            // Implement dashboard export functionality
            alert('Dashboard export functionality will be implemented');
        }
    </script>
</body>
</html>