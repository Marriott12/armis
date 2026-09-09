
<?php
require_once dirname(__DIR__) . '/shared/module_auth.php';
require_once dirname(__DIR__) . '/shared/csrf.php';
require_once 'training_manager.php';


$pageTitle = "Assignments";
$moduleName = "Training";
$moduleIcon = "graduation-cap";
$currentPage = "assignments";

bootModule(['module' => 'training', 'page' => 'assignments', 'pageTitle' => 'Training Assignments - ARMIS', 'moduleName' => 'Training', 'moduleIcon' => 'graduation-cap']);
$manager = new TrainingManager();

// Handle add/edit/delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    if (isset($_POST['add'])) {
        $manager->addAssignment([
            'personnel_id' => $_POST['personnel_id'],
            'course_id' => $_POST['course_id'],
            'session_id' => $_POST['session_id'],
            'status' => $_POST['status']
        ]);
        header('Location: assignments.php'); exit;
    }
    if (isset($_POST['edit_id'])) {
        $manager->updateAssignment($_POST['edit_id'], [
            'personnel_id' => $_POST['personnel_id'],
            'course_id' => $_POST['course_id'],
            'session_id' => $_POST['session_id'],
            'status' => $_POST['status']
        ]);
        header('Location: assignments.php'); exit;
    }
    if (isset($_POST['delete_id'])) {
        $manager->deleteAssignment($_POST['delete_id']);
        header('Location: assignments.php'); exit;
    }
}
$assignments = $manager->getAllAssignments();
$courses = $manager->getAllCourses();
$sessions = $manager->getAllSessions();
$personnel = $manager->getDb()->query('SELECT svcNo, fName, mName, lName FROM staff ORDER BY lName, fName')->fetchAll(PDO::FETCH_ASSOC);

require_once dirname(__DIR__) . '/shared/header.php';
require_once dirname(__DIR__) . '/shared/sidebar.php';
?>
<div class="content-wrapper with-sidebar">
    <div class="container-fluid"><div class="main-content">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4"><div><h1 class="section-title mb-1"><i class="fas fa-user-check"></i> Training Assignments</h1><p class="text-muted mb-0">Assign personnel to scheduled training and monitor their status.</p></div><a href="sessions.php" class="btn btn-outline-primary"><i class="fas fa-calendar-alt"></i> Training Sessions</a></div>
        <?php if (!empty($error)): ?><div class="alert alert-danger" role="alert"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <div class="card mb-4"><div class="card-header"><strong>New Assignment</strong></div><div class="card-body"><form method="post" class="row g-3">
            <?= csrf_field() ?>
            <div class="col-md-4"><label class="form-label" for="assignment-personnel">Personnel</label><select class="form-select" name="personnel_id" id="assignment-personnel" required><option value="">Select personnel</option><?php foreach ($personnel as $staff): ?><option value="<?= htmlspecialchars($staff['svcNo']) ?>"><?= htmlspecialchars(trim(($staff['fName'] ?? '') . ' ' . ($staff['mName'] ?? '') . ' ' . ($staff['lName'] ?? '') . ' (' . $staff['svcNo'] . ')')) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-3"><label class="form-label" for="assignment-course">Course</label><select class="form-select" name="course_id" id="assignment-course" required><option value="">Select course</option><?php foreach ($courses as $course): ?><option value="<?= $course['id'] ?>"><?= htmlspecialchars($course['name']) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-3"><label class="form-label" for="assignment-session">Session</label><select class="form-select" name="session_id" id="assignment-session"><option value="">No session selected</option><?php foreach ($sessions as $session): ?><option value="<?= $session['id'] ?>"><?= htmlspecialchars($session['title']) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-2"><label class="form-label" for="assignment-status">Status</label><select class="form-select" name="status" id="assignment-status"><option value="assigned">Assigned</option><option value="in_progress">In progress</option><option value="completed">Completed</option></select></div>
            <div class="col-12 d-flex justify-content-end"><button type="submit" name="add" class="btn btn-primary"><i class="fas fa-user-plus"></i> Assign Personnel</button></div>
        </form></div></div>
        <form method="get" class="mb-3">
            <div class="input-group">
                <input type="text" name="search" class="form-control" placeholder="Search assignments..." value="<?= isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '' ?>">
                <button type="submit" class="btn btn-primary">Search</button>
                <?php if (isset($_GET['search'])): ?><a class="btn btn-outline-secondary" href="assignments.php">Clear</a><?php endif; ?>
            </div>
        </form>
        <?php if (isset($_GET['search'])) {
            $assignments = $manager->searchAssignments($_GET['search']);
        } ?>
        <div class="card"><div class="card-header d-flex flex-wrap justify-content-between gap-2"><strong>Current Assignments</strong><span class="text-muted small"><?= count($assignments) ?> result<?= count($assignments) === 1 ? '' : 's' ?></span></div><div class="card-body p-0"><div class="table-responsive"><table class="table table-bordered table-striped mb-0" data-training-table>
            <thead>
                <tr>
                    <th data-sort>Course</th>
                    <th data-sort>Session</th>
                    <th data-sort>Personnel</th>
                    <th data-sort>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!empty($assignments)): ?>
                <?php foreach ($assignments as $a): ?>
                    <tr>
                        <td><?= htmlspecialchars($a['course_name'] ?? $a['course_id']) ?></td>
                        <td><?= htmlspecialchars($a['session_title'] ?? $a['session_id']) ?></td>
                        <td><?= htmlspecialchars($a['personnel_name'] ?? $a['personnel_id']) ?></td>
                        <td><?= htmlspecialchars($a['status']) ?></td>
                        <td>
                            <form method="post" style="display:inline;">
                                <?= csrf_field() ?>
                                <input type="hidden" name="delete_id" value="<?= $a['id'] ?>">
                                <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Remove this assignment?')">Remove</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="5" class="text-center text-muted py-4">No assignments found. Create an assignment once a course and session are available.</td></tr>
            <?php endif; ?>
            </tbody>
        </table></div></div></div>
    </div></div>
</div>
<?php require_once dirname(__DIR__) . '/shared/footer.php'; ?>
