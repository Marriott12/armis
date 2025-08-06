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

$pageTitle = "Personal Information";
$moduleName = "User Profile";
$moduleIcon = "id-card";
$currentPage = "personal";

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

$success = false;
$errors = [];

try {
    require_once dirname(__DIR__) . '/shared/database_connection.php';
    $pdo = getDbConnection();
    $profileManager = new UserProfileManager($_SESSION['user_id']);
    $userData = $profileManager->getUserProfile();
    $contactInfo = $profileManager->getContactInfo();
    $addresses = $profileManager->getAddresses();
    
    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['update_basic'])) {
            // Update staff table with new personal info
            $staff_id = $_SESSION['user_id'];
            $now = date('Y-m-d H:i:s');
            $service_number = $userData->svcNo ?? null;
            $fields = [
                'nrc' => $_POST['nrc'] ?? null,
                'dob' => $_POST['dob'] ?? null,
                'gender' => $_POST['gender'] ?? null,
                'marital_status' => $_POST['marital'] ?? null,
                'religion' => $_POST['religion'] ?? null,
                'bloodGp' => $_POST['bloodGp'] ?? null,
                'height' => $_POST['height'] ?? null,
                'email' => $_POST['email'] ?? null,
                'tel' => $_POST['tel'] ?? null,
                'updated_at' => $now
            ];
            $set = [];
            $params = [];
            foreach ($fields as $col => $val) {
                $set[] = "$col = ?";
                $params[] = $val;
            }
            $params[] = $service_number;
            $stmt = $pdo->prepare("UPDATE staff SET ".implode(',', $set)." WHERE service_number = ?");
            $stmt->execute($params);

            // --- Insert/Update Spouse in staff_spouse and Children in staff_family_members ---
            $spouse_name = trim($_POST['spouse_name'] ?? '');
            $spouse_dob = $_POST['spouse_dob'] ?? null;
            $spouse_nrc = $_POST['spouse_nrc'] ?? null;
            $spouse_occup = $_POST['spouse_occup'] ?? null;
            $spouse_contact = $_POST['spouse_contact'] ?? null;
            if ($spouse_name !== '' && $service_number) {
                // Upsert spouse (one per staff)
                $stmt = $pdo->prepare("SELECT id FROM staff_spouse WHERE service_number = ?");
                $stmt->execute([$service_number]);
                $spouse_id = $stmt->fetchColumn();
                if ($spouse_id) {
                    $stmt = $pdo->prepare("UPDATE staff_spouse SET spouseName=?, spouseDOB=?, spouseNRC=?, spouseOccup=?, spouseContact=? WHERE id=?");
                    $stmt->execute([$spouse_name, $spouse_dob, $spouse_nrc, $spouse_occup, $spouse_contact, $spouse_id]);
                } else {
                    $stmt = $pdo->prepare("INSERT INTO staff_spouse (service_number, spouseName, spouseDOB, spouseNRC, spouseOccup, spouseContact) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$service_number, $spouse_name, $spouse_dob, $spouse_nrc, $spouse_occup, $spouse_contact]);
                }
            }
            // Children (staff_family_members)
            $child_names = $_POST['child_name'] ?? [];
            $child_dobs = $_POST['child_dob'] ?? [];
            $child_genders = $_POST['child_gender'] ?? [];
            // Remove all previous children for this staff (to avoid duplicates)
            $pdo->prepare("DELETE FROM staff_family_members WHERE staff_id = ? AND relationship = 'Child'")->execute([$staff_id]);
            for ($i = 0; $i < count($child_names); $i++) {
                $name = trim($child_names[$i] ?? '');
                $dob = $child_dobs[$i] ?? null;
                $gender = $child_genders[$i] ?? null;
                if ($name !== '') {
                    $stmt = $pdo->prepare("INSERT INTO staff_family_members (staff_id, name, relationship, date_of_birth, gender, phone, occupation, is_next_of_kin, is_emergency_contact, created_at, updated_at) VALUES (?, ?, 'Child', ?, ?, NULL, NULL, 0, 0, ?, ?)");
                    $stmt->execute([$staff_id, $name, $dob, $gender, $now, $now]);
                }
            }
            // --- Education (staff_education) ---
            $pdo->prepare("DELETE FROM staff_education WHERE staff_id = ?")->execute([$staff_id]);
            $edu_institutions = $_POST['edu_institution'] ?? [];
            $edu_qualifications = $_POST['edu_qualification'] ?? [];
            $edu_levels = $_POST['edu_level'] ?? [];
            $edu_fields = $_POST['edu_field'] ?? [];
            $edu_year_started = $_POST['edu_year_started'] ?? [];
            $edu_year_completed = $_POST['edu_year_completed'] ?? [];
            $edu_grades = $_POST['edu_grade'] ?? [];
            $edu_is_highest = $_POST['edu_is_highest'] ?? [];
            for ($i = 0; $i < count($edu_institutions); $i++) {
                $institution = trim($edu_institutions[$i] ?? '');
                $qualification = trim($edu_qualifications[$i] ?? '');
                $level = trim($edu_levels[$i] ?? '');
                $field = trim($edu_fields[$i] ?? '');
                $year_started = $edu_year_started[$i] ?? null;
                $year_completed = $edu_year_completed[$i] ?? null;
                $grade = trim($edu_grades[$i] ?? '');
                $is_highest = isset($edu_is_highest[$i]) ? 1 : 0;
                if ($institution !== '' && $qualification !== '') {
                    $stmt = $pdo->prepare("INSERT INTO staff_education (staff_id, institution, qualification, level, field_of_study, year_started, year_completed, grade_obtained, is_highest_qualification, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$staff_id, $institution, $qualification, $level, $field, $year_started, $year_completed, $grade, $is_highest, $now, $now]);
                }
            }
            // --- Languages (staff_languages) ---
            $pdo->prepare("DELETE FROM staff_languages WHERE staff_id = ?")->execute([$staff_id]);
            $lang_names = $_POST['lang_name'] ?? [];
            $lang_proficiency = $_POST['lang_proficiency'] ?? [];
            $lang_can_read = $_POST['lang_can_read'] ?? [];
            $lang_can_write = $_POST['lang_can_write'] ?? [];
            $lang_can_speak = $_POST['lang_can_speak'] ?? [];
            $lang_can_understand = $_POST['lang_can_understand'] ?? [];
            for ($i = 0; $i < count($lang_names); $i++) {
                $name = trim($lang_names[$i] ?? '');
                $proficiency = $lang_proficiency[$i] ?? '';
                $can_read = isset($lang_can_read[$i]) ? 1 : 0;
                $can_write = isset($lang_can_write[$i]) ? 1 : 0;
                $can_speak = isset($lang_can_speak[$i]) ? 1 : 0;
                $can_understand = isset($lang_can_understand[$i]) ? 1 : 0;
                if ($name !== '') {
                    $stmt = $pdo->prepare("INSERT INTO staff_languages (staff_id, language_name, proficiency_level, can_read, can_write, can_speak, can_understand, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$staff_id, $name, $proficiency, $can_read, $can_write, $can_speak, $can_understand, $now, $now]);
                }
            }
            // --- End family members logic ---
            $success = true;
            // Reload data
            $userData = $profileManager->getUserProfile();
        } elseif (isset($_POST['upload_photo'])) {
            if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
                $service_number = $userData->svcNo ?? null;
                if (!$service_number) {
                    $errors[] = 'Service number not found.';
                } else {
                    $uploadDir = __DIR__ . '/uploads/profile_photo/';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0775, true);
                    }
                    $ext = strtolower(pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION));
                    $allowed = ['jpg','jpeg','png','gif','webp'];
                    if (!in_array($ext, $allowed)) {
                        $errors[] = 'Invalid file type.';
                    } else {
                        $newName = $service_number . '.' . $ext;
                        $targetPath = $uploadDir . $newName;
                        if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $targetPath)) {
                            // Save relative path in DB
                            $relativePath = 'users/uploads/profile_photo/' . $newName;
                            $stmt = $pdo->prepare("UPDATE staff SET profile_photo = ? WHERE service_number = ?");
                            $stmt->execute([$relativePath, $service_number]);
                            $success = true;
                            // Reload data to get updated photo
                            $userData = $profileManager->getUserProfile();
                        } else {
                            $errors[] = 'Failed to upload photo.';
                        }
                    }
                }
            } else {
                $errors[] = 'Please select a photo to upload';
            }
        } elseif (isset($_POST['update_contact'])) {
            $contactData = [];
            if (!empty($_POST['contact_types'])) {
                foreach ($_POST['contact_types'] as $index => $type) {
                    if (!empty($_POST['contact_values'][$index])) {
                        $contactData[] = [
                            'type' => $type,
                            'value' => $_POST['contact_values'][$index],
                            'is_primary' => isset($_POST['contact_primary'][$index])
                        ];
                    }
                }
            }
            
            $result = $profileManager->updateContactInfo($contactData);
            if ($result['success']) {
                $success = true;
                // Reload data
                $contactInfo = $profileManager->getContactInfo();
            } else {
                $errors[] = $result['message'];
            }
        }
    }
    
} catch (Exception $e) {
    error_log("Personal info page error: " . $e->getMessage());
    $errors[] = "Error loading profile information";
}

include dirname(__DIR__) . '/shared/header.php';
include dirname(__DIR__) . '/shared/sidebar.php';
?>

<!-- Main Content -->
<div class="content-wrapper with-sidebar">
    <div class="container-fluid">
        <div class="main-content">
            <!-- Header -->
            <div class="row">
                <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h1 class="section-title">
                            <i class="fas fa-id-card"></i> Personal Information
                        </h1>
                        <a href="/Armis2/users/index.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Profile
                        </a>
                    </div>
                </div>
            </div>

            <!-- Alerts -->
            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="fas fa-check-circle"></i> Information updated successfully!
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="fas fa-exclamation-triangle"></i>
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                            <li><?= htmlspecialchars($error ?? 'Unknown error') ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Personal Information Form -->
            <div class="row">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-user-edit"></i> Basic Information</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" id="personalInfoForm">
                                <!-- Military Standard Order: Name, NRC, DOB, Gender, Marital, Spouse, Children, Religion, Blood, Height, Contact, Academic -->
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">First Name</label>
                                        <input type="text" class="form-control" value="<?= htmlspecialchars($userData->fname ?? '') ?>" readonly>
                                        <small class="text-muted">Contact admin to change name</small>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Last Name</label>
                                        <input type="text" class="form-control" value="<?= htmlspecialchars($userData->lname ?? '') ?>" readonly>
                                        <small class="text-muted">Contact admin to change name</small>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">NRC</label>
                                        <input type="text" class="form-control" name="nrc" value="<?= htmlspecialchars($userData->nrc ?? '') ?>">
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Date of Birth</label>
                                        <input type="date" class="form-control" name="dob" value="<?= htmlspecialchars($userData->dob ?? '') ?>">
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Gender</label>
                                        <select class="form-select" name="gender">
                                            <option value="">Select Gender</option>
                                            <option value="Male" <?= ($userData->gender ?? '') === 'Male' ? 'selected' : '' ?>>Male</option>
                                            <option value="Female" <?= ($userData->gender ?? '') === 'Female' ? 'selected' : '' ?>>Female</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Marital Status</label>
                                        <select class="form-select" name="marital" id="maritalStatus">
                                            <option value="">Select Status</option>
                                            <?php 
                                            $maritalStatuses = ['Single', 'Married', 'Divorced', 'Widowed'];
                                            foreach ($maritalStatuses as $status): ?>
                                                <option value="<?= $status ?>" <?= ($userData->marital ?? '') === $status ? 'selected' : '' ?>><?= $status ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Religion</label>
                                        <select class="form-select" name="religion">
                                            <option value="">Select Religion</option>
                                            <?php $religions = ['Christianity','Islam','Hinduism','Buddhism','Judaism','Traditional','Other'];
                                            foreach ($religions as $rel): ?>
                                                <option value="<?= $rel ?>" <?= ($userData->religion ?? '') === $rel ? 'selected' : '' ?>><?= $rel ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Blood Group</label>
                                        <select class="form-select" name="bloodGp">
                                            <option value="">Select Blood Group</option>
                                            <?php 
                                            $bloodGroups = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
                                            foreach ($bloodGroups as $bg): ?>
                                                <option value="<?= $bg ?>" <?= ($userData->bloodGp ?? '') === $bg ? 'selected' : '' ?>><?= $bg ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Height (cm)</label>
                                        <input type="number" class="form-control" name="height" value="<?= htmlspecialchars($userData->height ?? '') ?>" min="100" max="250">
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Email Address</label>
                                        <input type="email" class="form-control" name="email" value="<?= htmlspecialchars($userData->email ?? '') ?>">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Phone Number</label>
                                        <input type="tel" class="form-control" name="tel" value="<?= htmlspecialchars($userData->tel ?? '') ?>">
                                    </div>
                                </div>
                                <!-- Academic Information -->
                                <div class="row">
                                    <div class="col-12 mb-3">
                                        <label class="form-label">Education History</label>
                                        <div id="educationList"></div>
                                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="addEducation()">Add Education</button>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-12 mb-3">
                                        <label class="form-label">Languages</label>
                                        <div id="languageList"></div>
                                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="addLanguage()">Add Language</button>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Service Number</label>
                                        <input type="text" class="form-control" value="<?= htmlspecialchars($userData->svcNo ?? '') ?>" readonly>
                                        <small class="text-muted">Cannot be changed</small>
                                    </div>
                                </div>
                                <div class="text-end">
                                    <button type="submit" name="update_basic" class="btn btn-primary">
                                        <i class="fas fa-save"></i> Update Information
                                    </button>
                                </div>
                            </form>
<script>
// Show/hide spouse section based on marital status
document.addEventListener('DOMContentLoaded', function() {
    var marital = document.getElementById('maritalStatus');
    var spouseSection = document.getElementById('spouseSection');
    function toggleSpouseSection() {
        if (marital.value === 'Married') {
            spouseSection.style.display = '';
        } else {
            spouseSection.style.display = 'none';
            // Clear spouse fields if not married
            spouseSection.querySelectorAll('input').forEach(function(input) { input.value = ''; });
        }
    }
    toggleSpouseSection();
    marital.addEventListener('change', toggleSpouseSection);
});
// --- Dynamic Education Fields ---
function addEducation(prefill) {
    const div = document.createElement('div');
    div.className = 'row mb-2 align-items-end education-block';
    div.innerHTML = `
        <div class="col-md-3 mb-2"><input type="text" name="edu_institution[]" class="form-control" placeholder="Institution" value="${prefill?.institution||''}"></div>
        <div class="col-md-2 mb-2"><input type="text" name="edu_qualification[]" class="form-control" placeholder="Qualification" value="${prefill?.qualification||''}"></div>
        <div class="col-md-2 mb-2"><input type="text" name="edu_level[]" class="form-control" placeholder="Level" value="${prefill?.level||''}"></div>
        <div class="col-md-2 mb-2"><input type="text" name="edu_field[]" class="form-control" placeholder="Field of Study" value="${prefill?.field_of_study||''}"></div>
        <div class="col-md-1 mb-2"><input type="number" name="edu_year_started[]" class="form-control" placeholder="Start" value="${prefill?.year_started||''}"></div>
        <div class="col-md-1 mb-2"><input type="number" name="edu_year_completed[]" class="form-control" placeholder="End" value="${prefill?.year_completed||''}"></div>
        <div class="col-md-1 mb-2"><input type="text" name="edu_grade[]" class="form-control" placeholder="Grade" value="${prefill?.grade_obtained||''}"></div>
        <div class="col-auto mb-2"><div class="form-check"><input class="form-check-input" type="checkbox" name="edu_is_highest[]" value="1" ${prefill?.is_highest_qualification?'checked':''}> <label class="form-check-label">Highest</label></div></div>
        <div class="col-auto mb-2"><button type="button" class="btn btn-danger btn-sm btn-remove-block" title="Remove"><i class="fa fa-times"></i></button></div>
    `;
    document.getElementById('educationList').appendChild(div);
}

// --- Dynamic Language Fields ---
function addLanguage(prefill) {
    const div = document.createElement('div');
    div.className = 'row mb-2 align-items-end language-block';
    div.innerHTML = `
        <div class="col-md-3 mb-2"><input type="text" name="lang_name[]" class="form-control" placeholder="Language" value="${prefill?.language_name||''}"></div>
        <div class="col-md-2 mb-2"><select name="lang_proficiency[]" class="form-select"><option value="">Proficiency</option><option value="Basic" ${prefill?.proficiency_level==='Basic'?'selected':''}>Basic</option><option value="Intermediate" ${prefill?.proficiency_level==='Intermediate'?'selected':''}>Intermediate</option><option value="Advanced" ${prefill?.proficiency_level==='Advanced'?'selected':''}>Advanced</option><option value="Fluent" ${prefill?.proficiency_level==='Fluent'?'selected':''}>Fluent</option></select></div>
        <div class="col-auto mb-2"><div class="form-check"><input class="form-check-input" type="checkbox" name="lang_can_read[]" value="1" ${prefill?.can_read?'checked':''}> <label class="form-check-label">Read</label></div></div>
        <div class="col-auto mb-2"><div class="form-check"><input class="form-check-input" type="checkbox" name="lang_can_write[]" value="1" ${prefill?.can_write?'checked':''}> <label class="form-check-label">Write</label></div></div>
        <div class="col-auto mb-2"><div class="form-check"><input class="form-check-input" type="checkbox" name="lang_can_speak[]" value="1" ${prefill?.can_speak?'checked':''}> <label class="form-check-label">Speak</label></div></div>
        <div class="col-auto mb-2"><div class="form-check"><input class="form-check-input" type="checkbox" name="lang_can_understand[]" value="1" ${prefill?.can_understand?'checked':''}> <label class="form-check-label">Understand</label></div></div>
        <div class="col-auto mb-2"><button type="button" class="btn btn-danger btn-sm btn-remove-block" title="Remove"><i class="fa fa-times"></i></button></div>
    `;
    document.getElementById('languageList').appendChild(div);
}

// Remove handler for all dynamic sections
function addDynamicRemoveHandler(listId) {
    document.getElementById(listId).addEventListener('click', function(e) {
        if (e.target.closest('.btn-remove-block')) {
            e.target.closest('.row').remove();
        }
    });
}

document.addEventListener('DOMContentLoaded', function() {
    addDynamicRemoveHandler('educationList');
    addDynamicRemoveHandler('languageList');
    // Optionally, prefill from PHP if available (not shown here)
});
// Show children details fields if number of children > 0
document.addEventListener('DOMContentLoaded', function() {
    function renderChildrenFields(count) {
        const container = document.getElementById('childrenDetails');
        container.innerHTML = '';
        if (count > 0) {
            for (let i = 1; i <= count; i++) {
                container.innerHTML += `
                <div class="row mb-2">
                    <div class="col-md-4 mb-1">
                        <label class="form-label">Child #${i} Name</label>
                        <input type="text" class="form-control" name="child_name[]" placeholder="Full Name">
                    </div>
                    <div class="col-md-4 mb-1">
                        <label class="form-label">Child #${i} DOB</label>
                        <input type="date" class="form-control" name="child_dob[]">
                    </div>
                    <div class="col-md-4 mb-1">
                        <label class="form-label">Child #${i} Gender</label>
                        <select class="form-select" name="child_gender[]">
                            <option value="">Select</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                        </select>
                    </div>
                </div>`;
            }
        }
    }
    const childrenInput = document.getElementById('childrenCount');
    if (childrenInput) {
        renderChildrenFields(parseInt(childrenInput.value) || 0);
        childrenInput.addEventListener('input', function() {
            renderChildrenFields(parseInt(this.value) || 0);
        });
    }
});
</script>
                        </div>
                    </div>
                </div>

                <!-- Profile Summary -->
                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-info-circle"></i> Profile Summary</h5>
                        </div>
                        <div class="card-body">
                            <div class="text-center mb-3">
                                <img src="<?= $profileManager->getProfilePhotoURL() ?>" 
                                     alt="Profile Picture" 
                                     class="rounded-circle mb-2" 
                                     id="profileImage"
                                     style="width: 120px; height: 120px; object-fit: cover; cursor: pointer; border: 3px solid #dee2e6;"
                                     onclick="document.getElementById('photoInput').click()">
                                
                                <!-- Photo Upload Form -->
                                <form method="POST" enctype="multipart/form-data" id="photoForm" class="mt-2">
                                    <input type="file" id="photoInput" name="profile_photo" accept="image/*" style="display: none;" onchange="previewAndUpload(this)">
                                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="document.getElementById('photoInput').click()">
                                        <i class="fas fa-camera"></i> Change Photo
                                    </button>
                                    <button type="submit" name="upload_photo" id="uploadBtn" class="btn btn-sm btn-success d-none">
                                        <i class="fas fa-upload"></i> Upload
                                    </button>
                                </form>
                                
                                <h5 class="mt-2"><?= htmlspecialchars($userData->fullName ?? 'N/A') ?></h5>
                                <p class="text-muted"><?= htmlspecialchars($userData->displayRank ?? 'N/A') ?></p>
                            </div>

                            <table class="table table-sm">
                                <tr>
                                    <td><strong>Service No:</strong></td>
                                    <td><?= htmlspecialchars($userData->svcNo ?? 'N/A') ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Unit:</strong></td>
                                    <td><?= htmlspecialchars($userData->unitName ?? 'N/A') ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Age:</strong></td>
                                    <td><?= htmlspecialchars($userData->age ?? 'N/A') ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Service:</strong></td>
                                    <td><?= htmlspecialchars($userData->serviceYears ?? 'N/A') ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Status:</strong></td>
                                    <td>
                                        <span class="badge bg-<?= ($userData->svcStatus ?? '') === 'active' ? 'success' : 'secondary' ?>">
                                            <?= ucfirst($userData->svcStatus ?? 'Unknown') ?>
                                        </span>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Contact Information -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-address-book"></i> Contact Information</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <div id="contactList">
                                    <?php if (empty($contactInfo)): ?>
                                        <div class="row mb-3">
                                            <div class="col-md-3">
                                                <select class="form-select" name="contact_types[]">
                                                    <option value="">Select Type</option>
                                                    <option value="Mobile">Mobile</option>
                                                    <option value="Home">Home</option>
                                                    <option value="Work">Work</option>
                                                    <option value="Email">Email</option>
                                                    <option value="Emergency">Emergency</option>
                                                </select>
                                            </div>
                                            <div class="col-md-6">
                                                <input type="text" class="form-control" name="contact_values[]" placeholder="Contact Value">
                                            </div>
                                            <div class="col-md-2">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="contact_primary[]" value="1">
                                                    <label class="form-check-label">Primary</label>
                                                </div>
                                            </div>
                                            <div class="col-md-1">
                                                <button type="button" class="btn btn-sm btn-danger" onclick="removeContact(this)">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <?php foreach ($contactInfo as $contact): ?>
                                            <div class="row mb-3">
                                                <div class="col-md-3">
                                                    <select class="form-select" name="contact_types[]">
                                                        <option value="">Select Type</option>
                                                        <option value="Mobile" <?= $contact->contact_type === 'Mobile' ? 'selected' : '' ?>>Mobile</option>
                                                        <option value="Home" <?= $contact->contact_type === 'Home' ? 'selected' : '' ?>>Home</option>
                                                        <option value="Work" <?= $contact->contact_type === 'Work' ? 'selected' : '' ?>>Work</option>
                                                        <option value="Email" <?= $contact->contact_type === 'Email' ? 'selected' : '' ?>>Email</option>
                                                        <option value="Emergency" <?= $contact->contact_type === 'Emergency' ? 'selected' : '' ?>>Emergency</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-6">
                                                    <input type="text" class="form-control" name="contact_values[]" value="<?= htmlspecialchars($contact->contact_value ?? '') ?>" placeholder="Contact Value">
                                                </div>
                                                <div class="col-md-2">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" name="contact_primary[]" value="1" <?= $contact->is_primary ? 'checked' : '' ?>>
                                                        <label class="form-check-label">Primary</label>
                                                    </div>
                                                </div>
                                                <div class="col-md-1">
                                                    <button type="button" class="btn btn-sm btn-danger" onclick="removeContact(this)">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <button type="button" class="btn btn-sm btn-success" onclick="addContact()">
                                            <i class="fas fa-plus"></i> Add Contact
                                        </button>
                                    </div>
                                    <div class="col-md-6 text-end">
                                        <button type="submit" name="update_contact" class="btn btn-primary">
                                            <i class="fas fa-save"></i> Update Contact Info
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function previewAndUpload(input) {
    if (input.files && input.files[0]) {
        const file = input.files[0];
        // Check file size (5MB limit)
        if (file.size > 5 * 1024 * 1024) {
            alert('File size must be less than 5MB');
            input.value = '';
            return;
        }
        // Check file type
        if (!file.type.startsWith('image/')) {
            alert('Please select an image file');
            input.value = '';
            return;
        }
        // Show preview
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('profileImage').src = e.target.result;
            document.getElementById('uploadBtn').classList.remove('d-none');
        };
        reader.readAsDataURL(file);
    }
}

// Auto-submit form on photo selection (optional)
document.getElementById('photoForm').addEventListener('submit', function(e) {
    if (!document.getElementById('photoInput').files[0]) {
        e.preventDefault();
        alert('Please select a photo first');
    }
});

function addContact() {
    const contactList = document.getElementById('contactList');
    const newContact = document.createElement('div');
    newContact.className = 'row mb-3';
    newContact.innerHTML = `
        <div class="col-md-3">
            <select class="form-select" name="contact_types[]">
                <option value="">Select Type</option>
                <option value="Mobile">Mobile</option>
                <option value="Home">Home</option>
                <option value="Work">Work</option>
                <option value="Email">Email</option>
                <option value="Emergency">Emergency</option>
            </select>
        </div>
        <div class="col-md-6">
            <input type="text" class="form-control" name="contact_values[]" placeholder="Contact Value">
        </div>
        <div class="col-md-2">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="contact_primary[]" value="1">
                <label class="form-check-label">Primary</label>
            </div>
        </div>
        <div class="col-md-1">
            <button type="button" class="btn btn-sm btn-danger" onclick="removeContact(this)">
                <i class="fas fa-times"></i>
            </button>
        </div>
    `;
    contactList.appendChild(newContact);
}

function removeContact(button) {
    button.closest('.row').remove();
}

// --- Real-time validation for contact and editable fields ---
function showFieldError(input, msg) {
    let el = input.parentNode.querySelector('.invalid-feedback');
    if (!el) {
        el = document.createElement('div');
        el.className = 'invalid-feedback d-block';
        input.parentNode.appendChild(el);
    }
    el.textContent = msg;
}
function clearFieldError(input) {
    let el = input.parentNode.querySelector('.invalid-feedback');
    if (el) el.remove();
}
function validateEmail(email) {
    return /^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(email);
}
function validatePhone(phone) {
    return /^\+?\d{8,15}$/.test(phone);
}
function validateLength(val, max) {
    return val.length <= max;
}
function validateHeight(val) {
    const n = Number(val);
    return !isNaN(n) && n >= 100 && n <= 250;
}

document.addEventListener('DOMContentLoaded', function() {
    // Email field
    const emailInput = document.querySelector('input[name="email"]');
    if (emailInput) {
        emailInput.addEventListener('blur', function() {
            clearFieldError(emailInput);
            if (emailInput.value && !validateEmail(emailInput.value)) {
                showFieldError(emailInput, 'Invalid email address.');
            }
        });
    }
    // Phone field
    const phoneInput = document.querySelector('input[name="tel"]');
    if (phoneInput) {
        phoneInput.addEventListener('blur', function() {
            clearFieldError(phoneInput);
            if (phoneInput.value && !validatePhone(phoneInput.value)) {
                showFieldError(phoneInput, 'Invalid phone number.');
            }
        });
    }
    // Height field
    const heightInput = document.querySelector('input[name="height"]');
    if (heightInput) {
        heightInput.addEventListener('blur', function() {
            clearFieldError(heightInput);
            if (heightInput.value && !validateHeight(heightInput.value)) {
                showFieldError(heightInput, 'Height must be between 100 and 250 cm.');
            }
        });
    }
    // Religion field (max 50 chars)
    const religionInput = document.querySelector('input[name="religion"]');
    if (religionInput) {
        religionInput.addEventListener('blur', function() {
            clearFieldError(religionInput);
            if (religionInput.value && !validateLength(religionInput.value, 50)) {
                showFieldError(religionInput, 'Religion must be 50 characters or less.');
            }
        });
    }
    // Contact list dynamic fields
    document.getElementById('contactList').addEventListener('blur', function(e) {
        if (e.target && e.target.name === 'contact_values[]') {
            clearFieldError(e.target);
            // If type is Email, validate as email
            const typeSel = e.target.closest('.row').querySelector('select[name="contact_types[]"]');
            if (typeSel && typeSel.value === 'Email') {
                if (e.target.value && !validateEmail(e.target.value)) {
                    showFieldError(e.target, 'Invalid email address.');
                }
            } else if (typeSel && (typeSel.value === 'Mobile' || typeSel.value === 'Home' || typeSel.value === 'Work' || typeSel.value === 'Emergency')) {
                if (e.target.value && !validatePhone(e.target.value)) {
                    showFieldError(e.target, 'Invalid phone number.');
                }
            }
        }
    }, true);
});
</script>

<?php include dirname(__DIR__) . '/shared/footer.php'; ?>
