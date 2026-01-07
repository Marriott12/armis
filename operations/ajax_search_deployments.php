<?php
require_once 'operations_manager.php';
$manager = new OperationsManager();
$name = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';
$location = $_GET['location'] ?? '';
$deployments = $manager->searchDeployments($name, $status, $location);
?>
<table class="table table-bordered table-striped">
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
