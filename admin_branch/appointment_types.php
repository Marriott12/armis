<?php
// Define module constants
define('ARMIS_ADMIN_BRANCH', true);

// Include admin branch authentication and database
require_once __DIR__ . '/includes/auth.php';
require_once dirname(__DIR__) . '/shared/database_connection.php';

// Require authentication
requireAuth();

$pageTitle = "Appointment Types - Admin Branch";
$moduleName = "Admin Branch";
$moduleIcon = "users-cog";
$currentPage = "appointment_types";

$sidebarLinks = [
    ['title' => 'Dashboard', 'url' => '/Armis2/admin_branch/index.php', 'icon' => 'tachometer-alt', 'page' => 'dashboard'],
    ['title' => 'Create Staff', 'url' => '/Armis2/admin_branch/create_staff.php', 'icon' => 'user-plus', 'page' => 'create_staff'],
    ['title' => 'Edit Staff', 'url' => '/Armis2/admin_branch/edit_staff.php', 'icon' => 'user-edit', 'page' => 'edit_staff'],
    ['title' => 'Appointments', 'url' => '/Armis2/admin_branch/appointments.php', 'icon' => 'briefcase', 'page' => 'appointments'],
    ['title' => 'Appointment Types', 'url' => '/Armis2/admin_branch/appointment_types.php', 'icon' => 'clipboard-list', 'page' => 'appointment_types'],
    ['title' => 'Medals', 'url' => '/Armis2/admin_branch/medals.php', 'icon' => 'medal', 'page' => 'medals'],
    [
        'title' => 'Reports',
        'icon' => 'chart-bar',
        'page' => 'reports',
        'children' => [
            ['title' => 'Seniority', 'url' => '/Armis2/admin_branch/reports_seniority.php'],
            ['title' => 'Unit List', 'url' => '/Armis2/admin_branch/reports_units.php'],
            ['title' => 'Appointments', 'url' => '/Armis2/admin_branch/reports_appointment.php'],
            ['title' => 'Contracts', 'url' => '/Armis2/admin_branch/reports_contract.php'],
            ['title' => 'Courses', 'url' => '/Armis2/admin_branch/reports_courses.php'],
            ['title' => 'Deceased', 'url' => '/Armis2/admin_branch/reports_deceased.php'],
            ['title' => 'Gender', 'url' => '/Armis2/admin_branch/reports_gender.php'],
            ['title' => 'Marital', 'url' => '/Armis2/admin_branch/reports_marital.php'],
            ['title' => 'Rank', 'url' => '/Armis2/admin_branch/reports_rank.php'],
            ['title' => 'Retired', 'url' => '/Armis2/admin_branch/reports_retired.php'],
            ['title' => 'Trade', 'url' => '/Armis2/admin_branch/reports_trade.php'],
            ['title' => 'Corps', 'url' => '/Armis2/admin_branch/reports_corps.php'],
            ['title' => 'Units', 'url' => '/Armis2/admin_branch/reports_units.php'],
            ['title' => 'Medals', 'url' => '/Armis2/admin_branch/reports_medals.php'],
        ]
    ],
];

// Initialize variables
$errors = [];
$success = false;
$pdo = getDbConnection();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_type'])) {
        // Add new appointment type
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $isTemporary = isset($_POST['is_temporary']) ? 1 : 0;
        $defaultDuration = $isTemporary ? (int)$_POST['default_duration'] : null;
        
        if (empty($name)) {
            $errors[] = "Appointment type name is required.";
        }
        
        if (empty($errors)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO appointment_types (name, description, is_temporary, default_duration_days) VALUES (?, ?, ?, ?)");
                $stmt->execute([$name, $description, $isTemporary, $defaultDuration]);
                $success = "Appointment type added successfully.";
            } catch (Exception $e) {
                $errors[] = "Error adding appointment type: " . $e->getMessage();
            }
        }
    } elseif (isset($_POST['edit_type'])) {
        // Edit existing appointment type
        $id = (int)$_POST['id'];
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $isTemporary = isset($_POST['is_temporary']) ? 1 : 0;
        $defaultDuration = $isTemporary ? (int)$_POST['default_duration'] : null;
        
        if (empty($name)) {
            $errors[] = "Appointment type name is required.";
        }
        
        if (empty($errors)) {
            try {
                $stmt = $pdo->prepare("UPDATE appointment_types SET name = ?, description = ?, is_temporary = ?, default_duration_days = ? WHERE id = ?");
                $stmt->execute([$name, $description, $isTemporary, $defaultDuration, $id]);
                $success = "Appointment type updated successfully.";
            } catch (Exception $e) {
                $errors[] = "Error updating appointment type: " . $e->getMessage();
            }
        }
    } elseif (isset($_POST['delete_type'])) {
        // Delete appointment type
        $id = (int)$_POST['id'];
        
        try {
            // Check if this appointment type is in use
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM staff_appointment WHERE appointment_type_id = ?");
            $stmt->execute([$id]);
            $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            if ($count > 0) {
                $errors[] = "Cannot delete this appointment type because it is in use by {$count} appointments.";
            } else {
                $stmt = $pdo->prepare("DELETE FROM appointment_types WHERE id = ?");
                $stmt->execute([$id]);
                $success = "Appointment type deleted successfully.";
            }
        } catch (Exception $e) {
            $errors[] = "Error deleting appointment type: " . $e->getMessage();
        }
    }
}

// Get all appointment types
try {
    $stmt = $pdo->query("SELECT * FROM appointment_types ORDER BY name");
    $appointmentTypes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $errors[] = "Error fetching appointment types: " . $e->getMessage();
    $appointmentTypes = [];
}

include dirname(__DIR__) . '/shared/header.php';
include dirname(__DIR__) . '/shared/sidebar.php';
?>

<!-- Main Content -->
<div class="content-wrapper with-sidebar">
    <div class="container-fluid">
        <div class="main-content">
            <div class="row">
                <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h1 class="section-title">
                            <i class="fas fa-clipboard-list"></i> Appointment Types
                        </h1>
                        <div>
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTypeModal">
                                <i class="fas fa-plus"></i> Add Appointment Type
                            </button>
                            <a href="/Armis2/admin_branch/appointments.php" class="btn btn-outline-secondary">
                                <i class="fas fa-briefcase"></i> Appointments
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>

            <?php if ($errors): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                            <li><?= htmlspecialchars($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="row">
                <div class="col-12">
                    <div class="card shadow-sm">
                        <div class="card-header bg-success text-white">
                            <h4 class="mb-0"><i class="fa fa-clipboard-list"></i> Appointment Types</h4>
                        </div>
                        <div class="card-body">
                            <?php if (empty($appointmentTypes)): ?>
                                <div class="alert alert-info">
                                    No appointment types found. Please add some using the "Add Appointment Type" button.
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover">
                                        <thead>
                                            <tr>
                                                <th>Name</th>
                                                <th>Description</th>
                                                <th>Temporary</th>
                                                <th>Default Duration</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($appointmentTypes as $type): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($type['name']) ?></td>
                                                    <td><?= htmlspecialchars($type['description']) ?></td>
                                                    <td>
                                                        <?php if ($type['is_temporary']): ?>
                                                            <span class="badge bg-warning">Temporary</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-success">Permanent</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($type['default_duration_days']): ?>
                                                            <?= htmlspecialchars($type['default_duration_days']) ?> days
                                                        <?php else: ?>
                                                            N/A
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <button type="button" class="btn btn-sm btn-primary edit-type-btn" 
                                                                data-id="<?= $type['id'] ?>"
                                                                data-name="<?= htmlspecialchars($type['name']) ?>"
                                                                data-description="<?= htmlspecialchars($type['description']) ?>"
                                                                data-is-temporary="<?= $type['is_temporary'] ?>"
                                                                data-default-duration="<?= $type['default_duration_days'] ?>">
                                                            <i class="fas fa-edit"></i> Edit
                                                        </button>
                                                        <button type="button" class="btn btn-sm btn-danger delete-type-btn"
                                                                data-id="<?= $type['id'] ?>"
                                                                data-name="<?= htmlspecialchars($type['name']) ?>">
                                                            <i class="fas fa-trash"></i> Delete
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Appointment Type Modal -->
<div class="modal fade" id="addTypeModal" tabindex="-1" aria-labelledby="addTypeModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" action="">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="addTypeModalLabel">Add Appointment Type</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="name" class="form-label">Name *</label>
                        <input type="text" class="form-control" id="name" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="is_temporary" name="is_temporary">
                        <label class="form-check-label" for="is_temporary">Temporary Appointment</label>
                    </div>
                    <div class="mb-3" id="duration_field" style="display:none;">
                        <label for="default_duration" class="form-label">Default Duration (days)</label>
                        <input type="number" class="form-control" id="default_duration" name="default_duration" min="1" value="90">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_type" class="btn btn-primary">Add Appointment Type</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Appointment Type Modal -->
<div class="modal fade" id="editTypeModal" tabindex="-1" aria-labelledby="editTypeModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" action="">
                <input type="hidden" id="edit_id" name="id">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="editTypeModalLabel">Edit Appointment Type</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit_name" class="form-label">Name *</label>
                        <input type="text" class="form-control" id="edit_name" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_description" class="form-label">Description</label>
                        <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="edit_is_temporary" name="is_temporary">
                        <label class="form-check-label" for="edit_is_temporary">Temporary Appointment</label>
                    </div>
                    <div class="mb-3" id="edit_duration_field" style="display:none;">
                        <label for="edit_default_duration" class="form-label">Default Duration (days)</label>
                        <input type="number" class="form-control" id="edit_default_duration" name="default_duration" min="1">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="edit_type" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Appointment Type Modal -->
<div class="modal fade" id="deleteTypeModal" tabindex="-1" aria-labelledby="deleteTypeModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" action="">
                <input type="hidden" id="delete_id" name="id">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="deleteTypeModalLabel">Delete Appointment Type</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete the appointment type "<span id="delete_name"></span>"?</p>
                    <p class="text-danger"><strong>Warning:</strong> This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="delete_type" class="btn btn-danger">Delete</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    $(document).ready(function() {
        // Toggle duration field based on temporary checkbox
        $('#is_temporary').change(function() {
            if ($(this).is(':checked')) {
                $('#duration_field').show();
            } else {
                $('#duration_field').hide();
            }
        });
        
        $('#edit_is_temporary').change(function() {
            if ($(this).is(':checked')) {
                $('#edit_duration_field').show();
            } else {
                $('#edit_duration_field').hide();
            }
        });
        
        // Handle edit button clicks
        $('.edit-type-btn').click(function() {
            const id = $(this).data('id');
            const name = $(this).data('name');
            const description = $(this).data('description');
            const isTemporary = $(this).data('is-temporary');
            const defaultDuration = $(this).data('default-duration');
            
            $('#edit_id').val(id);
            $('#edit_name').val(name);
            $('#edit_description').val(description);
            $('#edit_is_temporary').prop('checked', isTemporary == 1);
            $('#edit_default_duration').val(defaultDuration);
            
            if (isTemporary == 1) {
                $('#edit_duration_field').show();
            } else {
                $('#edit_duration_field').hide();
            }
            
            $('#editTypeModal').modal('show');
        });
        
        // Handle delete button clicks
        $('.delete-type-btn').click(function() {
            const id = $(this).data('id');
            const name = $(this).data('name');
            
            $('#delete_id').val(id);
            $('#delete_name').text(name);
            
            $('#deleteTypeModal').modal('show');
        });
    });
</script>

<?php include dirname(__DIR__) . '/shared/footer.php'; ?>
