<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include RBAC system
require_once dirname(__DIR__) . '/shared/rbac.php';
require_once 'dashboard_widgets.php';
require_once 'notifications.php';
require_once 'audit_log.php';
require_once 'search_filter.php';
require_once 'mission_history.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . dirname($_SERVER['PHP_SELF']) . '/../login.php');
    exit();
}

// Check if user has access to operations module
requireModuleAccess('operations');

$pageTitle = "Operations | Deployments";
$moduleName = "Operations";
$moduleIcon = "shield-alt";
$currentPage = "deployments";

require_once __DIR__ . '/includes/sidebar_nav.php';

include dirname(__DIR__) . '/shared/header.php';
include dirname(__DIR__) . '/shared/sidebar.php';

// Get data
$dashboardStats = getDashboardStats();
$notifications = getNotifications($_SESSION['user_id']);
$auditTrail = getAuditTrail(10);
$searchResults = filterDeployments($_GET['status'] ?? null, $_GET['location'] ?? null);
?>

<!-- Main Content -->
<div class="content-wrapper with-sidebar">
    <div class="container-fluid">
        <div class="main-content">
            <div class="row">
                <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h1 class="section-title">
                            <i class="fas fa-plane"></i> Deployments
                        </h1>
                        <a href="deployments.php?action=new" class="btn btn-primary">
                            <i class="fas fa-plus"></i> New Deployment
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Dashboard Widgets -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card text-white bg-success mb-3">
                        <div class="card-body">
                            <h5 class="card-title">Active Deployments</h5>
                            <p class="card-text"><?php echo $dashboardStats['active_deployments']; ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-white bg-info mb-3">
                        <div class="card-body">
                            <h5 class="card-title">Available Resources</h5>
                            <p class="card-text"><?php echo $dashboardStats['available_resources']; ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-white bg-warning mb-3">
                        <div class="card-body">
                            <h5 class="card-title">Priority Alerts</h5>
                            <p class="card-text"><?php echo $dashboardStats['priority_alerts']; ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-white bg-primary mb-3">
                        <div class="card-body">
                            <h5 class="card-title">Field Units</h5>
                            <p class="card-text"><?php echo $dashboardStats['field_units']; ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Notifications -->
            <div class="row mb-4">
                <div class="col-md-12">
                    <h4><i class="fas fa-bell"></i> Notifications</h4>
                    <?php if (empty($notifications)): ?>
                        <div class="alert alert-info">No notifications.</div>
                    <?php else: ?>
                        <ul class="list-group">
                            <?php foreach ($notifications as $note): ?>
                                <li class="list-group-item<?php echo $note['is_read'] ? '' : ' list-group-item-warning'; ?>">
                                    <?php echo htmlspecialchars($note['message']); ?>
                                    <span class="badge bg-secondary"><?php echo $note['type']; ?></span>
                                    <small class="text-muted float-end"><?php echo $note['createdAt']; ?></small>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Audit Trail -->
            <div class="row mb-4">
                <div class="col-md-12">
                    <h4><i class="fas fa-history"></i> Audit Trail</h4>
                    <table class="table table-sm table-bordered">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Action</th>
                                <th>Details</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($auditTrail as $log): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($log['username'] ?? 'Unknown'); ?></td>
                                    <td><?php echo htmlspecialchars($log['action']); ?></td>
                                    <td><?php echo htmlspecialchars($log['details']); ?></td>
                                    <td><?php echo htmlspecialchars($log['createdAt']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Search/Filter Deployments -->
            <div class="row mb-4">
                <div class="col-md-12">
                    <form class="row g-3 mb-3" method="get">
                        <div class="col-md-4">
                            <select name="status" class="form-select">
                                <option value="">All Statuses</option>
                                <option value="active">Active</option>
                                <option value="planned">Planned</option>
                                <option value="completed">Completed</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <input type="text" name="location" class="form-control" placeholder="Location ID">
                        </div>
                        <div class="col-md-4">
                            <button type="submit" class="btn btn-primary">Filter</button>
                        </div>
                    </form>
                    <h4><i class="fas fa-search"></i> Deployments</h4>
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Location</th>
                                <th>Status</th>
                                <th>Start Date</th>
                                <th>End Date</th>
                                <th>Personnel</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($searchResults as $dep): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($dep['deployment_name']); ?></td>
                                    <td><?php echo htmlspecialchars($dep['location_id']); ?></td>
                                    <td><?php echo htmlspecialchars($dep['status']); ?></td>
                                    <td><?php echo htmlspecialchars($dep['startDate']); ?></td>
                                    <td><?php echo htmlspecialchars($dep['endDate']); ?></td>
                                    <td><?php echo htmlspecialchars($dep['personnel_count'] ?? ''); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Mission History (for related missions) -->
            <div class="row mb-4">
                <div class="col-md-12">
                    <h4><i class="fas fa-list"></i> Mission History</h4>
                    <?php
                    // Example: show history for a mission if selected
                    if (isset($_GET['mission_id'])) {
                        $history = getMissionHistory($_GET['mission_id']);
                        if ($history) {
                            echo '<table class="table table-bordered"><thead><tr><th>User</th><th>Type</th><th>Details</th><th>Date</th></tr></thead><tbody>';
                            foreach ($history as $h) {
                                echo '<tr>';
                                echo '<td>' . htmlspecialchars($h['username'] ?? 'Unknown') . '</td>';
                                echo '<td>' . htmlspecialchars($h['change_type']) . '</td>';
                                echo '<td>' . htmlspecialchars($h['change_details']) . '</td>';
                                echo '<td>' . htmlspecialchars($h['changed_at']) . '</td>';
                                echo '</tr>';
                            }
                            echo '</tbody></table>';
                        } else {
                            echo '<div class="alert alert-info">No history for this mission.</div>';
                        }
                    } else {
                        echo '<div class="alert alert-secondary">Select a mission to view history.</div>';
                    }
                    ?>
                </div>
            </div>

            <?php
            require_once '../config.php';
            require_once '../operations_manager.php';

            // Handle Add Deployment
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['deployment_name']) && !isset($_POST['edit_id'])) {
                $deploymentData = [
                    'deployment_name' => $_POST['deployment_name'],
                    'deployment_type' => $_POST['deployment_type'],
                    'location' => $_POST['location'],
                    'status' => $_POST['status'],
                    'startDate' => $_POST['startDate'],
                    'endDate' => $_POST['endDate'],
                    'description' => $_POST['description']
                ];
                $operationsManager->addDeployment($deploymentData);
                header('Location: deployments.php');
                exit;
            }

            // Handle Edit Deployment
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_id'])) {
                $deploymentData = [
                    'deployment_id' => $_POST['edit_id'],
                    'deployment_name' => $_POST['deployment_name'],
                    'deployment_type' => $_POST['deployment_type'],
                    'location' => $_POST['location'],
                    'status' => $_POST['status'],
                    'startDate' => $_POST['startDate'],
                    'endDate' => $_POST['endDate'],
                    'description' => $_POST['description']
                ];
                $operationsManager->updateDeployment($deploymentData);
                header('Location: deployments.php');
                exit;
            }

            // Handle Delete Deployment
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
                $operationsManager->deleteDeployment($_POST['delete_id']);
                header('Location: deployments.php');
                exit;
            }

            // Assign Personnel to Deployment
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_personnel']) && isset($_POST['deployment_id'])) {
                $deploymentId = $_POST['deployment_id'];
                $personnelIds = $_POST['personnel_ids']; // array of selected personnel IDs
                $operationsManager->assignPersonnelToDeployment($deploymentId, $personnelIds);
                header('Location: deployments.php?action=view&id=' . $deploymentId);
                exit;
            }

            // Assign Personnel to Mission
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_personnel']) && isset($_POST['mission_id'])) {
                $missionId = $_POST['mission_id'];
                $personnelIds = $_POST['personnel_ids']; // array of selected personnel IDs
                $operationsManager->assignPersonnelToMission($missionId, $personnelIds);
                header('Location: missions.php?action=view&id=' . $missionId);
                exit;
            }

            // Fetch all deployments
            $deployments = $operationsManager->getAllDeployments();
            ?>

            <!-- Add Deployment Button -->
            <div class="mb-3 text-end">
                <a href="deployments.php?action=new" class="btn btn-success">Add Deployment</a>
            </div>

            <?php if (isset($_GET['action']) && $_GET['action'] === 'new'): ?>
                <!-- Add Deployment Form -->
                <div class="card mb-4">
                    <div class="card-header">Add New Deployment</div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <div class="row">
                                <div class="col-md-6">
                                    <label for="deployment_name" class="form-label">Deployment Name</label>
                                    <input type="text" name="deployment_name" id="deployment_name" class="form-control" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="deployment_type" class="form-label">Type</label>
                                    <input type="text" name="deployment_type" id="deployment_type" class="form-control" required>
                                </div>
                            </div>
                            <div class="row mt-3">
                                <div class="col-md-6">
                                    <label for="location" class="form-label">Location</label>
                                    <input type="text" name="location" id="location" class="form-control" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="status" class="form-label">Status</label>
                                    <select name="status" id="status" class="form-select" required>
                                        <option value="planned">Planned</option>
                                        <option value="active">Active</option>
                                        <option value="completed">Completed</option>
                                        <option value="cancelled">Cancelled</option>
                                    </select>
                                </div>
                            </div>
                            <div class="row mt-3">
                                <div class="col-md-6">
                                    <label for="startDate" class="form-label">Start Date</label>
                                    <input type="date" name="startDate" id="startDate" class="form-control" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="endDate" class="form-label">End Date</label>
                                    <input type="date" name="endDate" id="endDate" class="form-control" required>
                                </div>
                            </div>
                            <div class="row mt-3">
                                <div class="col-md-12">
                                    <label for="description" class="form-label">Description</label>
                                    <textarea name="description" id="description" class="form-control" rows="2"></textarea>
                                </div>
                            </div>
                            <div class="mt-3 text-end">
                                <a href="deployments.php" class="btn btn-secondary">Cancel</a>
                                <button type="submit" class="btn btn-primary">Create Deployment</button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])): ?>
                <?php $editDeployment = $operationsManager->getDeploymentDetails($_GET['id']); ?>
                <!-- Edit Deployment Form -->
                <div class="card mb-4">
                    <div class="card-header">Edit Deployment</div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <input type="hidden" name="edit_id" value="<?= $editDeployment['deployment_id'] ?>">
                            <div class="row">
                                <div class="col-md-6">
                                    <label for="edit_deployment_name" class="form-label">Deployment Name</label>
                                    <input type="text" name="deployment_name" id="edit_deployment_name" class="form-control" value="<?= htmlspecialchars($editDeployment['deployment_name']) ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="edit_deployment_type" class="form-label">Type</label>
                                    <input type="text" name="deployment_type" id="edit_deployment_type" class="form-control" value="<?= htmlspecialchars($editDeployment['deployment_type']) ?>" required>
                                </div>
                            </div>
                            <div class="row mt-3">
                                <div class="col-md-6">
                                    <label for="edit_location" class="form-label">Location</label>
                                    <input type="text" name="location" id="edit_location" class="form-control" value="<?= htmlspecialchars($editDeployment['location']) ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="edit_status" class="form-label">Status</label>
                                    <select name="status" id="edit_status" class="form-select" required>
                                        <option value="planned" <?= $editDeployment['status'] === 'planned' ? 'selected' : '' ?>>Planned</option>
                                        <option value="active" <?= $editDeployment['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                                        <option value="completed" <?= $editDeployment['status'] === 'completed' ? 'selected' : '' ?>>Completed</option>
                                        <option value="cancelled" <?= $editDeployment['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                    </select>
                                </div>
                            </div>
                            <div class="row mt-3">
                                <div class="col-md-6">
                                    <label for="edit_start_date" class="form-label">Start Date</label>
                                    <input type="date" name="startDate" id="edit_start_date" class="form-control" value="<?= htmlspecialchars($editDeployment['startDate']) ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="edit_end_date" class="form-label">End Date</label>
                                    <input type="date" name="endDate" id="edit_end_date" class="form-control" value="<?= htmlspecialchars($editDeployment['endDate']) ?>" required>
                                </div>
                            </div>
                            <div class="row mt-3">
                                <div class="col-md-12">
                                    <label for="edit_description" class="form-label">Description</label>
                                    <textarea name="description" id="edit_description" class="form-control" rows="2"><?= htmlspecialchars($editDeployment['description']) ?></textarea>
                                </div>
                            </div>
                            <div class="mt-3 text-end">
                                <a href="deployments.php" class="btn btn-secondary">Cancel</a>
                                <button type="submit" class="btn btn-primary">Update Deployment</button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['action']) && $_GET['action'] === 'view' && isset($_GET['id'])): ?>
                <?php $viewDeployment = $operationsManager->getDeploymentDetails($_GET['id']); ?>
                <!-- View Deployment Details -->
                <div class="card mb-4">
                    <div class="card-header">Deployment Details</div>
                    <div class="card-body">
                        <dl class="row">
                            <dt class="col-sm-3">Deployment Name</dt>
                            <dd class="col-sm-9"><?= htmlspecialchars($viewDeployment['deployment_name']) ?></dd>
                            <dt class="col-sm-3">Type</dt>
                            <dd class="col-sm-9"><?= htmlspecialchars($viewDeployment['deployment_type']) ?></dd>
                            <dt class="col-sm-3">Location</dt>
                            <dd class="col-sm-9"><?= htmlspecialchars($viewDeployment['location']) ?></dd>
                            <dt class="col-sm-3">Status</dt>
                            <dd class="col-sm-9"><?= htmlspecialchars(ucfirst($viewDeployment['status'])) ?></dd>
                            <dt class="col-sm-3">Start Date</dt>
                            <dd class="col-sm-9"><?= htmlspecialchars($viewDeployment['startDate']) ?></dd>
                            <dt class="col-sm-3">End Date</dt>
                            <dd class="col-sm-9"><?= htmlspecialchars($viewDeployment['endDate']) ?></dd>
                            <dt class="col-sm-3">Description</dt>
                            <dd class="col-sm-9"><?= htmlspecialchars($viewDeployment['description']) ?></dd>
                        </dl>
                        <div class="mt-3 text-end">
                            <a href="deployments.php?action=edit&id=<?= $viewDeployment['deployment_id'] ?>" class="btn btn-primary">Edit</a>
                            <a href="deployments.php" class="btn btn-secondary">Back</a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])): ?>
                <?php $deleteDeployment = $operationsManager->getDeploymentDetails($_GET['id']); ?>
                <!-- Delete Deployment Confirmation -->
                <div class="card mb-4">
                    <div class="card-header bg-danger text-white">Delete Deployment</div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <input type="hidden" name="delete_id" value="<?= $deleteDeployment['deployment_id'] ?>">
                            <p>Are you sure you want to delete the deployment <strong><?= htmlspecialchars($deleteDeployment['deployment_name']) ?></strong>?</p>
                            <div class="mt-3 text-end">
                                <a href="deployments.php" class="btn btn-secondary">Cancel</a>
                                <button type="submit" class="btn btn-danger">Delete Deployment</button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['action']) && $_GET['action'] === 'assign' && isset($_GET['id'])): ?>
    <?php
        $entityType = isset($_GET['type']) ? $_GET['type'] : 'deployment';
        $entityId = $_GET['id'];
        $allPersonnel = $operationsManager->getAllPersonnel();
    ?>
    <!-- Assign Personnel Form -->
    <div class="card mb-4">
        <div class="card-header">Assign Personnel to <?= ucfirst($entityType) ?></div>
        <div class="card-body">
            <form method="POST" action="">
                <input type="hidden" name="<?= $entityType ?>_id" value="<?= $entityId ?>">
                <div class="mb-3">
                    <label for="personnel_ids" class="form-label">Select Personnel</label>
                    <select name="personnel_ids[]" id="personnel_ids" class="form-select" multiple required>
                        <?php foreach ($allPersonnel as $person): ?>
                            <option value="<?= $person['personnel_id'] ?>">
                                <?= htmlspecialchars($person['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" name="assign_personnel" class="btn btn-primary">Assign</button>
                <a href="<?= $entityType === 'deployment' ? 'deployments.php?action=view&id=' . $entityId : 'missions.php?action=view&id=' . $entityId ?>" class="btn btn-secondary">Cancel</a>
            </form>
        </div>
    </div>
<?php endif; ?>

            <!-- Deployment List Table -->
            <div class="card mt-4">
                <div class="card-header">Deployment List</div>
                <div class="card-body">
                    <table class="table table-bordered table-hover">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Type</th>
                                <th>Location</th>
                                <th>Status</th>
                                <th>Start Date</th>
                                <th>End Date</th>
                                <th>Description</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($deployments as $deployment): ?>
                                <tr>
                                    <td><?= htmlspecialchars($deployment['deployment_name']) ?></td>
                                    <td><?= htmlspecialchars($deployment['deployment_type']) ?></td>
                                    <td><?= htmlspecialchars($deployment['location']) ?></td>
                                    <td><?= htmlspecialchars(ucfirst($deployment['status'])) ?></td>
                                    <td><?= htmlspecialchars($deployment['startDate']) ?></td>
                                    <td><?= htmlspecialchars($deployment['endDate']) ?></td>
                                    <td><?= htmlspecialchars($deployment['description']) ?></td>
                                    <td>
                                        <a href="deployments.php?action=view&id=<?= $deployment['deployment_id'] ?>" class="btn btn-info btn-sm">View</a>
                                        <a href="deployments.php?action=edit&id=<?= $deployment['deployment_id'] ?>" class="btn btn-primary btn-sm">Edit</a>
                                        <a href="deployments.php?action=delete&id=<?= $deployment['deployment_id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this deployment?');">Delete</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include dirname(__DIR__) . '/shared/footer.php'; ?>
