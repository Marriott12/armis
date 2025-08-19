<?php
// Define module constants
define('ARMIS_ORDINANCE', true);
define('ARMIS_DEVELOPMENT', true);

// Include ordinance authentication and services
require_once __DIR__ . '/includes/auth.php';
require_once dirname(__DIR__) . '/shared/database_connection.php';
require_once __DIR__ . '/includes/ordinance_service.php';

// Require authentication and ordinance access
requireOrdinanceAccess();

// Log page access
logOrdinanceActivity('dashboard_access', 'Accessed Ordinance Dashboard');

// Initialize ordinance service
$pdo = getDbConnection();
$ordinanceService = new OrdinanceService($pdo);
$dashboardData = [
    'kpi' => $ordinanceService->getKPIData(),
    'recent_activities' => $ordinanceService->getRecentActivities(4),
    'inventory_overview' => $ordinanceService->getInventoryOverview(),
    'maintenance_schedule' => $ordinanceService->getMaintenanceSchedule()
];

$pageTitle = "Ordinance Dashboard";
$moduleName = "Ordinance";
$moduleIcon = "tools";
$currentPage = "dashboard";

$sidebarLinks = [
    ['title' => 'Dashboard', 'url' => '/ordinance/index.php', 'icon' => 'tachometer-alt', 'page' => 'dashboard'],
    ['title' => 'Equipment Inventory', 'url' => '/ordinance/inventory.php', 'icon' => 'boxes', 'page' => 'inventory'],
    ['title' => 'Maintenance', 'url' => '/ordinance/maintenance.php', 'icon' => 'wrench', 'page' => 'maintenance'],
    ['title' => 'Supply Chain', 'url' => '/ordinance/supply.php', 'icon' => 'truck', 'page' => 'supply'],
    ['title' => 'Asset Tracking', 'url' => '/ordinance/assets.php', 'icon' => 'barcode', 'page' => 'assets'],
    ['title' => 'Reports', 'url' => '/ordinance/reports.php', 'icon' => 'chart-bar', 'page' => 'reports']
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
                            <i class="fas fa-shield-alt"></i> Ordinance Module Dashboard
                        </h1>
                        <div>
                            <span class="badge bg-danger">Secure Access</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Ordinance Dashboard Cards -->
            <div class="row">
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="card dashboard-card h-100">
                        <div class="card-body text-center">
                            <div class="dashboard-icon">
                                <i class="fas fa-boxes text-primary fa-3x"></i>
                            </div>
                            <h5 class="card-title mt-3">Total Inventory</h5>
                            <h3 class="text-primary">1,247</h3>
                            <p class="text-muted">Items in stock</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="card dashboard-card h-100">
                        <div class="card-body text-center">
                            <div class="dashboard-icon">
                                <i class="fas fa-crosshairs text-danger fa-3x"></i>
                            </div>
                            <h5 class="card-title mt-3">Weapons</h5>
                            <h3 class="text-danger">324</h3>
                            <p class="text-muted">Registered weapons</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="card dashboard-card h-100">
                        <div class="card-body text-center">
                            <div class="dashboard-icon">
                                <i class="fas fa-circle text-warning fa-3x"></i>
                            </div>
                            <h5 class="card-title mt-3">Ammunition</h5>
                            <h3 class="text-warning">45,678</h3>
                            <p class="text-muted">Rounds in stock</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="card dashboard-card h-100">
                        <div class="card-body text-center">
                            <div class="dashboard-icon">
                                <i class="fas fa-tools text-info fa-3x"></i>
                            </div>
                            <h5 class="card-title mt-3">Maintenance</h5>
                            <h3 class="text-info">12</h3>
                            <p class="text-muted">Items under maintenance</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Security Alert -->
            <div class="row">
                <div class="col-12">
                    <div class="alert alert-warning d-flex align-items-center">
                        <i class="fas fa-exclamation-triangle me-3"></i>
                        <div>
                            <strong>Security Notice:</strong> All ordinance operations require proper clearance. 
                            Unauthorized access will be logged and reported to security personnel.
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="row">
                <div class="col-12">
                    <div class="card dashboard-card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-bolt"></i> Quick Actions</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-3 mb-3">
                                    <a href="/Armis2/ordinance/inventory.php" class="btn btn-outline-primary w-100">
                                        <i class="fas fa-boxes"></i><br>
                                        Inventory Management
                                    </a>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <a href="/Armis2/ordinance/weapons.php" class="btn btn-outline-danger w-100">
                                        <i class="fas fa-crosshairs"></i><br>
                                        Weapons Registry
                                    </a>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <a href="/Armis2/ordinance/ammunition.php" class="btn btn-outline-warning w-100">
                                        <i class="fas fa-circle"></i><br>
                                        Ammunition Tracking
                                    </a>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <a href="/Armis2/ordinance/reports.php" class="btn btn-outline-info w-100">
                                        <i class="fas fa-chart-bar"></i><br>
                                        Security Reports
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="row">
                <div class="col-12">
                    <div class="card dashboard-card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-clock"></i> Recent Ordinance Activity</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Date/Time</th>
                                            <th>Activity</th>
                                            <th>Item/Weapon</th>
                                            <th>Personnel</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td><?php echo date('Y-m-d H:i'); ?></td>
                                            <td>Weapon Check-out</td>
                                            <td>M16A2 Rifle #4521</td>
                                            <td>SGT Smith, J.</td>
                                            <td><span class="badge bg-warning">Active</span></td>
                                        </tr>
                                        <tr>
                                            <td><?php echo date('Y-m-d H:i', strtotime('-1 hour')); ?></td>
                                            <td>Ammunition Issue</td>
                                            <td>5.56mm NATO (200 rounds)</td>
                                            <td>CPL Johnson, M.</td>
                                            <td><span class="badge bg-success">Completed</span></td>
                                        </tr>
                                        <tr>
                                            <td><?php echo date('Y-m-d H:i', strtotime('-3 hours')); ?></td>
                                            <td>Maintenance Scheduled</td>
                                            <td>M249 SAW #7834</td>
                                            <td>SPC Williams, R.</td>
                                            <td><span class="badge bg-info">Scheduled</span></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include dirname(__DIR__) . '/shared/footer.php'; ?>
