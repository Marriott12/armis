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

// Check if user has access to operations module
requireModuleAccess('operations');

// Log access
logAccess('operations', 'reports_view', true);

$pageTitle = "Operations | Status Reports";
$moduleName = "Operations";
$moduleIcon = "shield-alt";
$currentPage = "reports";

require_once __DIR__ . '/includes/sidebar_nav.php';

include dirname(__DIR__) . '/shared/header.php';
include dirname(__DIR__) . '/shared/sidebar.php';
require_once 'operations_manager.php';
$manager = new OperationsManager();

// Example: Get summary stats for reports
$missionStats = $manager->getAnalyticsSummary();
$fieldOps = $manager->getAllFieldOperations();
$deployments = $manager->getAllDeployments();
$resources = $manager->getAllResources();
?>
<!-- Main Content -->
<div class="content-wrapper with-sidebar">
    <div class="container-fluid">
        <div class="main-content">
            <div class="row">
                <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h1 class="section-title">
                            <i class="fas fa-clipboard-list"></i> Status Reports
                        </h1>
                        <a href="?action=new" class="btn btn-primary">
                            <i class="fas fa-plus"></i> New Report
                        </a>
                    </div>
                </div>
            </div>
            
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card text-bg-primary mb-3">
                        <div class="card-body">
                            <h5 class="card-title">Total Missions</h5>
                            <p class="card-text display-6"><?= $missionStats['total_missions'] ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-bg-success mb-3">
                        <div class="card-body">
                            <h5 class="card-title">Active Missions</h5>
                            <p class="card-text display-6"><?= $missionStats['active_missions'] ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-bg-info mb-3">
                        <div class="card-body">
                            <h5 class="card-title">Personnel Assigned</h5>
                            <p class="card-text display-6"><?= $missionStats['personnel_assigned'] ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-bg-warning mb-3">
                        <div class="card-body">
                            <h5 class="card-title">Total Resources</h5>
                            <p class="card-text display-6"><?= $missionStats['total_resources'] ?></p>
                        </div>
                    </div>
                </div>
            </div>
            <h4>Recent Field Operations</h4>
            <table class="table table-bordered table-striped mb-4">
                <thead>
                    <tr>
                        <th>Operation Name</th>
                        <th>Status</th>
                        <th>Location</th>
                        <th>Start Date</th>
                        <th>End Date</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($fieldOps as $f): ?>
                    <tr>
                        <td><?= htmlspecialchars($f['name']) ?></td>
                        <td><?= htmlspecialchars($f['status']) ?></td>
                        <td><?= htmlspecialchars($f['location']) ?></td>
                        <td><?= htmlspecialchars($f['startDate']) ?></td>
                        <td><?= htmlspecialchars($f['endDate']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <h4>Recent Deployments</h4>
            <table class="table table-bordered table-striped mb-4">
                <thead>
                    <tr>
                        <th>Deployment Name</th>
                        <th>Status</th>
                        <th>Location</th>
                        <th>Start Date</th>
                        <th>End Date</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($deployments as $d): ?>
                    <tr>
                        <td><?= htmlspecialchars($d['name']) ?></td>
                        <td><?= htmlspecialchars($d['status']) ?></td>
                        <td><?= htmlspecialchars($d['location']) ?></td>
                        <td><?= htmlspecialchars($d['startDate']) ?></td>
                        <td><?= htmlspecialchars($d['endDate']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <h4>Resource Overview</h4>
            <table class="table table-bordered table-striped mb-4">
                <thead>
                    <tr>
                        <th>Resource Name</th>
                        <th>Type</th>
                        <th>Quantity</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($resources as $r): ?>
                    <tr>
                        <td><?= htmlspecialchars($r['name']) ?></td>
                        <td><?= htmlspecialchars($r['type']) ?></td>
                        <td><?= htmlspecialchars($r['quantity']) ?></td>
                        <td><?= htmlspecialchars($r['status']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include dirname(__DIR__) . '/shared/footer.php'; ?>
