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

$pageTitle = "Enhanced Military Profile";
$moduleName = "Enhanced User Profile";
$moduleIcon = "user-shield";
$currentPage = "enhanced_profile";

// Load enhanced classes
require_once __DIR__ . '/classes/EnhancedProfileManager.php';
require_once __DIR__ . '/classes/ProfileValidator.php';
require_once __DIR__ . '/classes/MilitaryDataManager.php';

$successMessage = '';
$errorMessage = '';
$errors = [];

// Initialize managers
try {
    $profileManager = new EnhancedProfileManager($_SESSION['user_id']);
    $validator = new ProfileValidator();
    $militaryManager = new MilitaryDataManager();
} catch (Exception $e) {
    $errorMessage = "System initialization error: " . $e->getMessage();
    error_log("Enhanced profile initialization error: " . $e->getMessage());
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($profileManager)) {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_profile':
                // Validate CSRF token
                if (!$profileManager->validateCSRFToken($_POST['csrf_token'] ?? '')) {
                    $errorMessage = "Security token validation failed. Please try again.";
                    break;
                }
                
                // Validate and update profile
                $validatedData = $validator->validateProfile($_POST);
                
                if ($validatedData !== false) {
                    $result = $profileManager->updateUserProfile($validatedData);
                    if ($result['success']) {
                        $successMessage = $result['message'];
                    } else {
                        $errorMessage = $result['message'] ?? 'Update failed';
                        $errors = $result['errors'] ?? [];
                    }
                } else {
                    $errorMessage = "Please correct the validation errors below.";
                    $errors = $validator->getErrors();
                }
                break;
        }
    }
}

// Load profile data
try {
    $userData = $profileManager->getUserProfile();
    $profileCompletion = $profileManager->getProfileCompletion();
    $securityClearances = $profileManager->getSecurityClearances();
    $serviceRecords = $profileManager->getServiceRecords(10);
    $medicalRecords = $profileManager->getMedicalRecords();
    $trainingCompliance = $profileManager->getTrainingCompliance();
    $familyReadiness = $profileManager->getFamilyReadiness();
    
    // Load military reference data
    $ranks = $militaryManager->getRanks();
    $units = $militaryManager->getUnits();
    $corps = $militaryManager->getCorps();
    
    // Generate CSRF token
    $csrfToken = $profileManager->generateCSRFToken();
    
} catch (Exception $e) {
    $errorMessage = "Error loading profile data: " . $e->getMessage();
    error_log("Enhanced profile data loading error: " . $e->getMessage());
}

$sidebarLinks = [
    ['title' => 'Enhanced Profile', 'url' => '/Armis2/users/enhanced_profile.php', 'icon' => 'user-shield', 'page' => 'enhanced_profile'],
    ['title' => 'Personal Info', 'url' => '/Armis2/users/personal.php', 'icon' => 'id-card', 'page' => 'personal'],
    ['title' => 'Service Record', 'url' => '/Armis2/users/service.php', 'icon' => 'medal', 'page' => 'service'],
    ['title' => 'Training History', 'url' => '/Armis2/users/training.php', 'icon' => 'graduation-cap', 'page' => 'training'],
    ['title' => 'Family Members', 'url' => '/Armis2/users/family.php', 'icon' => 'users', 'page' => 'family'],
    ['title' => 'Account Settings', 'url' => '/Armis2/users/settings.php', 'icon' => 'cogs', 'page' => 'settings']
];

include dirname(__DIR__) . '/shared/header.php';
include dirname(__DIR__) . '/shared/sidebar.php';
?>

<style>
.military-badge {
    display: inline-block;
    padding: 0.25rem 0.5rem;
    font-size: 0.875rem;
    font-weight: 500;
    border-radius: 0.25rem;
    border: 1px solid;
}

.clearance-secret { background-color: #dc3545; color: white; border-color: #dc3545; }
.clearance-confidential { background-color: #fd7e14; color: white; border-color: #fd7e14; }
.clearance-top-secret { background-color: #6f42c1; color: white; border-color: #6f42c1; }
.clearance-none { background-color: #6c757d; color: white; border-color: #6c757d; }

.status-fit { background-color: #198754; color: white; border-color: #198754; }
.status-limited { background-color: #ffc107; color: #000; border-color: #ffc107; }
.status-unfit { background-color: #dc3545; color: white; border-color: #dc3545; }
.status-pending { background-color: #0dcaf0; color: #000; border-color: #0dcaf0; }

.deployment-available { background-color: #198754; color: white; border-color: #198754; }
.deployment-deployed { background-color: #dc3545; color: white; border-color: #dc3545; }
.deployment-training { background-color: #0d6efd; color: white; border-color: #0d6efd; }
.deployment-leave { background-color: #ffc107; color: #000; border-color: #ffc107; }
.deployment-medical { background-color: #6f42c1; color: white; border-color: #6f42c1; }

.progress-section {
    margin-bottom: 1rem;
}

.progress-section .progress {
    height: 0.5rem;
}

.alert-military {
    border-left: 4px solid #28a745;
    background-color: #f8f9fa;
}

.enhanced-form-group {
    margin-bottom: 1.5rem;
}

.enhanced-form-control {
    border-radius: 0.375rem;
    border: 1px solid #ced4da;
    padding: 0.75rem;
}

.enhanced-form-control:focus {
    border-color: #198754;
    box-shadow: 0 0 0 0.25rem rgba(25, 135, 84, 0.25);
}

.military-card {
    border: 1px solid #dee2e6;
    border-radius: 0.5rem;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
    background: white;
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
}

.military-card h5 {
    color: #198754;
    border-bottom: 2px solid #198754;
    padding-bottom: 0.5rem;
    margin-bottom: 1rem;
}
</style>

<!-- Main Content -->
<div class="content-wrapper with-sidebar">
    <div class="container-fluid">
        <div class="main-content">
            <!-- Header Section -->
            <div class="row">
                <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h1 class="section-title">
                            <i class="fas fa-user-shield"></i> Enhanced Military Profile
                        </h1>
                        <div class="d-flex align-items-center">
                            <?php if (isset($profileCompletion)): ?>
                            <span class="badge bg-<?php echo $profileCompletion['deployment_ready'] ? 'success' : 'warning'; ?> me-2">
                                <?php echo round($profileCompletion['overall_percentage']); ?>% Complete
                            </span>
                            <?php if ($profileCompletion['deployment_ready']): ?>
                            <span class="badge bg-success">
                                <i class="fas fa-check-circle"></i> Deployment Ready
                            </span>
                            <?php else: ?>
                            <span class="badge bg-warning text-dark">
                                <i class="fas fa-exclamation-triangle"></i> Profile Incomplete
                            </span>
                            <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Alert Messages -->
            <?php if ($successMessage): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($successMessage); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <?php if ($errorMessage): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($errorMessage); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <!-- Profile Overview Cards -->
            <div class="row mb-4">
                <!-- Basic Info Card -->
                <div class="col-md-4">
                    <div class="military-card">
                        <h5><i class="fas fa-user"></i> Basic Information</h5>
                        <?php if (isset($userData)): ?>
                        <div class="text-center mb-3">
                            <?php if ($userData->profile_picture): ?>
                            <img src="<?php echo htmlspecialchars($userData->profile_picture); ?>" 
                                 class="rounded-circle" width="80" height="80" alt="Profile Picture">
                            <?php else: ?>
                            <div class="bg-secondary rounded-circle d-inline-flex align-items-center justify-content-center" 
                                 style="width: 80px; height: 80px;">
                                <i class="fas fa-user fa-2x text-white"></i>
                            </div>
                            <?php endif; ?>
                        </div>
                        <h6 class="text-center"><?php echo htmlspecialchars($userData->fullName); ?></h6>
                        <table class="table table-sm table-borderless">
                            <tr>
                                <td><strong>Service #:</strong></td>
                                <td><?php echo htmlspecialchars($userData->service_number ?? 'Not Set'); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Rank:</strong></td>
                                <td><?php echo htmlspecialchars($userData->displayRank); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Unit:</strong></td>
                                <td><?php echo htmlspecialchars($userData->unitName ?? 'Not Assigned'); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Corps:</strong></td>
                                <td><?php echo htmlspecialchars($userData->corpsName ?? 'Not Set'); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Service:</strong></td>
                                <td><?php echo htmlspecialchars($userData->serviceYears); ?></td>
                            </tr>
                        </table>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Status Cards -->
                <div class="col-md-4">
                    <div class="military-card">
                        <h5><i class="fas fa-shield-alt"></i> Security & Medical</h5>
                        <?php if (isset($userData)): ?>
                        <div class="mb-3">
                            <label class="form-label">Security Clearance:</label>
                            <div>
                                <span class="military-badge clearance-<?php echo strtolower(str_replace(' ', '-', $userData->security_clearance_level ?? 'none')); ?>">
                                    <?php echo htmlspecialchars($userData->security_clearance_level ?? 'None'); ?>
                                </span>
                                <?php if ($userData->clearance_expiry_date): ?>
                                <small class="text-muted d-block">
                                    Expires: <?php echo date('M j, Y', strtotime($userData->clearance_expiry_date)); ?>
                                </small>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Medical Status:</label>
                            <div>
                                <span class="military-badge status-<?php echo strtolower($userData->medical_status ?? 'pending'); ?>">
                                    <?php echo htmlspecialchars($userData->medical_status ?? 'Pending'); ?>
                                </span>
                                <?php if ($userData->medical_expiry_date): ?>
                                <small class="text-muted d-block">
                                    Valid until: <?php echo date('M j, Y', strtotime($userData->medical_expiry_date)); ?>
                                </small>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div>
                            <label class="form-label">Deployment Status:</label>
                            <div>
                                <span class="military-badge deployment-<?php echo strtolower($userData->deployment_status ?? 'available'); ?>">
                                    <?php echo htmlspecialchars($userData->deployment_status ?? 'Available'); ?>
                                </span>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Profile Completion -->
                <div class="col-md-4">
                    <div class="military-card">
                        <h5><i class="fas fa-chart-pie"></i> Profile Completion</h5>
                        <?php if (isset($profileCompletion) && !empty($profileCompletion['sections'])): ?>
                        <?php foreach ($profileCompletion['sections'] as $section): ?>
                        <div class="progress-section">
                            <div class="d-flex justify-content-between">
                                <span class="small fw-bold"><?php echo ucwords(str_replace('_', ' ', $section->section)); ?></span>
                                <span class="small"><?php echo round($section->completion_percentage); ?>%</span>
                            </div>
                            <div class="progress">
                                <div class="progress-bar bg-<?php echo $section->completion_percentage >= 80 ? 'success' : 'warning'; ?>" 
                                     role="progressbar" 
                                     style="width: <?php echo $section->completion_percentage; ?>%">
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <hr>
                        <div class="text-center">
                            <strong>Overall: <?php echo round($profileCompletion['overall_percentage']); ?>%</strong>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Enhanced Profile Form -->
            <div class="row">
                <div class="col-12">
                    <div class="military-card">
                        <h5><i class="fas fa-edit"></i> Update Profile Information</h5>
                        
                        <form method="POST" class="needs-validation" novalidate>
                            <input type="hidden" name="action" value="update_profile">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken ?? ''); ?>">
                            
                            <div class="row">
                                <!-- Personal Information -->
                                <div class="col-md-6">
                                    <h6 class="text-success"><i class="fas fa-user"></i> Personal Information</h6>
                                    
                                    <div class="enhanced-form-group">
                                        <label for="first_name" class="form-label">First Name *</label>
                                        <input type="text" class="form-control enhanced-form-control <?php echo isset($errors['first_name']) ? 'is-invalid' : ''; ?>" 
                                               id="first_name" name="first_name" 
                                               value="<?php echo htmlspecialchars($userData->first_name ?? ''); ?>" required>
                                        <?php if (isset($errors['first_name'])): ?>
                                        <div class="invalid-feedback"><?php echo htmlspecialchars($errors['first_name']); ?></div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="enhanced-form-group">
                                        <label for="last_name" class="form-label">Last Name *</label>
                                        <input type="text" class="form-control enhanced-form-control <?php echo isset($errors['last_name']) ? 'is-invalid' : ''; ?>" 
                                               id="last_name" name="last_name" 
                                               value="<?php echo htmlspecialchars($userData->last_name ?? ''); ?>" required>
                                        <?php if (isset($errors['last_name'])): ?>
                                        <div class="invalid-feedback"><?php echo htmlspecialchars($errors['last_name']); ?></div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="enhanced-form-group">
                                                <label for="DOB" class="form-label">Date of Birth</label>
                                                <input type="date" class="form-control enhanced-form-control <?php echo isset($errors['DOB']) ? 'is-invalid' : ''; ?>" 
                                                       id="DOB" name="DOB" 
                                                       value="<?php echo htmlspecialchars($userData->DOB ?? ''); ?>">
                                                <?php if (isset($errors['DOB'])): ?>
                                                <div class="invalid-feedback"><?php echo htmlspecialchars($errors['DOB']); ?></div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="enhanced-form-group">
                                                <label for="gender" class="form-label">Gender</label>
                                                <select class="form-select enhanced-form-control" id="gender" name="gender">
                                                    <option value="">Select Gender</option>
                                                    <option value="M" <?php echo ($userData->gender ?? '') === 'M' ? 'selected' : ''; ?>>Male</option>
                                                    <option value="F" <?php echo ($userData->gender ?? '') === 'F' ? 'selected' : ''; ?>>Female</option>
                                                    <option value="Other" <?php echo ($userData->gender ?? '') === 'Other' ? 'selected' : ''; ?>>Other</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="enhanced-form-group">
                                        <label for="email" class="form-label">Email Address</label>
                                        <input type="email" class="form-control enhanced-form-control <?php echo isset($errors['email']) ? 'is-invalid' : ''; ?>" 
                                               id="email" name="email" 
                                               value="<?php echo htmlspecialchars($userData->email ?? ''); ?>">
                                        <?php if (isset($errors['email'])): ?>
                                        <div class="invalid-feedback"><?php echo htmlspecialchars($errors['email']); ?></div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="enhanced-form-group">
                                        <label for="tel" class="form-label">Phone Number</label>
                                        <input type="tel" class="form-control enhanced-form-control <?php echo isset($errors['tel']) ? 'is-invalid' : ''; ?>" 
                                               id="tel" name="tel" 
                                               value="<?php echo htmlspecialchars($userData->tel ?? ''); ?>">
                                        <?php if (isset($errors['tel'])): ?>
                                        <div class="invalid-feedback"><?php echo htmlspecialchars($errors['tel']); ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <!-- Military Information -->
                                <div class="col-md-6">
                                    <h6 class="text-success"><i class="fas fa-medal"></i> Military Information</h6>
                                    
                                    <div class="enhanced-form-group">
                                        <label for="rank_id" class="form-label">Rank *</label>
                                        <select class="form-select enhanced-form-control <?php echo isset($errors['rank_id']) ? 'is-invalid' : ''; ?>" 
                                                id="rank_id" name="rank_id" required>
                                            <option value="">Select Rank</option>
                                            <?php if (isset($ranks)): ?>
                                            <?php foreach ($ranks as $rank): ?>
                                            <option value="<?php echo $rank->id; ?>" 
                                                    <?php echo ($userData->rank_id ?? '') == $rank->id ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($rank->abbreviation . ' - ' . $rank->name); ?>
                                            </option>
                                            <?php endforeach; ?>
                                            <?php endif; ?>
                                        </select>
                                        <?php if (isset($errors['rank_id'])): ?>
                                        <div class="invalid-feedback"><?php echo htmlspecialchars($errors['rank_id']); ?></div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="enhanced-form-group">
                                        <label for="unit_id" class="form-label">Unit *</label>
                                        <select class="form-select enhanced-form-control <?php echo isset($errors['unit_id']) ? 'is-invalid' : ''; ?>" 
                                                id="unit_id" name="unit_id" required>
                                            <option value="">Select Unit</option>
                                            <?php if (isset($units)): ?>
                                            <?php foreach ($units as $unit): ?>
                                            <option value="<?php echo $unit->id; ?>" 
                                                    <?php echo ($userData->unit_id ?? '') == $unit->id ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($unit->code . ' - ' . $unit->name); ?>
                                            </option>
                                            <?php endforeach; ?>
                                            <?php endif; ?>
                                        </select>
                                        <?php if (isset($errors['unit_id'])): ?>
                                        <div class="invalid-feedback"><?php echo htmlspecialchars($errors['unit_id']); ?></div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="enhanced-form-group">
                                        <label for="corps" class="form-label">Corps</label>
                                        <select class="form-select enhanced-form-control" id="corps" name="corps">
                                            <option value="">Select Corps</option>
                                            <?php if (isset($corps)): ?>
                                            <?php foreach ($corps as $corp): ?>
                                            <option value="<?php echo $corp->abbreviation; ?>" 
                                                    <?php echo ($userData->corps ?? '') === $corp->abbreviation ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($corp->abbreviation . ' - ' . $corp->name); ?>
                                            </option>
                                            <?php endforeach; ?>
                                            <?php endif; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="enhanced-form-group">
                                                <label for="NRC" class="form-label">NRC/ID Number</label>
                                                <input type="text" class="form-control enhanced-form-control <?php echo isset($errors['NRC']) ? 'is-invalid' : ''; ?>" 
                                                       id="NRC" name="NRC" 
                                                       value="<?php echo htmlspecialchars($userData->NRC ?? ''); ?>">
                                                <?php if (isset($errors['NRC'])): ?>
                                                <div class="invalid-feedback"><?php echo htmlspecialchars($errors['NRC']); ?></div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="enhanced-form-group">
                                                <label for="bloodGp" class="form-label">Blood Group</label>
                                                <select class="form-select enhanced-form-control" id="bloodGp" name="bloodGp">
                                                    <option value="">Select Blood Group</option>
                                                    <?php 
                                                    $bloodGroups = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
                                                    foreach ($bloodGroups as $bg): 
                                                    ?>
                                                    <option value="<?php echo $bg; ?>" 
                                                            <?php echo ($userData->bloodGp ?? '') === $bg ? 'selected' : ''; ?>>
                                                        <?php echo $bg; ?>
                                                    </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="enhanced-form-group">
                                        <label for="marital" class="form-label">Marital Status</label>
                                        <select class="form-select enhanced-form-control" id="marital" name="marital">
                                            <option value="">Select Status</option>
                                            <option value="Single" <?php echo ($userData->marital ?? '') === 'Single' ? 'selected' : ''; ?>>Single</option>
                                            <option value="Married" <?php echo ($userData->marital ?? '') === 'Married' ? 'selected' : ''; ?>>Married</option>
                                            <option value="Divorced" <?php echo ($userData->marital ?? '') === 'Divorced' ? 'selected' : ''; ?>>Divorced</option>
                                            <option value="Widowed" <?php echo ($userData->marital ?? '') === 'Widowed' ? 'selected' : ''; ?>>Widowed</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Emergency Contact Section -->
                            <hr>
                            <h6 class="text-success"><i class="fas fa-phone"></i> Emergency Contact</h6>
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="enhanced-form-group">
                                        <label for="emergency_contact_name" class="form-label">Contact Name</label>
                                        <input type="text" class="form-control enhanced-form-control" 
                                               id="emergency_contact_name" name="emergency_contact_name" 
                                               value="<?php echo htmlspecialchars($userData->emergency_contact_name ?? ''); ?>">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="enhanced-form-group">
                                        <label for="emergency_contact_phone" class="form-label">Contact Phone</label>
                                        <input type="tel" class="form-control enhanced-form-control" 
                                               id="emergency_contact_phone" name="emergency_contact_phone" 
                                               value="<?php echo htmlspecialchars($userData->emergency_contact_phone ?? ''); ?>">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="enhanced-form-group">
                                        <label for="emergency_contact_relationship" class="form-label">Relationship</label>
                                        <input type="text" class="form-control enhanced-form-control" 
                                               id="emergency_contact_relationship" name="emergency_contact_relationship" 
                                               value="<?php echo htmlspecialchars($userData->emergency_contact_relationship ?? ''); ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="text-end">
                                <button type="submit" class="btn btn-success btn-lg">
                                    <i class="fas fa-save"></i> Update Profile
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
// Form validation
(function() {
    'use strict';
    window.addEventListener('load', function() {
        var forms = document.getElementsByClassName('needs-validation');
        var validation = Array.prototype.filter.call(forms, function(form) {
            form.addEventListener('submit', function(event) {
                if (form.checkValidity() === false) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add('was-validated');
            }, false);
        });
    }, false);
})();

// Auto-save functionality (simplified)
let autoSaveTimeout;
document.querySelectorAll('.enhanced-form-control').forEach(function(input) {
    input.addEventListener('input', function() {
        clearTimeout(autoSaveTimeout);
        autoSaveTimeout = setTimeout(function() {
            // Auto-save indicator (visual feedback only)
            const indicator = document.createElement('small');
            indicator.className = 'text-muted';
            indicator.textContent = 'Changes detected...';
            input.parentNode.appendChild(indicator);
            setTimeout(() => indicator.remove(), 2000);
        }, 1000);
    });
});
</script>

<?php include dirname(__DIR__) . '/shared/footer.php'; ?>