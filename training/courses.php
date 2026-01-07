<?php
require_once 'training_manager.php';
$manager = new TrainingManager();


$pageTitle = "Training Courses";
$moduleName = "Training";
$moduleIcon = "graduation-cap";
$currentPage = "courses";

$sidebarLinks = [
    ['title' => 'Dashboard', 'url' => '/Armis2/training/index.php', 'icon' => 'tachometer-alt', 'page' => 'dashboard'],
    ['title' => 'Course Catalog', 'url' => '/Armis2/training/courses.php', 'icon' => 'book', 'page' => 'courses'],
    ['title' => 'Training Records', 'url' => '/Armis2/training/records.php', 'icon' => 'certificate', 'page' => 'records'],
    ['title' => 'Assignments', 'url' => '/Armis2/training/assignments.php', 'icon' => 'pen', 'page' => 'assignments'],
    ['title' => 'Schedule', 'url' => '/Armis2/training/schedule.php', 'icon' => 'calendar', 'page' => 'schedule'],
    ['title' => 'Certifications', 'url' => '/Armis2/training/certifications.php', 'icon' => 'award', 'page' => 'certifications']
];

// Handle add course
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add'])) {
    $manager->addCourse([
        'name' => $_POST['name'],
        'description' => $_POST['description'],
        'duration_weeks' => $_POST['duration_weeks'],
        'category' => $_POST['category'],
        'code' => $_POST['code'],
        'type' => $_POST['type'],
        'institution_id' => $_POST['institution_id']
    ]);
    header('Location: courses.php');
    exit;
}
// Handle edit course
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_id'])) {
    $manager->updateCourse($_POST['edit_id'], [
        'name' => $_POST['name'],
        'description' => $_POST['description'],
        'duration_weeks' => $_POST['duration_weeks'],
        'category' => $_POST['category'],
        'code' => $_POST['code'],
        'type' => $_POST['type'],
        'institution_id' => $_POST['institution_id']
    ]);
    header('Location: courses.php');
    exit;
}
// Handle delete course
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $manager->deleteCourse($_POST['delete_id']);
    header('Location: courses.php');
    exit;
}
// Search/filter
if (isset($_GET['search'])) {
    $courses = $manager->searchCourses($_GET['search']);
} else {
    $courses = $manager->getAllCourses();
}
// Fetch institutions for dropdown
$institutions = $manager->getDb()->query('SELECT id, name, type, location, createdAt FROM institutions')->fetchAll(PDO::FETCH_ASSOC);


require_once '../shared/header.php';
require_once '../shared/sidebar.php';
?>
<div class="content-wrapper with-sidebar">
    <div class="main-content container mt-4">
        <h1 class="mb-4">Training Courses</h1>
            <form class="mb-3" onsubmit="return false;">
                <div class="input-group">
                    <input type="text" id="searchBox" class="form-control" placeholder="Search courses...">
                </div>
            </form>
        
        <table class="table table-bordered table-hover">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Description</th>
                    <th>Duration (weeks)</th>
                    <th>Category</th>
                    <th>Code</th>
                    <th>Type</th>
                    <th>Institution</th>
                    <th>Sessions</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($courses as $course): ?>
                    <tr>
                        <td><?= htmlspecialchars($course['id'] ?? '') ?></td>
                        <td><?= htmlspecialchars($course['name'] ?? '') ?></td>
                        <td><?= htmlspecialchars($course['description'] ?? '') ?></td>
                        <td><?= htmlspecialchars($course['duration_weeks'] ?? '') ?></td>
                        <td><?= htmlspecialchars($course['category'] ?? '') ?></td>
                        <td><?= htmlspecialchars($course['code'] ?? '') ?></td>
                        <td><?= htmlspecialchars($course['type'] ?? '') ?></td>
                        <td>
                            <?php
                                $inst_id = $course['institution_id'] ?? null;
                                $inst = $inst_id ? array_filter($institutions, function($i) use ($inst_id) { return $i['id'] == $inst_id; }) : [];
                                echo htmlspecialchars($inst ? reset($inst)['name'] : '');
                            ?>
                        </td>
                        <td><?= $course['session_count'] ?? '' ?></td>
                        <td>
                            <form method="POST" style="display:inline-block">
                                <input type="hidden" name="delete_id" value="<?= $course['id'] ?>">
                                <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Delete this course?')">Delete</button>
                            </form>
                            <button class="btn btn-primary btn-sm" onclick="showEditModal(<?= htmlspecialchars(json_encode($course), ENT_QUOTES) ?>)">Edit</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
                        <button class="btn btn-success mb-4" data-bs-toggle="modal" data-bs-target="#addCourseModal">Add Course</button>
                        <!-- Modal Add Form -->
                        <div class="modal fade" id="addCourseModal" tabindex="-1" aria-labelledby="addCourseModalLabel" aria-hidden="true">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <form method="POST">
                                        <div class="modal-header">
                                            <h5 class="modal-title" id="addCourseModalLabel">Add Course</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="mb-2"><input type="text" name="name" class="form-control" placeholder="Course Name" required></div>
                                </tbody>
                            </table>
        
                                            <div class="mb-2"><textarea name="description" class="form-control" placeholder="Description"></textarea></div>
                                            <div class="mb-2"><input type="number" name="duration_weeks" class="form-control" placeholder="Duration (weeks)"></div>
                                            <div class="mb-2"><input type="text" name="category" class="form-control" placeholder="Category"></div>
                                            <div class="mb-2"><input type="text" name="code" class="form-control" placeholder="Course Code"></div>
                                            <div class="mb-2"><input type="text" name="type" class="form-control" placeholder="Type"></div>
                                            <div class="mb-2">
                                                <select name="institution_id" class="form-select" required>
                                                    <option value="">Select Institution</option>
                                                    <?php foreach ($institutions as $inst): ?>
                                                        <option value="<?= $inst['id'] ?>"><?= htmlspecialchars($inst['name']) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                            <button type="submit" name="add" class="btn btn-success">Add Course</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                <!-- Modal Edit Form -->
                <div class="modal fade" id="editCourseModal" tabindex="-1" aria-labelledby="editCourseModalLabel" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <form method="POST">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="editCourseModalLabel">Edit Course</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <input type="hidden" name="edit_id" id="modal_edit_id">
                                    <div class="mb-2"><input type="text" name="name" id="modal_edit_name" class="form-control" required></div>
                                    <div class="mb-2"><textarea name="description" id="modal_edit_description" class="form-control"></textarea></div>
                                    <div class="mb-2"><input type="number" name="duration_weeks" id="modal_edit_duration_weeks" class="form-control" placeholder="Duration (weeks)"></div>
                                    <div class="mb-2"><input type="text" name="category" id="modal_edit_category" class="form-control" placeholder="Category"></div>
                                    <div class="mb-2"><input type="text" name="code" id="modal_edit_code" class="form-control" placeholder="Course Code"></div>
                                    <div class="mb-2"><input type="text" name="type" id="modal_edit_type" class="form-control" placeholder="Type"></div>
                                    <div class="mb-2">
                                        <select name="institution_id" id="modal_edit_institution_id" class="form-select" required>
                                            <option value="">Select Institution</option>
                                            <?php foreach ($institutions as $inst): ?>
                                                <option value="<?= $inst['id'] ?>"><?= htmlspecialchars($inst['name']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-primary">Update Course</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
    </div>
</div>
<?php require_once '../shared/footer.php'; ?>
<script>
function showEditModal(course) {
    var c = typeof course === 'string' ? JSON.parse(course) : course;
    document.getElementById('modal_edit_id').value = c.id;
    document.getElementById('modal_edit_name').value = c.name;
    document.getElementById('modal_edit_description').value = c.description;
    document.getElementById('modal_edit_duration_weeks').value = c.duration_weeks;
    document.getElementById('modal_edit_category').value = c.category;
    document.getElementById('modal_edit_code').value = c.code;
    document.getElementById('modal_edit_type').value = c.type;
    document.getElementById('modal_edit_institution_id').value = c.institution_id;
    var modal = new bootstrap.Modal(document.getElementById('editCourseModal'));
    modal.show();
}
</script>
