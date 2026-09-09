<?php
require_once dirname(__DIR__) . '/shared/module_auth.php';
require_once 'finance_manager.php';
bootModule(['module' => 'finance', 'page' => 'dashboard', 'pageTitle' => 'Finance Dashboard - ARMIS', 'moduleName' => 'Finance', 'moduleIcon' => 'calculator']);
$finance = new FinanceManager((string) $_SESSION['user_id']);
$summary = $finance->getDashboardSummary();
$activity = $finance->getRecentActivity();
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
                        <span class="badge status-badge">FY <?= $summary['year'] ?></span>
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
                            <h3 class="text-success">$<?= number_format($summary['total_budget'], 2) ?></h3>
                            <p class="text-muted">FY <?= $summary['year'] ?> allocation</p>
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
                            <h3 class="text-primary">$<?= number_format($summary['spent'], 2) ?></h3>
                            <p class="text-muted"><?= $summary['utilization'] ?>% of budget used</p>
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
                            <h3 class="text-warning"><?= number_format($summary['active_procurements']) ?></h3>
                            <p class="text-muted">Open procurement records</p>
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
                            <h3 class="text-info"><?= number_format($summary['pending_expenditures']) ?></h3>
                            <p class="text-muted">Pending expenditure reviews</p>
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
                                        <?php foreach ($activity as $item): ?><tr><td><?= htmlspecialchars($item['activity_date'] ?? 'Not scheduled') ?></td><td><?= htmlspecialchars($item['description']) ?></td><td>$<?= number_format((float) $item['amount'], 2) ?></td><td><?= htmlspecialchars($item['category']) ?></td><td><span class="badge text-bg-secondary"><?= htmlspecialchars(ucfirst($item['status'])) ?></span></td></tr><?php endforeach; ?>
                                        <?php if (!$activity): ?><tr><td colspan="5" class="text-center text-muted py-4">No financial activity recorded yet.</td></tr><?php endif; ?>
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
