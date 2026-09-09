<?php
require_once dirname(__DIR__) . '/shared/header.php';
require_once dirname(__DIR__) . '/shared/sidebar.php';
require_once 'operations_manager.php';
$manager = new OperationsManager();
$userId = $_SESSION['user_id'] ?? 1;

// Mark as read
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_read'])) {
    $manager->markNotificationRead($_POST['notification_id']);
}

$notifications = $manager->getUserNotifications($userId);
?>
<div class="content-wrapper with-sidebar">
    <div class="container-fluid">
        <div class="main-content">
            <h2 class="mt-4 mb-4">Notifications Center</h2>
            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>Type</th>
                        <th>Message</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($notifications as $n): ?>
                    <tr>
                        <td><?= htmlspecialchars($n['type']) ?></td>
                        <td><?= htmlspecialchars($n['title'] . ($n['message'] ? ' — ' . $n['message'] : '')) ?></td>
                        <td><?= htmlspecialchars($n['created_at']) ?></td>
                        <td><?= htmlspecialchars($n['status']) ?></td>
                        <td>
                            <?php if ($n['status'] !== 'read'): ?>
                                <form method="post" style="display:inline;">
                                    <input type="hidden" name="notification_id" value="<?= $n['id'] ?>">
                                    <button type="submit" name="mark_read" class="btn btn-success btn-sm">Mark Read</button>
                                </form>
                            <?php else: ?>
                                <span class="text-muted">Read</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require_once dirname(__DIR__) . '/shared/footer.php'; ?>
