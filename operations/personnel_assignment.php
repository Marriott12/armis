<?php
require_once dirname(__DIR__) . '/shared/header.php';
require_once dirname(__DIR__) . '/shared/sidebar.php';
require_once 'operations_manager.php';
$manager = new OperationsManager();

// Handle assignment form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_staff'])) {
    try {
        $missionId = $_POST['mission_id'];
        $staffId = $_POST['staff_id'];
        $roleId = $_POST['role_id'] ?? null;
        $startDate = $_POST['start_date'] ?? null;
        $endDate = $_POST['end_date'] ?? null;
        $manager->assignStaffToMission($missionId, $staffId, $roleId, $startDate, $endDate);
        $success = "Staff assigned successfully.";
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Handle removal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_staff'])) {
    try {
        $personnelId = $_POST['personnel_id'];
        $manager->removeStaffFromMission($personnelId);
        $success = "Staff removed from mission.";
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get missions and available staff
$missions = $manager->getAllMissions();
$availableStaff = $manager->getAvailableStaff();
// Get current assignments
$assignments = $manager->getCurrentAssignments();

?>
<div class="main-content">
    <h2 class="mt-4 mb-4">Personnel Assignment</h2>
    <?php if (!empty($success)): ?>
        <div class="alert alert-success"> <?= $success ?> </div>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"> <?= $error ?> </div>
    <?php endif; ?>
    <form method="post" class="row g-3">
        <div class="col-md-4">
            <label for="mission_id" class="form-label">Mission</label>
            <select name="mission_id" id="mission_id" class="form-select" required>
                <option value="">Select Mission</option>
                <?php foreach ($missions as $mission): ?>
                    <option value="<?= $mission['id'] ?>"> <?= htmlspecialchars($mission['name']) ?> </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <label for="staff_id" class="form-label">Staff Member</label>
            <select name="staff_id" id="staff_id" class="form-select" required>
                <option value="">Select Staff</option>
                <?php foreach ($availableStaff as $staff): ?>
                    <option value="<?= $staff['id'] ?>"> <?= htmlspecialchars($staff['full_name'] ?? $staff['name']) ?> </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <label for="role_id" class="form-label">Role/Position</label>
            <input type="text" name="role_id" id="role_id" class="form-control" placeholder="Enter role or position">
        </div>
        <div class="col-md-3">
            <label for="start_date" class="form-label">Start Date</label>
            <input type="date" name="start_date" id="start_date" class="form-control">
        </div>
        <div class="col-md-3">
            <label for="end_date" class="form-label">End Date</label>
            <input type="date" name="end_date" id="end_date" class="form-control">
        </div>
        <div class="col-md-12">
            <button type="submit" name="assign_staff" class="btn btn-primary">Assign Staff</button>
        </div>
    </form>
    <hr>
    <h3>Current Assignments</h3>
    <table class="table table-bordered table-striped">
        <thead>
            <tr>
                <th>Mission</th>
                <th>Staff Member</th>
                <th>Role/Position</th>
                <th>Start Date</th>
                <th>End Date</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
        <?php if (!empty($assignments)): ?>
            <?php foreach ($assignments as $a): ?>
                <tr>
                    <td><?= htmlspecialchars($a['mission_name'] ?? $a['mission_id']) ?></td>
                    <td><?= htmlspecialchars($a['staff_name'] ?? $a['staff_id']) ?></td>
                    <td><?= htmlspecialchars($a['role_id']) ?></td>
                    <td><?= htmlspecialchars($a['start_date']) ?></td>
                    <td><?= htmlspecialchars($a['end_date']) ?></td>
                    <td><?= htmlspecialchars($a['status']) ?></td>
                    <td>
                        <form method="post" style="display:inline;">
                            <input type="hidden" name="personnel_id" value="<?= $a['personnel_id'] ?>">
                            <button type="submit" name="remove_staff" class="btn btn-danger btn-sm" onclick="return confirm('Remove this staff from mission?')">Remove</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr><td colspan="7" class="text-center">No assignments found.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
<?php require_once dirname(__DIR__) . '/shared/footer.php'; ?>
