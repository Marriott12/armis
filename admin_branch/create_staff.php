<?php
// Start session for success messages and CSRF
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Define module constants
define('ARMIS_ADMIN_BRANCH', true);
define('ARMIS_DEVELOPMENT', true); // Set to false in production

// Include admin branch authentication and enhanced analytics
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/analytics.php';
require_once __DIR__ . '/partials/create_staff_config.php';

// Require authentication and admin branch access
requireAuth();

// Check if user has access to admin_branch module
requireModuleAccess('admin_branch');

// Check specific permission for creating staff
if (!hasPermission(PERM_CREATE_STAFF)) {
    header('HTTP/1.1 403 Forbidden');
    die('Access denied. You do not have permission to create staff records.');
}

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
    // NRC field removed
        $email = $_POST['email'] ?? '';
        $phone = $_POST['phone'] ?? '';
        $dob = $_POST['dob'] ?? '';
        // NOK and ALT NOK fields
    // NOK NRC field removed
        $nok_phone = $_POST['nok_phone'] ?? '';
        $nok_email = $_POST['nok_email'] ?? '';
    // ALT NOK NRC field removed
        $altnok_phone = $_POST['altnok_phone'] ?? '';
        $altnok_email = $_POST['altnok_email'] ?? '';

    $required_fields = ['email', 'fname', 'lname', 'DOB', 'svcNo', 'category', 'rankID'];
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                $form_errors[$field] = ucfirst(str_replace('_', ' ', $field)) . ' is required.';
            }
        }
    // NRC validation removed
        // Email format
        if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $form_errors['email'] = 'Invalid email address.';
        }
        // Phone format (digits, 8-15 chars)
        if ($phone && !preg_match('/^\+?\d{8,15}$/', $phone)) {
            $form_errors['phone'] = 'Invalid phone number.';
        }
    // NOK NRC validation removed
        // NOK phone format
        if ($nok_phone && !preg_match('/^\+?\d{8,15}$/', $nok_phone)) {
            $form_errors['nok_phone'] = 'Invalid phone number for Next of Kin.';
        }
        // NOK email format
        if ($nok_email && !filter_var($nok_email, FILTER_VALIDATE_EMAIL)) {
            $form_errors['nok_email'] = 'Invalid email address for Next of Kin.';
        }
    // ALT NOK NRC validation removed
        // ALT NOK phone format
        if ($altnok_phone && !preg_match('/^\+?\d{8,15}$/', $altnok_phone)) {
            $form_errors['altnok_phone'] = 'Invalid phone number for Alternate Next of Kin.';
        }
        // ALT NOK email format
        if ($altnok_email && !filter_var($altnok_email, FILTER_VALIDATE_EMAIL)) {
            $form_errors['altnok_email'] = 'Invalid email address for Alternate Next of Kin.';
        }
        // Character limits
        if (isset($_POST['fname']) && strlen($_POST['fname']) > 50) {
            $form_errors['fname'] = 'First name must be 50 characters or less.';
        }
        if (isset($_POST['lname']) && strlen($_POST['lname']) > 50) {
            $form_errors['lname'] = 'Last name must be 50 characters or less.';
        }
        // Age calculation (must be 18+)
        if (isset($_POST['DOB']) && $_POST['DOB']) {
            $dob_date = DateTime::createFromFormat('Y-m-d', $_POST['DOB']);
            if ($dob_date) {
                $age = $dob_date->diff(new DateTime('now'))->y;
                if ($age < 18) {
                    $form_errors['DOB'] = 'Staff member must be at least 18 years old.';
                }
            } else {
                $form_errors['DOB'] = 'Invalid date of birth.';
            }
        }

        // Service number validation (must be integer and > 0)
        if (isset($_POST['svcNo'])) {
            $service_number = $_POST['svcNo'];
            if (!ctype_digit($service_number) || intval($service_number) < 1) {
                $form_errors['svcNo'] = 'Service number must be numbers.';
            }
        }

    // Duplicate email check only
    require_once dirname(__DIR__) . '/shared/database_connection.php';
    $duplicate = false;
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

// Initialize success message variables
$success_message = '';
$display_credentials = false;
$temp_password = '';
$username = '';
$staff_name = '';
$staff_email = '';

// Check for success message from handler
if (isset($_GET['success']) && $_GET['success'] == '1') {
    if (isset($_SESSION['success_message'])) {
        $success_message = $_SESSION['success_message'];
        unset($_SESSION['success_message']);
    }
    
    // Show temporary credentials for the newly created staff
    if (isset($_SESSION['temp_password']) && isset($_SESSION['username'])) {
        $temp_password = $_SESSION['temp_password'];
        $username = $_SESSION['username'];
        $staff_name = $_SESSION['staff_name'] ?? '';
        $staff_email = $_SESSION['staff_email'] ?? '';
        $display_credentials = true;
        
        // Clear sensitive data from session
        unset($_SESSION['temp_password']);
        unset($_SESSION['username']);
        unset($_SESSION['staff_name']);
        unset($_SESSION['staff_email']);
    }
}

// Log page access with enhanced analytics
logActivity('create_staff_access', 'Accessed Create Staff page');

$pageTitle = "Create Staff - Admin Branch";
$moduleName = "Admin Branch";
$moduleIcon = "user-plus";
$currentPage = "create";

// Sidebar navigation
$sidebarLinks = [
    ['title' => 'Dashboard', 'url' => '/Armis2/admin_branch/index.php', 'icon' => 'tachometer-alt', 'page' => 'dashboard'],
    ['title' => 'Staff Management', 'url' => '/Armis2/admin_branch/edit_staff.php', 'icon' => 'users', 'page' => 'staff'],
    ['title' => 'Create Staff', 'url' => '/Armis2/admin_branch/create_staff.php', 'icon' => 'user-plus', 'page' => 'create'],
    ['title' => 'Promotions', 'url' => '/Armis2/admin_branch/promote_staff.php', 'icon' => 'arrow-up', 'page' => 'promotions'],
    ['title' => 'Appointments', 'url' => '/Armis2/admin_branch/appointments.php', 'icon' => 'user-tie', 'page' => 'appointments'],
    ['title' => 'Medals', 'url' => '/Armis2/admin_branch/assign_medal.php', 'icon' => 'medal', 'page' => 'medals'],
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
                    <!--<div class="auto-save-indicator" id="autoSaveIndicator">
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
                    </div>-->
                    <!-- Staff Creation Form -->
                    <div class="dashboard-card">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <div>
                                <h3 class="mb-0"><i class="fas fa-user-plus"></i> Register New Staff Member</h3>
                                <p class="text-muted mb-0">Complete the form below to add a new staff member to the system</p>
                            </div>
                            <div class="d-flex gap-2">
                                <!-- Load Draft button removed -->
                                <button type="button" class="btn btn-outline-secondary btn-sm" id="clearFormBtn">
                                    <i class="fa fa-refresh"></i> Clear Form
                                </button>
                                <!-- Save Draft button removed -->
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
                        
                        <!-- Success Message with Login Credentials -->
                        <?php if (!empty($success_message)): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <div class="d-flex align-items-center mb-2">
                                <i class="fas fa-check-circle me-2"></i>
                                <strong><?= htmlspecialchars($success_message) ?></strong>
                            </div>
                            
                            <?php if ($display_credentials): ?>
                            <hr class="my-3">
                            <div class="row">
                                <div class="col-12">
                                    <h6 class="mb-3"><i class="fas fa-key text-primary"></i> Temporary Login Credentials</h6>
                                    <?php if (!empty($staff_name)): ?>
                                    <p class="mb-2"><strong>Staff Member:</strong> <?= htmlspecialchars($staff_name) ?></p>
                                    <?php endif; ?>
                                    <?php if (!empty($staff_email)): ?>
                                    <p class="mb-2"><strong>Email:</strong> <?= htmlspecialchars($staff_email) ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="row mt-2">
                                <div class="col-md-6">
                                    <div class="bg-light p-3 rounded">
                                        <strong>Username:</strong><br>
                                        <code class="fs-6"><?= htmlspecialchars($username) ?></code>
                                        <button class="btn btn-sm btn-outline-secondary ms-2" onclick="copyToClipboard('<?= htmlspecialchars($username) ?>')">
                                            <i class="fas fa-copy"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="bg-light p-3 rounded">
                                        <strong>Temporary Password:</strong><br>
                                        <code class="fs-6"><?= htmlspecialchars($temp_password) ?></code>
                                        <button class="btn btn-sm btn-outline-secondary ms-2" onclick="copyToClipboard('<?= htmlspecialchars($temp_password) ?>')">
                                            <i class="fas fa-copy"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="alert alert-warning mt-3 mb-0">
                                <div class="d-flex align-items-start">
                                    <i class="fas fa-exclamation-triangle me-2 mt-1"></i>
                                    <div>
                                        <strong>Important Security Notice:</strong>
                                        <ul class="mb-0 mt-1">
                                            <li>The user must change this password on first login</li>
                                            <li>These credentials will be sent to the user's email address</li>
                                            <li>Store these credentials securely until the user logs in</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
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
                        
                        <?php //require 'partials/create_staff_tabs.php'; ?>
                        
                        <form id="createStaffForm" method="post" action="<?=htmlspecialchars($_SERVER["PHP_SELF"]);?>" autocomplete="off" novalidate aria-labelledby="formTitle">
                            <input type="hidden" name="csrf" value="<?=htmlspecialchars($csrfToken)?>">
                            
                            <div class="tab-content" id="staffTabContent">
                                <?php require 'partials/tab_personal.php'; ?>
                                <?php require 'partials/tab_honours.php'; ?>
                                <?php require 'partials/tab_id.php'; ?>
                            </div>
                            
                                <div class="d-flex justify-content-between align-items-center mt-4" aria-label="Form Actions">
                               
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
                                    <i class="fa fa-exclamation-triangle text-warning"></i> Please Clear These Errors
                                </h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <div id="validationSummaryContent">
                                    <!-- Dynamic content will be populated here -->
                                </div>
                <script src="/Armis2/assets/js/staff-form.js"></script>
                <?php require 'partials/create_staff_js.php'; ?>
                
                <!-- Copy to Clipboard Functionality -->
                <script>
                function copyToClipboard(text) {
                    // Create a temporary textarea element
                    const textarea = document.createElement('textarea');
                    textarea.value = text;
                    document.body.appendChild(textarea);
                    
                    // Select and copy the text
                    textarea.select();
                    textarea.setSelectionRange(0, 99999); // For mobile devices
                    
                    try {
                        const successful = document.execCommand('copy');
                        if (successful) {
                            // Show success feedback
                            // Notifications disabled
                            // showCopySuccess();
                        }
                    } catch (err) {
                        console.error('Failed to copy text: ', err);
                    }
                    
                    // Remove the temporary element
                    document.body.removeChild(textarea);
                }

                function showCopySuccess() {
                    // Create a temporary toast notification
                    const toast = document.createElement('div');
                    toast.className = 'position-fixed top-0 end-0 p-3';
                    toast.style.zIndex = '9999';
                    toast.innerHTML = `
                        <div class="toast show" role="alert">
                            <div class="toast-header">
                                <i class="fas fa-check-circle text-success me-2"></i>
                                <strong class="me-auto">Copied!</strong>
                            </div>
                            <div class="toast-body">
                                Text copied to clipboard successfully.
                            </div>
                        </div>
                    `;
                    
                    document.body.appendChild(toast);
                    
                    // Remove the toast after 3 seconds
                    setTimeout(() => {
                        if (toast.parentNode) {
                            toast.parentNode.removeChild(toast);
                        }
                    }, 3000);
                }
                </script>
            </div>
            </div>
        </div>
    </div>
</div>
</div></div>

<?php include dirname(__DIR__) . '/shared/footer.php'; ?>