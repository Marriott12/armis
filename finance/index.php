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

// Check if user has access to finance module
requireModuleAccess('finance');

// Log access
logAccess('finance', 'dashboard_view', true);$pageTitle = "Finance Module";
$moduleName = "Finance";
$moduleIcon = "calculator";
$currentPage = "dashboard";

$sidebarLinks = [
    ['title' => 'Dashboard', 'url' => '/Armis2/finance/index.php', 'icon' => 'tachometer-alt', 'page' => 'dashboard'],
    ['title' => 'Budget Planning', 'url' => '/Armis2/finance/budget.php', 'icon' => 'chart-line', 'page' => 'budget'],
    ['title' => 'Expenditures', 'url' => '/Armis2/finance/expenditures.php', 'icon' => 'money-bill-wave', 'page' => 'expenditures'],
    ['title' => 'Procurement', 'url' => '/Armis2/finance/procurement.php', 'icon' => 'shopping-cart', 'page' => 'procurement'],
    ['title' => 'Reports', 'url' => '/Armis2/finance/reports.php', 'icon' => 'chart-bar', 'page' => 'reports'],
    ['title' => 'Audit', 'url' => '/Armis2/finance/audit.php', 'icon' => 'search-dollar', 'page' => 'audit']
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
                            <i class="fas fa-calculator"></i> Finance Module Dashboard
                        </h1>
                        <div>
                            <span class="badge bg-success">Active</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Finance Dashboard Cards -->
            <div class="row">
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="card dashboard-card h-100">
                        <div class="card-body text-center">
                            <div class="dashboard-icon">
                                <i class="fas fa-dollar-sign text-success fa-3x"></i>
                            </div>
                            <h5 class="card-title mt-3">Total Budget</h5>
                            <h3 class="text-success">$2.5M</h3>
                            <p class="text-muted">Current fiscal year</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="card dashboard-card h-100">
                        <div class="card-body text-center">
                            <div class="dashboard-icon">
                                <i class="fas fa-chart-line text-primary fa-3x"></i>
                            </div>
                            <h5 class="card-title mt-3">Expenditures</h5>
                            <h3 class="text-primary">$1.8M</h3>
                            <p class="text-muted">72% of budget used</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="card dashboard-card h-100">
                        <div class="card-body text-center">
                            <div class="dashboard-icon">
                                <i class="fas fa-shopping-cart text-warning fa-3x"></i>
                            </div>
                            <h5 class="card-title mt-3">Procurement</h5>
                            <h3 class="text-warning">25</h3>
                            <p class="text-muted">Active orders</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="card dashboard-card h-100">
                        <div class="card-body text-center">
                            <div class="dashboard-icon">
                                <i class="fas fa-search-dollar text-info fa-3x"></i>
                            </div>
                            <h5 class="card-title mt-3">Audits</h5>
                            <h3 class="text-info">3</h3>
                            <p class="text-muted">Pending reviews</p>
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
                                    <a href="/Armis2/finance/budget.php" class="btn btn-outline-primary w-100">
                                        <i class="fas fa-chart-line"></i><br>
                                        Budget Planning
                                    </a>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <a href="/Armis2/finance/expenditures.php" class="btn btn-outline-success w-100">
                                        <i class="fas fa-money-bill-wave"></i><br>
                                        Track Expenditures
                                    </a>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <a href="/Armis2/finance/procurement.php" class="btn btn-outline-warning w-100">
                                        <i class="fas fa-shopping-cart"></i><br>
                                        Procurement
                                    </a>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <a href="/Armis2/finance/reports.php" class="btn btn-outline-info w-100">
                                        <i class="fas fa-chart-bar"></i><br>
                                        Financial Reports
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
                            <h5 class="mb-0"><i class="fas fa-clock"></i> Recent Financial Activity</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Transaction</th>
                                            <th>Amount</th>
                                            <th>Category</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td><?php echo date('Y-m-d'); ?></td>
                                            <td>Equipment Purchase Order</td>
                                            <td>$45,000</td>
                                            <td>Procurement</td>
                                            <td><span class="badge bg-warning">Pending</span></td>
                                        </tr>
                                        <tr>
                                            <td><?php echo date('Y-m-d', strtotime('-1 day')); ?></td>
                                            <td>Training Budget Allocation</td>
                                            <td>$12,500</td>
                                            <td>Training</td>
                                            <td><span class="badge bg-success">Approved</span></td>
                                        </tr>
                                        <tr>
                                            <td><?php echo date('Y-m-d', strtotime('-2 days')); ?></td>
                                            <td>Operations Funding</td>
                                            <td>$75,000</td>
                                            <td>Operations</td>
                                            <td><span class="badge bg-success">Completed</span></td>
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
