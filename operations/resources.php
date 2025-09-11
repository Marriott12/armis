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
logAccess('operations', 'resources_view', true);

$pageTitle = "Operations | Resources";
$moduleName = "Operations";
$moduleIcon = "shield-alt";
$currentPage = "resources";

$sidebarLinks = [
    ['title' => 'Dashboard', 'url' => '/Armis2/operations/index.php', 'icon' => 'tachometer-alt', 'page' => 'dashboard'],
    ['title' => 'Mission Planning', 'url' => '/Armis2/operations/missions.php', 'icon' => 'map-marked-alt', 'page' => 'missions'],
    ['title' => 'Deployments', 'url' => '/Armis2/operations/deployments.php', 'icon' => 'plane', 'page' => 'deployments'],
    ['title' => 'Resource Allocation', 'url' => '/Armis2/operations/resources.php', 'icon' => 'boxes', 'page' => 'resources'],
    ['title' => 'Status Reports', 'url' => '/Armis2/operations/reports.php', 'icon' => 'clipboard-list', 'page' => 'reports'],
    ['title' => 'Field Operations', 'url' => '/Armis2/operations/field.php', 'icon' => 'crosshairs', 'page' => 'field'],
    ['title' => 'Setup Database', 'url' => '/Armis2/operations/setup_database.php', 'icon' => 'database', 'page' => 'setup']
];

include dirname(__DIR__) . '/shared/header.php';
include dirname(__DIR__) . '/shared/sidebar.php';
require_once 'operations_manager.php';
$manager = new OperationsManager();

// Handle Add Resource
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resource_name']) && !isset($_POST['edit_id'])) {
    $resourceData = [
        'resource_name' => $_POST['resource_name'],
        'resource_type' => $_POST['resource_type'],
        'quantity' => $_POST['quantity'],
        'status' => $_POST['status'],
        'description' => $_POST['description']
    ];
    $operationsManager->addResource($resourceData);
    header('Location: resources.php');
    exit;
}

// Handle Edit Resource
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_id'])) {
    $resourceData = [
        'resource_id' => $_POST['edit_id'],
        'resource_name' => $_POST['resource_name'],
        'resource_type' => $_POST['resource_type'],
        'quantity' => $_POST['quantity'],
        'status' => $_POST['status'],
        'description' => $_POST['description']
    ];
    $operationsManager->updateResource($resourceData);
    header('Location: resources.php');
    exit;
}

// Handle Delete Resource
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $operationsManager->deleteResource($_POST['delete_id']);
    header('Location: resources.php');
    exit;
}

// Handle search
$searchTerm = $_GET['search'] ?? '';
$searchType = $_GET['type'] ?? '';
$searchStatus = $_GET['status'] ?? '';
$resources = $manager->searchResources($searchTerm, $searchType, $searchStatus);
?>

<!-- Main Content -->
<div class="content-wrapper with-sidebar">
    <div class="container-fluid">
        <div class="main-content">
            <div class="row">
                <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h1 class="section-title">
                            <i class="fas fa-boxes"></i> Resource Allocation
                        </h1>
                        <a href="?action=new" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Add Resources
                        </a>
                    </div>
                </div>
            </div>
            
            <h2 class="mt-4 mb-4">Advanced Resource Search</h2>
            <form method="get" class="row g-3 mb-4">
                <div class="col-md-4">
                    <label for="search" class="form-label">Resource Name</label>
                    <input type="text" name="search" id="search" class="form-control" value="<?= htmlspecialchars($searchTerm) ?>" placeholder="Enter resource name">
                </div>
                <div class="col-md-3">
                    <label for="type" class="form-label">Resource Type</label>
                    <input type="text" name="type" id="type" class="form-control" value="<?= htmlspecialchars($searchType) ?>" placeholder="Enter type">
                </div>
                <div class="col-md-3">
                    <label for="status" class="form-label">Status</label>
                    <input type="text" name="status" id="status" class="form-control" value="<?= htmlspecialchars($searchStatus) ?>" placeholder="Enter status">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">Search</button>
                </div>
            </form>
            <h2 class="mt-4 mb-4">All Resources</h2>
            <!-- Add Resource Button -->
            <div class="mb-3 text-end">
                <a href="resources.php?action=new" class="btn btn-success">Add Resource</a>
            </div>

            <?php if (isset($_GET['action']) && $_GET['action'] === 'new'): ?>
                <!-- Add Resource Form -->
                <div class="card mb-4">
                    <div class="card-header">Add New Resource</div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <div class="row">
                                <div class="col-md-6">
                                    <label for="resource_name" class="form-label">Resource Name</label>
                                    <input type="text" name="resource_name" id="resource_name" class="form-control" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="resource_type" class="form-label">Type</label>
                                    <input type="text" name="resource_type" id="resource_type" class="form-control" required>
                                </div>
                            </div>
                            <div class="row mt-3">
                                <div class="col-md-6">
                                    <label for="quantity" class="form-label">Quantity</label>
                                    <input type="number" name="quantity" id="quantity" class="form-control" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="status" class="form-label">Status</label>
                                    <select name="status" id="status" class="form-select" required>
                                        <option value="available">Available</option>
                                        <option value="in_use">In Use</option>
                                        <option value="maintenance">Maintenance</option>
                                        <option value="retired">Retired</option>
                                    </select>
                                </div>
                            </div>
                            <div class="row mt-3">
                                <div class="col-md-12">
                                    <label for="description" class="form-label">Description</label>
                                    <textarea name="description" id="description" class="form-control" rows="2"></textarea>
                                </div>
                            </div>
                            <div class="mt-3 text-end">
                                <a href="resources.php" class="btn btn-secondary">Cancel</a>
                                <button type="submit" class="btn btn-primary">Create Resource</button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])): ?>
                <?php $editResource = $operationsManager->getResourceDetails($_GET['id']); ?>
                <!-- Edit Resource Form -->
                <div class="card mb-4">
                    <div class="card-header">Edit Resource</div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <input type="hidden" name="edit_id" value="<?= $editResource['resource_id'] ?>">
                            <div class="row">
                                <div class="col-md-6">
                                    <label for="edit_resource_name" class="form-label">Resource Name</label>
                                    <input type="text" name="resource_name" id="edit_resource_name" class="form-control" value="<?= htmlspecialchars($editResource['resource_name']) ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="edit_resource_type" class="form-label">Type</label>
                                    <input type="text" name="resource_type" id="edit_resource_type" class="form-control" value="<?= htmlspecialchars($editResource['resource_type']) ?>" required>
                                </div>
                            </div>
                            <div class="row mt-3">
                                <div class="col-md-6">
                                    <label for="edit_quantity" class="form-label">Quantity</label>
                                    <input type="number" name="quantity" id="edit_quantity" class="form-control" value="<?= htmlspecialchars($editResource['quantity']) ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="edit_status" class="form-label">Status</label>
                                    <select name="status" id="edit_status" class="form-select" required>
                                        <option value="available" <?= $editResource['status'] === 'available' ? 'selected' : '' ?>>Available</option>
                                        <option value="in_use" <?= $editResource['status'] === 'in_use' ? 'selected' : '' ?>>In Use</option>
                                        <option value="maintenance" <?= $editResource['status'] === 'maintenance' ? 'selected' : '' ?>>Maintenance</option>
                                        <option value="retired" <?= $editResource['status'] === 'retired' ? 'selected' : '' ?>>Retired</option>
                                    </select>
                                </div>
                            </div>
                            <div class="row mt-3">
                                <div class="col-md-12">
                                    <label for="edit_description" class="form-label">Description</label>
                                    <textarea name="description" id="edit_description" class="form-control" rows="2"><?= htmlspecialchars($editResource['description']) ?></textarea>
                                </div>
                            </div>
                            <div class="mt-3 text-end">
                                <a href="resources.php" class="btn btn-secondary">Cancel</a>
                                <button type="submit" class="btn btn-primary">Update Resource</button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['action']) && $_GET['action'] === 'view' && isset($_GET['id'])): ?>
                <?php $viewResource = $operationsManager->getResourceDetails($_GET['id']); ?>
                <!-- View Resource Details -->
                <div class="card mb-4">
                    <div class="card-header">Resource Details</div>
                    <div class="card-body">
                        <dl class="row">
                            <dt class="col-sm-3">Resource Name</dt>
                            <dd class="col-sm-9"><?= htmlspecialchars($viewResource['resource_name']) ?></dd>
                            <dt class="col-sm-3">Type</dt>
                            <dd class="col-sm-9"><?= htmlspecialchars($viewResource['resource_type']) ?></dd>
                            <dt class="col-sm-3">Quantity</dt>
                            <dd class="col-sm-9"><?= htmlspecialchars($viewResource['quantity']) ?></dd>
                            <dt class="col-sm-3">Status</dt>
                            <dd class="col-sm-9"><?= htmlspecialchars(ucfirst($viewResource['status'])) ?></dd>
                            <dt class="col-sm-3">Description</dt>
                            <dd class="col-sm-9"><?= htmlspecialchars($viewResource['description']) ?></dd>
                        </dl>
                        <div class="mt-3 text-end">
                            <a href="resources.php?action=edit&id=<?= $viewResource['resource_id'] ?>" class="btn btn-primary">Edit</a>
                            <a href="resources.php" class="btn btn-secondary">Back</a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])): ?>
                <?php $deleteResource = $operationsManager->getResourceDetails($_GET['id']); ?>
                <!-- Delete Resource Confirmation -->
                <div class="card mb-4">
                    <div class="card-header bg-danger text-white">Delete Resource</div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <input type="hidden" name="delete_id" value="<?= $deleteResource['resource_id'] ?>">
                            <p>Are you sure you want to delete the resource <strong><?= htmlspecialchars($deleteResource['resource_name']) ?></strong>?</p>
                            <div class="mt-3 text-end">
                                <a href="resources.php" class="btn btn-secondary">Cancel</a>
                                <button type="submit" class="btn btn-danger">Delete Resource</button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Resource List Table -->
            <div class="card mt-4">
                <div class="card-header">Resource List</div>
                <div class="card-body">
                    <table class="table table-bordered table-hover">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Type</th>
                                <th>Quantity</th>
                                <th>Status</th>
                                <th>Description</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($resources as $resource): ?>
                                <tr>
                                    <td><?= htmlspecialchars($resource['resource_name']) ?></td>
                                    <td><?= htmlspecialchars($resource['resource_type']) ?></td>
                                    <td><?= htmlspecialchars($resource['quantity']) ?></td>
                                    <td><?= htmlspecialchars(ucfirst($resource['status'])) ?></td>
                                    <td><?= htmlspecialchars($resource['description']) ?></td>
                                    <td>
                                        <a href="resources.php?action=view&id=<?= $resource['resource_id'] ?>" class="btn btn-info btn-sm">View</a>
                                        <a href="resources.php?action=edit&id=<?= $resource['resource_id'] ?>" class="btn btn-primary btn-sm">Edit</a>
                                        <a href="resources.php?action=delete&id=<?= $resource['resource_id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this resource?');">Delete</a>
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
