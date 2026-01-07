
<?php
require_once 'training_manager.php';
$manager = new TrainingManager();


$pageTitle = "Assignments";
$moduleName = "Training";
$moduleIcon = "graduation-cap";
$currentPage = "assignments";

$sidebarLinks = [
    ['title' => 'Dashboard', 'url' => '/Armis2/training/index.php', 'icon' => 'tachometer-alt', 'page' => 'dashboard'],
    ['title' => 'Course Catalog', 'url' => '/Armis2/training/courses.php', 'icon' => 'book', 'page' => 'courses'],
    ['title' => 'Training Records', 'url' => '/Armis2/training/records.php', 'icon' => 'certificate', 'page' => 'records'],
    ['title' => 'Assignments', 'url' => '/Armis2/training/assignments.php', 'icon' => 'pen', 'page' => 'assignments'],
    ['title' => 'Schedule', 'url' => '/Armis2/training/schedule.php', 'icon' => 'calendar', 'page' => 'schedule'],
    ['title' => 'Certifications', 'url' => '/Armis2/training/certifications.php', 'icon' => 'award', 'page' => 'certifications']
];

// Handle add/edit/delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
$personnel = $manager->getDb()->query('SELECT id, fName, lName FROM staff')->fetchAll(PDO::FETCH_ASSOC);

require_once dirname(__DIR__) . '/shared/header.php';
require_once dirname(__DIR__) . '/shared/sidebar.php';
?>
<div class="content-wrapper with-sidebar">
    <div class="main-content container mt-4">
        <h1>Training Assignments</h1>
        <form method="GET" class="mb-3">
            <div class="input-group">
                <input type="text" name="search" class="form-control" placeholder="Search assignments..." value="<?= isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '' ?>">
                <button type="submit" class="btn btn-primary">Search</button>
            </div>
        </form>
        <?php if (isset($_GET['search'])) {
            $assignments = $manager->searchAssignments($_GET['search']);
        } ?>
        <h3 class="mt-4">Current Assignments</h3>
        <table class="table table-bordered table-striped">
            <thead>
                <tr>
                    <th>Course</th>
                    <th>Session</th>
                    <th>Personnel</th>
                    <th>Status</th>
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
                                <input type="hidden" name="assignment_id" value="<?= $a['id'] ?>">
                                <button type="submit" name="remove_assignment" class="btn btn-danger btn-sm" onclick="return confirm('Remove this assignment?')">Remove</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="5" class="text-center">No assignments found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require_once dirname(__DIR__) . '/shared/footer.php'; ?>
