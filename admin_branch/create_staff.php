<?php
/**
 * Staff Creation and Import Module
 * 
 * RANK LEVEL SYSTEM:
 * - Levels 1-13:  Officers (1=General/highest, 13=2nd Lieutenant/lowest)
 * - Level 14:     Officer Cadets (commissioning candidates)
 * - Levels 15-26: Non-Commissioned Officers (15=WO1/highest, 26=Private/lowest)
 * - Level 27:     Recruits (in basic training)
 * - Level 28:     Civilian Employees (admin/support staff)
 * 
 * SENIORITY: Lower level = Higher rank (ORDER BY level ASC shows highest rank first)
 * 
 * See: /shared/rank_levels.php for helper functions
 * See: /RANK_LEVELS_DOCUMENTATION.md for complete documentation
 */

// Start session for success messages and CSRF
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Quick-fix download endpoint for failed import rows
if (isset($_GET['download_quick_fix']) && isset($_SESSION['quick_fix_csv'])) {
    $csvContent = $_SESSION['quick_fix_csv'];
    $filename = 'staff_import_failed_rows_' . date('Y-m-d_His') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo $csvContent;
    exit;
}

// Define module constants
define('ARMIS_ADMIN_BRANCH', true);
define('ARMIS_DEVELOPMENT', true); // Set to false in production

// Include admin branch authentication and enhanced analytics
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/analytics.php';
require_once __DIR__ . '/partials/create_staff_config.php';

// AJAX endpoint: return failed-rows CSV (base64) and filename as JSON for client-side download
if (isset($_GET['ajax_get_quick_fix'])) {
    header('Content-Type: application/json');
    $has = isset($_SESSION['quick_fix_csv']) && $_SESSION['quick_fix_csv'];
    if ($has) {
        $csvContent = $_SESSION['quick_fix_csv'];
        $filename = 'staff_import_failed_rows_' . date('Y-m-d_His') . '.csv';
        echo json_encode([
            'success' => true,
            'filename' => $filename,
            // base64 encode to safely transfer binary/CSV in JSON
            'csv_base64' => base64_encode($csvContent)
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'No failed CSV stored in session.']);
    }
    exit;
}

// AJAX endpoint: clear failed-rows CSV from session (expects POST with CSRF)
if (isset($_GET['ajax_clear_quick_fix']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // Accept CSRF token via header X-CSRF-Token or as POST param 'csrf'
    $provided = null;
    if (!empty($_SERVER['HTTP_X_CSRF_TOKEN'])) {
        $provided = $_SERVER['HTTP_X_CSRF_TOKEN'];
    } elseif (isset($_POST['csrf'])) {
        $provided = $_POST['csrf'];
    }
    header('Content-Type: application/json');
    if (!isset($csrfToken) || !$provided || $provided !== $csrfToken) {
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token.']);
        exit;
    }
    unset($_SESSION['quick_fix_csv']);
    echo json_encode(['success' => true, 'message' => 'Failed rows CSV cleared from session.']);
    exit;
}

// Handle clearing of quick-fix CSV via POST (requires CSRF) - legacy non-AJAX fallback
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_quick_fix'])) {
    if (!isset($_POST['csrf']) || $_POST['csrf'] !== $csrfToken) {
        $_SESSION['success_message'] = 'Unable to clear failed CSV: invalid session token.';
    } else {
        unset($_SESSION['quick_fix_csv']);
        $_SESSION['success_message'] = 'Failed rows CSV cleared from session.';
    }
    // Redirect to avoid form resubmission
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

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
// Only process regular form submissions (not CSV imports)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_FILES['csv_file'])) {
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
        $nok_phone = $_POST['nok_tel'] ?? '';
        $nok_email = $_POST['nok_email'] ?? '';
        $altnok_phone = $_POST['alt_nok_tel'] ?? '';
        $altnok_email = $_POST['altnok_email'] ?? '';

    // Required fields validation with proper error messages
        $required_fields = [
            'email' => 'Email',
            'fname' => 'First Name',
            'lname' => 'Surname',
            'DOB' => 'Date of Birth',
            'svcNo' => 'Service Number',
            'category' => 'Category',
            'rankID' => 'Rank',
            'nrc' => 'NRC',
            'blood_group' => 'Blood Group'
        ];
        
        foreach ($required_fields as $field => $label) {
            if (empty($_POST[$field]) || trim($_POST[$field]) === '') {
                $form_errors[$field] = $label . ' is required.';
            }
        }
        // NRC validation and auto-formatting (xxxxxx/xx/1 format)
        if ($nrc) {
            // Remove any spaces and normalize
            $nrc = trim(str_replace(' ', '', $nrc));
            
            // Auto-format if only digits provided (e.g., 12345678 -> 123456/78/1)
            if (preg_match('/^(\d{6})(\d{2})$/', $nrc, $matches)) {
                $nrc = $matches[1] . '/' . $matches[2] . '/1';
                $_POST['nrc'] = $nrc;
            }
            // Auto-append /1 if missing (e.g., 123456/78 -> 123456/78/1)
            elseif (preg_match('/^(\d{6}\/\d{2})$/', $nrc)) {
                $nrc = $nrc . '/1';
                $_POST['nrc'] = $nrc;
            }
            // Validate final format
            if (!preg_match('/^\d{6}\/\d{2}\/1$/', $nrc)) {
                $form_errors['nrc'] = 'Invalid NRC format. Expected: 123456/78/1';
            }
        }
        // Email format
        if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $form_errors['email'] = 'Invalid email address.';
        }
        
        // Zambian phone format (+260 + 9 digits) - Auto-formatting
        if ($phone) {
            // Remove all non-digits first to normalize input
            $phone_digits = preg_replace('/[^0-9]/', '', $phone);
            
            // Auto-format based on input pattern
            if (substr($phone_digits, 0, 3) === '260' && strlen($phone_digits) === 12) {
                // Has country code (260XXXXXXXXX) - add + prefix
                $phone = '+' . $phone_digits;
                $_POST['phone'] = $phone;
            } elseif (strlen($phone_digits) === 9 && in_array($phone_digits[0], ['9', '7', '5'])) {
                // Local format (9 digits starting with 9, 7, or 5) - add +260 prefix
                $phone = '+260' . $phone_digits;
                $_POST['phone'] = $phone;
            } elseif (substr($phone_digits, 0, 1) === '0' && strlen($phone_digits) === 10 && in_array($phone_digits[1], ['9', '7', '5'])) {
                // Format with leading 0 (0976123456) - remove 0 and add +260
                $phone = '+260' . substr($phone_digits, 1);
                $_POST['phone'] = $phone;
            } else {
                // Invalid format
                $form_errors['phone'] = 'Invalid phone. Use: 976123456, 0976123456, or +260976123456';
            }
        }
        
    // NOK NRC validation removed
        // NOK phone format (Zambian: +260 + 9 digits) - Auto-formatting
        if ($nok_phone) {
            $nok_phone_digits = preg_replace('/[^0-9]/', '', $nok_phone);
            
            if (substr($nok_phone_digits, 0, 3) === '260' && strlen($nok_phone_digits) === 12) {
                $nok_phone = '+' . $nok_phone_digits;
                $_POST['nok_tel'] = $nok_phone;
            } elseif (strlen($nok_phone_digits) === 9 && in_array($nok_phone_digits[0], ['9', '7', '5'])) {
                $nok_phone = '+260' . $nok_phone_digits;
                $_POST['nok_tel'] = $nok_phone;
            } elseif (substr($nok_phone_digits, 0, 1) === '0' && strlen($nok_phone_digits) === 10 && in_array($nok_phone_digits[1], ['9', '7', '5'])) {
                $nok_phone = '+260' . substr($nok_phone_digits, 1);
                $_POST['nok_tel'] = $nok_phone;
            } else {
                $form_errors['nok_tel'] = 'Invalid NOK phone. Use: 976123456, 0976123456, or +260976123456';
            }
        }
        // NOK email format
        if ($nok_email && !filter_var($nok_email, FILTER_VALIDATE_EMAIL)) {
            $form_errors['nok_email'] = 'Invalid email address for Next of Kin.';
        }
    // ALT NOK NRC validation removed
        // ALT NOK phone format (Zambian: +260 + 9 digits) - Auto-formatting
        if ($altnok_phone) {
            $altnok_phone_digits = preg_replace('/[^0-9]/', '', $altnok_phone);
            
            if (substr($altnok_phone_digits, 0, 3) === '260' && strlen($altnok_phone_digits) === 12) {
                $altnok_phone = '+' . $altnok_phone_digits;
                $_POST['alt_nok_tel'] = $altnok_phone;
            } elseif (strlen($altnok_phone_digits) === 9 && in_array($altnok_phone_digits[0], ['9', '7', '5'])) {
                $altnok_phone = '+260' . $altnok_phone_digits;
                $_POST['alt_nok_tel'] = $altnok_phone;
            } elseif (substr($altnok_phone_digits, 0, 1) === '0' && strlen($altnok_phone_digits) === 10 && in_array($altnok_phone_digits[1], ['9', '7', '5'])) {
                $altnok_phone = '+260' . substr($altnok_phone_digits, 1);
                $_POST['alt_nok_tel'] = $altnok_phone;
            } else {
                $form_errors['alt_nok_tel'] = 'Invalid Alt NOK phone. Use: 976123456, 0976123456, or +260976123456';
            }
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
            $svcNo = $_POST['svcNo'];
            if (!ctype_digit($svcNo) || intval($svcNo) < 1) {
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
            require_once __DIR__ . '/partials/create_staff_handler.php';
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

// CSV Import result variables
$csv_import_success = [];
$csv_import_errors = [];
$csv_import_credentials = [];

// Load import processor classes
require_once __DIR__ . '/lib/ImportProcessor.php';

// Handle CSV/Excel import POST with enhanced validation and transaction management
// Accept either a fresh upload or an "apply_temp" submission which uses the previously stored temp file
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_FILES['csv_file']) || isset($_POST['apply_temp']))) {
    if (!isset($_POST['csrf']) || $_POST['csrf'] !== $csrfToken) {
        $csv_import_errors[] = 'Invalid CSRF token.';
    } else {
        // Determine source file: either the uploaded file or a previously stored temp file (apply_temp)
        $csvFile = null; $fileType = null;
        if (isset($_POST['apply_temp']) && $_POST['apply_temp'] == '1') {
            if (empty($_SESSION['import_temp_file']) || !file_exists($_SESSION['import_temp_file'])) {
                $csv_import_errors[] = 'No uploaded file available to apply. Please re-upload.';
            } else {
                $csvFile = $_SESSION['import_temp_file'];
                $fileType = strtolower(pathinfo($_SESSION['import_temp_name'] ?? $csvFile, PATHINFO_EXTENSION));
            }
        } elseif (isset($_FILES['csv_file'])) {
            if ($_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
                $csv_import_errors[] = 'File upload error: ' . $_FILES['csv_file']['error'];
            } else {
                // Move uploaded file to tmp so it can be re-used for a confirm/apply action
                $origName = basename($_FILES['csv_file']['name']);
                $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
                $tmpName = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'armis_import_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                if (!move_uploaded_file($_FILES['csv_file']['tmp_name'], $tmpName)) {
                    $csv_import_errors[] = 'Failed to move uploaded file to temporary storage.';
                } else {
                    $_SESSION['import_temp_file'] = $tmpName;
                    $_SESSION['import_temp_name'] = $origName;
                    $csvFile = $tmpName;
                    $fileType = $ext;
                }
            }
        }
        
        // Get database connection
        require_once dirname(__DIR__) . '/shared/database_connection.php';
        
        // Create import processor
        $userId = $_SESSION['user_id'] ?? 0;
        $processor = new ImportProcessor($pdo, $userId);

        // Read import options from form
        $importMode = isset($_POST['import_mode']) ? $_POST['import_mode'] : 'skip';
        $dryRun = isset($_POST['dry_run']) && $_POST['dry_run'] === 'on';

        // Rank updates are handled inside the import processor so previews include tempRank -> rankId mapping.

        // Process file based on type
        if ($csvFile && ($fileType === 'csv' || $fileType === 'xlsx' || $fileType === 'xls')) {
            if ($fileType === 'csv') {
                $result = $processor->processCSV($csvFile, $importMode, $dryRun);
            } else {
                $result = $processor->processExcel($csvFile, $importMode, $dryRun);
            }
        } else {
            $result = [
                'success' => [],
                'errors' => ['Unsupported file type. Please upload CSV, XLSX, or XLS files only.'],
                'credentials' => []
            ];
        }
        
        // Extract results
        $csv_import_success = $result['success'] ?? [];
        $csv_import_errors = $result['errors'] ?? [];
        $csv_import_credentials = $result['credentials'] ?? [];
        
        // Store credentials in session for display
        if (!empty($csv_import_credentials)) {
            $_SESSION['import_credentials'] = $csv_import_credentials;
        }

        // Clear dashboard session cache so dashboard reflects recent import changes
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (!empty($csv_import_success) || !empty($rankUpdateRes['affected'])) {
            unset($_SESSION['dashboard_cache']);
            // Mark that dashboard should be refreshed on next view
            $_SESSION['dashboard_refresh_needed'] = true;
        }

        // If this was a real import (not dry run) and it used a temp upload, remove the temp file
        if (!$dryRun && !empty($_SESSION['import_temp_file']) && file_exists($_SESSION['import_temp_file'])) {
            @unlink($_SESSION['import_temp_file']);
            unset($_SESSION['import_temp_file']);
            unset($_SESSION['import_temp_name']);
        }
    }
}

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
        $created_staff_id = $_SESSION['created_staff_id'] ?? 0; // Get staff ID for view profile button
        $display_credentials = true;
        
        // Clear sensitive data from session
        unset($_SESSION['temp_password']);
        unset($_SESSION['username']);
        unset($_SESSION['staff_name']);
        unset($_SESSION['staff_email']);
        unset($_SESSION['created_staff_id']);
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
// Add unified design system CSS
echo '<link rel="stylesheet" href="css/armis-unified.css">';

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
                                <button type="button" class="btn btn-outline-secondary btn-sm" id="clearFormBtn">
                                    <i class="fa fa-refresh"></i> Clear Form
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
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <h6 class="mb-0">
                                            <i class="fas fa-key text-primary"></i> 
                                            <strong>Temporary Login Credentials</strong>
                                        </h6>
                                        <button class="btn btn-sm btn-primary" onclick="copyAllCredentials('<?= htmlspecialchars($username) ?>', '<?= htmlspecialchars($temp_password) ?>', '<?= htmlspecialchars($staff_name) ?>')">
                                            <i class="fas fa-copy me-1"></i> Copy All
                                        </button>
                                    </div>
                                    <?php if (!empty($staff_name)): ?>
                                    <p class="mb-2"><strong>Staff Member:</strong> <span class="text-primary"><?= htmlspecialchars($staff_name) ?></span></p>
                                    <?php endif; ?>
                                    <?php if (!empty($staff_email)): ?>
                                    <p class="mb-2"><strong>Email:</strong> <span class="text-primary"><?= htmlspecialchars($staff_email) ?></span></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="row mt-3">
                                <div class="col-md-6 mb-3 mb-md-0">
                                    <div class="border border-primary rounded p-3 h-100" style="background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <strong class="text-primary">
                                                <i class="fas fa-user me-1"></i> Username
                                            </strong>
                                            <button class="btn btn-sm btn-outline-primary" onclick="copyToClipboard('<?= htmlspecialchars($username) ?>')" title="Copy username">
                                                <i class="fas fa-copy"></i>
                                            </button>
                                        </div>
                                        <code class="fs-5 text-dark d-block p-2 bg-white rounded border" style="word-break: break-all;">
                                            <?= htmlspecialchars($username) ?>
                                        </code>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="border border-success rounded p-3 h-100" style="background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <strong class="text-success">
                                                <i class="fas fa-lock me-1"></i> Temporary Password
                                            </strong>
                                            <button class="btn btn-sm btn-outline-success" onclick="copyToClipboard('<?= htmlspecialchars($temp_password) ?>')" title="Copy password">
                                                <i class="fas fa-copy"></i>
                                            </button>
                                        </div>
                                        <code class="fs-5 text-dark d-block p-2 bg-white rounded border" style="word-break: break-all;">
                                            <?= htmlspecialchars($temp_password) ?>
                                        </code>
                                    </div>
                                </div>
                            </div>
                            <div class="alert alert-warning mt-3 mb-0 border-warning">
                                <div class="d-flex align-items-start">
                                    <i class="fas fa-exclamation-triangle me-2 mt-1 fs-5"></i>
                                    <div>
                                        <strong class="d-block mb-2">Important Security Notice:</strong>
                                        <ul class="mb-0 ps-3">
                                            <li class="mb-1"><strong>First Login:</strong> User must change this password on first login</li>
                                            <li class="mb-1"><strong>Email Delivery:</strong> Credentials will be sent to user's email (<?= htmlspecialchars($staff_email) ?>)</li>
                                            <li class="mb-1"><strong>Secure Storage:</strong> Copy and store these credentials securely until user logs in</li>
                                            <li><strong>One-Time Display:</strong> These credentials will not be shown again after closing this alert</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Action Buttons -->
                            <div class="mt-3 d-flex gap-2">
                                <?php if (isset($created_staff_id) && $created_staff_id > 0): ?>
                                <a href="view_staff.php?id=<?= $created_staff_id ?>" class="btn btn-primary btn-sm">
                                    <i class="fa fa-user"></i> View Profile
                                </a>
                                <?php endif; ?>
                                <a href="create_staff.php" class="btn btn-secondary btn-sm">
                                    <i class="fa fa-user-plus"></i> Create Another Staff
                                </a>
                            </div>
                            
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
                        
                        <!-- CSV/Excel Import Form with Enhanced Features -->
                        <div class="dashboard-card mb-4">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5 class="mb-0"><i class="fas fa-file-csv"></i> Bulk Import Staff (CSV/Excel)</h5>
                                <button class="btn btn-outline-info btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#lookupTables">
                                    <i class="fa fa-table"></i> Show Reference Tables
                                </button>
                            </div>
                            
                            <!-- Reference Tables (Collapsible) -->
                            <div class="collapse mb-3" id="lookupTables">
                                <div class="alert alert-info">
                                    <h6><i class="fa fa-info-circle"></i> Reference Tables for Import (Use these IDs)</h6>
                                    <div class="row">
                                        <div class="col-md-4">
                                            <strong>Ranks:</strong>
                                            <div style="max-height: 200px; overflow-y: auto;">
                                                <table class="table table-sm table-bordered">
                                                    <thead><tr><th>ID</th><th>Rank</th><th>Abbr</th></tr></thead>
                                                    <tbody>
                                                    <?php
                                                    // Use ranks loaded by partial (create_staff_config.php) where possible.
                                                    // The partial provides $ranks as an array of objects; support multiple shapes for compatibility.
                                                    if (!empty($ranks)) {
                                                        foreach ($ranks as $r) {
                                                            // Support both object and associative array shapes safely
                                                            if (is_object($r)) {
                                                                $id = $r->rankID ?? ($r->id ?? '');
                                                                $name = $r->rankName ?? ($r->name ?? '');
                                                                $abbr = $r->abbreviation ?? '';
                                                            } elseif (is_array($r)) {
                                                                $id = $r['rankID'] ?? ($r['id'] ?? '');
                                                                $name = $r['rankName'] ?? ($r['name'] ?? '');
                                                                $abbr = $r['abbreviation'] ?? '';
                                                            } else {
                                                                $id = '';
                                                                $name = '';
                                                                $abbr = '';
                                                            }
                                                            echo "<tr><td>" . htmlspecialchars($id) . "</td><td>" . htmlspecialchars($name) . "</td><td>" . htmlspecialchars($abbr) . "</td></tr>";
                                                        }
                                                    } else {
                                                        // Fallback to a safe query with try/catch in case partial failed to load ranks
                                                        try {
                                                            require_once dirname(__DIR__) . '/shared/database_connection.php';
                                                            $ranksStmt = $pdo->query("SELECT rankId as id, rankId as name, rankId as rankId as abbreviation FROM rank ORDER BY level ASC");
                                                            while ($r = $ranksStmt->fetch(PDO::FETCH_ASSOC)) {
                                                                echo "<tr><td>" . htmlspecialchars($r['id']) . "</td><td>" . htmlspecialchars($r['name']) . "</td><td>" . htmlspecialchars($r['abbreviation']) . "</td></tr>";
                                                            }
                                                        } catch (Exception $e) {
                                                            error_log('create_staff: ranks lookup failed: ' . $e->getMessage());
                                                            // show nothing to avoid fatal errors
                                                        }
                                                    }
                                                    ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <strong>Units:</strong>
                                            <div style="max-height: 200px; overflow-y: auto;">
                                                <table class="table table-sm table-bordered">
                                                    <thead><tr><th>ID</th><th>Unit</th><th>Code</th></tr></thead>
                                                    <tbody>
                                                    <?php
                                                    // Use units loaded by partial (create_staff_config.php) where possible.
                                                    if (!empty($units)) {
                                                        foreach ($units as $u) {
                                                            if (is_object($u)) {
                                                                $id = $u->unitID ?? ($u->id ?? '');
                                                                $name = $u->unitName ?? ($u->name ?? '');
                                                                $code = $u->unitCode ?? ($u->code ?? '');
                                                            } elseif (is_array($u)) {
                                                                $id = $u['unitID'] ?? ($u['id'] ?? '');
                                                                $name = $u['unitName'] ?? ($u['name'] ?? '');
                                                                $code = $u['unitCode'] ?? ($u['code'] ?? '');
                                                            } else {
                                                                $id = '';
                                                                $name = '';
                                                                $code = '';
                                                            }
                                                            echo "<tr><td>" . htmlspecialchars($id) . "</td><td>" . htmlspecialchars($name) . "</td><td>" . htmlspecialchars($code) . "</td></tr>";
                                                        }
                                                    } else {
                                                        try {
                                                            $unitsStmt = $pdo->query("SELECT unitId as id, code as name, code FROM unit ORDER BY code ASC");
                                                            while ($u = $unitsStmt->fetch(PDO::FETCH_ASSOC)) {
                                                                echo "<tr><td>" . htmlspecialchars($u['id']) . "</td><td>" . htmlspecialchars($u['name']) . "</td><td>" . htmlspecialchars($u['code']) . "</td></tr>";
                                                            }
                                                        } catch (Exception $e) {
                                                            error_log('create_staff: units lookup failed: ' . $e->getMessage());
                                                            // silent fallback
                                                        }
                                                    }
                                                    ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <strong>Corps:</strong>
                                            <div style="max-height: 200px; overflow-y: auto;">
                                                <table class="table table-sm table-bordered">
                                                    <thead><tr><th>ID</th><th>Corps</th></tr></thead>
                                                    <tbody>
                                                    <?php
                                                    // Use $corps populated by partial when available
                                                    if (!empty($corps)) {
                                                        foreach ($corps as $c) {
                                                            if (is_object($c)) {
                                                                $id = $c->corpsID ?? ($c->id ?? ($c->corpsID ?? ''));
                                                                $name = $c->corpsName ?? ($c->name ?? ($c->corps ?? ''));
                                                            } elseif (is_array($c)) {
                                                                $id = $c['corpsID'] ?? ($c['id'] ?? ($c['corpsID'] ?? ''));
                                                                $name = $c['corpsName'] ?? ($c['name'] ?? ($c['corps'] ?? ''));
                                                            } else {
                                                                $id = '';
                                                                $name = '';
                                                            }
                                                            echo "<tr><td>" . htmlspecialchars($id) . "</td><td>" . htmlspecialchars($name) . "</td></tr>";
                                                        }
                                                    } else {
                                                        try {
                                                            // corps table uses corpsId as primary key and abbreviation for name
                                                            $corpsStmt = $pdo->query("SELECT corpsId as id, abbreviation as name FROM corps ORDER BY abbreviation ASC");
                                                            while ($c = $corpsStmt->fetch(PDO::FETCH_ASSOC)) {
                                                                echo "<tr><td>" . htmlspecialchars($c['id']) . "</td><td>" . htmlspecialchars($c['name']) . "</td></tr>";
                                                            }
                                                        } catch (Exception $e) {
                                                            error_log('create_staff: corps lookup failed: ' . $e->getMessage());
                                                        }
                                                    }
                                                    ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row mt-2">
                                        <div class="col-md-6">
                                            <strong>Valid Genders:</strong> Male, Female<br>
                                            <strong>Valid Marital Status:</strong> Single, Married, Divorced, Widowed, Separated
                                        </div>
                                        <div class="col-md-6">
                                            <strong>Valid Blood Groups:</strong> A+, A-, B+, B-, AB+, AB-, O+, O-<br>
                                            <strong>Date Format:</strong> YYYY-MM-DD (e.g., 2015-06-30)
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <form method="post" enctype="multipart/form-data" class="mb-3" id="importForm">
                                <input type="hidden" name="csrf" value="<?=htmlspecialchars($csrfToken)?>">
                                
                                <div class="row">
                                    <div class="col-md-8">
                                        <label for="csv_file" class="form-label">Select File:</label>
                                        <input type="file" id="csv_file" name="csv_file" accept=".csv,.xlsx,.xls" required class="form-control">
                                        <small class="text-muted">
                                            Supported: CSV (.csv), Excel (.xlsx, .xls) | Max size: 5MB
                                        </small>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label d-block">&nbsp;</label>
                                        
                                        <div class="mt-2">
                                            <label for="importMode" class="form-label">Import Mode</label>
                                            <select name="import_mode" id="importMode" class="form-select">
                                                <option value="skip" selected>Skip existing (safe)</option>
                                                <option value="update">Update existing (overwrite)</option>
                                                <option value="merge">Merge existing (selective fields)</option>
                                            </select>
                                        </div>
                                        <div class="form-check mt-2">
                                            <input class="form-check-input" type="checkbox" name="dry_run" id="dryRun">
                                            <label class="form-check-label" for="dryRun">Dry Run (preview only, no DB changes)</label>
                                        </div>
                                        <small class="text-muted">Check file for errors without saving</small>
                                    </div>
                                </div>
                                
                                <div class="mt-3">
                                    <button type="submit" class="btn btn-success">
                                        <i class="fas fa-upload"></i> Import Staff
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary" onclick="downloadEnhancedTemplate()">
                                        <i class="fa fa-download"></i> Download Template (with Examples)
                                    </button>
                                </div>
                                
                                <div class="alert alert-warning mt-3 mb-0">
                                    <h6><i class="fa fa-exclamation-triangle"></i> Important Notes:</h6>
                                    <ul class="mb-0">
                                        <li><strong>Required Fields:</strong> fornames, surnames, email, dob</li>
                                        <li><strong>Transaction Mode:</strong> If ANY row fails validation, NO rows will be imported (all-or-nothing)</li>
                                        <li><strong>Duplicate Check:</strong> Service numbers, emails, and NRCs must be unique</li>
                                        <li><strong>User Accounts:</strong> Login accounts will be automatically created for all imported staff</li>
                                        <li><strong>Date Format:</strong> All dates must be in YYYY-MM-DD format (e.g., 1990-05-15)</li>
                                        <li><strong>NRC Format:</strong> Must be 123456/78/9 (6 digits, slash, 2 digits, slash, 1 digit)</li>
                                    </ul>
                                </div>
                            </form>
                            
                            <script>
                            function downloadEnhancedTemplate() {
                                const cols = [
                                    'service number', 'rankId', 'fornames', 'surnames', 'email', 'dob', 
                                    'gender', 'marital', 'NRC', 'tel', 'unitId', 
                                    'corpsId', 'attestDate', 'subWef', 'tempWef', 'province', 
                                    'bloodGp', 'intake', 'prefix', 'initials', 'titles', 
                                    'subRank', 'tempRank', 'unitAtt', 'appt'
                                ];
                                
                                // Header row
                                let csv = cols.join(',') + '\n';
                                
                                // Example row with proper formats
                                const example = [
                                    '12345',                  // service number
                                    '5',                      // rankId (see reference table)
                                    'John',                   // fornames (REQUIRED)
                                    'Banda',                  // surnames (REQUIRED)
                                    'john.banda@army.zm',     // email (REQUIRED)
                                    '1990-05-15',             // dob (REQUIRED - YYYY-MM-DD)
                                    'Male',                   // gender (Male/Female)
                                    'Married',                // marital (Single/Married/Divorced/Widowed/Separated)
                                    '123456/78/1',            // NRC (123456/78/9 format)
                                    '+260977123456',          // tel (+260XXXXXXXXX)
                                    '3',                      // unitId (see reference table)
                                    '2',                      // corpsId (see reference table)
                                    '2015-06-01',             // attestDate (YYYY-MM-DD)
                                    '2020-01-01',             // subWef (YYYY-MM-DD)
                                    '',                       // tempWef (YYYY-MM-DD or leave blank)
                                    'Lusaka',                 // province
                                    'A+',                     // bloodGp (A+, A-, B+, B-, AB+, AB-, O+, O-)
                                    '2015',                   // intake
                                    'Mr',                     // prefix
                                    'J.K.',                   // initials
                                    'BA',                     // titles/qualifications
                                    '',                       // subRank
                                    '',                       // tempRank
                                    '',                       // unitAtt
                                    'Officer'                 // appt (appointment)
                                ];
                                
                                csv += example.map(v => '"' + v + '"').join(',') + '\n';
                                
                                // Instructions
                                csv += '\n# INSTRUCTIONS:\n';
                                csv += '# 1. REQUIRED FIELDS: fornames, surnames, service number (email and dob are optional)\n';
                                csv += '# 2. DATE FORMAT: YYYY-MM-DD (e.g., 2015-06-30)\n';
                                csv += '# 3. NRC FORMAT: 123456/78/9 (exactly this pattern)\n';
                                csv += '# 4. PHONE FORMAT: +260977123456 (include country code)\n';
                                csv += '# 5. Use IDs from reference tables for rankId, unitId, corpsId\n';
                                csv += '# 6. Delete this instruction section and the example row before importing\n';
                                csv += '# 7. Keep the header row (first row with column names)\n';
                                
                                const blob = new Blob([csv], {type: 'text/csv;charset=utf-8;'});
                                const url = URL.createObjectURL(blob);
                                const a = document.createElement('a');
                                a.href = url;
                                a.download = 'staff_import_template_with_example.csv';
                                document.body.appendChild(a);
                                a.click();
                                document.body.removeChild(a);
                                URL.revokeObjectURL(url);
                            }
                            </script>
                            
                            <!-- Import Results -->
                            <?php if (!empty($csv_import_success)): ?>
                                <div class="alert alert-success alert-dismissible fade show">
                                    <h6><i class="fas fa-check-circle"></i> Import Successful!</h6>
                                    <strong><?= count($csv_import_success) ?> staff members imported successfully.</strong>
                                    <details class="mt-2">
                                        <summary style="cursor: pointer;">View Details</summary>
                                        <ul class="mb-0 mt-2">
                                            <?php foreach (array_slice($csv_import_success, 0, 10) as $msg): ?>
                                                <li><?= htmlspecialchars($msg) ?></li>
                                            <?php endforeach; ?>
                                            <?php if (count($csv_import_success) > 10): ?>
                                                <li><em>... and <?= count($csv_import_success) - 10 ?> more</em></li>
                                            <?php endif; ?>
                                        </ul>
                                    </details>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                                
                                <!-- User Credentials Display -->
                                <?php if (!empty($csv_import_credentials)): ?>
                                <div class="alert alert-info alert-dismissible fade show">
                                    <h6><i class="fas fa-key"></i> Generated User Credentials</h6>
                                    <p><strong>Important:</strong> Save these credentials securely. Users must change passwords on first login.</p>
                                    <div style="max-height: 300px; overflow-y: auto;">
                                        <table class="table table-sm table-bordered bg-white">
                                            <thead>
                                                <tr>
                                                    <th>Name</th>
                                                    <th>Email</th>
                                                    <th>Username</th>
                                                    <th>Temporary Password</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($csv_import_credentials as $cred): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($cred['name']) ?></td>
                                                    <td><?= htmlspecialchars($cred['email']) ?></td>
                                                    <td><code><?= htmlspecialchars($cred['username']) ?></code></td>
                                                    <td>
                                                        <code><?= htmlspecialchars($cred['password']) ?></code>
                                                        <button class="btn btn-sm btn-outline-secondary" onclick="copyToClipboard('<?= htmlspecialchars($cred['password']) ?>')">
                                                            <i class="fa fa-copy"></i>
                                                        </button>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    <button type="button" class="btn btn-warning btn-sm mt-2" onclick="downloadCredentials()">
                                        <i class="fa fa-download"></i> Download Credentials as CSV
                                    </button>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                                
                                <script>
                                function downloadCredentials() {
                                    let csv = 'Name,Email,Username,Temporary Password\n';
                                    <?php foreach ($csv_import_credentials as $cred): ?>
                                    csv += '<?= addslashes($cred['name']) ?>,<?= addslashes($cred['email']) ?>,<?= addslashes($cred['username']) ?>,<?= addslashes($cred['password']) ?>\n';
                                    <?php endforeach; ?>
                                    
                                    const blob = new Blob([csv], {type: 'text/csv'});
                                    const url = URL.createObjectURL(blob);
                                    const a = document.createElement('a');
                                    a.href = url;
                                    a.download = 'imported_staff_credentials_<?= date("Y-m-d_His") ?>.csv';
                                    document.body.appendChild(a);
                                    a.click();
                                    document.body.removeChild(a);
                                    URL.revokeObjectURL(url);
                                }
                                </script>
                                <?php endif; ?>
                            <?php endif; ?>
                            <?php if (!empty($result) && !empty($result['preview'])): ?>
                                <div class="alert alert-secondary mt-3">
                                    <h6><i class="fas fa-eye"></i> Import Preview</h6>
                                    <p>Dry run preview (no changes applied):</p>
                                    <ul>
                                        <li>Would insert: <?= intval($result['preview']['would_insert'] ?? 0) ?></li>
                                        <li>Would update: <?= intval($result['preview']['would_update'] ?? 0) ?></li>
                                        <li>Would skip: <?= intval($result['preview']['would_skip'] ?? 0) ?></li>
                                    </ul>
                                </div>
                                <?php if (isset($dryRun) && $dryRun): ?>
                                    <form method="post" class="mt-2" onsubmit="return confirm('Proceed with importing the file? This will apply the changes.');">
                                        <input type="hidden" name="csrf" value="<?=htmlspecialchars($csrfToken)?>">
                                        <input type="hidden" name="apply_temp" value="1">
                                        <input type="hidden" name="import_mode" value="<?=htmlspecialchars($importMode ?? 'skip')?>">
                                        <button type="submit" class="btn btn-primary"><i class="fa fa-play"></i> Proceed with Import</button>
                                    </form>
                                <?php endif; ?>
                                <?php if (!empty($result['rank_update'])): ?>
                                    <div class="mt-2">
                                        <?php $ru = $result['rank_update']; ?>
                                        <?php if (!empty($ru['affected'])): ?>
                                            <div class="alert alert-info mt-2">
                                                <strong>Rank Update:</strong> <?=intval($ru['affected'])?> rows would be updated by tempRank mapping.
                                                <?php if (!empty($ru['backup'])): 
                                                    $bn = basename($ru['backup']);
                                                    $link = '/Armis2/tmp/' . rawurlencode($bn);
                                                ?>
                                                    Backup: <a href="<?= htmlspecialchars($link) ?>" download><?=htmlspecialchars($bn)?></a>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                            
                            <?php if (!empty($csv_import_errors)): ?>
                                <div class="alert alert-danger alert-dismissible fade show">
                                    <h6><i class="fas fa-exclamation-triangle"></i> Import Completed With Errors</h6>
                                    <p><strong>Some rows failed validation and were not imported.</strong></p>
                                    <p>Please fix the following errors and try again:</p>
                                    <div style="max-height: 400px; overflow-y: auto; background: #fff; padding: 10px; border-radius: 4px;">
                                        <ul class="mb-0">
                                            <?php foreach ($csv_import_errors as $msg): ?>
                                                <li><?= htmlspecialchars($msg) ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                    <div class="mt-3">
                                        <strong>Quick Fixes:</strong>
                                        <ul class="mb-0">
                                            <li>Check that all required fields (fornames, surnames, email, dob) are filled</li>
                                            <li>Verify date format is YYYY-MM-DD (e.g., 1990-05-15)</li>
                                            <li>Ensure emails are valid and unique</li>
                                            <li>Check that rankId, unitId, corpsId exist in reference tables</li>
                                            <li>Verify NRC format is 123456/78/9</li>
                                        </ul>
                                    </div>
                                    <?php if (!empty($_SESSION['quick_fix_csv'])): ?>
                                    <div class="mt-3 d-flex gap-2">
                                        <button type="button" id="btnDownloadFailedCsv" class="btn btn-secondary">
                                            <i class="fa fa-download"></i> Download failed rows CSV for correction
                                        </button>
                                        <button type="button" id="btnClearFailedCsv" data-csrf="<?=htmlspecialchars($csrfToken)?>" class="btn btn-outline-danger">
                                            <i class="fa fa-trash"></i> Clear failed CSV
                                        </button>
                                    </div>
                                    <?php endif; ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                            <?php endif; ?>
                        </div>

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
                                    <button type="submit" class="btn btn-armis-primary px-4 py-2" id="submitBtn" title="Submit the staff registration form">
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
                    // Try modern clipboard API first
                    if (navigator.clipboard && window.isSecureContext) {
                        navigator.clipboard.writeText(text).then(function() {
                            showCopySuccess();
                        }).catch(function(err) {
                            console.error('Clipboard API failed, using fallback: ', err);
                            copyToClipboardFallback(text);
                        });
                    } else {
                        // Fallback for older browsers or non-HTTPS
                        copyToClipboardFallback(text);
                    }
                }
                
                function copyToClipboardFallback(text) {
                    // Create a temporary textarea element
                    const textarea = document.createElement('textarea');
                    textarea.value = text;
                    textarea.style.position = 'fixed';
                    textarea.style.opacity = '0';
                    document.body.appendChild(textarea);
                    
                    // Select and copy the text
                    textarea.select();
                    textarea.setSelectionRange(0, 99999); // For mobile devices
                    
                    try {
                        const successful = document.execCommand('copy');
                        if (successful) {
                            showCopySuccess();
                        }
                    } catch (err) {
                        console.error('Failed to copy text: ', err);
                        alert('Failed to copy to clipboard. Please copy manually.');
                    }
                    
                    // Remove the temporary element
                    document.body.removeChild(textarea);
                }
                
                function showCopySuccess(message = 'Text copied to clipboard successfully.') {
                    // Create a temporary toast notification
                    const toast = document.createElement('div');
                    toast.className = 'position-fixed bottom-0 end-0 p-3';
                    toast.style.zIndex = '11';
                    toast.innerHTML = `
                        <div class="toast show" role="alert">
                            <div class="toast-header bg-success text-white">
                                <i class="fas fa-check-circle me-2"></i>
                                <strong class="me-auto">Copied!</strong>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast"></button>
                            </div>
                            <div class="toast-body">
                                ${message}
                            </div>
                        </div>
                    `;
                    document.body.appendChild(toast);
                    
                    // Auto-remove after 3 seconds
                    setTimeout(() => {
                        toast.remove();
                    }, 3000);
                }
                
                function copyAllCredentials(username, password, staffName) {
                    const credentials = `ARMIS Login Credentials
=========================

Staff Member: ${staffName}
Username: ${username}
Temporary Password: ${password}

IMPORTANT:
- User must change password on first login
- Keep these credentials secure
- Credentials sent to user's email

Generated: ${new Date().toLocaleString()}
`;
                    
                    // Try modern clipboard API first
                    if (navigator.clipboard && window.isSecureContext) {
                        navigator.clipboard.writeText(credentials).then(function() {
                            showCopySuccess('All credentials copied to clipboard!');
                        }).catch(function(err) {
                            console.error('Clipboard API failed: ', err);
                            copyToClipboardFallback(credentials);
                        });
                    } else {
                        copyToClipboardFallback(credentials);
                    }
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
                <script>
                (function(){
                    async function fetchFailedCsv() {
                        try {
                            const res = await fetch('?ajax_get_quick_fix=1', { credentials: 'same-origin' });
                            const data = await res.json();
                            if (!data || !data.success) {
                                alert(data && data.message ? data.message : 'No failed CSV available.');
                                return;
                            }
                            const csvText = atob(data.csv_base64);
                            const blob = new Blob([csvText], { type: 'text/csv;charset=utf-8;' });
                            const url = URL.createObjectURL(blob);
                            const a = document.createElement('a');
                            a.href = url;
                            a.download = data.filename || 'failed_rows.csv';
                            document.body.appendChild(a);
                            a.click();
                            a.remove();
                            URL.revokeObjectURL(url);
                        } catch (err) {
                            console.error('fetchFailedCsv error', err);
                            alert('Error fetching failed CSV: ' + (err.message || err));
                        }
                    }

                    async function clearFailedCsv(csrf) {
                        if (!confirm('Clear the failed rows CSV from session? This cannot be undone.')) return;
                        try {
                            const params = new URLSearchParams();
                            params.append('csrf', csrf || '');
                            const res = await fetch('?ajax_clear_quick_fix=1', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                                body: params.toString(),
                                credentials: 'same-origin'
                            });
                            const data = await res.json();
                                    if (data && data.success) {
                                        // Update UI without reload: disable buttons and show temporary success toast
                                        alert(data.message || 'Cleared failed CSV.');
                                        const dlBtn = document.getElementById('btnDownloadFailedCsv');
                                        const clrBtn = document.getElementById('btnClearFailedCsv');
                                        if (dlBtn) {
                                            dlBtn.disabled = true;
                                            dlBtn.innerHTML = '<i class="fa fa-download"></i> No failed rows';
                                        }
                                        if (clrBtn) {
                                            clrBtn.disabled = true;
                                            clrBtn.innerHTML = '<i class="fa fa-check"></i> Cleared';
                                        }
                                        // Also hide the Quick Fix notes container if present
                                        const quickFixContainer = clrBtn ? clrBtn.closest('.mt-3') : null;
                                        if (quickFixContainer) quickFixContainer.classList.add('opacity-50');
                                    } else {
                                        alert(data && data.message ? data.message : 'Failed to clear failed CSV.');
                                    }
                        } catch (err) {
                            console.error('clearFailedCsv error', err);
                            alert('Error clearing failed CSV: ' + (err.message || err));
                        }
                    }

                    document.addEventListener('DOMContentLoaded', function() {
                        const dl = document.getElementById('btnDownloadFailedCsv');
                        if (dl) dl.addEventListener('click', fetchFailedCsv);
                        const clr = document.getElementById('btnClearFailedCsv');
                        if (clr) clr.addEventListener('click', function() { clearFailedCsv(this.dataset.csrf); });
                    });
                })();
                </script>
            </div>
            </div>
        </div>
    </div>
</div>
</div></div>

<?php include dirname(__DIR__) . '/shared/footer.php'; ?>
