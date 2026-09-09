<?php
require_once dirname(__DIR__) . '/shared/module_auth.php';
require_once dirname(__DIR__) . '/shared/csrf.php';
require_once 'operations_manager.php';
bootModule(['module' => 'operations', 'page' => 'assignments', 'pageTitle' => 'Personnel Assignment - Operations - ARMIS', 'moduleName' => 'Operations', 'moduleIcon' => 'map-marked-alt']);
$manager = new OperationsManager($_SESSION['user_id']);

// Handle assignment form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_staff'])) {
    require_csrf();
    try {
        $missionId = $_POST['mission_id'];
        $staffId = $_POST['svcNo'];
        $roleId = $_POST['role_id'] ?? null;
        $startDate = $_POST['startDate'] ?? null;
        $endDate = $_POST['endDate'] ?? null;
        $manager->assignStaffToMission($missionId, $staffId, $roleId, $startDate, $endDate);
        $success = "Staff assigned successfully.";
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Handle removal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_staff'])) {
    require_csrf();
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

require_once dirname(__DIR__) . '/shared/header.php';
require_once dirname(__DIR__) . '/shared/sidebar.php';
?>
<div class="content-wrapper with-sidebar"><div class="container-fluid"><div class="main-content">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4"><div><h1 class="section-title mb-1"><i class="fas fa-user-plus"></i> Personnel Assignment</h1><p class="text-muted mb-0">Assign available personnel to an operational mission.</p></div><a class="btn btn-outline-primary" href="missions.php"><i class="fas fa-map-marked-alt"></i> Mission Planning</a></div>
    <?php if (!empty($success)): ?>
        <div class="alert alert-success"> <?= $success ?> </div>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"> <?= $error ?> </div>
    <?php endif; ?>
    <div class="card mb-4"><div class="card-header"><strong>New Assignment</strong></div><div class="card-body"><form method="post" class="row g-3">
        <?= csrf_field() ?>
        <div class="col-md-4">
            <label for="mission_id" class="form-label">Mission</label>
            <select name="mission_id" id="mission_id" class="form-select" required>
                <option value="">Select Mission</option>
                <?php foreach ($missions as $mission): ?>
                    <option value="<?= $mission['mission_id'] ?>"> <?= htmlspecialchars($mission['mission_name']) ?> </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <label for="svcNo" class="form-label">Staff Member</label>
            <select name="svcNo" id="svcNo" class="form-select" required>
                <option value="">Select Staff</option>
                <?php foreach ($availableStaff as $staff): ?>
                    <option value="<?= htmlspecialchars($staff['svcNo']) ?>"> <?= htmlspecialchars(trim(($staff['fName'] ?? '') . ' ' . ($staff['mName'] ?? '') . ' ' . ($staff['lName'] ?? ''))) ?> </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <label for="role_id" class="form-label">Role/Position</label>
            <input type="text" name="role_id" id="role_id" class="form-control" placeholder="Enter role or position">
        </div>
        <div class="col-md-3">
            <label for="startDate" class="form-label">Start Date</label>
            <input type="date" name="startDate" id="startDate" class="form-control">
        </div>
        <div class="col-md-3">
            <label for="endDate" class="form-label">End Date</label>
            <input type="date" name="endDate" id="endDate" class="form-control">
        </div>
        <div class="col-md-12 d-flex justify-content-end">
            <button type="submit" name="assign_staff" class="btn btn-primary">Assign Staff</button>
        </div>
    </form></div></div>
    <div class="card"><div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2"><strong>Current Assignments</strong><span class="text-muted small"><?= count($assignments) ?> assignment<?= count($assignments) === 1 ? '' : 's' ?></span></div><div class="card-body p-0"><div class="table-responsive"><table class="table table-bordered table-striped mb-0">
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
                    <td><?= htmlspecialchars($a['staff_name'] ?? $a['svcNo']) ?></td>
                    <td><?= htmlspecialchars($a['role_id']) ?></td>
                    <td><?= htmlspecialchars($a['startDate']) ?></td>
                    <td><?= htmlspecialchars($a['endDate']) ?></td>
                    <td><?= htmlspecialchars($a['status']) ?></td>
                    <td>
                        <form method="post" style="display:inline;">
                            <?= csrf_field() ?>
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
    </table></div></div></div>
</div></div></div>
<?php require_once dirname(__DIR__) . '/shared/footer.php'; ?>
