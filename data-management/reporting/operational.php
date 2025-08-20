<?php
/**
 * ARMIS Operational Intelligence Dashboard
 * Real-time Operations Center with live operational data feeds
 */

// Module constants
define('ARMIS_OPERATIONAL_DASHBOARD', true);

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

// Get operational intelligence data
$operationalData = $dataManagementService->getOperationalIntelligence();

$pageTitle = 'Operational Intelligence - Real-time Operations Center';
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
    
    <style>
        .ops-center {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: white;
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
        }
        .alert-level-green { color: #28a745; }
        .alert-level-yellow { color: #ffc107; }
        .alert-level-red { color: #dc3545; }
        .live-indicator {
            width: 10px;
            height: 10px;
            background: #28a745;
            border-radius: 50%;
            display: inline-block;
            margin-right: 5px;
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }
        .metric-card {
            background: white;
            border: none;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.2s;
        }
        .metric-card:hover {
            transform: translateY(-3px);
        }
        .alert-card {
            border-left: 4px solid;
            margin-bottom: 0.5rem;
        }
        .alert-critical { border-left-color: #dc3545; }
        .alert-high { border-left-color: #fd7e14; }
        .alert-medium { border-left-color: #ffc107; }
        .alert-low { border-left-color: #28a745; }
        .trend-arrow {
            font-size: 1.2rem;
            margin-left: 0.5rem;
        }
        .operations-map {
            background: #f8f9fa;
            border-radius: 10px;
            min-height: 300px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .real-time-feed {
            max-height: 400px;
            overflow-y: auto;
        }
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
                    <!-- Operations Center Header -->
                    <div class="ops-center">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <h1 class="h3 mb-2">
                                    <i class="fas fa-satellite-dish"></i>
                                    Real-time Operations Center
                                    <span class="live-indicator"></span>
                                </h1>
                                <p class="mb-0 opacity-75">Live operational data feeds and situational awareness</p>
                                <small>Last updated: <span id="lastUpdate"><?= date('Y-m-d H:i:s') ?></span></small>
                            </div>
                            <div class="col-md-4 text-end">
                                <div class="alert-level-display">
                                    <h4 class="mb-1">Alert Level</h4>
                                    <h2 class="alert-level-green">
                                        <i class="fas fa-shield-alt"></i>
                                        <?= $operationalData['real_time_operations']['alert_level'] ?? 'GREEN' ?>
                                    </h2>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Real-time Metrics -->
                    <div class="row mb-4">
                        <div class="col-lg-3 col-md-6 mb-3">
                            <div class="card metric-card">
                                <div class="card-body text-center">
                                    <i class="fas fa-crosshairs fa-2x text-primary mb-2"></i>
                                    <h3 class="text-primary"><?= $operationalData['real_time_operations']['active_operations'] ?? 8 ?></h3>
                                    <p class="text-muted mb-0">Active Operations</p>
                                    <small class="text-success">
                                        <i class="fas fa-arrow-up trend-arrow"></i>
                                        +2 from yesterday
                                    </small>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-6 mb-3">
                            <div class="card metric-card">
                                <div class="card-body text-center">
                                    <i class="fas fa-users fa-2x text-success mb-2"></i>
                                    <h3 class="text-success"><?= $operationalData['real_time_operations']['personnel_deployed'] ?? 125 ?></h3>
                                    <p class="text-muted mb-0">Personnel Deployed</p>
                                    <small class="text-warning">
                                        <i class="fas fa-minus trend-arrow"></i>
                                        Stable
                                    </small>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-6 mb-3">
                            <div class="card metric-card">
                                <div class="card-body text-center">
                                    <i class="fas fa-cogs fa-2x text-info mb-2"></i>
                                    <h3 class="text-info"><?= $operationalData['real_time_operations']['equipment_in_use'] ?? 87 ?></h3>
                                    <p class="text-muted mb-0">Equipment In Use</p>
                                    <small class="text-success">
                                        <i class="fas fa-arrow-up trend-arrow"></i>
                                        94.2% efficiency
                                    </small>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-6 mb-3">
                            <div class="card metric-card">
                                <div class="card-body text-center">
                                    <i class="fas fa-heartbeat fa-2x text-danger mb-2"></i>
                                    <h3 class="text-success">99.8%</h3>
                                    <p class="text-muted mb-0">System Uptime</p>
                                    <small class="text-success">
                                        <i class="fas fa-check trend-arrow"></i>
                                        Excellent
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Operations Map and Alert Aggregation -->
                    <div class="row mb-4">
                        <div class="col-lg-8">
                            <div class="card">
                                <div class="card-header bg-primary text-white">
                                    <h6 class="mb-0">
                                        <i class="fas fa-map-marked-alt"></i>
                                        Operational Situational Awareness
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <div class="operations-map">
                                        <div class="text-center">
                                            <i class="fas fa-globe fa-4x text-muted mb-3"></i>
                                            <h5 class="text-muted">Interactive Operations Map</h5>
                                            <p class="text-muted">Real-time operational positions and status</p>
                                            <button class="btn btn-outline-primary">
                                                <i class="fas fa-expand"></i> View Full Map
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <!-- Situational Status Indicators -->
                                    <div class="row mt-3">
                                        <div class="col-md-3 text-center">
                                            <div class="border rounded p-2">
                                                <i class="fas fa-bullseye text-success fa-2x"></i>
                                                <p class="mb-0 mt-1"><small>Current Status</small></p>
                                                <strong class="text-success">OPERATIONAL</strong>
                                            </div>
                                        </div>
                                        <div class="col-md-3 text-center">
                                            <div class="border rounded p-2">
                                                <i class="fas fa-exclamation-triangle text-warning fa-2x"></i>
                                                <p class="mb-0 mt-1"><small>Threat Level</small></p>
                                                <strong class="text-success">LOW</strong>
                                            </div>
                                        </div>
                                        <div class="col-md-3 text-center">
                                            <div class="border rounded p-2">
                                                <i class="fas fa-battery-full text-success fa-2x"></i>
                                                <p class="mb-0 mt-1"><small>Resources</small></p>
                                                <strong class="text-success">HIGH</strong>
                                            </div>
                                        </div>
                                        <div class="col-md-3 text-center">
                                            <div class="border rounded p-2">
                                                <i class="fas fa-signal text-success fa-2x"></i>
                                                <p class="mb-0 mt-1"><small>Communications</small></p>
                                                <strong class="text-success">GOOD</strong>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-4">
                            <div class="card">
                                <div class="card-header bg-warning text-dark">
                                    <h6 class="mb-0">
                                        <i class="fas fa-bell"></i>
                                        Alert Aggregation (24H)
                                    </h6>
                                </div>
                                <div class="card-body real-time-feed">
                                    <?php 
                                    $alerts = $operationalData['alert_aggregation'] ?? [
                                        ['severity' => 'LOW', 'count' => 15],
                                        ['severity' => 'MEDIUM', 'count' => 3],
                                        ['severity' => 'HIGH', 'count' => 1],
                                        ['severity' => 'CRITICAL', 'count' => 0]
                                    ];
                                    foreach ($alerts as $alert): 
                                    ?>
                                    <div class="alert-card alert-<?= strtolower($alert['severity']) ?> card p-2 mb-2">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <strong><?= $alert['severity'] ?></strong>
                                                <small class="text-muted d-block">Last 24 hours</small>
                                            </div>
                                            <span class="badge bg-secondary"><?= $alert['count'] ?></span>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                    
                                    <div class="mt-3">
                                        <h6>Recent Alerts</h6>
                                        <div class="alert alert-success alert-dismissible" role="alert">
                                            <i class="fas fa-check-circle"></i>
                                            Equipment maintenance completed
                                            <small class="d-block">5 minutes ago</small>
                                        </div>
                                        <div class="alert alert-info alert-dismissible" role="alert">
                                            <i class="fas fa-info-circle"></i>
                                            New personnel assignment
                                            <small class="d-block">12 minutes ago</small>
                                        </div>
                                        <div class="alert alert-warning alert-dismissible" role="alert">
                                            <i class="fas fa-exclamation-triangle"></i>
                                            Resource threshold reached
                                            <small class="d-block">1 hour ago</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Trend Analysis and Predictive Analytics -->
                    <div class="row mb-4">
                        <div class="col-lg-6">
                            <div class="card">
                                <div class="card-header bg-info text-white">
                                    <h6 class="mb-0">
                                        <i class="fas fa-chart-line"></i>
                                        Trend Analysis
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <canvas id="trendAnalysisChart" height="300"></canvas>
                                    
                                    <div class="row mt-3">
                                        <div class="col-6">
                                            <div class="text-center">
                                                <h6>Personnel Trend</h6>
                                                <span class="badge bg-success">
                                                    <i class="fas fa-arrow-up"></i> INCREASING
                                                </span>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="text-center">
                                                <h6>Equipment Trend</h6>
                                                <span class="badge bg-secondary">
                                                    <i class="fas fa-minus"></i> STABLE
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div class="card">
                                <div class="card-header bg-dark text-white">
                                    <h6 class="mb-0">
                                        <i class="fas fa-brain"></i>
                                        Predictive Analytics
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-6">
                                            <div class="text-center border rounded p-3 mb-3">
                                                <i class="fas fa-wrench fa-2x text-warning mb-2"></i>
                                                <h4 class="text-warning">12</h4>
                                                <p class="mb-0">Maintenance Predictions</p>
                                                <small class="text-muted">Next 30 days</small>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="text-center border rounded p-3 mb-3">
                                                <i class="fas fa-graduation-cap fa-2x text-info mb-2"></i>
                                                <h4 class="text-info">35</h4>
                                                <p class="mb-0">Training Requirements</p>
                                                <small class="text-muted">Upcoming</small>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-12">
                                            <h6>Resource Demand Forecast</h6>
                                            <div class="progress mb-2" style="height: 25px;">
                                                <div class="progress-bar bg-danger" style="width: 80%">
                                                    HIGH DEMAND
                                                </div>
                                            </div>
                                            
                                            <h6>Budget Projections</h6>
                                            <div class="alert alert-success" role="alert">
                                                <i class="fas fa-bullseye"></i>
                                                <strong>ON TARGET</strong> - Budget projections within acceptable range
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Performance Metrics -->
                    <div class="row">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header bg-success text-white">
                                    <h6 class="mb-0">
                                        <i class="fas fa-tachometer-alt"></i>
                                        Real-time Performance Metrics
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-lg-3 col-md-6">
                                            <div class="text-center">
                                                <canvas id="uptimeGauge" width="150" height="150"></canvas>
                                                <h6 class="mt-2">System Uptime</h6>
                                                <h4 class="text-success">99.8%</h4>
                                            </div>
                                        </div>
                                        <div class="col-lg-3 col-md-6">
                                            <div class="text-center">
                                                <canvas id="responseGauge" width="150" height="150"></canvas>
                                                <h6 class="mt-2">Response Time</h6>
                                                <h4 class="text-info">0.25s</h4>
                                            </div>
                                        </div>
                                        <div class="col-lg-3 col-md-6">
                                            <div class="text-center">
                                                <canvas id="satisfactionGauge" width="150" height="150"></canvas>
                                                <h6 class="mt-2">User Satisfaction</h6>
                                                <h4 class="text-warning">4.7/5</h4>
                                            </div>
                                        </div>
                                        <div class="col-lg-3 col-md-6">
                                            <div class="text-center">
                                                <canvas id="errorGauge" width="150" height="150"></canvas>
                                                <h6 class="mt-2">Error Rate</h6>
                                                <h4 class="text-success">0.02%</h4>
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
    </div>

    <!-- Include footer -->
    <?php include dirname(dirname(__DIR__)) . '/shared/footer.php'; ?>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Initialize dashboard
        document.addEventListener('DOMContentLoaded', function() {
            initializeCharts();
            startRealTimeUpdates();
        });

        function initializeCharts() {
            // Trend Analysis Chart
            const trendCtx = document.getElementById('trendAnalysisChart').getContext('2d');
            new Chart(trendCtx, {
                type: 'line',
                data: {
                    labels: ['00:00', '04:00', '08:00', '12:00', '16:00', '20:00'],
                    datasets: [{
                        label: 'Personnel Activity',
                        data: [45, 52, 68, 85, 92, 78],
                        borderColor: '#007bff',
                        backgroundColor: 'rgba(0, 123, 255, 0.1)',
                        tension: 0.4
                    }, {
                        label: 'Equipment Usage',
                        data: [38, 42, 55, 72, 68, 58],
                        borderColor: '#28a745',
                        backgroundColor: 'rgba(40, 167, 69, 0.1)',
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        title: { display: true, text: '24-Hour Activity Trends' }
                    },
                    scales: {
                        y: { beginAtZero: true, max: 100 }
                    }
                }
            });

            // Initialize gauge charts
            createGaugeChart('uptimeGauge', 99.8, 'System Uptime');
            createGaugeChart('responseGauge', 75, 'Response Time'); // 0.25s = 75% of 1s target
            createGaugeChart('satisfactionGauge', 94, 'User Satisfaction'); // 4.7/5 = 94%
            createGaugeChart('errorGauge', 2, 'Error Rate'); // 0.02% = 2% of 1% threshold
        }

        function createGaugeChart(canvasId, value, label) {
            const ctx = document.getElementById(canvasId).getContext('2d');
            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    datasets: [{
                        data: [value, 100 - value],
                        backgroundColor: [
                            value > 80 ? '#28a745' : value > 60 ? '#ffc107' : '#dc3545',
                            '#e9ecef'
                        ],
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: false,
                    cutout: '80%',
                    plugins: {
                        legend: { display: false },
                        tooltip: { enabled: false }
                    }
                }
            });
        }

        function startRealTimeUpdates() {
            // Update timestamp every 30 seconds
            setInterval(function() {
                document.getElementById('lastUpdate').textContent = new Date().toLocaleString();
            }, 30000);

            // Simulate real-time data updates
            setInterval(function() {
                // Update metrics with slight variations
                updateRealTimeMetrics();
            }, 5000);
        }

        function updateRealTimeMetrics() {
            // Simulate small changes in real-time metrics
            console.log('Updating real-time metrics...');
        }
    </script>
</body>
</html>