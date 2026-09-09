<?php
require_once dirname(__DIR__) . '/shared/module_auth.php';
require_once dirname(__DIR__) . '/shared/csrf.php';
require_once 'training_manager.php';
require_once '../shared/military_formatting.php';
bootModule(['module' => 'training', 'page' => 'records', 'pageTitle' => 'Training Records - ARMIS', 'moduleName' => 'Training', 'moduleIcon' => 'graduation-cap']);
$manager = new TrainingManager();
$db = $manager->getDb();

$pageTitle = "Training Records";
$moduleName = "Training";
$moduleIcon = "graduation-cap";
$currentPage = "records";

// Handle add record
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add'])) {
    require_csrf();
    $manager->addAssignment([
        'personnel_id' => $_POST['personnel_id'],
        'course_id' => $_POST['course_id'],
        'session_id' => $_POST['session_id'],
        'status' => $_POST['status']
    ]);
    header('Location: records.php');
    exit;
}
// Handle edit record
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_id'])) {
    require_csrf();
    $manager->updateAssignment($_POST['edit_id'], [
        'personnel_id' => $_POST['personnel_id'],
        'course_id' => $_POST['course_id'],
        'session_id' => $_POST['session_id'],
        'status' => $_POST['status']
    ]);
    header('Location: records.php');
    exit;
}
// Handle delete record
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    require_csrf();
    $manager->deleteAssignment($_POST['delete_id']);
    header('Location: records.php');
    exit;
}
// Search/filter
if (isset($_GET['search'])) {
    $records = $manager->searchAssignments($_GET['search']);
} else {
    $records = $manager->getAllAssignments();
}
$courses = $manager->getAllCourses();
$sessions = $manager->getAllSessions();
$personnel = $db->query('SELECT svcNo, rankId, fName, mName, lName FROM staff ORDER BY lName, fName')->fetchAll(PDO::FETCH_ASSOC);

require_once '../shared/header.php';
require_once '../shared/sidebar.php';
?>
<div class="content-wrapper with-sidebar">
    <div class="main-content container mt-4">
        <h1 class="mb-4">Training Records</h1>
        <form method="GET" class="mb-3">
            <div class="input-group">
                <input type="text" name="search" class="form-control" placeholder="Search records..." value="<?= isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '' ?>">
                <button type="submit" class="btn btn-primary">Search</button>
            </div>
        </form>
        <table class="table table-bordered table-hover">
            <thead>
                <tr><th>Personnel</th><th>Course</th><th>Session</th><th>Status</th><th>Actions</th></tr>
            </thead>
            <tbody>
                <?php foreach ($records as $rec): ?>
                    <tr>
                        <td>
                        <?php
                            echo htmlspecialchars($rec['personnel_name'] ?? $rec['personnel_id']);
                        ?>
                        </td>
                        <td><?= htmlspecialchars($rec['course_name']) ?></td>
                        <td><?= htmlspecialchars($rec['session_title']) ?></td>
                        <td><?= htmlspecialchars($rec['status']) ?></td>
                        <td>
                            <form method="POST" style="display:inline-block">
                                <?= csrf_field() ?>
                                <input type="hidden" name="delete_id" value="<?= $rec['id'] ?>">
                                <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Delete this record?')">Delete</button>
                            </form>
                            <button type="button" class="btn btn-primary btn-sm" onclick='showEdit(<?= json_encode($rec['id']) ?>, <?= json_encode($rec['personnel_id']) ?>, <?= json_encode($rec['course_id']) ?>, <?= json_encode($rec['session_id']) ?>, <?= json_encode($rec['status']) ?>)'>Edit</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <h3 class="mt-4">Add Training Record</h3>
        <form method="POST" class="mb-4">
            <?= csrf_field() ?>
            <div class="mb-2">
                <select name="personnel_id" class="form-select" required>
                    <option value="">Select Personnel</option>
                    <?php foreach ($personnel as $p): ?>
                        <option value="<?= htmlspecialchars($p['svcNo']) ?>">
                            <?= htmlspecialchars(trim(($p['rankId'] ?? '') . ' ' . ($p['fName'] ?? '') . ' ' . ($p['mName'] ?? '') . ' ' . ($p['lName'] ?? '') . ' (' . $p['svcNo'] . ')')) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-2">
                <select name="course_id" class="form-select" required>
                    <option value="">Select Course</option>
                    <?php foreach ($courses as $course): ?>
                        <option value="<?= $course['id'] ?>">
                            <?= htmlspecialchars($course['name']) ?>
                            <?php /* Optionally show more info: echo " - {$course['code']} ({$course['duration_weeks']}w, {$course['category']})"; */ ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-2">
                <select name="session_id" class="form-select" required>
                    <option value="">Select Session</option>
                    <?php foreach ($sessions as $session): ?>
                        <option value="<?= $session['id'] ?>"><?= htmlspecialchars($session['title']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-2">
                <select name="status" class="form-select" required>
                    <option value="Assigned">Assigned</option>
                    <option value="In Progress">In Progress</option>
                    <option value="Completed">Completed</option>
                </select>
            </div>
            <button type="submit" name="add" class="btn btn-success">Add Record</button>
        </form>
        <div id="editForm" style="display:none;">
            <h3>Edit Training Record</h3>
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="edit_id" id="edit_id">
                <div class="mb-2">
                    <select name="personnel_id" id="edit_personnel_id" class="form-select" required>
                        <option value="">Select Personnel</option>
                        <?php foreach ($personnel as $p): ?>
                            <option value="<?= htmlspecialchars($p['svcNo']) ?>">
                                <?= htmlspecialchars(trim(($p['rankId'] ?? '') . ' ' . ($p['fName'] ?? '') . ' ' . ($p['mName'] ?? '') . ' ' . ($p['lName'] ?? '') . ' (' . $p['svcNo'] . ')')) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-2">
                    <select name="course_id" id="edit_course_id" class="form-select" required>
                        <option value="">Select Course</option>
                        <?php foreach ($courses as $course): ?>
                            <option value="<?= $course['id'] ?>">
                                <?= htmlspecialchars($course['name']) ?>
                                <?php /* Optionally show more info: echo " - {$course['code']} ({$course['duration_weeks']}w, {$course['category']})"; */ ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-2">
                    <select name="session_id" id="edit_session_id" class="form-select" required>
                        <option value="">Select Session</option>
                        <?php foreach ($sessions as $session): ?>
                            <option value="<?= $session['id'] ?>"><?= htmlspecialchars($session['title']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-2">
                    <select name="status" id="edit_status" class="form-select" required>
                        <option value="Assigned">Assigned</option>
                        <option value="In Progress">In Progress</option>
                        <option value="Completed">Completed</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">Update Record</button>
                <button type="button" class="btn btn-secondary" onclick="hideEdit()">Cancel</button>
            </form>
        </div>
    </div>
</div>
<?php require_once '../shared/footer.php'; ?>
<script>
function showEdit(id, personnelId, courseId, sessionId, status) {
    document.getElementById('editForm').style.display = 'block';
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_personnel_id').value = personnelId;
    document.getElementById('edit_course_id').value = courseId;
    document.getElementById('edit_session_id').value = sessionId;
    document.getElementById('edit_status').value = status;
}
function hideEdit() {
    document.getElementById('editForm').style.display = 'none';
}
</script>
