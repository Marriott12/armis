<?php
// Define module constants
define('ARMIS_ADMIN_BRANCH', true);
define('ARMIS_DEVELOPMENT', true); // Set to false in production

// Include admin branch authentication and enhanced analytics
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/analytics.php';
require_once __DIR__ . '/partials/create_staff_config.php';

// Require authentication and admin privileges
//requireAdmin();


// CSRF validation, input sanitization, and duplicate NRC/email check
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf']) || $_POST['csrf'] !== $csrfToken) {
        // Log CSRF failure
        error_log('CSRF token mismatch on staff creation');
        $form_errors['csrf'] = 'Invalid session. Please refresh and try again.';
    } else {
        // Sanitize all POST input
        foreach ($_POST as $key => $value) {
            $_POST[$key] = is_string($value) ? trim(htmlspecialchars($value, ENT_QUOTES, 'UTF-8')) : $value;
        }

        // Additional server-side validations
        $nrc = $_POST['nrc'] ?? '';
        $email = $_POST['email'] ?? '';
        $phone = $_POST['phone'] ?? '';
        $dob = $_POST['dob'] ?? '';
        // NOK and ALT NOK fields
        $nok_nrc = $_POST['nok_nrc'] ?? '';
        $nok_phone = $_POST['nok_phone'] ?? '';
        $nok_email = $_POST['nok_email'] ?? '';
        $altnok_nrc = $_POST['altnok_nrc'] ?? '';
        $altnok_phone = $_POST['altnok_phone'] ?? '';
        $altnok_email = $_POST['altnok_email'] ?? '';

        $required_fields = ['nrc', 'email', 'first_name', 'last_name', 'dob'];
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                $form_errors[$field] = ucfirst(str_replace('_', ' ', $field)) . ' is required.';
            }
        }
        // NRC format (example: 12/ABC12345/67)
        if ($nrc && !preg_match('/^\d{2}\/\w{3,}\d{4,}\/\d{2}$/i', $nrc)) {
            $form_errors['nrc'] = 'Invalid NRC format.';
        }
        // Email format
        if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $form_errors['email'] = 'Invalid email address.';
        }
        // Phone format (digits, 8-15 chars)
        if ($phone && !preg_match('/^\+?\d{8,15}$/', $phone)) {
            $form_errors['phone'] = 'Invalid phone number.';
        }
        // NOK NRC format
        if ($nok_nrc && !preg_match('/^\d{2}\/\w{3,}\d{4,}\/\d{2}$/i', $nok_nrc)) {
            $form_errors['nok_nrc'] = 'Invalid NRC format for Next of Kin.';
        }
        // NOK phone format
        if ($nok_phone && !preg_match('/^\+?\d{8,15}$/', $nok_phone)) {
            $form_errors['nok_phone'] = 'Invalid phone number for Next of Kin.';
        }
        // NOK email format
        if ($nok_email && !filter_var($nok_email, FILTER_VALIDATE_EMAIL)) {
            $form_errors['nok_email'] = 'Invalid email address for Next of Kin.';
        }
        // ALT NOK NRC format
        if ($altnok_nrc && !preg_match('/^\d{2}\/\w{3,}\d{4,}\/\d{2}$/i', $altnok_nrc)) {
            $form_errors['altnok_nrc'] = 'Invalid NRC format for Alternate Next of Kin.';
        }
        // ALT NOK phone format
        if ($altnok_phone && !preg_match('/^\+?\d{8,15}$/', $altnok_phone)) {
            $form_errors['altnok_phone'] = 'Invalid phone number for Alternate Next of Kin.';
        }
        // ALT NOK email format
        if ($altnok_email && !filter_var($altnok_email, FILTER_VALIDATE_EMAIL)) {
            $form_errors['altnok_email'] = 'Invalid email address for Alternate Next of Kin.';
        }
        // Character limits
        if (isset($_POST['first_name']) && strlen($_POST['first_name']) > 50) {
            $form_errors['first_name'] = 'First name must be 50 characters or less.';
        }
        if (isset($_POST['last_name']) && strlen($_POST['last_name']) > 50) {
            $form_errors['last_name'] = 'Last name must be 50 characters or less.';
        }
        // Age calculation (must be 18+)
        if ($dob) {
            $dob_date = DateTime::createFromFormat('Y-m-d', $dob);
            if ($dob_date) {
                $age = $dob_date->diff(new DateTime('now'))->y;
                if ($age < 18) {
                    $form_errors['dob'] = 'Staff member must be at least 18 years old.';
                }
            } else {
                $form_errors['dob'] = 'Invalid date of birth.';
            }
        }

        // Duplicate NRC and email check
        require_once dirname(__DIR__) . '/shared/database_connection.php';
        $duplicate = false;
        if ($nrc) {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM staff WHERE nrc = ?');
            $stmt->execute([$nrc]);
            if ($stmt->fetchColumn() > 0) {
                $form_errors['nrc'] = 'A staff member with this NRC already exists.';
                $duplicate = true;
            }
        }
        if ($email) {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM staff WHERE email = ?');
            $stmt->execute([$email]);
            if ($stmt->fetchColumn() > 0) {
                $form_errors['email'] = 'A staff member with this email address already exists.';
                $duplicate = true;
            }
        }
        if (empty($form_errors) && !$duplicate) {
            require_once __DIR__ . '/partials/create_staff_handler_simple.php';
        }
    }
}

// Log page access with enhanced analytics
logActivity('create_staff_access', 'Accessed Create Staff page');

$pageTitle = "Create Staff - Admin Branch";
$moduleName = "Admin Branch";
$moduleIcon = "user-plus";
$currentPage = "create";

$sidebarLinks = [
    ['title' => 'Dashboard', 'url' => '/Armis2/admin_branch/index.php', 'icon' => 'tachometer-alt', 'page' => 'dashboard'],
    ['title' => 'Staff Management', 'url' => '/Armis2/admin_branch/edit_staff.php', 'icon' => 'users', 'page' => 'staff'],
    ['title' => 'Create Staff', 'url' => '/Armis2/admin_branch/create_staff.php', 'icon' => 'user-plus', 'page' => 'create'],
    ['title' => 'Promotions', 'url' => '/Armis2/admin_branch/promote_staff.php', 'icon' => 'arrow-up', 'page' => 'promotions'],
    ['title' => 'Medals', 'url' => '/Armis2/admin_branch/medal.php', 'icon' => 'medal', 'page' => 'medals'],
    ['title' => 'Reports', 'url' => '/Armis2/admin_branch/reports_seniority.php', 'icon' => 'chart-bar', 'page' => 'reports'],
    ['title' => 'System Settings', 'url' => '/Armis2/admin_branch/system_settings.php', 'icon' => 'cogs', 'page' => 'settings']
];

// Ensure shared admin branch CSS is loaded
echo '<link rel="stylesheet" href="/Armis2/assets/css/admin_branch.css">';
include dirname(__DIR__) . '/shared/header.php';
include dirname(__DIR__) . '/shared/sidebar.php';
?>
<!-- Main Content -->
<div class="content-wrapper with-sidebar">
    <div class="container-fluid">
        <div class="main-content">
            <!-- Main Form Content -->
            <div class="col-12">
                <div class="staff-form-container">
                    <!-- Auto-save indicator -->
                    <div class="auto-save-indicator" id="autoSaveIndicator">
                        <div class="toast" role="alert" aria-live="assertive" aria-atomic="true">
                            <div class="toast-header">
                                <i class="fa fa-save text-success me-2"></i>
                                <strong class="me-auto">Auto-Save</strong>
                                <button type="button" class="btn-close" data-bs-dismiss="toast"></button>
                            </div>
                            <div class="toast-body">
                                Form data saved successfully!
                            </div>
                        </div>
                    </div>
                    <!-- Staff Creation Form -->
                    <div class="dashboard-card">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <div>
                                <h3 class="mb-0"><i class="fas fa-user-plus"></i> Register New Staff Member</h3>
                                <p class="text-muted mb-0">Complete the form below to add a new staff member to the system</p>
                            </div>
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-outline-secondary btn-sm" id="loadDraftBtn">
                                    <i class="fa fa-folder-open"></i> Load Draft
                                </button>
                                <button type="button" class="btn btn-outline-secondary btn-sm" id="clearFormBtn">
                                    <i class="fa fa-refresh"></i> Clear Form
                                </button>
                                <button type="button" class="btn btn-outline-secondary btn-sm" id="saveDraftBtn">
                                    <i class="fa fa-save"></i> Save Draft
                                </button>
                            </div>
                    </div>
                            
                        <!-- Form Progress Section -->
                        <div class="dashboard-card mb-4">
                            <div class="row">
                                <div class="col-md-8">
                                    <div class="progress mb-2" style="height: 12px;">
                                        <div class="progress-bar bg-success progress-bar-striped" role="progressbar" style="width: 0%" id="formProgress"></div>
                                    </div>
                                    <div class="d-flex justify-content-between small text-muted">
                                        <span>Form Completion</span>
                                        <span id="progressText">0% Complete</span>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="d-flex justify-content-between">
                                        <div class="text-center">
                                            <span class="badge bg-info" id="completionPercentage">0%</span>
                                            <small class="d-block text-muted">Completion</small>
                                        </div>
                                        <div class="text-center">
                                            <span class="badge bg-warning" id="requiredFields">0/0</span>
                                            <small class="d-block text-muted">Required</small>
                                        </div>
                                        <div class="text-center">
                                            <span class="badge bg-danger" id="errorCount">0</span>
                                            <small class="d-block text-muted">Errors</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <?php require 'partials/alerts.php'; ?>
                        
                        <!-- Success Message -->
                        <?php if (isset($success_message)): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success_message) ?>
                            
                            <?php if (isset($display_credentials) && $display_credentials): ?>
                            <hr>
                            <div class="mt-3">
                                <h6><i class="fas fa-key"></i> Temporary Login Credentials</h6>
                                <div class="row">
                                    <div class="col-md-6">
                                        <strong>Username:</strong> <code><?= htmlspecialchars($username) ?></code>
                                    </div>
                                    <div class="col-md-6">
                                        <strong>Temporary Password:</strong> <code><?= htmlspecialchars($temp_password) ?></code>
                                    </div>
                                </div>
                                <div class="alert alert-warning mt-2 mb-0">
                                    <small><i class="fas fa-exclamation-triangle"></i> <strong>Important:</strong> The user must change this password on first login. This information has been sent via email.</small>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Error Messages -->
                        <?php if (!empty($form_errors)): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <h6><i class="fas fa-exclamation-triangle"></i> Please fix the following errors:</h6>
                            <ul class="mb-0">
                                <?php foreach ($form_errors as $field => $error): ?>
                                    <li><?= htmlspecialchars($error) ?></li>
                                <?php endforeach; ?>
                            </ul>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                        <?php endif; ?>
                        
                        <?php require 'partials/create_staff_tabs.php'; ?>
                        
                        <form id="createStaffForm" method="post" action="<?=htmlspecialchars($_SERVER["PHP_SELF"]);?>" autocomplete="off" novalidate aria-labelledby="formTitle">
                            <input type="hidden" name="csrf" value="<?=htmlspecialchars($csrfToken)?>">
                            
                            <div class="tab-content" id="staffTabContent">
                                <?php require 'partials/tab_personal.php'; ?>
                                <?php require 'partials/tab_service.php'; ?>
                                <?php require 'partials/tab_family.php'; ?>
                                <?php require 'partials/tab_academic.php'; ?>
                                <?php require 'partials/tab_honours.php'; ?>
                                <?php require 'partials/tab_id.php'; ?>
                                <?php require 'partials/tab_residence.php'; ?>
                                <?php require 'partials/tab_language.php'; ?>
                            </div>
                            
                                <div class="d-flex justify-content-between align-items-center mt-4" aria-label="Form Actions">
                                <div>
                                    <button type="button" class="btn btn-outline-secondary" id="saveAndContinueBtn" title="Save your progress and move to the next tab">
                                        <i class="fa fa-save"></i> Save & Continue Later
                                    </button>
                                </div>
                                <div>
                                    <button type="button" class="btn btn-outline-info me-2" id="validateFormBtn" title="Check for errors before submitting">
                                        <i class="fa fa-check-circle"></i> Validate Form
                                    </button>
                                    <button type="submit" class="btn btn-success px-4 py-2" id="submitBtn" title="Submit the staff registration form">
                                        <i class="fa fa-user-plus"></i> Register Staff
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>    <!-- Validation Summary Modal -->
                <div class="modal fade" id="validationModal" tabindex="-1" aria-labelledby="validationModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="validationModalLabel">
                                    <i class="fa fa-exclamation-triangle text-warning"></i> Form Validation Summary
                                </h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <div id="validationSummaryContent">
                                    <!-- Dynamic content will be populated here -->
                                </div>
                <script src="/Armis2/assets/js/staff-form.js"></script>
                <?php require 'partials/create_staff_js.php'; ?>
            </div>
            </div>
        </div>
    </div>
</div>
</div></div>

<?php include dirname(__DIR__) . '/shared/footer.php'; ?>