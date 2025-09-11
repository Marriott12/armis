<?php
require_once dirname(__DIR__) . '/shared/header.php';
require_once dirname(__DIR__) . '/shared/sidebar.php';
require_once 'operations_manager.php';
$manager = new OperationsManager();
$summary = $manager->getAnalyticsSummary();
?>
<div class="main-content">
    <h2 class="mt-4 mb-4">Operations Analytics Dashboard</h2>
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card text-bg-primary mb-3">
                <div class="card-body">
                    <h5 class="card-title">Total Missions</h5>
                    <p class="card-text display-6"><?= $summary['total_missions'] ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-bg-success mb-3">
                <div class="card-body">
                    <h5 class="card-title">Active Missions</h5>
                    <p class="card-text display-6"><?= $summary['active_missions'] ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-bg-info mb-3">
                <div class="card-body">
                    <h5 class="card-title">Personnel Assigned</h5>
                    <p class="card-text display-6"><?= $summary['personnel_assigned'] ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-bg-warning mb-3">
                <div class="card-body">
                    <h5 class="card-title">Total Resources</h5>
                    <p class="card-text display-6"><?= $summary['total_resources'] ?></p>
                </div>
            </div>
        </div>
    </div>
    <h4>Resource Status Breakdown</h4>
    <table class="table table-bordered table-striped">
        <thead>
            <tr>
                <th>Status</th>
                <th>Count</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($summary['resource_status'] as $row): ?>
            <tr>
                <td><?= htmlspecialchars($row['status']) ?></td>
                <td><?= $row['count'] ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php require_once dirname(__DIR__) . '/shared/footer.php'; ?>
