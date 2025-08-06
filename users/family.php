<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . dirname($_SERVER['PHP_SELF']) . '/../login.php');
    exit();
}

$pageTitle = "Family Members";
$moduleName = "User Profile";
$moduleIcon = "user";
$currentPage = "family";

$sidebarLinks = [
    ['title' => 'My Profile', 'url' => '/Armis2/users/index.php', 'icon' => 'user', 'page' => 'profile'],
    ['title' => 'Personal Info', 'url' => '/Armis2/users/personal.php', 'icon' => 'id-card', 'page' => 'personal'],
    ['title' => 'Service Record', 'url' => '/Armis2/users/service.php', 'icon' => 'medal', 'page' => 'service'],
    ['title' => 'Training History', 'url' => '/Armis2/users/training.php', 'icon' => 'graduation-cap', 'page' => 'training'],
    ['title' => 'Family Members', 'url' => '/Armis2/users/family.php', 'icon' => 'users', 'page' => 'family'],
    ['title' => 'Download CV', 'url' => '/Armis2/users/cv_download.php', 'icon' => 'download', 'page' => 'cv_download'],
    ['title' => 'Account Settings', 'url' => '/Armis2/users/settings.php', 'icon' => 'cogs', 'page' => 'settings']
];

// Load user profile data
require_once __DIR__ . '/profile_manager.php';

$successMessage = '';
$errorMessage = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $profileManager = new UserProfileManager($_SESSION['user_id']);
        
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'add_family_member':
                    $familyData = [
                        'name' => $_POST['name'] ?? '',
                        'relationship' => $_POST['relationship'] ?? '',
                        'date_of_birth' => $_POST['date_of_birth'] ?? null,
                        'phone' => $_POST['phone'] ?? '',
                        'email' => $_POST['email'] ?? '',
                        'address' => $_POST['address'] ?? '',
                        'is_emergency_contact' => isset($_POST['is_emergency_contact']) ? 1 : 0,
                        'is_dependent' => isset($_POST['is_dependent']) ? 1 : 0,
                        'notes' => $_POST['notes'] ?? ''
                    ];
                    
                    $result = $profileManager->addFamilyMember($familyData);
                    if ($result['success']) {
                        $successMessage = $result['message'];
                    } else {
                        $errorMessage = $result['message'];
                    }
                    break;
                    
                case 'update_family_member':
                    $memberId = $_POST['member_id'] ?? 0;
                    $familyData = [
                        'name' => $_POST['name'] ?? '',
                        'relationship' => $_POST['relationship'] ?? '',
                        'date_of_birth' => $_POST['date_of_birth'] ?? null,
                        'phone' => $_POST['phone'] ?? '',
                        'email' => $_POST['email'] ?? '',
                        'address' => $_POST['address'] ?? '',
                        'is_emergency_contact' => isset($_POST['is_emergency_contact']) ? 1 : 0,
                        'is_dependent' => isset($_POST['is_dependent']) ? 1 : 0,
                        'notes' => $_POST['notes'] ?? ''
                    ];
                    
                    $result = $profileManager->updateFamilyMember($memberId, $familyData);
                    if ($result['success']) {
                        $successMessage = $result['message'];
                    } else {
                        $errorMessage = $result['message'];
                    }
                    break;
                    
                case 'delete_family_member':
                    $memberId = $_POST['member_id'] ?? 0;
                    $result = $profileManager->deleteFamilyMember($memberId);
                    if ($result['success']) {
                        $successMessage = $result['message'];
                    } else {
                        $errorMessage = $result['message'];
                    }
                    break;
            }
        }
    } catch (Exception $e) {
        $errorMessage = "An error occurred: " . $e->getMessage();
    }
}

try {
    $profileManager = new UserProfileManager($_SESSION['user_id']);
    $userData = $profileManager->getUserProfile();
    $familyMembers = $profileManager->getFamilyMembers();
} catch (Exception $e) {
    $errorMessage = "Error loading family data: " . $e->getMessage();
    $familyMembers = [];
}

include dirname(__DIR__) . '/shared/header.php';
include dirname(__DIR__) . '/shared/sidebar.php';
?>

<!-- Main Content -->
<div class="content-wrapper with-sidebar">
    <div class="container-fluid">
        <div class="main-content">
            <!-- Header Section -->
            <div class="row">
                <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h1 class="section-title">
                            <i class="fas fa-users"></i> Family Members
                        </h1>
                        <div>
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addFamilyModal">
                                <i class="fas fa-plus"></i> Add Family Member
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Success/Error Messages -->
            <?php if ($successMessage): ?>
            <div class="row mb-3">
                <div class="col-12">
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="fas fa-check-circle"></i> <?= htmlspecialchars($successMessage) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($errorMessage): ?>
            <div class="row mb-3">
                <div class="col-12">
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($errorMessage) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Family Members List -->
            <div class="row">
                <div class="col-12">
                    <div class="card dashboard-card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-users"></i> My Family Members</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($familyMembers)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-users fa-3x text-muted mb-3"></i>
                                <h4 class="text-muted">No Family Members Registered</h4>
                                <p class="text-muted">Add your family members to maintain your personal records and emergency contacts.</p>
                                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addFamilyModal">
                                    <i class="fas fa-plus"></i> Add First Family Member
                                </button>
                            </div>
                            <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Relationship</th>
                                            <th>Contact</th>
                                            <th>Date of Birth</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($familyMembers as $member): ?>
                                        <tr>
                                            <td>
                                                <strong><?= htmlspecialchars($member->name) ?></strong>
                                                <?php if ($member->is_emergency_contact): ?>
                                                <br><small class="text-danger"><i class="fas fa-exclamation-triangle"></i> Emergency Contact</small>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= htmlspecialchars($member->relationship) ?></td>
                                            <td>
                                                <?php if ($member->phone): ?>
                                                <i class="fas fa-phone text-primary"></i> <?= htmlspecialchars($member->phone) ?><br>
                                                <?php endif; ?>
                                                <?php if ($member->email): ?>
                                                <i class="fas fa-envelope text-info"></i> <?= htmlspecialchars($member->email) ?>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($member->date_of_birth): ?>
                                                <?= date('M j, Y', strtotime($member->date_of_birth)) ?>
                                                <br><small class="text-muted"><?= $profileManager->calculateAge($member->date_of_birth) ?> years old</small>
                                                <?php else: ?>
                                                N/A
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($member->is_dependent): ?>
                                                <span class="badge bg-info mb-1">Dependent</span><br>
                                                <?php endif; ?>
                                                <?php if ($member->is_emergency_contact): ?>
                                                <span class="badge bg-danger">Emergency Contact</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <button type="button" class="btn btn-outline-primary" 
                                                            onclick="editFamilyMember(<?= $member->id ?>, '<?= htmlspecialchars($member->name, ENT_QUOTES) ?>', '<?= htmlspecialchars($member->relationship, ENT_QUOTES) ?>', '<?= $member->date_of_birth ?>', '<?= htmlspecialchars($member->phone, ENT_QUOTES) ?>', '<?= htmlspecialchars($member->email, ENT_QUOTES) ?>', '<?= htmlspecialchars($member->address, ENT_QUOTES) ?>', <?= $member->is_emergency_contact ? 'true' : 'false' ?>, <?= $member->is_dependent ? 'true' : 'false' ?>, '<?= htmlspecialchars($member->notes, ENT_QUOTES) ?>')">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-outline-danger" 
                                                            onclick="deleteFamilyMember(<?= $member->id ?>, '<?= htmlspecialchars($member->name, ENT_QUOTES) ?>')">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
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

            <!-- Emergency Contacts Summary -->
            <?php 
            $emergencyContacts = array_filter($familyMembers, function($member) {
                return $member->is_emergency_contact;
            });
            ?>
            <?php if (!empty($emergencyContacts)): ?>
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card border-warning">
                        <div class="card-header bg-warning text-dark">
                            <h5 class="mb-0"><i class="fas fa-exclamation-triangle"></i> Emergency Contacts</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <?php foreach ($emergencyContacts as $contact): ?>
                                <div class="col-md-6 mb-3">
                                    <div class="card h-100">
                                        <div class="card-body">
                                            <h6 class="card-title"><?= htmlspecialchars($contact->name) ?></h6>
                                            <p class="card-text">
                                                <strong>Relationship:</strong> <?= htmlspecialchars($contact->relationship) ?><br>
                                                <?php if ($contact->phone): ?>
                                                <strong>Phone:</strong> <?= htmlspecialchars($contact->phone) ?><br>
                                                <?php endif; ?>
                                                <?php if ($contact->email): ?>
                                                <strong>Email:</strong> <?= htmlspecialchars($contact->email) ?><br>
                                                <?php endif; ?>
                                                <?php if ($contact->address): ?>
                                                <strong>Address:</strong> <?= htmlspecialchars($contact->address) ?>
                                                <?php endif; ?>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Add Family Member Modal -->
<div class="modal fade" id="addFamilyModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-plus"></i> Add Family Member</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_family_member">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="add_name" class="form-label">Full Name *</label>
                            <input type="text" class="form-control" id="add_name" name="name" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="add_relationship" class="form-label">Relationship *</label>
                            <select class="form-select" id="add_relationship" name="relationship" required>
                                <option value="">Select Relationship</option>
                                <option value="Spouse">Spouse</option>
                                <option value="Child">Child</option>
                                <option value="Parent">Parent</option>
                                <option value="Sibling">Sibling</option>
                                <option value="Guardian">Guardian</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="add_date_of_birth" class="form-label">Date of Birth</label>
                            <input type="date" class="form-control" id="add_date_of_birth" name="date_of_birth">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="add_phone" class="form-label">Phone Number</label>
                            <input type="tel" class="form-control" id="add_phone" name="phone">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="add_email" class="form-label">Email Address</label>
                            <input type="email" class="form-control" id="add_email" name="email">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="add_address" class="form-label">Address</label>
                            <textarea class="form-control" id="add_address" name="address" rows="2"></textarea>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="add_is_emergency_contact" name="is_emergency_contact">
                                <label class="form-check-label" for="add_is_emergency_contact">
                                    Emergency Contact
                                </label>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="add_is_dependent" name="is_dependent">
                                <label class="form-check-label" for="add_is_dependent">
                                    Dependent
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="add_notes" class="form-label">Notes</label>
                        <textarea class="form-control" id="add_notes" name="notes" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Family Member</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Family Member Modal -->
<div class="modal fade" id="editFamilyModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-edit"></i> Edit Family Member</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_family_member">
                    <input type="hidden" name="member_id" id="edit_member_id">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="edit_name" class="form-label">Full Name *</label>
                            <input type="text" class="form-control" id="edit_name" name="name" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="edit_relationship" class="form-label">Relationship *</label>
                            <select class="form-select" id="edit_relationship" name="relationship" required>
                                <option value="">Select Relationship</option>
                                <option value="Spouse">Spouse</option>
                                <option value="Child">Child</option>
                                <option value="Parent">Parent</option>
                                <option value="Sibling">Sibling</option>
                                <option value="Guardian">Guardian</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="edit_date_of_birth" class="form-label">Date of Birth</label>
                            <input type="date" class="form-control" id="edit_date_of_birth" name="date_of_birth">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="edit_phone" class="form-label">Phone Number</label>
                            <input type="tel" class="form-control" id="edit_phone" name="phone">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="edit_email" class="form-label">Email Address</label>
                            <input type="email" class="form-control" id="edit_email" name="email">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="edit_address" class="form-label">Address</label>
                            <textarea class="form-control" id="edit_address" name="address" rows="2"></textarea>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="edit_is_emergency_contact" name="is_emergency_contact">
                                <label class="form-check-label" for="edit_is_emergency_contact">
                                    Emergency Contact
                                </label>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="edit_is_dependent" name="is_dependent">
                                <label class="form-check-label" for="edit_is_dependent">
                                    Dependent
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_notes" class="form-label">Notes</label>
                        <textarea class="form-control" id="edit_notes" name="notes" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Family Member</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteFamilyModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-danger"><i class="fas fa-trash"></i> Delete Family Member</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete <strong id="delete_member_name"></strong> from your family members?</p>
                <p class="text-danger"><small>This action cannot be undone.</small></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="delete_family_member">
                    <input type="hidden" name="member_id" id="delete_member_id">
                    <button type="submit" class="btn btn-danger">Delete</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function editFamilyMember(id, name, relationship, dob, phone, email, address, isEmergency, isDependent, notes) {
    document.getElementById('edit_member_id').value = id;
    document.getElementById('edit_name').value = name;
    document.getElementById('edit_relationship').value = relationship;
    document.getElementById('edit_date_of_birth').value = dob;
    document.getElementById('edit_phone').value = phone;
    document.getElementById('edit_email').value = email;
    document.getElementById('edit_address').value = address;
    document.getElementById('edit_is_emergency_contact').checked = isEmergency;
    document.getElementById('edit_is_dependent').checked = isDependent;
    document.getElementById('edit_notes').value = notes;
    
    new bootstrap.Modal(document.getElementById('editFamilyModal')).show();
}

function deleteFamilyMember(id, name) {
    document.getElementById('delete_member_id').value = id;
    document.getElementById('delete_member_name').textContent = name;
    
    new bootstrap.Modal(document.getElementById('deleteFamilyModal')).show();
}
</script>

<?php include dirname(__DIR__) . '/shared/footer.php'; ?>
