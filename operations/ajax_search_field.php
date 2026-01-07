<?php
require_once 'operations_manager.php';
$manager = new OperationsManager();
$name = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';
$location = $_GET['location'] ?? '';
$fields = $manager->searchFieldOperations($name, $status, $location);
?>
<table class="table table-bordered table-striped">
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
    <?php foreach ($fields as $f): ?>
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
