<?php
// Define module constants
define('ARMIS_FINANCE', true);
define('ARMIS_DEVELOPMENT', true);

// Include finance authentication and services
require_once __DIR__ . '/includes/auth.php';
require_once dirname(__DIR__) . '/shared/database_connection.php';
require_once __DIR__ . '/includes/finance_service.php';

// Require authentication and finance access
requireFinanceAccess();

// Log page access
logFinanceActivity('dashboard_access', 'Accessed Finance Dashboard');

// Initialize finance service
$pdo = getDbConnection();
$financeService = null;
$dashboardData = null;

try {
    if ($pdo) {
        $financeService = new FinanceService($pdo);
        $dashboardData = [
            'kpi' => $financeService->getKPIData(),
            'recent_activities' => $financeService->getRecentActivities(4),
            'budget_overview' => $financeService->getBudgetOverview(),
            'expense_breakdown' => $financeService->getExpenseBreakdown(),
            'financial_alerts' => $financeService->getFinancialAlerts()
        ];
        
        error_log("Finance dashboard initialization successful");
    } else {
        throw new Exception('Database connection failed');
    }
} catch (Exception $e) {
    error_log("Finance dashboard initialization error: " . $e->getMessage());
    // Set fallback data
    $dashboardData = [
        'kpi' => ['total_budget' => 0, 'total_expenses' => 0, 'budget_utilization' => 0, 'pending_approvals' => 0, 'active_contracts' => 0],
        'recent_activities' => [],
        'budget_overview' => [],
        'expense_breakdown' => [],
        'financial_alerts' => []
    ];
}

$pageTitle = "Finance Dashboard";
$moduleName = "Finance";
$moduleIcon = "dollar-sign";
$currentPage = "dashboard";

$sidebarLinks = [
    ['title' => 'Dashboard', 'url' => '/finance/index.php', 'icon' => 'tachometer-alt', 'page' => 'dashboard'],
    ['title' => 'Budget Management', 'url' => '/finance/budget.php', 'icon' => 'chart-line', 'page' => 'budget'],
    ['title' => 'Transactions', 'url' => '/finance/transactions.php', 'icon' => 'money-bill-wave', 'page' => 'transactions'],
    ['title' => 'Contracts', 'url' => '/finance/contracts.php', 'icon' => 'handshake', 'page' => 'contracts'],
    ['title' => 'Financial Reports', 'url' => '/finance/reports.php', 'icon' => 'chart-bar', 'page' => 'reports'],
    ['title' => 'Audit Trail', 'url' => '/finance/audit.php', 'icon' => 'search-dollar', 'page' => 'audit']
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
                            <i class="fas fa-dollar-sign"></i> Finance Dashboard
                        </h1>
                        <button class="btn btn-primary">
                            <i class="fas fa-sync-alt"></i> Refresh
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- KPI Cards -->
            <div class="row g-4 mb-4">
                <div class="col-md-6 col-lg-2-4">
                    <div class="card bg-success text-white h-100">
                        <div class="card-body d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="card-title">Total Budget</h5>
                                <h2 class="mb-0">$<?= number_format($dashboardData['kpi']['total_budget'], 0) ?></h2>
                            </div>
                            <i class="fas fa-wallet fa-2x opacity-75"></i>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-2-4">
                    <div class="card bg-primary text-white h-100">
                        <div class="card-body d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="card-title">Total Expenses</h5>
                                <h2 class="mb-0">$<?= number_format($dashboardData['kpi']['total_expenses'], 0) ?></h2>
                            </div>
                            <i class="fas fa-money-bill-wave fa-2x opacity-75"></i>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-2-4">
                    <div class="card bg-info text-white h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h5 class="card-title mb-0">Budget Utilization</h5>
                                <i class="fas fa-chart-pie fa-2x opacity-75"></i>
                            </div>
                            <h2 class="mb-2"><?= $dashboardData['kpi']['budget_utilization'] ?>%</h2>
                            <div class="progress" style="height: 6px;">
                                <div class="progress-bar bg-white" style="width: <?= $dashboardData['kpi']['budget_utilization'] ?>%"></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-2-4">
                    <div class="card bg-warning text-white h-100">
                        <div class="card-body d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="card-title">Pending Approvals</h5>
                                <h2 class="mb-0"><?= $dashboardData['kpi']['pending_approvals'] ?></h2>
                            </div>
                            <i class="fas fa-clock fa-2x opacity-75"></i>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-2-4">
                    <div class="card bg-secondary text-white h-100">
                        <div class="card-body d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="card-title">Active Contracts</h5>
                                <h2 class="mb-0"><?= $dashboardData['kpi']['active_contracts'] ?></h2>
                            </div>
                            <i class="fas fa-handshake fa-2x opacity-75"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Main Dashboard Content -->
            <div class="row g-4">
                <!-- Recent Activities -->
                <div class="col-lg-6">
                    <div class="card h-100">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-clock"></i> Recent Financial Activities</h5>
                        </div>
                        <div class="card-body">
                            <div class="list-group list-group-flush">
                                <?php if (!empty($dashboardData['recent_activities'])): ?>
                                    <?php foreach ($dashboardData['recent_activities'] as $activity): ?>
                                        <div class="list-group-item d-flex justify-content-between align-items-center">
                                            <div>
                                                <h6 class="mb-1"><?= htmlspecialchars($activity['title']) ?></h6>
                                                <p class="mb-1"><?= htmlspecialchars($activity['description']) ?></p>
                                                <small class="text-muted"><?= date('M j, H:i', strtotime($activity['timestamp'])) ?></small>
                                            </div>
                                            <span class="badge bg-<?= $activity['status'] === 'approved' ? 'success' : ($activity['status'] === 'pending' ? 'warning' : 'secondary') ?>">
                                                <?= ucfirst($activity['status']) ?>
                                            </span>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="text-center text-muted">No recent activities</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Financial Alerts -->
                <div class="col-lg-6">
                    <div class="card h-100">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-exclamation-triangle"></i> Financial Alerts</h5>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($dashboardData['financial_alerts'])): ?>
                                <?php foreach ($dashboardData['financial_alerts'] as $alert): ?>
                                    <div class="alert alert-<?= $alert['priority'] === 'high' ? 'danger' : 'warning' ?> alert-dismissible">
                                        <h6 class="alert-heading"><?= htmlspecialchars($alert['title']) ?></h6>
                                        <p class="mb-0"><?= htmlspecialchars($alert['message']) ?></p>
                                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="text-center text-muted">No financial alerts</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Budget Overview -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-chart-bar"></i> Budget Overview</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <?php if (!empty($dashboardData['budget_overview'])): ?>
                                    <?php foreach ($dashboardData['budget_overview'] as $budget): ?>
                                        <?php 
                                            $allocated = (float)$budget['allocated_amount'];
                                            $spent = (float)$budget['spent_amount'];
                                            $utilization = $allocated > 0 ? round(($spent / $allocated) * 100, 1) : 0;
                                        ?>
                                        <div class="col-md-6 col-lg-4 mb-3">
                                            <div class="card">
                                                <div class="card-body text-center">
                                                    <h6 class="card-title"><?= htmlspecialchars($budget['category']) ?></h6>
                                                    <div class="progress mb-2" style="height: 20px;">
                                                        <div class="progress-bar <?= $utilization > 90 ? 'bg-danger' : ($utilization > 75 ? 'bg-warning' : 'bg-success') ?>" 
                                                             style="width: <?= min($utilization, 100) ?>%">
                                                            <?= $utilization ?>%
                                                        </div>
                                                    </div>
                                                    <small class="text-muted">$<?= number_format($spent, 0) ?> / $<?= number_format($allocated, 0) ?></small>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="text-center text-muted">No budget data available</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include dirname(__DIR__) . '/shared/footer.php'; ?>

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
