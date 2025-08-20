<?php
/**
 * ARMIS Data Warehouse Dashboard
 * Strategic data storage and ETL process management
 */

// Module constants
define('ARMIS_DATA_WAREHOUSE', true);

// Include core files
require_once dirname(dirname(__DIR__)) . '/config.php';
require_once dirname(dirname(__DIR__)) . '/shared/session_init.php';
require_once dirname(dirname(__DIR__)) . '/shared/database_connection.php';
require_once dirname(dirname(__DIR__)) . '/shared/rbac.php';
require_once dirname(__DIR__) . '/services/warehouse_service.php';

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
$warehouseService = new WarehouseService($pdo);

// Get warehouse data
$warehouseData = $warehouseService->getWarehouseOverview();

$pageTitle = 'Data Warehouse Dashboard';
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
        .warehouse-card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.2s;
        }
        .warehouse-card:hover {
            transform: translateY(-3px);
        }
        .etl-pipeline {
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .data-flow {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin: 20px 0;
        }
        .data-flow-step {
            background: white;
            color: #333;
            border-radius: 50px;
            padding: 15px 25px;
            font-weight: bold;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .data-flow-arrow {
            color: white;
            font-size: 1.5rem;
        }
        .query-performance {
            font-family: monospace;
            font-size: 0.9rem;
        }
        .data-mart-card {
            border-left: 4px solid;
            margin-bottom: 15px;
        }
        .data-mart-personnel { border-left-color: #007bff; }
        .data-mart-operations { border-left-color: #28a745; }
        .data-mart-financial { border-left-color: #ffc107; }
        .data-mart-inventory { border-left-color: #dc3545; }
        .status-active { color: #28a745; }
        .status-inactive { color: #6c757d; }
        .status-error { color: #dc3545; }
        .performance-metric {
            text-align: center;
            padding: 20px;
            border-radius: 10px;
            background: #f8f9fa;
            margin-bottom: 15px;
        }
        .metric-value {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .metric-label {
            color: #6c757d;
            font-size: 0.9rem;
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
                    <!-- Page header -->
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <h1 class="h3 mb-0">
                                <i class="fas fa-warehouse text-warning"></i>
                                Data Warehouse Dashboard
                            </h1>
                            <p class="text-muted mb-0">Strategic Data Storage & ETL Process Management</p>
                        </div>
                        <div class="btn-group" role="group">
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createDataMartModal">
                                <i class="fas fa-plus"></i> Create Data Mart
                            </button>
                            <button class="btn btn-outline-success" onclick="runETLProcess()">
                                <i class="fas fa-cogs"></i> Run ETL
                            </button>
                            <button class="btn btn-outline-info" onclick="optimizeWarehouse()">
                                <i class="fas fa-tachometer-alt"></i> Optimize
                            </button>
                        </div>
                    </div>

                    <!-- ETL Pipeline Overview -->
                    <div class="etl-pipeline">
                        <h5 class="mb-3">
                            <i class="fas fa-sitemap"></i>
                            ETL Pipeline Status
                        </h5>
                        <div class="data-flow">
                            <div class="data-flow-step">
                                <i class="fas fa-database"></i> Extract
                            </div>
                            <i class="fas fa-arrow-right data-flow-arrow"></i>
                            <div class="data-flow-step">
                                <i class="fas fa-cogs"></i> Transform
                            </div>
                            <i class="fas fa-arrow-right data-flow-arrow"></i>
                            <div class="data-flow-step">
                                <i class="fas fa-warehouse"></i> Load
                            </div>
                        </div>
                        <div class="row text-center">
                            <div class="col-md-4">
                                <h4><?= $warehouseData['etl_stats']['extracted_records'] ?? 0 ?></h4>
                                <small>Records Extracted (24h)</small>
                            </div>
                            <div class="col-md-4">
                                <h4><?= $warehouseData['etl_stats']['transformed_records'] ?? 0 ?></h4>
                                <small>Records Transformed</small>
                            </div>
                            <div class="col-md-4">
                                <h4><?= $warehouseData['etl_stats']['loaded_records'] ?? 0 ?></h4>
                                <small>Records Loaded</small>
                            </div>
                        </div>
                    </div>

                    <!-- Warehouse Statistics -->
                    <div class="row mb-4">
                        <div class="col-lg-3 col-md-6 mb-3">
                            <div class="performance-metric">
                                <div class="metric-value text-primary"><?= $warehouseData['total_data_marts'] ?? 0 ?></div>
                                <div class="metric-label">Data Marts</div>
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-6 mb-3">
                            <div class="performance-metric">
                                <div class="metric-value text-success"><?= $warehouseData['storage_size_gb'] ?? 0 ?>GB</div>
                                <div class="metric-label">Storage Used</div>
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-6 mb-3">
                            <div class="performance-metric">
                                <div class="metric-value text-info"><?= $warehouseData['avg_query_time'] ?? 0 ?>ms</div>
                                <div class="metric-label">Avg Query Time</div>
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-6 mb-3">
                            <div class="performance-metric">
                                <div class="metric-value text-warning"><?= $warehouseData['active_etl_processes'] ?? 0 ?></div>
                                <div class="metric-label">Active ETL Jobs</div>
                            </div>
                        </div>
                    </div>

                    <!-- Data Marts and ETL Processes -->
                    <div class="row mb-4">
                        <div class="col-lg-8">
                            <div class="card warehouse-card">
                                <div class="card-header bg-primary text-white">
                                    <h6 class="mb-0">
                                        <i class="fas fa-cubes"></i>
                                        Data Marts
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <?php 
                                    $dataMarts = $warehouseData['data_marts'] ?? [
                                        ['id' => 1, 'name' => 'Personnel Analytics', 'type' => 'personnel', 'size_gb' => 2.5, 'last_refresh' => '2025-08-20 06:00:00', 'status' => 'active'],
                                        ['id' => 2, 'name' => 'Operations Summary', 'type' => 'operations', 'size_gb' => 1.8, 'last_refresh' => '2025-08-20 12:00:00', 'status' => 'active'],
                                        ['id' => 3, 'name' => 'Financial Reporting', 'type' => 'financial', 'size_gb' => 3.2, 'last_refresh' => '2025-08-20 03:00:00', 'status' => 'active'],
                                        ['id' => 4, 'name' => 'Inventory Intelligence', 'type' => 'inventory', 'size_gb' => 1.1, 'last_refresh' => '2025-08-19 18:00:00', 'status' => 'inactive']
                                    ];
                                    foreach ($dataMarts as $mart): 
                                    ?>
                                    <div class="card data-mart-card data-mart-<?= $mart['type'] ?>">
                                        <div class="card-body">
                                            <div class="row align-items-center">
                                                <div class="col-md-6">
                                                    <h6 class="mb-1"><?= htmlspecialchars($mart['name']) ?></h6>
                                                    <small class="text-muted">
                                                        Size: <?= $mart['size_gb'] ?>GB | 
                                                        Last Refresh: <?= date('M j, H:i', strtotime($mart['last_refresh'])) ?>
                                                    </small>
                                                </div>
                                                <div class="col-md-3">
                                                    <span class="status-<?= $mart['status'] ?>">
                                                        <i class="fas fa-circle"></i> <?= ucfirst($mart['status']) ?>
                                                    </span>
                                                </div>
                                                <div class="col-md-3">
                                                    <div class="btn-group btn-group-sm">
                                                        <button class="btn btn-outline-primary" onclick="refreshDataMart(<?= $mart['id'] ?>)">
                                                            <i class="fas fa-sync"></i>
                                                        </button>
                                                        <button class="btn btn-outline-info" onclick="viewDataMart(<?= $mart['id'] ?>)">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                        <button class="btn btn-outline-secondary" onclick="configureDataMart(<?= $mart['id'] ?>)">
                                                            <i class="fas fa-cog"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-4">
                            <div class="card warehouse-card">
                                <div class="card-header bg-success text-white">
                                    <h6 class="mb-0">
                                        <i class="fas fa-chart-area"></i>
                                        Storage Distribution
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <canvas id="storageDistributionChart"></canvas>
                                    
                                    <div class="mt-3">
                                        <h6>Storage Breakdown</h6>
                                        <div class="row">
                                            <div class="col-6">
                                                <small class="text-muted">Personnel Data</small>
                                                <div class="progress mb-2" style="height: 15px;">
                                                    <div class="progress-bar bg-primary" style="width: 30%"></div>
                                                </div>
                                            </div>
                                            <div class="col-6">
                                                <small class="text-muted">Operations Data</small>
                                                <div class="progress mb-2" style="height: 15px;">
                                                    <div class="progress-bar bg-success" style="width: 22%"></div>
                                                </div>
                                            </div>
                                            <div class="col-6">
                                                <small class="text-muted">Financial Data</small>
                                                <div class="progress mb-2" style="height: 15px;">
                                                    <div class="progress-bar bg-warning" style="width: 38%"></div>
                                                </div>
                                            </div>
                                            <div class="col-6">
                                                <small class="text-muted">Inventory Data</small>
                                                <div class="progress mb-2" style="height: 15px;">
                                                    <div class="progress-bar bg-danger" style="width: 10%"></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ETL Processes and Query Performance -->
                    <div class="row">
                        <div class="col-lg-6">
                            <div class="card warehouse-card">
                                <div class="card-header bg-info text-white">
                                    <h6 class="mb-0">
                                        <i class="fas fa-cogs"></i>
                                        ETL Processes
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Process Name</th>
                                                    <th>Type</th>
                                                    <th>Status</th>
                                                    <th>Next Run</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php 
                                                $etlProcesses = $warehouseData['etl_processes'] ?? [
                                                    ['id' => 1, 'name' => 'Personnel Data ETL', 'type' => 'full_etl', 'status' => 'active', 'next_run' => '2025-08-21 02:00:00'],
                                                    ['id' => 2, 'name' => 'Operations Extract', 'type' => 'extract', 'status' => 'active', 'next_run' => '2025-08-20 18:00:00'],
                                                    ['id' => 3, 'name' => 'Financial Transform', 'type' => 'transform', 'status' => 'inactive', 'next_run' => null],
                                                    ['id' => 4, 'name' => 'Inventory Load', 'type' => 'load', 'status' => 'error', 'next_run' => '2025-08-20 16:00:00']
                                                ];
                                                foreach ($etlProcesses as $process): 
                                                ?>
                                                <tr>
                                                    <td><strong><?= htmlspecialchars($process['name']) ?></strong></td>
                                                    <td>
                                                        <span class="badge bg-light text-dark">
                                                            <?= strtoupper($process['type']) ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span class="status-<?= $process['status'] ?>">
                                                            <i class="fas fa-circle"></i>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <?= $process['next_run'] ? date('M j, H:i', strtotime($process['next_run'])) : '-' ?>
                                                    </td>
                                                    <td>
                                                        <div class="btn-group btn-group-sm">
                                                            <button class="btn btn-outline-success" onclick="runETLProcess(<?= $process['id'] ?>)">
                                                                <i class="fas fa-play"></i>
                                                            </button>
                                                            <button class="btn btn-outline-info" onclick="viewETLLogs(<?= $process['id'] ?>)">
                                                                <i class="fas fa-file-alt"></i>
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
                        </div>
                        <div class="col-lg-6">
                            <div class="card warehouse-card">
                                <div class="card-header bg-warning text-dark">
                                    <h6 class="mb-0">
                                        <i class="fas fa-tachometer-alt"></i>
                                        Query Performance
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <div class="row mb-3">
                                        <div class="col-md-4 text-center">
                                            <h4 class="text-success"><?= $warehouseData['query_stats']['avg_time_ms'] ?? 150 ?>ms</h4>
                                            <small class="text-muted">Avg Query Time</small>
                                        </div>
                                        <div class="col-md-4 text-center">
                                            <h4 class="text-primary"><?= $warehouseData['query_stats']['queries_per_hour'] ?? 1250 ?></h4>
                                            <small class="text-muted">Queries/Hour</small>
                                        </div>
                                        <div class="col-md-4 text-center">
                                            <h4 class="text-info"><?= $warehouseData['query_stats']['cache_hit_rate'] ?? 92 ?>%</h4>
                                            <small class="text-muted">Cache Hit Rate</small>
                                        </div>
                                    </div>
                                    
                                    <canvas id="queryPerformanceChart"></canvas>
                                    
                                    <div class="mt-3">
                                        <h6>Top Slow Queries</h6>
                                        <div class="query-performance">
                                            <div class="mb-2 p-2 bg-light rounded">
                                                <strong>Personnel Analytics Query</strong><br>
                                                <small class="text-muted">Avg: 245ms | Last: 2 min ago</small>
                                            </div>
                                            <div class="mb-2 p-2 bg-light rounded">
                                                <strong>Financial Summary Query</strong><br>
                                                <small class="text-muted">Avg: 189ms | Last: 5 min ago</small>
                                            </div>
                                            <div class="mb-2 p-2 bg-light rounded">
                                                <strong>Operations Report Query</strong><br>
                                                <small class="text-muted">Avg: 156ms | Last: 8 min ago</small>
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

    <!-- Create Data Mart Modal -->
    <div class="modal fade" id="createDataMartModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Create Data Mart</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Data Mart Name</label>
                                    <input type="text" class="form-control" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Category</label>
                                    <select class="form-select" required>
                                        <option value="">Select category...</option>
                                        <option value="personnel">Personnel Analytics</option>
                                        <option value="operations">Operations Intelligence</option>
                                        <option value="financial">Financial Reporting</option>
                                        <option value="inventory">Inventory Management</option>
                                        <option value="training">Training Analytics</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" rows="3"></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Source Tables</label>
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" value="staff">
                                        <label class="form-check-label">staff</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" value="audit_logs">
                                        <label class="form-check-label">audit_logs</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" value="inventory_items">
                                        <label class="form-check-label">inventory_items</label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" value="workflow_instances">
                                        <label class="form-check-label">workflow_instances</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" value="financial_records">
                                        <label class="form-check-label">financial_records</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" value="training_records">
                                        <label class="form-check-label">training_records</label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" value="supply_requisitions">
                                        <label class="form-check-label">supply_requisitions</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" value="notifications">
                                        <label class="form-check-label">notifications</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" value="performance_metrics">
                                        <label class="form-check-label">performance_metrics</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Refresh Schedule</label>
                                    <select class="form-select">
                                        <option value="0 1 * * *">Daily at 1:00 AM</option>
                                        <option value="0 */6 * * *">Every 6 hours</option>
                                        <option value="0 0 * * 0">Weekly (Sunday)</option>
                                        <option value="0 0 1 * *">Monthly (1st day)</option>
                                        <option value="custom">Custom cron expression</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Target Table Name</label>
                                    <input type="text" class="form-control" placeholder="e.g., personnel_analytics">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Create Data Mart</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Include footer -->
    <?php include dirname(dirname(__DIR__)) . '/shared/footer.php'; ?>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Initialize charts
        document.addEventListener('DOMContentLoaded', function() {
            initializeCharts();
        });

        function initializeCharts() {
            // Storage Distribution Chart
            const storageCtx = document.getElementById('storageDistributionChart').getContext('2d');
            new Chart(storageCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Personnel', 'Operations', 'Financial', 'Inventory'],
                    datasets: [{
                        data: [30, 22, 38, 10],
                        backgroundColor: ['#007bff', '#28a745', '#ffc107', '#dc3545']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'bottom' }
                    }
                }
            });

            // Query Performance Chart
            const performanceCtx = document.getElementById('queryPerformanceChart').getContext('2d');
            new Chart(performanceCtx, {
                type: 'line',
                data: {
                    labels: ['00:00', '04:00', '08:00', '12:00', '16:00', '20:00'],
                    datasets: [{
                        label: 'Avg Query Time (ms)',
                        data: [120, 135, 185, 165, 145, 130],
                        borderColor: '#007bff',
                        backgroundColor: 'rgba(0, 123, 255, 0.1)',
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        y: { beginAtZero: true }
                    }
                }
            });
        }

        function refreshDataMart(martId) {
            if (confirm('Refresh this data mart? This may take several minutes.')) {
                showNotification('Data mart refresh started', 'info');
                // Implement data mart refresh
            }
        }

        function viewDataMart(martId) {
            // Show data mart details
            console.log('View data mart:', martId);
        }

        function configureDataMart(martId) {
            // Show data mart configuration
            console.log('Configure data mart:', martId);
        }

        function runETLProcess(processId) {
            if (confirm('Run this ETL process?')) {
                showNotification('ETL process started', 'info');
                // Implement ETL process execution
            }
        }

        function viewETLLogs(processId) {
            // Show ETL process logs
            console.log('View ETL logs:', processId);
        }

        function optimizeWarehouse() {
            if (confirm('Optimize warehouse performance? This may take some time.')) {
                showNotification('Warehouse optimization started', 'info');
                // Implement warehouse optimization
            }
        }

        function showNotification(message, type) {
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
            alertDiv.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            
            const contentArea = document.querySelector('.content-area');
            contentArea.insertBefore(alertDiv, contentArea.firstChild);
            
            setTimeout(() => {
                alertDiv.remove();
            }, 5000);
        }
    </script>
</body>
</html>