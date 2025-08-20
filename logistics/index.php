<?php
/**
 * ARMIS Logistics Module - Main Dashboard
 * Logistics & Supply Chain Management System
 */

// Module constants
define('ARMIS_LOGISTICS', true);

// Include core files
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/shared/session_init.php';
require_once dirname(__DIR__) . '/shared/database_connection.php';
require_once dirname(__DIR__) . '/shared/rbac.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/logistics_service.php';

// Authentication and authorization
requireAuth();
requireModuleAccess('logistics');

// Initialize services
$pdo = getDbConnection();
$logisticsService = new LogisticsService($pdo);

// Get dashboard data
$dashboardData = [
    'inventory_summary' => $logisticsService->getInventorySummary(),
    'pending_requisitions' => $logisticsService->getPendingRequisitions(),
    'low_stock_items' => $logisticsService->getLowStockItems(),
    'maintenance_due' => $logisticsService->getMaintenanceDue(),
    'recent_activities' => $logisticsService->getRecentActivities(10)
];

// Page title
$pageTitle = 'Logistics Dashboard';
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
    <!-- Custom CSS -->
    <link href="css/logistics.css" rel="stylesheet">
</head>
<body>
    <!-- Include header -->
    <?php include dirname(__DIR__) . '/shared/header.php'; ?>

    <div class="container-fluid">
        <div class="row">
            <!-- Include sidebar -->
            <div class="col-md-2 px-0">
                <?php include dirname(__DIR__) . '/shared/sidebar.php'; ?>
            </div>

            <!-- Main content -->
            <div class="col-md-10">
                <div class="content-area">
                    <!-- Page header -->
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <h1 class="h3 mb-0">
                                <i class="fas fa-truck text-primary"></i>
                                Logistics Dashboard
                            </h1>
                            <p class="text-muted mb-0">Supply Chain Management & Inventory Control</p>
                        </div>
                        <div class="btn-group" role="group">
                            <a href="requisitions.php" class="btn btn-primary">
                                <i class="fas fa-plus"></i> New Requisition
                            </a>
                            <a href="inventory.php" class="btn btn-outline-primary">
                                <i class="fas fa-boxes"></i> Inventory
                            </a>
                            <button class="btn btn-outline-secondary" onclick="refreshDashboard()">
                                <i class="fas fa-sync-alt"></i> Refresh
                            </button>
                        </div>
                    </div>

                    <!-- KPI Cards -->
                    <div class="row mb-4">
                        <div class="col-lg-3 col-md-6 mb-3">
                            <div class="card border-0 shadow-sm">
                                <div class="card-body">
                                    <div class="d-flex align-items-center">
                                        <div class="me-3">
                                            <div class="bg-primary bg-gradient rounded-circle p-3">
                                                <i class="fas fa-boxes text-white"></i>
                                            </div>
                                        </div>
                                        <div>
                                            <h5 class="card-title mb-0"><?= number_format($dashboardData['inventory_summary']['total_items'] ?? 0) ?></h5>
                                            <p class="card-text text-muted">Total Items</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-3 col-md-6 mb-3">
                            <div class="card border-0 shadow-sm">
                                <div class="card-body">
                                    <div class="d-flex align-items-center">
                                        <div class="me-3">
                                            <div class="bg-warning bg-gradient rounded-circle p-3">
                                                <i class="fas fa-exclamation-triangle text-white"></i>
                                            </div>
                                        </div>
                                        <div>
                                            <h5 class="card-title mb-0"><?= count($dashboardData['low_stock_items']) ?></h5>
                                            <p class="card-text text-muted">Low Stock Items</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-3 col-md-6 mb-3">
                            <div class="card border-0 shadow-sm">
                                <div class="card-body">
                                    <div class="d-flex align-items-center">
                                        <div class="me-3">
                                            <div class="bg-info bg-gradient rounded-circle p-3">
                                                <i class="fas fa-clipboard-list text-white"></i>
                                            </div>
                                        </div>
                                        <div>
                                            <h5 class="card-title mb-0"><?= count($dashboardData['pending_requisitions']) ?></h5>
                                            <p class="card-text text-muted">Pending Requisitions</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-3 col-md-6 mb-3">
                            <div class="card border-0 shadow-sm">
                                <div class="card-body">
                                    <div class="d-flex align-items-center">
                                        <div class="me-3">
                                            <div class="bg-danger bg-gradient rounded-circle p-3">
                                                <i class="fas fa-wrench text-white"></i>
                                            </div>
                                        </div>
                                        <div>
                                            <h5 class="card-title mb-0"><?= count($dashboardData['maintenance_due']) ?></h5>
                                            <p class="card-text text-muted">Maintenance Due</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Actions & Alerts -->
                    <div class="row mb-4">
                        <div class="col-lg-8">
                            <!-- Low Stock Alerts -->
                            <?php if (!empty($dashboardData['low_stock_items'])): ?>
                            <div class="card border-0 shadow-sm mb-3">
                                <div class="card-header bg-warning bg-gradient text-white">
                                    <h6 class="mb-0">
                                        <i class="fas fa-exclamation-triangle"></i>
                                        Low Stock Alerts
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Item</th>
                                                    <th>Current Stock</th>
                                                    <th>Minimum Required</th>
                                                    <th>Action</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($dashboardData['low_stock_items'] as $item): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($item['name']) ?></td>
                                                    <td>
                                                        <span class="badge bg-danger"><?= $item['current_stock'] ?></span>
                                                    </td>
                                                    <td><?= $item['minimum_stock'] ?></td>
                                                    <td>
                                                        <a href="requisitions.php?item_id=<?= $item['id'] ?>" 
                                                           class="btn btn-sm btn-primary">
                                                            <i class="fas fa-plus"></i> Order
                                                        </a>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>

                            <!-- Pending Requisitions -->
                            <div class="card border-0 shadow-sm">
                                <div class="card-header bg-primary bg-gradient text-white">
                                    <h6 class="mb-0">
                                        <i class="fas fa-clipboard-list"></i>
                                        Recent Requisitions
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($dashboardData['pending_requisitions'])): ?>
                                        <p class="text-muted text-center mb-0">No pending requisitions</p>
                                    <?php else: ?>
                                        <div class="table-responsive">
                                            <table class="table table-sm">
                                                <thead>
                                                    <tr>
                                                        <th>Requisition #</th>
                                                        <th>Requester</th>
                                                        <th>Status</th>
                                                        <th>Priority</th>
                                                        <th>Date</th>
                                                        <th>Action</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($dashboardData['pending_requisitions'] as $req): ?>
                                                    <tr>
                                                        <td>
                                                            <a href="requisition_details.php?id=<?= $req['id'] ?>">
                                                                <?= htmlspecialchars($req['requisition_number']) ?>
                                                            </a>
                                                        </td>
                                                        <td><?= htmlspecialchars($req['requester_name']) ?></td>
                                                        <td>
                                                            <span class="badge bg-<?= $req['status'] === 'SUBMITTED' ? 'warning' : 'info' ?>">
                                                                <?= $req['status'] ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <span class="badge bg-<?= $req['priority'] === 'HIGH' ? 'danger' : ($req['priority'] === 'NORMAL' ? 'primary' : 'secondary') ?>">
                                                                <?= $req['priority'] ?>
                                                            </span>
                                                        </td>
                                                        <td><?= date('M j, Y', strtotime($req['requested_date'])) ?></td>
                                                        <td>
                                                            <a href="requisition_details.php?id=<?= $req['id'] ?>" 
                                                               class="btn btn-sm btn-outline-primary">
                                                                <i class="fas fa-eye"></i>
                                                            </a>
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

                        <div class="col-lg-4">
                            <!-- Quick Actions -->
                            <div class="card border-0 shadow-sm mb-3">
                                <div class="card-header bg-success bg-gradient text-white">
                                    <h6 class="mb-0">
                                        <i class="fas fa-bolt"></i>
                                        Quick Actions
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <div class="d-grid gap-2">
                                        <a href="requisitions.php" class="btn btn-outline-primary">
                                            <i class="fas fa-plus"></i> New Requisition
                                        </a>
                                        <a href="inventory.php" class="btn btn-outline-info">
                                            <i class="fas fa-search"></i> Search Inventory
                                        </a>
                                        <a href="vendors.php" class="btn btn-outline-warning">
                                            <i class="fas fa-handshake"></i> Manage Vendors
                                        </a>
                                        <a href="maintenance.php" class="btn btn-outline-danger">
                                            <i class="fas fa-wrench"></i> Equipment Maintenance
                                        </a>
                                        <a href="reports.php" class="btn btn-outline-success">
                                            <i class="fas fa-chart-bar"></i> View Reports
                                        </a>
                                    </div>
                                </div>
                            </div>

                            <!-- Recent Activities -->
                            <div class="card border-0 shadow-sm">
                                <div class="card-header bg-info bg-gradient text-white">
                                    <h6 class="mb-0">
                                        <i class="fas fa-history"></i>
                                        Recent Activities
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($dashboardData['recent_activities'])): ?>
                                        <p class="text-muted text-center mb-0">No recent activities</p>
                                    <?php else: ?>
                                        <div class="timeline">
                                            <?php foreach ($dashboardData['recent_activities'] as $activity): ?>
                                            <div class="timeline-item">
                                                <small class="text-muted"><?= date('M j, g:i A', strtotime($activity['created_at'])) ?></small>
                                                <p class="mb-0"><?= htmlspecialchars($activity['description']) ?></p>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Include footer -->
    <?php include dirname(__DIR__) . '/shared/footer.php'; ?>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Custom JS -->
    <script src="js/logistics.js"></script>

    <script>
        // Initialize dashboard on page load
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Logistics dashboard loaded');
        });

        function refreshDashboard() {
            location.reload();
        }
    </script>
</body>
</html>