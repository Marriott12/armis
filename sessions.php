<?php
require_once dirname(__DIR__) . '/shared/module_auth.php';
require_once dirname(__DIR__) . '/shared/csrf.php';
require_once 'training_manager.php';
bootModule(['module' => 'training', 'page' => 'sessions', 'pageTitle' => 'Training Sessions - ARMIS', 'moduleName' => 'Training', 'moduleIcon' => 'graduation-cap']);
$manager = new TrainingManager();
// Handle add/edit/delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    if (isset($_POST['add'])) {
        $manager->addSession([
            'course_id' => $_POST['course_id'],
            'title' => $_POST['title'],
            'date' => $_POST['date'],
            'description' => $_POST['description']
        ]);
        header('Location: sessions.php?result=created'); exit;
    }
    if (isset($_POST['edit_id'])) {
        $manager->updateSession($_POST['edit_id'], [
            'course_id' => $_POST['course_id'],
            'title' => $_POST['title'],
            'date' => $_POST['date'],
            'description' => $_POST['description']
        ]);
        header('Location: sessions.php?result=updated'); exit;
    }
    if (isset($_POST['delete_id'])) {
        $manager->deleteSession($_POST['delete_id']);
        header('Location: sessions.php?result=deleted'); exit;
    }
}
$sessions = $manager->getAllSessions();
$courses = $manager->getAllCourses();
include dirname(__DIR__) . '/shared/header.php';
include dirname(__DIR__) . '/shared/sidebar.php';
?>
<div class="content-wrapper with-sidebar"><div class="container-fluid"><div class="main-content">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4"><div><h1 class="section-title mb-1"><i class="fas fa-calendar-alt"></i> Training Sessions</h1><p class="text-muted mb-0">Schedule and manage delivery of training courses.</p></div><a href="courses.php" class="btn btn-outline-primary"><i class="fas fa-book"></i> Course Catalog</a></div>
    <?php if (isset($_GET['result'])): ?><div class="alert alert-success" role="status"><?= $_GET['result'] === 'created' ? 'Training session created.' : ($_GET['result'] === 'updated' ? 'Training session updated.' : 'Training session deleted.') ?></div><?php endif; ?>
    <form method="GET" class="mb-3">
        <div class="input-group"><input type="text" name="search" class="form-control" placeholder="Search sessions..." value="<?= isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '' ?>"><button type="submit" class="btn btn-primary">Search</button><?php if (isset($_GET['search'])): ?><a href="sessions.php" class="btn btn-outline-secondary">Clear</a><?php endif; ?></div>
    </form>
    <?php if (isset($_GET['search'])) {
        $sessions = $manager->searchSessions($_GET['search']);
    } ?>
    <div class="card mb-4"><div class="card-header d-flex flex-wrap justify-content-between gap-2"><strong>Scheduled Sessions</strong><span class="text-muted small"><?= count($sessions) ?> result<?= count($sessions) === 1 ? '' : 's' ?></span></div><div class="card-body p-0"><div class="table-responsive"><table class="table table-bordered mb-0" data-training-table>
        <thead>
            <tr><th data-sort>Title</th><th data-sort>Course</th><th data-sort>Date</th><th data-sort>Assigned</th><th>Actions</th></tr>
        </thead>
        <tbody>
            <?php foreach ($sessions as $session): ?>
                <tr>
                    <td><?= htmlspecialchars($session['title']) ?></td>
                    <td><?= htmlspecialchars($session['course_name']) ?></td>
                    <td><?= htmlspecialchars($session['date']) ?></td>
                    <td><?= $session['assigned_count'] ?></td>
                    <td>
                        <form method="POST" style="display:inline-block">
                            <?= csrf_field() ?>
                            <input type="hidden" name="delete_id" value="<?= $session['id'] ?>">
                            <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Delete this session?')">Delete</button>
                        </form>
                        <button class="btn btn-primary btn-sm" onclick="showEdit(<?= $session['id'] ?>, <?= $session['course_id'] ?>, '<?= htmlspecialchars($session['title'], ENT_QUOTES) ?>', '<?= htmlspecialchars($session['date'], ENT_QUOTES) ?>', '<?= htmlspecialchars($session['description'], ENT_QUOTES) ?>')">Edit</button>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$sessions): ?><tr><td colspan="5" class="text-center text-muted py-4">No sessions found. Create a session once a course is available.</td></tr><?php endif; ?>
        </tbody>
    </table></div></div></div>
    <div class="card"><div class="card-header"><strong>Add Session</strong></div><div class="card-body"><form method="POST" class="row g-3 mb-0">
        <?= csrf_field() ?>
        <div class="col-md-4"><label class="form-label" for="new-course">Course</label>
            <select id="new-course" name="course_id" class="form-select" required>
                <option value="">Select Course</option>
                <?php foreach ($courses as $course): ?>
                    <option value="<?= $course['id'] ?>"><?= htmlspecialchars($course['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4"><label class="form-label" for="new-title">Session title</label><input id="new-title" type="text" name="title" class="form-control" required></div>
        <div class="col-md-4"><label class="form-label" for="new-date">Date</label><input id="new-date" type="date" name="date" class="form-control" required></div>
        <div class="col-12"><label class="form-label" for="new-description">Description</label><textarea id="new-description" name="description" class="form-control" rows="2"></textarea></div>
        <div class="col-12 d-flex justify-content-end"><button type="submit" name="add" class="btn btn-success"><i class="fas fa-plus"></i> Add Session</button></div>
    </form></div></div>
    <div class="modal fade" id="editForm" tabindex="-1" aria-labelledby="editSessionTitle" aria-hidden="true"><div class="modal-dialog"><div class="modal-content"><form method="POST">
            <?= csrf_field() ?>
            <div class="modal-header"><h2 class="modal-title h5" id="editSessionTitle">Edit Session</h2><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body">
            <input type="hidden" name="edit_id" id="edit_id">
            <div class="mb-2">
                <select name="course_id" id="edit_course_id" class="form-select" required>
                    <option value="">Select Course</option>
                    <?php foreach ($courses as $course): ?>
                        <option value="<?= $course['id'] ?>"><?= htmlspecialchars($course['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-2"><input type="text" name="title" id="edit_title" class="form-control" required></div>
            <div class="mb-2"><input type="date" name="date" id="edit_date" class="form-control" required></div>
            <div class="mb-2"><textarea name="description" id="edit_description" class="form-control"></textarea></div>
            </div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Update Session</button></div></form></div></div></div>
</div></div></div>
<script>
function showEdit(id, courseId, title, date, desc) {
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_course_id').value = courseId;
    document.getElementById('edit_title').value = title;
    document.getElementById('edit_date').value = date;
    document.getElementById('edit_description').value = desc;
    bootstrap.Modal.getOrCreateInstance(document.getElementById('editForm')).show();
}
</script>
<?php include dirname(__DIR__) . '/shared/footer.php'; ?>
