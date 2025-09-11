<?php
// List, add, edit, delete training sessions
require_once 'training_manager.php';
$manager = new TrainingManager();
// Handle add/edit/delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add'])) {
        $manager->addSession([
            'course_id' => $_POST['course_id'],
            'title' => $_POST['title'],
            'date' => $_POST['date'],
            'description' => $_POST['description']
        ]);
        header('Location: sessions.php'); exit;
    }
    if (isset($_POST['edit_id'])) {
        $manager->updateSession($_POST['edit_id'], [
            'course_id' => $_POST['course_id'],
            'title' => $_POST['title'],
            'date' => $_POST['date'],
            'description' => $_POST['description']
        ]);
        header('Location: sessions.php'); exit;
    }
    if (isset($_POST['delete_id'])) {
        $manager->deleteSession($_POST['delete_id']);
        header('Location: sessions.php'); exit;
    }
}
$sessions = $manager->getAllSessions();
$courses = $manager->getAllCourses();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Training Sessions</title>
    <link rel="stylesheet" href="../assets/bootstrap.min.css">
</head>
<body>
<div class="container mt-4">
    <h1>Training Sessions</h1>
    <form method="GET" class="mb-3">
        <input type="text" name="search" class="form-control" placeholder="Search sessions..." value="<?= isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '' ?>">
        <button type="submit" class="btn btn-primary mt-2">Search</button>
    </form>
    <?php if (isset($_GET['search'])) {
        $sessions = $manager->searchSessions($_GET['search']);
    } ?>
    <table class="table table-bordered">
        <thead>
            <tr><th>Title</th><th>Course</th><th>Date</th><th>Assigned</th><th>Actions</th></tr>
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
                            <input type="hidden" name="delete_id" value="<?= $session['id'] ?>">
                            <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Delete this session?')">Delete</button>
                        </form>
                        <button class="btn btn-primary btn-sm" onclick="showEdit(<?= $session['id'] ?>, <?= $session['course_id'] ?>, '<?= htmlspecialchars($session['title'], ENT_QUOTES) ?>', '<?= htmlspecialchars($session['date'], ENT_QUOTES) ?>', '<?= htmlspecialchars($session['description'], ENT_QUOTES) ?>')">Edit</button>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <h3 class="mt-4">Add Session</h3>
    <form method="POST" class="mb-4">
        <div class="mb-2">
            <select name="course_id" class="form-select" required>
                <option value="">Select Course</option>
                <?php foreach ($courses as $course): ?>
                    <option value="<?= $course['id'] ?>"><?= htmlspecialchars($course['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="mb-2"><input type="text" name="title" class="form-control" placeholder="Session Title" required></div>
        <div class="mb-2"><input type="date" name="date" class="form-control" required></div>
        <div class="mb-2"><textarea name="description" class="form-control" placeholder="Description"></textarea></div>
        <button type="submit" name="add" class="btn btn-success">Add Session</button>
    </form>
    <div id="editForm" style="display:none;">
        <h3>Edit Session</h3>
        <form method="POST">
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
            <button type="submit" class="btn btn-primary">Update Session</button>
            <button type="button" class="btn btn-secondary" onclick="hideEdit()">Cancel</button>
        </form>
    </div>
</div>
<script>
function showEdit(id, courseId, title, date, desc) {
    document.getElementById('editForm').style.display = 'block';
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_course_id').value = courseId;
    document.getElementById('edit_title').value = title;
    document.getElementById('edit_date').value = date;
    document.getElementById('edit_description').value = desc;
}
function hideEdit() {
    document.getElementById('editForm').style.display = 'none';
}
</script>
</body>
</html>
