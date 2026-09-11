<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Set to 1 for debugging
ini_set('log_errors', 1);

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . dirname($_SERVER['PHP_SELF']) . '/../login.php');
    exit();
}

$pageTitle = "Personal Information";
$moduleName = "User Profile";
$moduleIcon = "id-card";
$currentPage = "personal";

// Load shared navigation (replaces duplicate navigation array)
require_once dirname(__DIR__) . '/shared/user_navigation.php';
$sidebarLinks = $userNavigationItems;

// Initialize variables
$success = false;
$errors = [];
$userData = null;
$contactInfo = [];
$educationRecords = [];
$languageRecords = [];

// Load user profile data
try {
    require_once __DIR__ . '/profile_manager.php';
    require_once dirname(__DIR__) . '/shared/database_connection.php';
    
    $profileManager = new UserProfileManager($_SESSION['user_id']);
    $userData = $profileManager->getUserProfile();
    
    if (!$userData) {
        $errors[] = "Unable to load profile information. Please contact system administrator.";
        error_log("Profile not found for user ID: " . $_SESSION['user_id']);
    } else {
        $contactInfo = $profileManager->getContactInfo();
        $educationRecords = $profileManager->getEducationRecords();
        $languageRecords = $profileManager->getLanguageRecords();
    }
    
} catch (Exception $e) {
    $errors[] = "Error loading profile information: " . $e->getMessage();
    error_log("Profile loading error for user " . $_SESSION['user_id'] . ": " . $e->getMessage());
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $userData) {
    try {
        if (isset($_POST['update_basic'])) {
            $successMessages = [];

            // Update personal information
            $personalData = [
                'nrc' => $_POST['nrc'] ?? '',
                'DOB' => $_POST['dob'] ?? '', // Map to correct field name in DB
                'gender' => $_POST['gender'] ?? '',
                'religion' => $_POST['religion'] ?? '',
                'marital_status' => $_POST['marital_status'] ?? '',
                'address' => $_POST['address'] ?? '',
                'tel' => $_POST['tel'] ?? '',
                'email' => $_POST['email'] ?? '',
                'height' => $_POST['height'] ?? '',
                'combatSize' => $_POST['combatSize'] ?? '',
                'bsize' => $_POST['bsize'] ?? '',
                'ssize' => $_POST['ssize'] ?? '',
                'hdress' => $_POST['hdress'] ?? '',
                'blood_group' => $_POST['bloodGp'] ?? '',
                'province' => $_POST['province'] ?? '',
                'district' => $_POST['district'] ?? ''
            ];
            
            $result = $profileManager->updatePersonalInfo($personalData);

            if ($result['success']) {
                $successMessages[] = $result['message'];
                $userData = $profileManager->getUserProfile();
            } else {
                $errors[] = $result['message'];
            }

            // FIX: education/language record processing removed from
            // here — that data now lives exclusively on training.php
            // (see the note there), which owns saving it.

            if (empty($errors) && !empty($successMessages)) {
                $success = implode(' ', $successMessages);
            }
        }
        
        if (isset($_POST['update_contact'])) {
            $contactData = [];
            $types = $_POST['contact_types'] ?? [];
            $values = $_POST['contact_values'] ?? [];
            $names = $_POST['contact_names'] ?? [];
            $relationships = $_POST['contact_relationships'] ?? [];
            $primaries = $_POST['contact_primary'] ?? [];
            $verifieds = $_POST['contact_verified'] ?? [];
            $notes = $_POST['contact_notes'] ?? [];
            $ids = $_POST['contact_ids'] ?? [];
            $count = count($types);
            for ($i = 0; $i < $count; $i++) {
                if (empty($types[$i]) || empty($values[$i])) continue;
                $contactData[] = [
                    'id' => $ids[$i] ?? null,
                    'contact_type' => $types[$i],
                    'contact_value' => $values[$i],
                    'contact_name' => $names[$i] ?? '',
                    'relationship' => $relationships[$i] ?? '',
                    'is_primary' => !empty($primaries[$i]) ? 1 : 0,
                    'is_verified' => !empty($verifieds[$i]) ? 1 : 0,
                    'notes' => $notes[$i] ?? ''
                ];
            }
            $result = $profileManager->updateContactInfo($contactData);
            if ($result['success']) {
                $success = $result['message'];
                $contactInfo = $profileManager->getContactInfo();
            } else {
                $errors[] = $result['message'];
            }
        }
        
    } catch (Exception $e) {
        $errors[] = "Error updating profile: " . $e->getMessage();
        error_log("Profile update error for user " . $_SESSION['user_id'] . ": " . $e->getMessage());
    }
}

/*} catch (Exception $e) {
    error_log("Personal info page error: " . $e->getMessage());
    $errors[] = "Error loading profile information";
}*/

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
            <div class="row g-4">
                <div class="col-lg-8">
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
                                        <input type="text" class="form-control" name="nrc" value="<?= htmlspecialchars($userData->NRC ?? '') ?>">
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Date of Birth</label>
                                        <input type="date" class="form-control" name="dob" value="<?= htmlspecialchars($userData->DOB ?? '') ?>">
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
                                        <select class="form-select" name="marital_status" id="maritalStatus">
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
                                            <?php $religions = ['Christian','Islam','Hinduism','Buddhism','Judaism','Traditional','Other'];
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
                                        <select class="form-select" name="height">
                                            <option value="">Select Height</option>
                                            <?php for ($h = 140; $h <= 210; $h++): ?>
                                                <option value="<?= $h ?>" <?= ($userData->height ?? '') == $h ? 'selected' : '' ?>><?= $h ?> cm</option>
                                            <?php endfor; ?>
                                        </select>
                                    </div>
                                </div>
                                
                                <!-- Military Sizing Information -->
                                <div class="row">
                                    <div class="col-12 mb-2">
                                        <h6 class="text-muted"><i class="fas fa-ruler"></i> Military Sizing Information</h6>
                                        <p class="small text-muted mb-2">These sizes are used for uniform and equipment allocation</p>
                                        <hr class="my-2">
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-3 mb-3">
                                        <label class="form-label">Combat Size</label>
                                        <select class="form-select" name="combatSize">
                                            <option value="">Select Size</option>
                                            <?php 
                                            $combatSizes = [
                                                'XS' => 'Extra Small (XS)',
                                                'S' => 'Small (S)', 
                                                'M' => 'Medium (M)',
                                                'L' => 'Large (L)',
                                                'XL' => 'Extra Large (XL)',
                                                'XXL' => '2X Large (XXL)',
                                                '3XL' => '3X Large (3XL)',
                                                '4XL' => '4X Large (4XL)'
                                            ];
                                            foreach ($combatSizes as $value => $label): ?>
                                                <option value="<?= $value ?>" <?= ($userData->combatSize ?? '') === $value ? 'selected' : '' ?>><?= $label ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <label class="form-label">Boot Size</label>
                                        <select class="form-select" name="bsize">
                                            <option value="">Select Size</option>
                                            <?php 
                                            for ($i = 4; $i <= 15; $i++): ?>
                                                <option value="<?= $i ?>" <?= ($userData->bsize ?? '') == $i ? 'selected' : '' ?>><?= $i ?></option>
                                            <?php endfor; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <label class="form-label">Staff Shoe Size</label>
                                        <select class="form-select" name="ssize">
                                            <option value="">Select Size</option>
                                            <?php 
                                            for ($i = 4; $i <= 15; $i++): ?>
                                                <option value="<?= $i ?>" <?= ($userData->ssize ?? '') == $i ? 'selected' : '' ?>><?= $i ?></option>
                                            <?php endfor; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <label class="form-label">Head Dress Size</label>
                                        <select class="form-select" name="hdress">
                                            <option value="">Select Size</option>
                                            <?php 
                                            for ($i = 52; $i <= 65; $i++): ?>
                                                <option value="<?= $i ?>" <?= ($userData->hdress ?? '') == $i ? 'selected' : '' ?>><?= $i ?></option>
                                            <?php endfor; ?>
                                        </select>
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
                                <!-- FIX: education/language editing (with its own
                                     ~350 lines of duplicated JS) and a broken, non-functional
                                     "children" mini-form used to live inline here, duplicating
                                     training.php and family.php respectively. Removed in favor
                                     of one clear owner per kind of record. -->
                                <div class="alert alert-info d-flex align-items-start gap-2">
                                    <i class="fa fa-info-circle mt-1"></i>
                                    <div>
                                        Manage your <a href="training.php">education and language records</a> on the Training History page,
                                        and your <a href="family.php">family members</a> (including children) on the Family Members page.
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
        if (!marital || !spouseSection) {
            return;
        }
        if (marital.value === 'Married') {
            spouseSection.style.display = '';
        } else {
            spouseSection.style.display = 'none';
            spouseSection.querySelectorAll('input').forEach(function(input) { input.value = ''; });
        }
    }
    if (marital) {
        toggleSpouseSection();
        marital.addEventListener('change', toggleSpouseSection);
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
                                     style="width: 120px; height: 120px; object-fit: cover; border: 3px solid #dee2e6;">
                                
                                <!-- FIX: this used to have its own photo
                                     upload form here — a real <form> that
                                     submitted, but with no server-side
                                     handler anywhere behind it (silently
                                     did nothing). Photo upload is now
                                     handled in one real place: Account
                                     Settings. -->
                                <div>
                                    <a href="settings.php#profile-photo" class="btn btn-sm btn-outline-primary mt-1">
                                        <i class="fas fa-camera"></i> Change Photo
                                    </a>
                                </div>
                                
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
            <div id="contact-section" class="row mt-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-address-book"></i> Contact Information</h5>
                            <p class="mb-0 small text-muted">Add emergency contacts, family members, and other important contacts</p>
                        </div>
                        <div class="card-body">
                            <form method="POST" onsubmit="return validateContactFields()">
                                <div class="row mb-2">
                                    <div class="col-md-2"><strong>Type</strong></div>
                                    <div class="col-md-3"><strong>Contact Value</strong></div>
                                    <div class="col-md-2"><strong>Contact Name</strong></div>
                                    <div class="col-md-2"><strong>Relationship</strong></div>
                                    <div class="col-md-2"><strong>Primary</strong></div>
                                    <div class="col-md-1"><strong>Action</strong></div>
                                </div>
                                <div id="contactList">
                                    <?php if (empty($contactInfo)): ?>
                                        <div class="row mb-3">
                                            <div class="col-md-2">
                                                <input type="hidden" name="contact_ids[0]" value="">
                                                <input type="hidden" name="contact_verified[0]" value="0">
                                                <input type="hidden" name="contact_notes[0]" value="">
                                                <select class="form-select" name="contact_types[]">
                                                    <option value="">Select Type</option>
                                                    <option value="Mobile">Mobile</option>
                                                    <option value="Home">Home</option>
                                                    <option value="Work">Work</option>
                                                    <option value="Email">Email</option>
                                                    <option value="Emergency">Emergency</option>
                                                </select>
                                            </div>
                                            <div class="col-md-3">
                                                <input type="text" class="form-control" name="contact_values[]" placeholder="Contact Value">
                                            </div>
                                            <div class="col-md-2">
                                                <input type="text" class="form-control" name="contact_names[]" placeholder="Contact Name">
                                            </div>
                                            <div class="col-md-2">
                                                <input type="text" class="form-control" name="contact_relationships[]" placeholder="Relationship">
                                            </div>
                                            <div class="col-md-2">
                                                <input type="hidden" name="contact_primary[0]" value="0">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="contact_primary[0]" value="1">
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
                                        <?php foreach ($contactInfo as $index => $contact): ?>
                                            <div class="row mb-3">
                                                <div class="col-md-2">
                                                    <input type="hidden" name="contact_ids[<?= $index ?>]" value="<?= htmlspecialchars($contact->id ?? '') ?>">
                                                    <input type="hidden" name="contact_verified[<?= $index ?>]" value="<?= $contact->is_verified ? 1 : 0 ?>">
                                                    <input type="hidden" name="contact_notes[<?= $index ?>]" value="<?= htmlspecialchars($contact->notes ?? '') ?>">
                                                    <select class="form-select" name="contact_types[]">
                                                        <option value="">Select Type</option>
                                                        <option value="Mobile" <?= $contact->contact_type === 'Mobile' ? 'selected' : '' ?>>Mobile</option>
                                                        <option value="Home" <?= $contact->contact_type === 'Home' ? 'selected' : '' ?>>Home</option>
                                                        <option value="Work" <?= $contact->contact_type === 'Work' ? 'selected' : '' ?>>Work</option>
                                                        <option value="Email" <?= $contact->contact_type === 'Email' ? 'selected' : '' ?>>Email</option>
                                                        <option value="Emergency" <?= $contact->contact_type === 'Emergency' ? 'selected' : '' ?>>Emergency</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-3">
                                                    <input type="text" class="form-control" name="contact_values[]" value="<?= htmlspecialchars($contact->contact_value ?? '') ?>" placeholder="Contact Value">
                                                </div>
                                                <div class="col-md-2">
                                                    <input type="text" class="form-control" name="contact_names[]" value="<?= htmlspecialchars($contact->contact_name ?? '') ?>" placeholder="Contact Name">
                                                </div>
                                                <div class="col-md-2">
                                                    <input type="text" class="form-control" name="contact_relationships[]" value="<?= htmlspecialchars($contact->relationship ?? '') ?>" placeholder="Relationship">
                                                </div>
                                                <div class="col-md-2">
                                                    <input type="hidden" name="contact_primary[<?= $index ?>]" value="0">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" name="contact_primary[<?= $index ?>]" value="1" <?= $contact->is_primary ? 'checked' : '' ?>>
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

            <!-- Help & Recommendations Section -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card border-info">
                        <div class="card-header bg-info text-white">
                            <h6 class="mb-0"><i class="fas fa-lightbulb"></i> Recommendations & Tips</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6 class="text-primary"><i class="fas fa-ruler"></i> Military Sizing Guidelines</h6>
                                    <ul class="small mb-3">
                                        <li><strong>Combat Size:</strong> Used for combat uniforms and protective gear</li>
                                        <li><strong>Boot Size:</strong> Military boots and protective footwear</li>
                                        <li><strong>Staff Shoe Size:</strong> Dress shoes and formal footwear</li>
                                        <li><strong>Head Dress Size:</strong> Berets, caps, and ceremonial headwear (measured in cm)</li>
                                    </ul>
                                    
                                    <h6 class="text-success"><i class="fas fa-phone"></i> Contact Information Best Practices</h6>
                                    <ul class="small mb-3">
                                        <li>Always include at least one <strong>Emergency Contact</strong> with name and relationship</li>
                                        <li>Keep your <strong>Primary</strong> contact information up to date</li>
                                        <li>Include multiple contact methods (mobile, email, home)</li>
                                        <li>For emergency contacts, specify relationship (spouse, parent, sibling, etc.)</li>
                                    </ul>
                                </div>
                                <div class="col-md-6">
                                    <h6 class="text-warning"><i class="fas fa-exclamation-triangle"></i> Important Notes</h6>
                                    <ul class="small mb-3">
                                        <li>Size information is used for uniform and equipment allocation</li>
                                        <li>Accurate sizing helps prevent delays in equipment issue</li>
                                        <li>Contact information is used for personnel administration and emergencies</li>
                                        <li>Regular updates ensure you receive important communications</li>
                                    </ul>
                                    
                                    <h6 class="text-info"><i class="fas fa-shield-alt"></i> Privacy & Security</h6>
                                    <ul class="small mb-0">
                                        <li>Your personal information is protected and used only for official purposes</li>
                                        <li>Emergency contacts will only be contacted in genuine emergencies</li>
                                        <li>Size information is used solely for equipment allocation</li>
                                        <li>All data is stored securely and accessed only by authorized personnel</li>
                                    </ul>
                                </div>
                            </div>
                            
                            <div class="row mt-3">
                                <div class="col-12">
                                    <div class="alert alert-light border border-primary">
                                        <h6 class="text-primary mb-2"><i class="fas fa-cogs"></i> Future Enhancements</h6>
                                        <p class="small mb-2">We are continuously improving the system. Upcoming features include:</p>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <ul class="small mb-0">
                                                    <li>Automated size recommendations based on measurements</li>
                                                    <li>Digital uniform fitting appointments</li>
                                                    <li>Equipment request tracking</li>
                                                    <li>Contact verification notifications</li>
                                                </ul>
                                            </div>
                                            <div class="col-md-6">
                                                <ul class="small mb-0">
                                                    <li>Emergency contact SMS verification</li>
                                                    <li>Medical information integration</li>
                                                    <li>Personnel photo management</li>
                                                    <li>Mobile app for profile updates</li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function addContact() {
    const contactList = document.getElementById('contactList');
    const index = contactList.querySelectorAll('.row').length;
    const newContact = document.createElement('div');
    newContact.className = 'row mb-3';
    newContact.innerHTML = `
        <input type="hidden" name="contact_ids[${index}]" value="">
        <input type="hidden" name="contact_verified[${index}]" value="0">
        <input type="hidden" name="contact_notes[${index}]" value="">
        <div class="col-md-2">
            <select class="form-select" name="contact_types[]">
                <option value="">Select Type</option>
                <option value="Mobile">Mobile</option>
                <option value="Home">Home</option>
                <option value="Work">Work</option>
                <option value="Email">Email</option>
                <option value="Emergency">Emergency</option>
            </select>
        </div>
        <div class="col-md-3">
            <input type="text" class="form-control" name="contact_values[]" placeholder="Contact Value">
        </div>
        <div class="col-md-2">
            <input type="text" class="form-control" name="contact_names[]" placeholder="Contact Name">
        </div>
        <div class="col-md-2">
            <input type="text" class="form-control" name="contact_relationships[]" placeholder="Relationship">
        </div>
        <div class="col-md-2">
            <input type="hidden" name="contact_primary[${index}]" value="0">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="contact_primary[${index}]" value="1">
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
    reindexContactRows();
}

function reindexContactRows() {
    const rows = document.querySelectorAll('#contactList > .row');
    rows.forEach((row, index) => {
        const idInput = row.querySelector('[name^="contact_ids["]');
        const verifiedInput = row.querySelector('[name^="contact_verified["]');
        const notesInput = row.querySelector('[name^="contact_notes["]');
        const primaryInputs = row.querySelectorAll('[name^="contact_primary["]');

        if (idInput) {
            idInput.name = `contact_ids[${index}]`;
        }
        if (verifiedInput) {
            verifiedInput.name = `contact_verified[${index}]`;
        }
        if (notesInput) {
            notesInput.name = `contact_notes[${index}]`;
        }
        if (primaryInputs.length) {
            primaryInputs.forEach((input) => {
                input.name = `contact_primary[${index}]`;
            });
        }
    });
}

function removeContact(button) {
    button.closest('.row').remove();
    reindexContactRows();
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

// Enhanced validation for new fields
function validateSizingFields() {
    const sizingFields = ['combatSize', 'bsize', 'ssize', 'hdress'];
    let isValid = true;
    
    sizingFields.forEach(field => {
        const element = document.querySelector(`[name="${field}"]`);
        if (element && element.value) {
            element.classList.add('is-valid');
            element.classList.remove('is-invalid');
        }
    });
    
    return isValid;
}

// Contact validation with enhanced feedback
function validateContactFields() {
    const contactRows = document.querySelectorAll('#contactList .row');
    let isValid = true;
    
    contactRows.forEach(row => {
        const typeSelect = row.querySelector('[name="contact_types[]"]');
        const valueInput = row.querySelector('[name="contact_values[]"]');
        const nameInput = row.querySelector('[name="contact_names[]"]');
        
        if (typeSelect && typeSelect.value && valueInput && valueInput.value.trim()) {
            // Email validation
            if (typeSelect.value === 'Email') {
                if (validateEmail(valueInput.value)) {
                    valueInput.classList.add('is-valid');
                    valueInput.classList.remove('is-invalid');
                    clearFieldError(valueInput);
                } else {
                    valueInput.classList.add('is-invalid');
                    valueInput.classList.remove('is-valid');
                    showFieldError(valueInput, 'Please enter a valid email address');
                    isValid = false;
                }
            }
            
            // Phone validation
            if (['Mobile', 'Home', 'Work', 'Emergency'].includes(typeSelect.value)) {
                if (validatePhone(valueInput.value)) {
                    valueInput.classList.add('is-valid');
                    valueInput.classList.remove('is-invalid');
                    clearFieldError(valueInput);
                } else {
                    valueInput.classList.add('is-invalid');
                    valueInput.classList.remove('is-valid');
                    showFieldError(valueInput, 'Please enter a valid phone number');
                    isValid = false;
                }
            }
            
            // Emergency contact validation
            if (typeSelect.value === 'Emergency' && nameInput && !nameInput.value.trim()) {
                nameInput.classList.add('is-invalid');
                showFieldError(nameInput, 'Emergency contact name is required');
                isValid = false;
            }
        }
    });
    
    return isValid;
}

// Enhanced removeContact with confirmation
function removeContact(button) {
    if (confirm('Are you sure you want to remove this contact?')) {
        button.closest('.row').remove();
    }
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
