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
logAccess('operations', 'field_operations_view', true);

$pageTitle = "Operations | Field Operations";
$moduleName = "Operations";
$moduleIcon = "shield-alt";
$currentPage = "field";

require_once __DIR__ . '/includes/sidebar_nav.php';

require_once dirname(__DIR__) . '/shared/header.php';
require_once dirname(__DIR__) . '/shared/sidebar.php';
require_once 'operations_manager.php';
$manager = new OperationsManager();

// Handle Add Field
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['field_name']) && !isset($_POST['edit_id'])) {
    $fieldData = [
        'field_name' => $_POST['field_name'],
        'field_type' => $_POST['field_type'],
        'location' => $_POST['location'],
        'status' => $_POST['status'],
        'description' => $_POST['description']
    ];
    $manager->addField($fieldData);
    header('Location: field.php');
    exit;
}

// Handle Edit Field
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_id'])) {
    $fieldData = [
        'field_id' => $_POST['edit_id'],
        'field_name' => $_POST['field_name'],
        'field_type' => $_POST['field_type'],
        'location' => $_POST['location'],
        'status' => $_POST['status'],
        'description' => $_POST['description']
    ];
    $manager->updateField($fieldData);
    header('Location: field.php');
    exit;
}

// Handle Delete Field
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $manager->deleteField($_POST['delete_id']);
    header('Location: field.php');
    exit;
}

// Fetch all fields
$fields = $manager->getAllFieldOperations();
?>

<!-- Main Content -->
<div class="content-wrapper with-sidebar">
    <div class="container-fluid">
        <div class="main-content">
            <div class="row">
                <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h1 class="section-title">
                            <i class="fas fa-crosshairs"></i> Field Operations
                        </h1>
                        <a href="?action=new" class="btn btn-primary">
                            <i class="fas fa-plus"></i> New Operation
                        </a>
                    </div>
                </div>
                <?php if (!empty($success)): ?>
                    <div class="alert alert-success"> <?= $success ?> </div>
                <?php endif; ?>
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger"> <?= $error ?> </div>
                <?php endif; ?>
                <form method="post" class="row g-3 mb-4">
                    <div class="col-md-3">
                        <label for="name" class="form-label">Operation Name</label>
                        <input type="text" name="name" id="name" class="form-control" placeholder="Enter operation name" required>
                    </div>
                    <div class="col-md-2">
                        <label for="status" class="form-label">Status</label>
                        <input type="text" name="status" id="status" class="form-control" placeholder="Enter status" required>
                    </div>
                    <div class="col-md-3">
                        <label for="location" class="form-label">Location</label>
                        <input type="text" name="location" id="location" class="form-control" placeholder="Enter location" required>
                    </div>
                    <div class="col-md-2">
                        <label for="startDate" class="form-label">Start Date</label>
                        <input type="date" name="startDate" id="startDate" class="form-control" required>
                    </div>
                    <div class="col-md-2">
                        <label for="endDate" class="form-label">End Date</label>
                        <input type="date" name="endDate" id="endDate" class="form-control" required>
                    </div>
                    <div class="col-md-12 d-flex align-items-end">
                        <button type="submit" name="create_field" class="btn btn-primary">Create Operation</button>
                    </div>
                </form>
                <!-- Add Field Button -->
                <div class="mb-3 text-end">
                    <a href="field.php?action=new" class="btn btn-success">Add Field</a>
                </div>

                <?php if (isset($_GET['action']) && $_GET['action'] === 'new'): ?>
                    <!-- Add Field Form -->
                    <div class="card mb-4">
                        <div class="card-header">Add New Field</div>
                        <div class="card-body">
                            <form method="POST" action="">
                                <div class="row">
                                    <div class="col-md-6">
                                        <label for="field_name" class="form-label">Field Name</label>
                                        <input type="text" name="field_name" id="field_name" class="form-control" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="field_type" class="form-label">Type</label>
                                        <input type="text" name="field_type" id="field_type" class="form-control" required>
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
                                            <option value="active">Active</option>
                                            <option value="inactive">Inactive</option>
                                            <option value="maintenance">Maintenance</option>
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
                                    <a href="field.php" class="btn btn-secondary">Cancel</a>
                                    <button type="submit" class="btn btn-primary">Create Field</button>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])): ?>
                    <?php $editField = $manager->getFieldDetails($_GET['id']); ?>
                    <!-- Edit Field Form -->
                    <div class="card mb-4">
                        <div class="card-header">Edit Field</div>
                        <div class="card-body">
                            <form method="POST" action="">
                                <input type="hidden" name="edit_id" value="<?= $editField['field_id'] ?>">
                                <div class="row">
                                    <div class="col-md-6">
                                        <label for="edit_field_name" class="form-label">Field Name</label>
                                        <input type="text" name="field_name" id="edit_field_name" class="form-control" value="<?= htmlspecialchars($editField['field_name']) ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="edit_field_type" class="form-label">Type</label>
                                        <input type="text" name="field_type" id="edit_field_type" class="form-control" value="<?= htmlspecialchars($editField['field_type']) ?>" required>
                                    </div>
                                </div>
                                <div class="row mt-3">
                                    <div class="col-md-6">
                                        <label for="edit_location" class="form-label">Location</label>
                                        <input type="text" name="location" id="edit_location" class="form-control" value="<?= htmlspecialchars($editField['location']) ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="edit_status" class="form-label">Status</label>
                                        <select name="status" id="edit_status" class="form-select" required>
                                            <option value="active" <?= $editField['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                                            <option value="inactive" <?= $editField['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                                            <option value="maintenance" <?= $editField['status'] === 'maintenance' ? 'selected' : '' ?>>Maintenance</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="row mt-3">
                                    <div class="col-md-12">
                                        <label for="edit_description" class="form-label">Description</label>
                                        <textarea name="description" id="edit_description" class="form-control" rows="2"><?= htmlspecialchars($editField['description']) ?></textarea>
                                    </div>
                                </div>
                                <div class="mt-3 text-end">
                                    <a href="field.php" class="btn btn-secondary">Cancel</a>
                                    <button type="submit" class="btn btn-primary">Update Field</button>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (isset($_GET['action']) && $_GET['action'] === 'view' && isset($_GET['id'])): ?>
                    <?php $viewField = $manager->getFieldDetails($_GET['id']); ?>
                    <!-- View Field Details -->
                    <div class="card mb-4">
                        <div class="card-header">Field Details</div>
                        <div class="card-body">
                            <dl class="row">
                                <dt class="col-sm-3">Field Name</dt>
                                <dd class="col-sm-9"><?= htmlspecialchars($viewField['field_name']) ?></dd>
                                <dt class="col-sm-3">Type</dt>
                                <dd class="col-sm-9"><?= htmlspecialchars($viewField['field_type']) ?></dd>
                                <dt class="col-sm-3">Location</dt>
                                <dd class="col-sm-9"><?= htmlspecialchars($viewField['location']) ?></dd>
                                <dt class="col-sm-3">Status</dt>
                                <dd class="col-sm-9"><?= htmlspecialchars(ucfirst($viewField['status'])) ?></dd>
                                <dt class="col-sm-3">Description</dt>
                                <dd class="col-sm-9"><?= htmlspecialchars($viewField['description']) ?></dd>
                            </dl>
                            <div class="mt-3 text-end">
                                <a href="field.php?action=edit&id=<?= $viewField['field_id'] ?>" class="btn btn-primary">Edit</a>
                                <a href="field.php" class="btn btn-secondary">Back</a>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])): ?>
                    <?php $deleteField = $manager->getFieldDetails($_GET['id']); ?>
                    <!-- Delete Field Confirmation -->
                    <div class="card mb-4">
                        <div class="card-header bg-danger text-white">Delete Field</div>
                        <div class="card-body">
                            <form method="POST" action="">
                                <input type="hidden" name="delete_id" value="<?= $deleteField['field_id'] ?>">
                                <p>Are you sure you want to delete the field <strong><?= htmlspecialchars($deleteField['field_name']) ?></strong>?</p>
                                <div class="mt-3 text-end">
                                    <a href="field.php" class="btn btn-secondary">Cancel</a>
                                    <button type="submit" class="btn btn-danger">Delete Field</button>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Field List Table -->
                <div class="card mt-4">
                    <div class="card-header">Field List</div>
                    <div class="card-body">
                        <table class="table table-bordered table-hover">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Type</th>
                                    <th>Location</th>
                                    <th>Status</th>
                                    <th>Description</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($fields as $field): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($field['field_name']) ?></td>
                                        <td><?= htmlspecialchars($field['field_type']) ?></td>
                                        <td><?= htmlspecialchars($field['location']) ?></td>
                                        <td><?= htmlspecialchars(ucfirst($field['status'])) ?></td>
                                        <td><?= htmlspecialchars($field['description']) ?></td>
                                        <td>
                                            <a href="field.php?action=view&id=<?= $field['field_id'] ?>" class="btn btn-info btn-sm">View</a>
                                            <a href="field.php?action=edit&id=<?= $field['field_id'] ?>" class="btn btn-primary btn-sm">Edit</a>
                                            <a href="field.php?action=delete&id=<?= $field['field_id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this field?');">Delete</a>
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
</div>

<?php include dirname(__DIR__) . '/shared/footer.php'; ?>
