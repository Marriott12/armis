<?php
/**
 * Enhanced Personal Information Page
 * Military-grade profile management with real-time validation and auto-save
 */

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
    ['title' => 'My Profile', 'url' => '/users/index.php', 'icon' => 'user', 'page' => 'profile'],
    ['title' => 'Personal Info', 'url' => '/users/enhanced_personal.php', 'icon' => 'id-card', 'page' => 'personal'],
    ['title' => 'Service Record', 'url' => '/users/service.php', 'icon' => 'medal', 'page' => 'service'],
    ['title' => 'Training History', 'url' => '/users/training.php', 'icon' => 'graduation-cap', 'page' => 'training'],
    ['title' => 'Family Members', 'url' => '/users/family.php', 'icon' => 'users', 'page' => 'family'],
    ['title' => 'Account Settings', 'url' => '/users/settings.php', 'icon' => 'cogs', 'page' => 'settings']
];

// Load enhanced profile manager
require_once __DIR__ . '/classes/EnhancedProfileManager.php';
require_once __DIR__ . '/classes/ProfileValidator.php';

$success = false;
$errors = [];
$warnings = [];

try {
    // Initialize enhanced components
    $profileManager = new EnhancedProfileManager($_SESSION['user_id']);
    $validator = new ProfileValidator();
    
    // Get comprehensive profile data
    $userData = $profileManager->getUserProfile();
    $contactInfo = $profileManager->getContactInfo();
    $familyMembers = $profileManager->getFamilyMembers();
    $rankProgression = $profileManager->getRankProgression();
    $securityClearance = $profileManager->getSecurityClearance();
    $medicalReadiness = $profileManager->getMedicalReadiness();
    
    // Handle AJAX validation requests
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_validate'])) {
        header('Content-Type: application/json');
        
        $fieldName = $_POST['field_name'] ?? '';
        $fieldValue = $_POST['field_value'] ?? '';
        $context = $_POST['context'] ?? [];
        
        $result = $validator->validateField($fieldName, $fieldValue, $context);
        echo json_encode($result);
        exit();
    }
    
    // Handle auto-save requests
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['auto_save'])) {
        header('Content-Type: application/json');
        
        $data = $_POST['profile_data'] ?? [];
        $sanitizedData = $validator->sanitizeData($data);
        
        $result = $profileManager->updateProfile($sanitizedData, false);
        echo json_encode($result);
        exit();
    }
    
    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
        // Generate CSRF token
        $csrfToken = bin2hex(random_bytes(32));
        $_SESSION['csrf_token'] = $csrfToken;
        $_SESSION['csrf_expires'] = time() + 1800; // 30 minutes
        
        // Validate CSRF token
        if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
            $errors[] = 'Invalid security token. Please try again.';
        } elseif (time() > ($_SESSION['csrf_expires'] ?? 0)) {
            $errors[] = 'Security token expired. Please try again.';
        } else {
            // Sanitize and validate data
            $formData = $validator->sanitizeData($_POST);
            $validationResult = $validator->validateProfile($formData);
            
            if ($validationResult['valid']) {
                $updateResult = $profileManager->updateProfile($formData);
                
                if ($updateResult['success']) {
                    $success = true;
                    // Reload data to show updates
                    $userData = $profileManager->getUserProfile();
                } else {
                    $errors[] = $updateResult['message'];
                }
            } else {
                $errors = array_merge($errors, array_values($validationResult['errors']));
                $warnings = array_merge($warnings, array_values($validationResult['warnings']));
            }
        }
    }
    
    // Generate CSRF token for the form
    if (!isset($_SESSION['csrf_token']) || time() > ($_SESSION['csrf_expires'] ?? 0)) {
        $csrfToken = bin2hex(random_bytes(32));
        $_SESSION['csrf_token'] = $csrfToken;
        $_SESSION['csrf_expires'] = time() + 1800; // 30 minutes
    }
    
} catch (Exception $e) {
    error_log("Enhanced personal info page error: " . $e->getMessage());
    $errors[] = "Error loading profile information. Please try again.";
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
                            <i class="fas fa-id-card"></i> Enhanced Personal Information
                        </h1>
                        <div class="d-flex align-items-center">
                            <!-- Profile Completion Indicator -->
                            <div class="me-3">
                                <small class="text-muted">Profile Completion</small>
                                <div class="progress" style="width: 150px; height: 6px;">
                                    <div class="progress-bar bg-<?= ($userData->profile_completion ?? 0) >= 80 ? 'success' : (($userData->profile_completion ?? 0) >= 60 ? 'warning' : 'danger') ?>" 
                                         style="width: <?= $userData->profile_completion ?? 0 ?>%"></div>
                                </div>
                                <small class="text-muted"><?= $userData->profile_completion ?? 0 ?>% Complete</small>
                            </div>
                            <a href="/users/index.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> Back to Profile
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Military Alerts -->
            <?php if ($securityClearance['renewal_required'] ?? false): ?>
                <div class="alert alert-warning">
                    <i class="fas fa-shield-alt"></i>
                    <strong>Security Clearance Alert:</strong> Your clearance expires in <?= $securityClearance['days_until_expiry'] ?> days.
                    <a href="#" class="alert-link">Request renewal</a>
                </div>
            <?php endif; ?>

            <?php if ($medicalReadiness['exam_required'] ?? false): ?>
                <div class="alert alert-info">
                    <i class="fas fa-heartbeat"></i>
                    <strong>Medical Exam Due:</strong> Medical examination required within <?= $medicalReadiness['days_until_due'] ?> days.
                    <a href="#" class="alert-link">Schedule appointment</a>
                </div>
            <?php endif; ?>

            <!-- Status Messages -->
            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="fas fa-check-circle"></i> Profile updated successfully!
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="fas fa-exclamation-triangle"></i>
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                            <li><?= htmlspecialchars($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (!empty($warnings)): ?>
                <div class="alert alert-warning alert-dismissible fade show">
                    <i class="fas fa-exclamation-triangle"></i>
                    <ul class="mb-0">
                        <?php foreach ($warnings as $warning): ?>
                            <li><?= htmlspecialchars($warning) ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Enhanced Profile Form -->
            <form method="POST" id="enhancedProfileForm" novalidate>
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="update_profile" value="1">
                
                <div class="row">
                    <!-- Main Profile Information -->
                    <div class="col-lg-8">
                        <!-- Personal Information Card -->
                        <div class="card mb-4">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0"><i class="fas fa-user"></i> Personal Information</h5>
                                <div class="auto-save-indicator d-none">
                                    <i class="fas fa-save text-success"></i>
                                    <small class="text-muted">Auto-saved</small>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <!-- Name Fields -->
                                    <div class="col-md-2 mb-3">
                                        <label for="prefix" class="form-label">Prefix</label>
                                        <input type="text" class="form-control" id="prefix" name="prefix" 
                                               value="<?= htmlspecialchars($userData->prefix ?? '') ?>"
                                               data-validate="true" placeholder="Mr., Mrs., Dr.">
                                        <div class="invalid-feedback"></div>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <label for="first_name" class="form-label">First Name <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="first_name" name="first_name" 
                                               value="<?= htmlspecialchars($userData->first_name ?? '') ?>"
                                               data-validate="true" required>
                                        <div class="invalid-feedback"></div>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <label for="middle_name" class="form-label">Middle Name</label>
                                        <input type="text" class="form-control" id="middle_name" name="middle_name" 
                                               value="<?= htmlspecialchars($userData->middle_name ?? '') ?>"
                                               data-validate="true">
                                        <div class="invalid-feedback"></div>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <label for="last_name" class="form-label">Last Name <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="last_name" name="last_name" 
                                               value="<?= htmlspecialchars($userData->last_name ?? '') ?>"
                                               data-validate="true" required>
                                        <div class="invalid-feedback"></div>
                                    </div>
                                    <div class="col-md-1 mb-3">
                                        <label for="suffix" class="form-label">Suffix</label>
                                        <input type="text" class="form-control" id="suffix" name="suffix" 
                                               value="<?= htmlspecialchars($userData->suffix ?? '') ?>"
                                               data-validate="true" placeholder="Jr., Sr.">
                                        <div class="invalid-feedback"></div>
                                    </div>
                                </div>

                                <div class="row">
                                    <!-- Core Identity Fields -->
                                    <div class="col-md-4 mb-3">
                                        <label for="national_id" class="form-label">National ID</label>
                                        <input type="text" class="form-control" id="national_id" name="national_id" 
                                               value="<?= htmlspecialchars($userData->national_id ?? '') ?>"
                                               data-validate="true" placeholder="123456/78/9">
                                        <div class="invalid-feedback"></div>
                                        <small class="form-text text-muted">Format: 123456/78/9</small>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label for="date_of_birth" class="form-label">Date of Birth</label>
                                        <input type="date" class="form-control" id="date_of_birth" name="date_of_birth" 
                                               value="<?= htmlspecialchars($userData->date_of_birth ?? '') ?>"
                                               data-validate="true">
                                        <div class="invalid-feedback"></div>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label for="gender" class="form-label">Gender</label>
                                        <select class="form-select" id="gender" name="gender" data-validate="true">
                                            <option value="">Select Gender</option>
                                            <option value="Male" <?= ($userData->gender ?? '') === 'Male' ? 'selected' : '' ?>>Male</option>
                                            <option value="Female" <?= ($userData->gender ?? '') === 'Female' ? 'selected' : '' ?>>Female</option>
                                            <option value="Other" <?= ($userData->gender ?? '') === 'Other' ? 'selected' : '' ?>>Other</option>
                                        </select>
                                        <div class="invalid-feedback"></div>
                                    </div>
                                </div>

                                <div class="row">
                                    <!-- Additional Personal Info -->
                                    <div class="col-md-4 mb-3">
                                        <label for="marital_status" class="form-label">Marital Status</label>
                                        <select class="form-select" id="marital_status" name="marital_status" data-validate="true">
                                            <option value="">Select Status</option>
                                            <?php 
                                            $maritalStatuses = ['Single', 'Married', 'Divorced', 'Widowed', 'Separated'];
                                            foreach ($maritalStatuses as $status): ?>
                                                <option value="<?= $status ?>" <?= ($userData->marital_status ?? '') === $status ? 'selected' : '' ?>><?= $status ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <div class="invalid-feedback"></div>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label for="nationality" class="form-label">Nationality</label>
                                        <input type="text" class="form-control" id="nationality" name="nationality" 
                                               value="<?= htmlspecialchars($userData->nationality ?? 'Zambian') ?>"
                                               data-validate="true">
                                        <div class="invalid-feedback"></div>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label for="religion" class="form-label">Religion</label>
                                        <select class="form-select" id="religion" name="religion" data-validate="true">
                                            <option value="">Select Religion</option>
                                            <?php $religions = ['Christianity','Islam','Hinduism','Buddhism','Judaism','Traditional','Other'];
                                            foreach ($religions as $rel): ?>
                                                <option value="<?= $rel ?>" <?= ($userData->religion ?? '') === $rel ? 'selected' : '' ?>><?= $rel ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <div class="invalid-feedback"></div>
                                    </div>
                                </div>

                                <div class="row">
                                    <!-- Contact Information -->
                                    <div class="col-md-6 mb-3">
                                        <label for="email" class="form-label">Email Address</label>
                                        <input type="email" class="form-control" id="email" name="email" 
                                               value="<?= htmlspecialchars($userData->email ?? '') ?>"
                                               data-validate="true">
                                        <div class="invalid-feedback"></div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="phone" class="form-label">Phone Number</label>
                                        <input type="tel" class="form-control" id="phone" name="phone" 
                                               value="<?= htmlspecialchars($userData->phone ?? '') ?>"
                                               data-validate="true" placeholder="+260 XXX XXX XXX">
                                        <div class="invalid-feedback"></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Emergency Contact Card -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-phone"></i> Emergency Contact</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label for="emergency_contact_name" class="form-label">Contact Name</label>
                                        <input type="text" class="form-control" id="emergency_contact_name" name="emergency_contact_name" 
                                               value="<?= htmlspecialchars($userData->emergency_contact_name ?? '') ?>"
                                               data-validate="true">
                                        <div class="invalid-feedback"></div>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label for="emergency_contact_phone" class="form-label">Contact Phone</label>
                                        <input type="tel" class="form-control" id="emergency_contact_phone" name="emergency_contact_phone" 
                                               value="<?= htmlspecialchars($userData->emergency_contact_phone ?? '') ?>"
                                               data-validate="true">
                                        <div class="invalid-feedback"></div>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label for="emergency_contact_relationship" class="form-label">Relationship</label>
                                        <select class="form-select" id="emergency_contact_relationship" name="emergency_contact_relationship" data-validate="true">
                                            <option value="">Select Relationship</option>
                                            <?php 
                                            $relationships = ['Parent', 'Spouse', 'Child', 'Sibling', 'Grandparent', 'Aunt', 'Uncle', 'Cousin', 'Friend', 'Other'];
                                            foreach ($relationships as $rel): ?>
                                                <option value="<?= $rel ?>" <?= ($userData->emergency_contact_relationship ?? '') === $rel ? 'selected' : '' ?>><?= $rel ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <div class="invalid-feedback"></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Medical Information Card -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-heartbeat"></i> Medical Information</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label for="blood_group" class="form-label">Blood Group</label>
                                        <select class="form-select" id="blood_group" name="blood_group" data-validate="true">
                                            <option value="">Select Blood Group</option>
                                            <?php 
                                            $bloodGroups = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
                                            foreach ($bloodGroups as $bg): ?>
                                                <option value="<?= $bg ?>" <?= ($userData->blood_group ?? '') === $bg ? 'selected' : '' ?>><?= $bg ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <div class="invalid-feedback"></div>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label for="height" class="form-label">Height (cm)</label>
                                        <input type="number" class="form-control" id="height" name="height" 
                                               value="<?= htmlspecialchars($userData->height ?? '') ?>" 
                                               min="100" max="250" data-validate="true">
                                        <div class="invalid-feedback"></div>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label for="medical_fitness_status" class="form-label">Fitness Status</label>
                                        <select class="form-select" id="medical_fitness_status" name="medical_fitness_status" data-validate="true">
                                            <option value="">Select Status</option>
                                            <option value="Fit" <?= ($userData->medical_fitness_status ?? '') === 'Fit' ? 'selected' : '' ?>>Fit</option>
                                            <option value="Limited Duties" <?= ($userData->medical_fitness_status ?? '') === 'Limited Duties' ? 'selected' : '' ?>>Limited Duties</option>
                                            <option value="Medical Board" <?= ($userData->medical_fitness_status ?? '') === 'Medical Board' ? 'selected' : '' ?>>Medical Board</option>
                                            <option value="Unfit" <?= ($userData->medical_fitness_status ?? '') === 'Unfit' ? 'selected' : '' ?>>Unfit</option>
                                        </select>
                                        <div class="invalid-feedback"></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Additional Notes -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-sticky-note"></i> Additional Notes</h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label for="notes" class="form-label">Personal Notes</label>
                                    <textarea class="form-control" id="notes" name="notes" rows="4" 
                                              data-validate="true" placeholder="Any additional information or notes..."><?= htmlspecialchars($userData->notes ?? '') ?></textarea>
                                    <div class="invalid-feedback"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Sidebar Information -->
                    <div class="col-lg-4">
                        <!-- Military Status Card -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-medal"></i> Military Status</h5>
                            </div>
                            <div class="card-body">
                                <table class="table table-sm">
                                    <tr>
                                        <td><strong>Service No:</strong></td>
                                        <td><?= htmlspecialchars($userData->service_number ?? 'Not Assigned') ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Rank:</strong></td>
                                        <td><?= htmlspecialchars($userData->display_rank ?? 'N/A') ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Unit:</strong></td>
                                        <td><?= htmlspecialchars($userData->unit_name ?? 'Not Assigned') ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Corps:</strong></td>
                                        <td><?= htmlspecialchars($userData->corps ?? 'N/A') ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Service Years:</strong></td>
                                        <td><?= htmlspecialchars($userData->service_years ?? 'N/A') ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Status:</strong></td>
                                        <td>
                                            <span class="badge bg-<?= ($userData->service_status ?? '') === 'active' ? 'success' : 'secondary' ?>">
                                                <?= ucfirst($userData->service_status ?? 'Unknown') ?>
                                            </span>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </div>

                        <!-- Security Clearance Card -->
                        <?php if (!empty($securityClearance['level'])): ?>
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-shield-alt"></i> Security Clearance</h5>
                            </div>
                            <div class="card-body">
                                <p><strong>Level:</strong> <?= htmlspecialchars($securityClearance['level']) ?></p>
                                <p><strong>Status:</strong> 
                                    <span class="badge bg-<?= $securityClearance['renewal_required'] ? 'warning' : 'success' ?>">
                                        <?= htmlspecialchars($securityClearance['status']) ?>
                                    </span>
                                </p>
                                <?php if ($securityClearance['expiry_date']): ?>
                                <p><strong>Expires:</strong> <?= date('M j, Y', strtotime($securityClearance['expiry_date'])) ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Medical Readiness Card -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-heartbeat"></i> Medical Readiness</h5>
                            </div>
                            <div class="card-body">
                                <p><strong>Status:</strong> 
                                    <span class="badge bg-<?= $medicalReadiness['exam_required'] ? 'warning' : 'success' ?>">
                                        <?= htmlspecialchars($medicalReadiness['status']) ?>
                                    </span>
                                </p>
                                <?php if ($medicalReadiness['next_due']): ?>
                                <p><strong>Next Exam:</strong> <?= date('M j, Y', strtotime($medicalReadiness['next_due'])) ?></p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Quick Actions -->
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-bolt"></i> Quick Actions</h5>
                            </div>
                            <div class="card-body">
                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save"></i> Save Changes
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary" onclick="window.location.reload()">
                                        <i class="fas fa-undo"></i> Reset Form
                                    </button>
                                    <a href="/users/index.php" class="btn btn-outline-info">
                                        <i class="fas fa-user"></i> View Profile
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Enhanced JavaScript for Real-time Validation and Auto-save -->
<script>
class EnhancedProfileManager {
    constructor() {
        this.autoSaveDelay = 2000; // 2 seconds
        this.autoSaveTimer = null;
        this.validationCache = new Map();
        this.init();
    }
    
    init() {
        this.setupRealTimeValidation();
        this.setupAutoSave();
        this.setupFormEnhancements();
    }
    
    setupRealTimeValidation() {
        // Real-time validation on input/change
        document.addEventListener('input', (e) => {
            if (e.target.matches('[data-validate]')) {
                this.debounceValidation(e.target);
            }
        });
        
        document.addEventListener('change', (e) => {
            if (e.target.matches('[data-validate]')) {
                this.validateField(e.target);
            }
        });
        
        // Clear validation on focus
        document.addEventListener('focus', (e) => {
            if (e.target.matches('[data-validate]')) {
                this.clearFieldValidation(e.target);
            }
        }, true);
    }
    
    setupAutoSave() {
        // Auto-save on form changes
        document.addEventListener('input', (e) => {
            if (e.target.form && e.target.form.id === 'enhancedProfileForm') {
                this.scheduleAutoSave();
            }
        });
        
        document.addEventListener('change', (e) => {
            if (e.target.form && e.target.form.id === 'enhancedProfileForm') {
                this.scheduleAutoSave();
            }
        });
    }
    
    setupFormEnhancements() {
        // Enhanced form submission
        const form = document.getElementById('enhancedProfileForm');
        if (form) {
            form.addEventListener('submit', (e) => {
                e.preventDefault();
                this.submitForm();
            });
        }
        
        // Progress indicator updates
        this.updateProgressIndicator();
    }
    
    debounceValidation(field) {
        clearTimeout(field.validationTimer);
        field.validationTimer = setTimeout(() => {
            this.validateField(field);
        }, 500);
    }
    
    async validateField(field) {
        const fieldName = field.name;
        const fieldValue = field.value;
        
        // Skip validation for empty optional fields
        if (!field.required && !fieldValue.trim()) {
            this.clearFieldValidation(field);
            return;
        }
        
        this.showValidationLoading(field);
        
        try {
            const formData = new FormData();
            formData.append('ajax_validate', '1');
            formData.append('field_name', fieldName);
            formData.append('field_value', fieldValue);
            formData.append('context', JSON.stringify(this.getFormContext()));
            
            const response = await fetch(window.location.href, {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            this.displayValidationResult(field, result);
            
        } catch (error) {
            console.error('Validation error:', error);
            this.showValidationError(field, 'Validation failed');
        }
    }
    
    scheduleAutoSave() {
        clearTimeout(this.autoSaveTimer);
        this.autoSaveTimer = setTimeout(() => {
            this.performAutoSave();
        }, this.autoSaveDelay);
    }
    
    async performAutoSave() {
        const form = document.getElementById('enhancedProfileForm');
        if (!form) return;
        
        const formData = new FormData(form);
        formData.append('auto_save', '1');
        
        // Convert FormData to regular object for profile_data
        const profileData = {};
        for (let [key, value] of formData.entries()) {
            if (key !== 'auto_save' && key !== 'csrf_token' && key !== 'update_profile') {
                profileData[key] = value;
            }
        }
        
        const autoSaveData = new FormData();
        autoSaveData.append('auto_save', '1');
        autoSaveData.append('profile_data', JSON.stringify(profileData));
        
        try {
            const response = await fetch(window.location.href, {
                method: 'POST',
                body: autoSaveData
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.showAutoSaveSuccess();
                this.updateProgressIndicator(result.completion);
            }
            
        } catch (error) {
            console.error('Auto-save error:', error);
        }
    }
    
    async submitForm() {
        const form = document.getElementById('enhancedProfileForm');
        const submitBtn = form.querySelector('button[type="submit"]');
        
        // Show loading state
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
        submitBtn.disabled = true;
        
        try {
            // Validate all fields first
            const isValid = await this.validateAllFields();
            
            if (isValid) {
                form.submit();
            } else {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
                this.showValidationError(null, 'Please correct the highlighted errors before submitting.');
            }
            
        } catch (error) {
            console.error('Form submission error:', error);
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        }
    }
    
    async validateAllFields() {
        const fields = document.querySelectorAll('[data-validate]');
        let allValid = true;
        
        for (const field of fields) {
            await this.validateField(field);
            if (field.classList.contains('is-invalid')) {
                allValid = false;
            }
        }
        
        return allValid;
    }
    
    displayValidationResult(field, result) {
        this.clearFieldValidation(field);
        
        if (!result.valid) {
            this.showValidationError(field, result.error);
        } else if (result.warning) {
            this.showValidationWarning(field, result.warning);
        } else {
            this.showValidationSuccess(field);
        }
    }
    
    showValidationLoading(field) {
        field.classList.remove('is-valid', 'is-invalid');
        field.classList.add('is-validating');
    }
    
    showValidationSuccess(field) {
        field.classList.remove('is-invalid', 'is-validating');
        field.classList.add('is-valid');
        
        const feedback = field.parentNode.querySelector('.invalid-feedback');
        if (feedback) feedback.textContent = '';
    }
    
    showValidationError(field, message) {
        if (field) {
            field.classList.remove('is-valid', 'is-validating');
            field.classList.add('is-invalid');
            
            const feedback = field.parentNode.querySelector('.invalid-feedback');
            if (feedback) feedback.textContent = message;
        } else {
            // Show general error message
            this.showAlert('danger', message);
        }
    }
    
    showValidationWarning(field, message) {
        field.classList.remove('is-invalid', 'is-validating');
        field.classList.add('is-valid');
        
        // Show warning badge or tooltip
        this.showAlert('warning', message);
    }
    
    clearFieldValidation(field) {
        field.classList.remove('is-valid', 'is-invalid', 'is-validating');
        
        const feedback = field.parentNode.querySelector('.invalid-feedback');
        if (feedback) feedback.textContent = '';
    }
    
    showAutoSaveSuccess() {
        const indicator = document.querySelector('.auto-save-indicator');
        if (indicator) {
            indicator.classList.remove('d-none');
            setTimeout(() => {
                indicator.classList.add('d-none');
            }, 3000);
        }
    }
    
    updateProgressIndicator(completion = null) {
        if (completion !== null) {
            const progressBar = document.querySelector('.progress-bar');
            const progressText = progressBar.parentNode.parentNode.querySelector('small:last-child');
            
            progressBar.style.width = completion + '%';
            progressText.textContent = completion + '% Complete';
            
            // Update color based on completion
            progressBar.className = 'progress-bar bg-' + (completion >= 80 ? 'success' : (completion >= 60 ? 'warning' : 'danger'));
        }
    }
    
    getFormContext() {
        const form = document.getElementById('enhancedProfileForm');
        const context = {};
        
        if (form) {
            const formData = new FormData(form);
            for (let [key, value] of formData.entries()) {
                if (key !== 'csrf_token' && key !== 'update_profile') {
                    context[key] = value;
                }
            }
        }
        
        return context;
    }
    
    showAlert(type, message) {
        // Create and show bootstrap alert
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
        alertDiv.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        const container = document.querySelector('.main-content');
        container.insertBefore(alertDiv, container.firstChild);
        
        // Auto-dismiss after 5 seconds
        setTimeout(() => {
            if (alertDiv.parentNode) {
                alertDiv.remove();
            }
        }, 5000);
    }
}

// Enhanced validation CSS
const validationStyle = document.createElement('style');
validationStyle.textContent = `
    .is-validating {
        border-color: #0d6efd !important;
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 12' width='12' height='12' fill='none' stroke='%230d6efd'%3e%3ccircle cx='6' cy='6' r='4.5'/%3e%3cpath d='M6 3v3l1.5 1.5'/%3e%3c/svg%3e") !important;
        background-repeat: no-repeat !important;
        background-position: right calc(0.375em + 0.1875rem) center !important;
        background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem) !important;
    }
    
    .auto-save-indicator {
        transition: opacity 0.3s ease;
    }
    
    .progress {
        transition: all 0.3s ease;
    }
`;
document.head.appendChild(validationStyle);

// Initialize enhanced profile manager
document.addEventListener('DOMContentLoaded', () => {
    window.enhancedProfileManager = new EnhancedProfileManager();
});
</script>

<?php include dirname(__DIR__) . '/shared/footer.php'; ?>