<?php
/**
 * Enhanced Personal Information Page
 * Military-Grade User Profile with Real-time Validation
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

$pageTitle = "Enhanced Personal Information";
$moduleName = "User Profile";
$moduleIcon = "id-card";
$currentPage = "personal_enhanced";

$sidebarLinks = [
    ['title' => 'My Profile', 'url' => '/users/index.php', 'icon' => 'user', 'page' => 'profile'],
    ['title' => 'Personal Info', 'url' => '/users/personal.php', 'icon' => 'id-card', 'page' => 'personal'],
    ['title' => 'Enhanced Personal', 'url' => '/users/personal_enhanced.php', 'icon' => 'id-card-o', 'page' => 'personal_enhanced'],
    ['title' => 'Service Record', 'url' => '/users/service.php', 'icon' => 'medal', 'page' => 'service'],
    ['title' => 'Training History', 'url' => '/users/training.php', 'icon' => 'graduation-cap', 'page' => 'training'],
    ['title' => 'Family Members', 'url' => '/users/family.php', 'icon' => 'users', 'page' => 'family'],
    ['title' => 'Account Settings', 'url' => '/users/settings.php', 'icon' => 'cogs', 'page' => 'settings']
];

// Load enhanced profile manager and validators
require_once __DIR__ . '/profile_manager.php';
require_once __DIR__ . '/classes/SecurityValidator.php';
require_once __DIR__ . '/classes/MilitaryValidator.php';
require_once __DIR__ . '/classes/AuditLogger.php';

$success = false;
$errors = [];

try {
    require_once dirname(__DIR__) . '/shared/database_connection.php';
    $pdo = getDbConnection();
    
    // Initialize enhanced components
    $auditLogger = new AuditLogger($pdo, $_SESSION['user_id']);
    $securityValidator = new SecurityValidator($auditLogger);
    $militaryValidator = new MilitaryValidator($pdo);
    $profileManager = new UserProfileManager($_SESSION['user_id']);
    
    // Get profile data
    $userData = $profileManager->getCompleteProfile();
    
    // Get ranks and units for dropdowns
    $stmt = $pdo->query("SELECT id, name, abbreviation FROM ranks ORDER BY id");
    $ranks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $stmt = $pdo->query("SELECT id, name, code FROM units ORDER BY name");
    $units = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get profile completion data
    $stmt = $pdo->prepare("
        SELECT section_name, completion_percentage, mandatory_fields_complete, optional_fields_complete
        FROM profile_completion_tracking 
        WHERE staff_id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $completionData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $overallCompletion = 0;
    if (!empty($completionData)) {
        $totalCompletion = array_sum(array_column($completionData, 'completion_percentage'));
        $overallCompletion = round($totalCompletion / count($completionData), 2);
    }
    
} catch (Exception $e) {
    error_log("Enhanced profile loading error: " . $e->getMessage());
    $userData = (object)['error' => 'Failed to load profile data'];
    $ranks = [];
    $units = [];
    $completionData = [];
    $overallCompletion = 0;
}

include dirname(__DIR__) . '/shared/header.php';
include dirname(__DIR__) . '/shared/sidebar.php';
?>

<!-- Enhanced CSS and JS -->
<link rel="stylesheet" href="/users/assets/css/enhanced-profile.css">
<script src="/users/assets/js/enhanced-validation.js" defer></script>

<!-- Main Content -->
<div class="content-wrapper with-sidebar">
    <div class="container-fluid">
        <div class="main-content">
            
            <!-- Alert Container -->
            <div id="alert-container"></div>
            
            <!-- Profile Completion Card -->
            <div class="profile-completion-card">
                <h5><i class="fas fa-chart-line text-primary"></i> Profile Completion Status</h5>
                <div class="completion-progress">
                    <div id="profile-completion">
                        <div class="progress">
                            <div class="progress-bar bg-success" role="progressbar" 
                                 style="width: <?= $overallCompletion ?>%" 
                                 aria-valuenow="<?= $overallCompletion ?>" 
                                 aria-valuemin="0" aria-valuemax="100">
                                <?= $overallCompletion ?>% Complete
                            </div>
                        </div>
                    </div>
                </div>
                
                <?php if (!empty($completionData)): ?>
                <div class="completion-sections">
                    <?php foreach ($completionData as $section): ?>
                    <div class="completion-section">
                        <span><?= ucfirst(str_replace('_', ' ', $section['section_name'])) ?></span>
                        <span class="completion-percentage"><?= $section['completion_percentage'] ?>%</span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Enhanced Personal Information Form -->
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">
                        <i class="fas fa-id-card me-2"></i>
                        Enhanced Personal Information
                        <small class="float-end">Military-Grade Security</small>
                    </h4>
                </div>
                
                <div class="card-body">
                    <form id="enhanced-personal-form" data-enhanced-validation="true" method="POST">
                        
                        <!-- Basic Information Group -->
                        <div class="military-field-group">
                            <h6><i class="fas fa-user"></i> Basic Information</h6>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="first_name" class="form-label required">
                                            First Name <span class="text-danger">*</span>
                                        </label>
                                        <input type="text" 
                                               id="first_name" 
                                               name="first_name" 
                                               class="form-control enhanced-field" 
                                               value="<?= htmlspecialchars($userData->first_name ?? '') ?>"
                                               data-validate="true"
                                               data-field-type="first_name"
                                               data-auto-save="true"
                                               required>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="last_name" class="form-label required">
                                            Last Name <span class="text-danger">*</span>
                                        </label>
                                        <input type="text" 
                                               id="last_name" 
                                               name="last_name" 
                                               class="form-control enhanced-field" 
                                               value="<?= htmlspecialchars($userData->last_name ?? '') ?>"
                                               data-validate="true"
                                               data-field-type="last_name"
                                               data-auto-save="true"
                                               required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="email" class="form-label required">
                                            Email Address <span class="text-danger">*</span>
                                        </label>
                                        <input type="email" 
                                               id="email" 
                                               name="email" 
                                               class="form-control enhanced-field" 
                                               value="<?= htmlspecialchars($userData->email ?? '') ?>"
                                               data-validate="true"
                                               data-field-type="email"
                                               data-auto-save="true"
                                               required>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="phone" class="form-label">
                                            Phone Number
                                        </label>
                                        <input type="tel" 
                                               id="phone" 
                                               name="phone" 
                                               class="form-control enhanced-field" 
                                               value="<?= htmlspecialchars($userData->tel ?? '') ?>"
                                               data-validate="true"
                                               data-field-type="phone"
                                               data-auto-save="true">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Military Information Group -->
                        <div class="military-field-group">
                            <h6><i class="fas fa-medal"></i> Military Information</h6>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="service_number" class="form-label required">
                                            Service Number <span class="text-danger">*</span>
                                        </label>
                                        <input type="text" 
                                               id="service_number" 
                                               name="service_number" 
                                               class="form-control enhanced-field" 
                                               value="<?= htmlspecialchars($userData->service_number ?? '') ?>"
                                               data-validate="true"
                                               data-field-type="service_number"
                                               data-auto-save="true"
                                               placeholder="ZA123456"
                                               required>
                                        <small class="form-text text-muted">Format: 2 letters + 6-8 digits</small>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="rank_id" class="form-label required">
                                            Military Rank <span class="text-danger">*</span>
                                        </label>
                                        <select id="rank_id" 
                                                name="rank_id" 
                                                class="form-control enhanced-field" 
                                                data-validate="true"
                                                data-field-type="rank_id"
                                                data-auto-save="true"
                                                required>
                                            <option value="">Select Rank</option>
                                            <?php foreach ($ranks as $rank): ?>
                                            <option value="<?= $rank['id'] ?>" 
                                                    <?= ($userData->rank_id ?? '') == $rank['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($rank['name']) ?> (<?= htmlspecialchars($rank['abbreviation']) ?>)
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="unit_id" class="form-label required">
                                            Unit <span class="text-danger">*</span>
                                        </label>
                                        <select id="unit_id" 
                                                name="unit_id" 
                                                class="form-control enhanced-field" 
                                                data-validate="true"
                                                data-field-type="unit_id"
                                                data-auto-save="true"
                                                required>
                                            <option value="">Select Unit</option>
                                            <?php foreach ($units as $unit): ?>
                                            <option value="<?= $unit['id'] ?>" 
                                                    <?= ($userData->unit_id ?? '') == $unit['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($unit['name']) ?> (<?= htmlspecialchars($unit['code']) ?>)
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="corps" class="form-label required">
                                            Corps <span class="text-danger">*</span>
                                        </label>
                                        <select id="corps" 
                                                name="corps" 
                                                class="form-control enhanced-field" 
                                                data-validate="true"
                                                data-field-type="corps"
                                                data-auto-save="true"
                                                required>
                                            <option value="">Select Corps</option>
                                            <option value="Infantry" <?= ($userData->corps ?? '') == 'Infantry' ? 'selected' : '' ?>>Infantry</option>
                                            <option value="Artillery" <?= ($userData->corps ?? '') == 'Artillery' ? 'selected' : '' ?>>Artillery</option>
                                            <option value="Armoured" <?= ($userData->corps ?? '') == 'Armoured' ? 'selected' : '' ?>>Armoured</option>
                                            <option value="Engineers" <?= ($userData->corps ?? '') == 'Engineers' ? 'selected' : '' ?>>Engineers</option>
                                            <option value="Signals" <?= ($userData->corps ?? '') == 'Signals' ? 'selected' : '' ?>>Signals</option>
                                            <option value="Medical" <?= ($userData->corps ?? '') == 'Medical' ? 'selected' : '' ?>>Medical</option>
                                            <option value="Logistics" <?= ($userData->corps ?? '') == 'Logistics' ? 'selected' : '' ?>>Logistics</option>
                                            <option value="Intelligence" <?= ($userData->corps ?? '') == 'Intelligence' ? 'selected' : '' ?>>Intelligence</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Identity Information Group -->
                        <div class="military-field-group">
                            <h6><i class="fas fa-id-badge"></i> Identity Information</h6>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="nrc" class="form-label required">
                                            NRC Number <span class="text-danger">*</span>
                                        </label>
                                        <input type="text" 
                                               id="nrc" 
                                               name="nrc" 
                                               class="form-control enhanced-field" 
                                               value="<?= htmlspecialchars($userData->NRC ?? '') ?>"
                                               data-validate="true"
                                               data-field-type="nrc"
                                               data-auto-save="true"
                                               placeholder="123456/12/1"
                                               required>
                                        <small class="form-text text-muted">Format: 123456/12/1</small>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="passport_number" class="form-label">
                                            Passport Number
                                        </label>
                                        <input type="text" 
                                               id="passport_number" 
                                               name="passport_number" 
                                               class="form-control enhanced-field" 
                                               value="<?= htmlspecialchars($userData->passport ?? '') ?>"
                                               data-validate="true"
                                               data-field-type="passport_number"
                                               data-auto-save="true"
                                               placeholder="ZN1234567">
                                        <small class="form-text text-muted">Format: ZN1234567</small>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="date_of_birth" class="form-label required">
                                            Date of Birth <span class="text-danger">*</span>
                                        </label>
                                        <input type="date" 
                                               id="date_of_birth" 
                                               name="date_of_birth" 
                                               class="form-control enhanced-field" 
                                               value="<?= $userData->DOB ?? '' ?>"
                                               data-validate="true"
                                               data-field-type="date_of_birth"
                                               data-auto-save="true"
                                               required>
                                    </div>
                                </div>
                                
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="gender" class="form-label required">
                                            Gender <span class="text-danger">*</span>
                                        </label>
                                        <select id="gender" 
                                                name="gender" 
                                                class="form-control enhanced-field" 
                                                data-validate="true"
                                                data-field-type="gender"
                                                data-auto-save="true"
                                                required>
                                            <option value="">Select Gender</option>
                                            <option value="Male" <?= ($userData->gender ?? '') == 'Male' ? 'selected' : '' ?>>Male</option>
                                            <option value="Female" <?= ($userData->gender ?? '') == 'Female' ? 'selected' : '' ?>>Female</option>
                                            <option value="Other" <?= ($userData->gender ?? '') == 'Other' ? 'selected' : '' ?>>Other</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="nationality" class="form-label required">
                                            Nationality <span class="text-danger">*</span>
                                        </label>
                                        <input type="text" 
                                               id="nationality" 
                                               name="nationality" 
                                               class="form-control enhanced-field" 
                                               value="<?= htmlspecialchars($userData->nationality ?? 'Zambian') ?>"
                                               data-validate="true"
                                               data-field-type="nationality"
                                               data-auto-save="true"
                                               required>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Submit Button -->
                        <div class="text-center mt-4">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-save"></i> Update Profile
                            </button>
                            <button type="button" class="btn btn-secondary btn-lg ms-2" onclick="window.location.reload()">
                                <i class="fas fa-undo"></i> Reset Form
                            </button>
                        </div>
                        
                        <!-- Hidden CSRF Token -->
                        <input type="hidden" name="csrf_token" value="<?= $securityValidator->generateCSRFToken() ?>">
                    </form>
                </div>
            </div>
            
        </div>
    </div>
</div>

<?php include dirname(__DIR__) . '/shared/footer.php'; ?>

<script>
// Initialize enhanced validation when page loads
document.addEventListener('DOMContentLoaded', function() {
    console.log('Enhanced Personal Information page loaded with military-grade security');
    
    // Add visual indicators for required fields
    document.querySelectorAll('[required]').forEach(field => {
        field.parentNode.querySelector('label').style.fontWeight = '600';
    });
    
    // Initialize tooltips for help text
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});
</script>