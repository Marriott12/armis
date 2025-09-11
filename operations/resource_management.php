<?php
require_once dirname(__DIR__) . '/shared/header.php';
require_once dirname(__DIR__) . '/shared/sidebar.php';
require_once 'operations_manager.php';
$manager = new OperationsManager();

// Handle add resource
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_resource'])) {
    try {
        $manager->addResource($_POST['name'], $_POST['type'], $_POST['quantity'], $_POST['status']);
        $success = "Resource added successfully.";
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Handle update resource
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_resource'])) {
    try {
        $manager->updateResource($_POST['id'], $_POST['name'], $_POST['type'], $_POST['quantity'], $_POST['status']);
        $success = "Resource updated successfully.";
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Handle delete resource
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_resource'])) {
    try {
        $manager->deleteResource($_POST['id']);
        $success = "Resource deleted.";
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

$resources = $manager->getAllResources();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Resource Management</title>
    <link rel="stylesheet" href="/assets/bootstrap.min.css">
</head>
<body>
<div class="container mt-4">
    <div class="main-content">
        <h2 class="mt-4 mb-4">Resource Management</h2>
        <?php if (!empty($success)): ?>
            <div class="alert alert-success"> <?= $success ?> </div>
        <?php endif; ?>
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"> <?= $error ?> </div>
        <?php endif; ?>
        <form method="post" class="row g-3 mb-4">
            <div class="col-md-3">
                <label for="name" class="form-label">Resource Name</label>
                <input type="text" name="name" id="name" class="form-control" placeholder="Enter resource name" required>
            </div>
            <div class="col-md-3">
                <label for="type" class="form-label">Resource Type</label>
                <input type="text" name="type" id="type" class="form-control" placeholder="Enter resource type" required>
            </div>
            <div class="col-md-2">
                <label for="quantity" class="form-label">Quantity</label>
                <input type="number" name="quantity" id="quantity" class="form-control" placeholder="Enter quantity" required>
            </div>
            <div class="col-md-2">
                <label for="status" class="form-label">Status</label>
                <input type="text" name="status" id="status" class="form-control" placeholder="Enter status" required>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" name="add_resource" class="btn btn-primary w-100">Add Resource</button>
            </div>
        </form>
        <table class="table table-bordered table-striped">
            <thead>
                <tr>
                    <th>Resource Name</th>
                    <th>Resource Type</th>
                    <th>Quantity</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($resources as $r): ?>
                <tr>
                    <form method="post" class="row g-2">
                        <td><input type="text" name="name" value="<?= htmlspecialchars($r['name']) ?>" class="form-control" required></td>
                        <td><input type="text" name="type" value="<?= htmlspecialchars($r['type']) ?>" class="form-control" required></td>
                        <td><input type="number" name="quantity" value="<?= htmlspecialchars($r['quantity']) ?>" class="form-control" required></td>
                        <td><input type="text" name="status" value="<?= htmlspecialchars($r['status']) ?>" class="form-control" required></td>
                        <td>
                            <input type="hidden" name="id" value="<?= $r['id'] ?>">
                            <button type="submit" name="update_resource" class="btn btn-success btn-sm">Update</button>
                            <button type="submit" name="delete_resource" class="btn btn-danger btn-sm" onclick="return confirm('Delete this resource?')">Delete</button>
                        </td>
                    </form>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require_once dirname(__DIR__) . '/shared/footer.php'; ?>
</body>
</html>
