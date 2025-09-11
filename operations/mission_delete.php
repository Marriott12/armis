<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once dirname(__DIR__) . '/shared/rbac.php';
require_once 'operations_manager.php';
require_once 'notifications.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . dirname($_SERVER['PHP_SELF']) . '/../login.php');
    exit();
}
requireModuleAccess('operations');
$pageTitle = "Delete Mission";
include dirname(__DIR__) . '/shared/header.php';
include dirname(__DIR__) . '/shared/sidebar.php';

$operationsManager = new OperationsManager($_SESSION['user_id']);
$missionId = $_GET['id'] ?? null;
$success = $error = '';

if (!$missionId) {
    $error = "No mission selected.";
} else {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            $operationsManager->deleteMission($missionId);
            sendNotification($_SESSION['user_id'], "Mission deleted successfully.", 'success');
            $success = 'Mission deleted successfully.';
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
    $mission = $operationsManager->getMissionDetails($missionId);
}
?>

<div class="container-fluid">
    <h1 class="mt-4"><i class="fas fa-trash"></i> Delete Mission</h1>
    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
        <a href="missions.php" class="btn btn-primary">Back to Missions</a>
    <?php elseif ($error): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php elseif ($mission): ?>
        <div class="alert alert-warning">
            Are you sure you want to delete mission <strong><?php echo htmlspecialchars($mission['mission_name']); ?></strong>?
        </div>
        <form method="post">
            <button type="submit" class="btn btn-danger">Delete</button>
            <a href="missions.php" class="btn btn-secondary">Cancel</a>
        </form>
    <?php endif; ?>
</div>
<?php include dirname(__DIR__) . '/shared/footer.php'; ?>
