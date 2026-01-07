<?php
require_once 'operations_manager.php';
$manager = new OperationsManager();
$name = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';
$priority = $_GET['priority'] ?? '';
$missions = $manager->searchMissions($name, $status, $priority);
?>
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
                    <?= date('M d, Y', strtotime($mission['startDate'])) ?> -
                    <?= date('M d, Y', strtotime($mission['endDate'])) ?>
                </small>
            </td>
            <td>
                <a href="mission_details.php?id=<?= $mission['mission_id'] ?>" class="btn btn-info btn-sm">View</a>
                <a href="mission_edit.php?id=<?= $mission['mission_id'] ?>" class="btn btn-primary btn-sm">Edit</a>
                <a href="mission_delete.php?id=<?= $mission['mission_id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Delete this mission?')">Delete</a>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
