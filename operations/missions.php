<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include RBAC system
require_once dirname(__DIR__) . '/shared/rbac.php';
require_once 'operations_manager.php';
require_once 'notifications.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . dirname($_SERVER['PHP_SELF']) . '/../login.php');
    exit();
}

// Check if user has access to operations module
requireModuleAccess('operations');

$pageTitle = "Operations | Missions";
$moduleName = "Operations";
$moduleIcon = "shield-alt";
$currentPage = "missions";

$sidebarLinks = [
    ['title' => 'Dashboard', 'url' => '/Armis2/operations/index.php', 'icon' => 'tachometer-alt', 'page' => 'dashboard'],
    ['title' => 'Mission Planning', 'url' => '/Armis2/operations/missions.php', 'icon' => 'map-marked-alt', 'page' => 'missions'],
    ['title' => 'Deployments', 'url' => '/Armis2/operations/deployments.php', 'icon' => 'plane', 'page' => 'deployments'],
    ['title' => 'Resource Allocation', 'url' => '/Armis2/operations/resources.php', 'icon' => 'boxes', 'page' => 'resources'],
    ['title' => 'Status Reports', 'url' => '/Armis2/operations/reports.php', 'icon' => 'clipboard-list', 'page' => 'reports'],
    ['title' => 'Field Operations', 'url' => '/Armis2/operations/field.php', 'icon' => 'crosshairs', 'page' => 'field'],
    ['title' => 'Setup Database', 'url' => '/Armis2/operations/setup_database.php', 'icon' => 'database', 'page' => 'setup']
];

$success = $error = '';
try {
    $operationsManager = new OperationsManager($_SESSION['user_id']);
    // Handle form submissions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $missionName = trim($_POST['mission_name'] ?? '');
        $missionCode = trim($_POST['mission_code'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $status = $_POST['status'] ?? 'planned';
        $priority = $_POST['priority'] ?? 'medium';
        $locationId = $_POST['location_id'] ?? null;
        $startDate = $_POST['start_date'] ?? null;
        $endDate = $_POST['end_date'] ?? null;

        // Validation
        if ($missionName === '' || $missionCode === '') {
            $error = 'Mission Name and Mission Code are required.';
        } else {
            try {
                $operationsManager->createMission([
                    'mission_name' => $missionName,
                    'mission_code' => $missionCode,
                    'description' => $description,
                    'status' => $status,
                    'priority' => $priority,
                    'location_id' => $locationId,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'created_by' => $_SESSION['user_id']
                ]);
                sendNotification($_SESSION['user_id'], "Mission '$missionName' created successfully.", 'success');
                $success = 'Mission created successfully.';
            } catch (Exception $e) {
                $error = $e->getMessage();
            }
        }
    }
    
    // Get all missions (with resource/personnel counts fallback)
    $missions = $operationsManager->getAllMissions();
    foreach ($missions as &$mission) {
        if (!isset($mission['resource_count'])) {
            $mission['resource_count'] = 0;
        }
        if (!isset($mission['personnel_count'])) {
            $mission['personnel_count'] = 0;
        }
    }
    unset($mission);
    
    // Get locations for dropdown
    $locations = $operationsManager->getLocations();
} catch (Exception $e) {
    $error = $e->getMessage();
}

include dirname(__DIR__) . '/shared/header.php';
include dirname(__DIR__) . '/shared/sidebar.php';
?>

<!-- Main Content -->
<div class="content-wrapper with-sidebar">
    <div class="container-fluid">
        <div class="main-content">
            <h2 class="mt-4 mb-4">Mission Planning</h2>
            <form id="mission-search-form" class="row g-3 mb-4">
                <div class="col-md-4">
                    <label for="search" class="form-label">Mission Name</label>
                    <input type="text" name="search" id="search" class="form-control" placeholder="Enter mission name">
                </div>
                <div class="col-md-3">
                    <label for="status" class="form-label">Status</label>
                    <input type="text" name="status" id="status" class="form-control" placeholder="Enter status">
                </div>
                <div class="col-md-3">
                    <label for="priority" class="form-label">Priority</label>
                    <input type="text" name="priority" id="priority" class="form-control" placeholder="Enter priority">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="button" id="search-btn" class="btn btn-primary w-100">Search</button>
                </div>
            </form>
            <!-- Add Mission Button -->
            <div class="mb-3 text-end">
                <a href="missions.php?action=new" class="btn btn-success">Add Mission</a>
            </div>
            <div id="mission-results">
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>Mission Name</th>
                            <th>Status</th>
                            <th>Priority</th>
                            <th>Timeline</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($missions as $mission): ?>
                        <tr>
                            <td><?= htmlspecialchars($mission['mission_name']) ?></td>
                            <td><?= htmlspecialchars($mission['status']) ?></td>
                            <td><?= htmlspecialchars($mission['priority']) ?></td>
                            <td>
                                <small>
                                    <?= date('M d, Y', strtotime($mission['start_date'])) ?> -
                                    <?= date('M d, Y', strtotime($mission['end_date'])) ?>
                                </small>
                            </td>
                            <td>
                                <a href="mission_details.php?id=<?= $mission['mission_id'] ?>" class="btn btn-info btn-sm">View</a>
                                <a href="mission_edit.php?id=<?= $mission['mission_id'] ?>" class="btn btn-primary btn-sm">Edit</a>
                                <a href="mission_delete.php?id=<?= $mission['mission_id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Delete this mission?')">Delete</a>
                                <a href="mission_status.php?id=<?= $mission['mission_id'] ?>" class="btn btn-warning btn-sm">Change Status</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if (isset($_GET['action']) && $_GET['action'] === 'new'): ?>
    <!-- Add Mission Form -->
    <div class="card mb-4">
        <div class="card-header">Add New Mission</div>
        <div class="card-body">
            <form method="POST" action="">
                <div class="row">
                    <div class="col-md-6">
                        <label for="mission_name" class="form-label">Mission Name</label>
                        <input type="text" name="mission_name" id="mission_name" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label for="mission_code" class="form-label">Mission Code</label>
                        <input type="text" name="mission_code" id="mission_code" class="form-control" required>
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-md-12">
                        <label for="description" class="form-label">Description</label>
                        <textarea name="description" id="description" class="form-control" rows="3"></textarea>
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-md-4">
                        <label for="status" class="form-label">Status</label>
                        <select name="status" id="status" class="form-select" required>
                            <option value="planned">Planned</option>
                            <option value="active">Active</option>
                            <option value="completed">Completed</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="priority" class="form-label">Priority</label>
                        <select name="priority" id="priority" class="form-select" required>
                            <option value="low">Low</option>
                            <option value="medium" selected>Medium</option>
                            <option value="high">High</option>
                            <option value="critical">Critical</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="location_id" class="form-label">Location</label>
                        <select name="location_id" id="location_id" class="form-select" required>
                            <option value="">Select Location</option>
                            <?php foreach ($locations as $location): ?>
                                <option value="<?= $location['location_id'] ?>">
                                    <?= htmlspecialchars($location['location_name'] . ' (' . $location['country'] . ')') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-md-6">
                        <label for="start_date" class="form-label">Start Date</label>
                        <input type="date" name="start_date" id="start_date" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label for="end_date" class="form-label">End Date</label>
                        <input type="date" name="end_date" id="end_date" class="form-control" required>
                    </div>
                </div>
                <div class="mt-3 text-end">
                    <a href="missions.php" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">Create Mission</button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<?php if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])): ?>
    <?php $editMission = $operationsManager->getMissionDetails($_GET['id']); ?>
    <!-- Edit Mission Form -->
    <div class="card mb-4">
        <div class="card-header">Edit Mission</div>
        <div class="card-body">
            <form method="POST" action="">
                <input type="hidden" name="edit_id" value="<?= $editMission['mission_id'] ?>">
                <div class="row">
                    <div class="col-md-6">
                        <label for="edit_mission_name" class="form-label">Mission Name</label>
                        <input type="text" name="mission_name" id="edit_mission_name" class="form-control" value="<?= htmlspecialchars($editMission['mission_name']) ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label for="edit_mission_code" class="form-label">Mission Code</label>
                        <input type="text" name="mission_code" id="edit_mission_code" class="form-control" value="<?= htmlspecialchars($editMission['mission_code']) ?>" required>
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-md-12">
                        <label for="edit_description" class="form-label">Description</label>
                        <textarea name="description" id="edit_description" class="form-control" rows="3"><?= htmlspecialchars($editMission['description']) ?></textarea>
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-md-4">
                        <label for="edit_status" class="form-label">Status</label>
                        <select name="status" id="edit_status" class="form-select" required>
                            <option value="planned" <?= $editMission['status'] === 'planned' ? 'selected' : '' ?>>Planned</option>
                            <option value="active" <?= $editMission['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                            <option value="completed" <?= $editMission['status'] === 'completed' ? 'selected' : '' ?>>Completed</option>
                            <option value="cancelled" <?= $editMission['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="edit_priority" class="form-label">Priority</label>
                        <select name="priority" id="edit_priority" class="form-select" required>
                            <option value="low" <?= $editMission['priority'] === 'low' ? 'selected' : '' ?>>Low</option>
                            <option value="medium" <?= $editMission['priority'] === 'medium' ? 'selected' : '' ?>>Medium</option>
                            <option value="high" <?= $editMission['priority'] === 'high' ? 'selected' : '' ?>>High</option>
                            <option value="critical" <?= $editMission['priority'] === 'critical' ? 'selected' : '' ?>>Critical</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="edit_location_id" class="form-label">Location</label>
                        <select name="location_id" id="edit_location_id" class="form-select" required>
                            <option value="">Select Location</option>
                            <?php foreach ($locations as $location): ?>
                                <option value="<?= $location['location_id'] ?>" <?= $editMission['location_id'] == $location['location_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($location['location_name'] . ' (' . $location['country'] . ')') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-md-6">
                        <label for="edit_start_date" class="form-label">Start Date</label>
                        <input type="date" name="start_date" id="edit_start_date" class="form-control" value="<?= htmlspecialchars($editMission['start_date']) ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label for="edit_end_date" class="form-label">End Date</label>
                        <input type="date" name="end_date" id="edit_end_date" class="form-control" value="<?= htmlspecialchars($editMission['end_date']) ?>" required>
                    </div>
                </div>
                <div class="mt-3 text-end">
                    <a href="missions.php" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">Update Mission</button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<?php if (isset($_GET['action']) && $_GET['action'] === 'view' && isset($_GET['id'])): ?>
    <?php $viewMission = $operationsManager->getMissionDetails($_GET['id']); ?>
    <!-- View Mission Details -->
    <div class="card mb-4">
        <div class="card-header">Mission Details</div>
        <div class="card-body">
            <dl class="row">
                <dt class="col-sm-3">Mission Name</dt>
                <dd class="col-sm-9"><?= htmlspecialchars($viewMission['mission_name']) ?></dd>
                <dt class="col-sm-3">Mission Code</dt>
                <dd class="col-sm-9"><?= htmlspecialchars($viewMission['mission_code']) ?></dd>
                <dt class="col-sm-3">Description</dt>
                <dd class="col-sm-9"><?= htmlspecialchars($viewMission['description']) ?></dd>
                <dt class="col-sm-3">Status</dt>
                <dd class="col-sm-9"><?= htmlspecialchars(ucfirst($viewMission['status'])) ?></dd>
                <dt class="col-sm-3">Priority</dt>
                <dd class="col-sm-9"><?= htmlspecialchars(ucfirst($viewMission['priority'])) ?></dd>
                <dt class="col-sm-3">Location</dt>
                <dd class="col-sm-9"><?= htmlspecialchars($viewMission['location_name'] . ' (' . $viewMission['country'] . ')') ?></dd>
                <dt class="col-sm-3">Start Date</dt>
                <dd class="col-sm-9"><?= htmlspecialchars($viewMission['start_date']) ?></dd>
                <dt class="col-sm-3">End Date</dt>
                <dd class="col-sm-9"><?= htmlspecialchars($viewMission['end_date']) ?></dd>
            </dl>
            <div class="mt-3 text-end">
                <a href="missions.php?action=edit&id=<?= $viewMission['mission_id'] ?>" class="btn btn-primary">Edit</a>
                <a href="missions.php" class="btn btn-secondary">Back</a>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])): ?>
    <?php $deleteMission = $operationsManager->getMissionDetails($_GET['id']); ?>
    <!-- Delete Mission Confirmation -->
    <div class="card mb-4">
        <div class="card-header bg-danger text-white">Delete Mission</div>
        <div class="card-body">
            <form method="POST" action="">
                <input type="hidden" name="delete_id" value="<?= $deleteMission['mission_id'] ?>">
                <p>Are you sure you want to delete the mission <strong><?= htmlspecialchars($deleteMission['mission_name']) ?></strong>?</p>
                <div class="mt-3 text-end">
                    <a href="missions.php" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-danger">Delete Mission</button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>
        </div>
    </div>
</div>

<!-- Page specific script -->
<script>
$(document).ready(function() {
    // Initialize datatable if it exists
    if ($.fn.DataTable && $('#missionsTable').length > 0) {
        $('#missionsTable').DataTable({
            "order": [[ 2, "asc" ], [ 3, "desc" ]],
            "pageLength": 25
        });
    }
    
    // Automatically set end date based on start date (3 months later)
    $('#start_date').on('change', function() {
        if ($(this).val()) {
            var startDate = new Date($(this).val());
            var endDate = new Date(startDate);
            endDate.setMonth(endDate.getMonth() + 3);
            
            // Format the date as YYYY-MM-DD
            var month = ('0' + (endDate.getMonth() + 1)).slice(-2);
            var day = ('0' + endDate.getDate()).slice(-2);
            var formattedDate = endDate.getFullYear() + '-' + month + '-' + day;
            
            $('#end_date').val(formattedDate);
        }
    });
    
    document.getElementById('search-btn').onclick = function() {
        var form = document.getElementById('mission-search-form');
        var data = new FormData(form);
        var params = new URLSearchParams(data).toString();
        fetch('ajax_search_missions.php?' + params)
            .then(response => response.text())
            .then(html => {
                document.getElementById('mission-results').innerHTML = html;
            });
    };
});
</script>

<?php include dirname(__DIR__) . '/shared/footer.php'; ?>
