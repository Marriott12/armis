<?php
// Define module constants
define('ARMIS_ADMIN_BRANCH', true);
define('ARMIS_DEVELOPMENT', true);

// Enable detailed error reporting and logging
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', dirname(__DIR__) . '/logs/appointments_errors.log');
error_reporting(E_ALL);

// Log script start
error_log("APPOINTMENTS: Script started at " . date('Y-m-d H:i:s'));

// Include admin branch authentication and database
try {
    error_log("APPOINTMENTS: Including auth.php");
    require_once __DIR__ . '/includes/auth.php';
    error_log("APPOINTMENTS: Auth included successfully");
    
    error_log("APPOINTMENTS: Including database connection");
    require_once dirname(__DIR__) . '/shared/database_connection.php';
    error_log("APPOINTMENTS: Database connection included successfully");
} catch (Exception $e) {
    error_log("APPOINTMENTS CRITICAL ERROR: Unable to load required files - " . $e->getMessage());
    error_log("APPOINTMENTS CRITICAL ERROR: File: " . $e->getFile() . " Line: " . $e->getLine());
    die("Critical Error: Unable to load required files - " . htmlspecialchars($e->getMessage()));
}

// Require authentication
try {
    error_log("APPOINTMENTS: Checking authentication");
    requireAuth();
    error_log("APPOINTMENTS: Authentication successful");
} catch (Exception $e) {
    error_log("APPOINTMENTS AUTH ERROR: " . $e->getMessage());
    error_log("APPOINTMENTS AUTH ERROR: File: " . $e->getFile() . " Line: " . $e->getLine());
    die("Authentication Error: " . htmlspecialchars($e->getMessage()));
}

// Generate CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$pageTitle = "Appointments - Admin Branch";
$moduleName = "Admin Branch";
$moduleIcon = "users-cog";
$currentPage = "appointments";

// Sidebar navigation
$sidebarLinks = [
    ['title' => 'Dashboard', 'url' => '/Armis2/admin_branch/index.php', 'icon' => 'tachometer-alt', 'page' => 'dashboard'],
    ['title' => 'Staff Management', 'url' => '/Armis2/admin_branch/edit_staff.php', 'icon' => 'users', 'page' => 'staff'],
    ['title' => 'Create Staff', 'url' => '/Armis2/admin_branch/create_staff.php', 'icon' => 'user-plus', 'page' => 'create'],
    ['title' => 'Promotions', 'url' => '/Armis2/admin_branch/promote_staff.php', 'icon' => 'arrow-up', 'page' => 'promotions'],
    ['title' => 'Appointments', 'url' => '/Armis2/admin_branch/appointments.php', 'icon' => 'user-tie', 'page' => 'appointments'],
    ['title' => 'Medals', 'url' => '/Armis2/admin_branch/assign_medal.php', 'icon' => 'medal', 'page' => 'medals'],
    [
        'title' => 'Seniority Rolls',
        'icon' => 'users',
        'page' => 'seniority',
        'children' => [
            ['title' => 'Officer Seniority', 'url' => '/Armis2/admin_branch/reports_seniority.php?report_type=officer'],
            ['title' => 'NCO Seniority', 'url' => '/Armis2/admin_branch/reports_nco_seniority.php?report_type=nco'],
            ['title' => 'CE Seniority', 'url' => '/Armis2/admin_branch/reports_ce_seniority.php?report_type=ce'],
        ]
    ],
    [
        'title' => 'Norminal Rolls',
        'icon' => 'bars',
        'page' => 'norminal',
        'children' => [
            ['title' => 'Officer Norminal Roll', 'url' => '/Armis2/admin_branch/reports_officer_norminal.php?report_type=officer'],
            ['title' => 'NCO Norminal Roll', 'url' => '/Armis2/admin_branch/reports_nco_norminal.php?report_type=nco'],
            ['title' => 'CE Norminal Roll', 'url' => '/Armis2/admin_branch/reports_ce_norminal.php?report_type=ce'],
        ]
    ],
    [
        'title' => 'Reports',
        'icon' => 'chart-bar',
        'page' => 'reports',
        'children' => [
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
        ]
    ],
];

// Use PDO for all DB operations with caching
try {
    $pdo = getDbConnection();
    if (!$pdo) {
        throw new Exception("Failed to establish database connection");
    }
} catch (Exception $e) {
    die("Database Connection Error: " . htmlspecialchars($e->getMessage()));
}

$ranks = [];
$units = [];

// Initialize session cache if not exists
if (!isset($_SESSION['dropdown_cache'])) {
    $_SESSION['dropdown_cache'] = [];
}

try {
    error_log("APPOINTMENTS: Starting dropdown data fetch");
    
    // Check if ranks are cached and still valid (5 minutes)
    $cache_key = 'ranks_data';
    $cache_timeout = 300; // 5 minutes
    
    if (isset($_SESSION['dropdown_cache'][$cache_key]) && 
        time() - $_SESSION['dropdown_cache'][$cache_key]['timestamp'] < $cache_timeout) {
        $ranks = $_SESSION['dropdown_cache'][$cache_key]['data'];
        error_log("APPOINTMENTS: Using cached ranks data");
    } else {
        error_log("APPOINTMENTS: Fetching ranks from database");
        // Get all ranks ordered by rank level (seniority)
        $ranksStmt = $pdo->query("SELECT id as rankID, name as rankName, level as rankIndex FROM ranks ORDER BY rankIndex ASC");
        $ranks = $ranksStmt->fetchAll(PDO::FETCH_OBJ);
        error_log("APPOINTMENTS: Fetched " . count($ranks) . " ranks successfully");
        
        // Cache the results
        $_SESSION['dropdown_cache'][$cache_key] = [
            'data' => $ranks,
            'timestamp' => time()
        ];
    }
    
    // Check if units are cached and still valid
    $cache_key = 'units_data';
    
    if (isset($_SESSION['dropdown_cache'][$cache_key]) && 
        time() - $_SESSION['dropdown_cache'][$cache_key]['timestamp'] < $cache_timeout) {
        $units = $_SESSION['dropdown_cache'][$cache_key]['data'];
        error_log("APPOINTMENTS: Using cached units data");
    } else {
        error_log("APPOINTMENTS: Fetching units from database");
        // Get all units ordered by name
        $unitsStmt = $pdo->query("SELECT id as unitID, name as unitName FROM units ORDER BY unitName ASC");
        $units = $unitsStmt->fetchAll(PDO::FETCH_OBJ);
        error_log("APPOINTMENTS: Fetched " . count($units) . " units successfully");
        
        // Cache the results
        $_SESSION['dropdown_cache'][$cache_key] = [
            'data' => $units,
            'timestamp' => time()
        ];
    }
    
    error_log("APPOINTMENTS: Fetching rank counts");
    // Count total staff at each rank for display (not cached as it changes frequently)
    $rankCounts = [];
    $rankCountStmt = $pdo->query("SELECT rank_id, COUNT(*) as count FROM staff WHERE svcStatus = 'Active' GROUP BY rank_id");
    while ($row = $rankCountStmt->fetch(PDO::FETCH_ASSOC)) {
        $rankCounts[$row['rank_id']] = $row['count'];
    }
    error_log("APPOINTMENTS: Rank counts fetched successfully");
    
} catch (Exception $e) {
    error_log("APPOINTMENTS DATABASE ERROR: " . $e->getMessage());
    error_log("APPOINTMENTS DATABASE ERROR: File: " . $e->getFile() . " Line: " . $e->getLine());
    $errors[] = "Error fetching ranks or units: " . htmlspecialchars($e->getMessage());
    error_log("Dropdown data fetch error: " . $e->getMessage());
}

// Exclude Officer Cadet, Recruit, and CE ranks (Mister, Miss)
$excludedRanks = ['Officer Cadet', 'Recruit', 'Mister', 'Miss'];
$excludedRankIds = array_map(function($r) use ($excludedRanks) {
    return in_array($r->rankName, $excludedRanks) ? $r->rankID : null;
}, $ranks);
$excludedRankIds = array_filter($excludedRankIds);

$errors = [];
$success = false;

// Step 1: Select current rank
$currentRankId = $_POST['current_rank'] ?? $_GET['current_rank'] ?? '';
$currentRank = null;
if ($currentRankId) {
    $stmt = $pdo->prepare("SELECT * FROM ranks WHERE id = ? LIMIT 1");
    $stmt->execute([$currentRankId]);
    $currentRank = $stmt->fetch(PDO::FETCH_OBJ);
}

// Step 2: Handle form submission for appointments
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['appoint_staff'])) {
    error_log("APPOINTMENTS: Form submission received");
    
    // CSRF Protection
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
        error_log("APPOINTMENTS: CSRF token validation failed");
        $errors[] = "Invalid security token. Please refresh the page and try again.";
    }
    
    if (empty($errors)) {
        error_log("APPOINTMENTS: Processing form data");
        $selectedStaff = $_POST['selected_staff'] ?? [];
        $appointmentTypeId = $_POST['appointment_type'] ?? '';
        $apptDate = $_POST['appt_date'] ?? '';
        $endDate = $_POST['end_date'] ?? null;
        $requiresApproval = isset($_POST['requires_approval']);
        $unitsSelected = $_POST['unit'] ?? [];
        $positions = $_POST['position'] ?? [];
        $comments = $_POST['comment'] ?? [];
        $createdBy = $_SESSION['user_id'] ?? 0;
    $dateCreated = date('Y-m-d H:i:s');
    $status = $requiresApproval ? 'pending' : 'approved';

    if (empty($selectedStaff)) $errors[] = "Please select at least one staff member.";
    if (empty($apptDate)) {
        $errors[] = "Please select the appointment date.";
    } else {
        // Validate appointment date format and range
        $apptTimestamp = strtotime($apptDate);
        if (!$apptTimestamp) {
            $errors[] = "Invalid appointment date format.";
        } else {
            $today = strtotime(date('Y-m-d'));
            $maxFutureDate = strtotime('+2 years');
            if ($apptTimestamp < $today) {
                $errors[] = "Appointment date cannot be in the past.";
            } elseif ($apptTimestamp > $maxFutureDate) {
                $errors[] = "Appointment date cannot be more than 2 years in the future.";
            }
        }
    }
    if (empty($appointmentTypeId)) $errors[] = "Please select an appointment type.";

    // Get appointment type details to check if it's temporary
    $isTemporary = false;
    if (!empty($appointmentTypeId)) {
        try {
            $typeStmt = $pdo->prepare("SELECT is_temporary FROM appointment_types WHERE id = ?");
            $typeStmt->execute([$appointmentTypeId]);
            $appointmentType = $typeStmt->fetch(PDO::FETCH_OBJ);
            $isTemporary = $appointmentType && $appointmentType->is_temporary;
        } catch (Exception $e) {
            $errors[] = "Error checking appointment type: " . htmlspecialchars($e->getMessage());
            error_log("Appointment type check error: " . $e->getMessage());
        }
    }

    // If it's a temporary appointment, we need an end date
    if ($isTemporary && empty($endDate)) {
        $errors[] = "End date is required for temporary appointments.";
    } elseif ($isTemporary && !empty($endDate)) {
        // Validate end date
        $endTimestamp = strtotime($endDate);
        $apptTimestamp = strtotime($apptDate);
        
        if (!$endTimestamp) {
            $errors[] = "Invalid end date format.";
        } elseif ($apptTimestamp && $endTimestamp <= $apptTimestamp) {
            $errors[] = "End date must be after the appointment date.";
        } elseif ($endTimestamp > strtotime('+5 years')) {
            $errors[] = "End date cannot be more than 5 years in the future.";
        }
    }

    // Validate each selected staff member and their units
    foreach ($selectedStaff as $svcNo) {
        $unitId = trim($unitsSelected[$svcNo] ?? '');
        if (empty($unitId)) {
            $errors[] = "Please select a unit for staff member " . htmlspecialchars($svcNo) . ".";
        } elseif (!is_numeric($unitId)) {
            $errors[] = "Invalid unit selection for staff member " . htmlspecialchars($svcNo) . ".";
        }
        
        // Validate service number format (assuming format like AR001234)
        if (!preg_match('/^[A-Z]{2}\d{6}$/', $svcNo)) {
            $errors[] = "Invalid service number format for " . htmlspecialchars($svcNo) . ". Expected format: AR123456";
        }
    }
    
    if (empty($errors)) {
        // Use PDO for all DB operations
        try {
            $pdo = getDbConnection();
            if (!$pdo) {
                throw new Exception("Database connection failed");
            }
        } catch (Exception $e) {
            $errors[] = "Database connection error: " . htmlspecialchars($e->getMessage());
            error_log("Database connection error in appointments: " . $e->getMessage());
        }
        
        if (empty($errors)) {
        
        // Prepare all statements outside the loop for better performance
        $selectStaffStmt = $pdo->prepare("SELECT id, service_number FROM staff WHERE service_number = ? LIMIT 1");
        $insertApptStmt = $pdo->prepare("INSERT INTO staff_appointment (staff_id, appointment_id, unit_id, service_number, appointment_date, comment, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $updateStaffStmt = $pdo->prepare("UPDATE staff SET unit_id = ? WHERE service_number = ?");
        
        // Process each staff member in a transaction
        foreach ($selectedStaff as $serviceNumber) {
            try {
                // Start transaction for each appointment
                $pdo->beginTransaction();
                
                $unitId = htmlspecialchars(trim($unitsSelected[$serviceNumber]));
                $position = htmlspecialchars(trim($positions[$serviceNumber] ?? ''));
                $comment = htmlspecialchars(trim($comments[$serviceNumber] ?? ''));
                
                // Get staff details
                $selectStaffStmt->execute([$serviceNumber]);
                $staff = $selectStaffStmt->fetch(PDO::FETCH_OBJ);
                $staffId = $staff ? $staff->id : null;
                
                if (!$staffId) {
                    throw new Exception("Staff member with service number {$serviceNumber} not found");
                }
                
                // Insert appointment record (adapted for current database schema)
                $appointmentId = 'APT' . date('Ymd') . '_' . $serviceNumber; // Generate appointment ID
                $fullComment = "Position: " . $position . ($comment ? " | Notes: " . $comment : "");
                
                $insertApptStmt->execute([
                    $staffId,
                    $appointmentId,
                    $unitId,
                    $serviceNumber,
                    $apptDate,
                    $fullComment,
                    $createdBy,
                    $dateCreated
                ]);
                
                // Update staff unit for the appointment
                $updateStaffStmt->execute([$unitId, $serviceNumber]);
                
                // Commit the transaction
                $pdo->commit();
                
                // Log successful appointment with simplified logging
                error_log("AUDIT: Appointment created - User: {$createdBy}, Staff: {$serviceNumber}, Unit: {$unitId}, Appointment: {$appointmentId}");
                
            } catch (Exception $e) {
                // Rollback the transaction on error
                $pdo->rollback();
                
                // Log the error for debugging
                error_log("Appointment creation failed for staff {$serviceNumber}: " . $e->getMessage());
                
                // Add user-friendly error message
                $errors[] = "Error processing appointment for staff member " . htmlspecialchars($serviceNumber) . ": " . 
                           (strpos($e->getMessage(), 'not found') !== false ? 'Staff member not found' : 'System error occurred');
            }
        }
        
        if (empty($errors)) {
            $success = true;
            $successMessage = "Appointments " . ($requiresApproval ? "submitted for approval" : "successfully processed") . " for all selected staff.";
        }
        } // Close inner if (empty($errors))
    } // Close if (empty($errors)) from line 214 (validation check)
    } // Close if (empty($errors)) from line 140 (first validation check)
} // Close if ($_SERVER['REQUEST_METHOD'] === 'POST')

include dirname(__DIR__) . '/shared/header.php';
include dirname(__DIR__) . '/shared/sidebar.php';
?>

<!-- Custom styles for staff select dropdown -->
<style>
    /* Enhanced staff selection styling */
    .select2-container--default .select2-results__option {
        padding: 6px 12px;
        border-bottom: 1px solid #f0f0f0;
    }
    
    .select2-container--default .select2-results__option:last-child {
        border-bottom: none;
    }
    
    .select2-container--default .select2-results__option--highlighted[aria-selected] .staff-badge {
        background-color: rgba(255,255,255,0.2) !important;
        color: #fff !important;
    }
    
    .select2-container--default .select2-results__option--highlighted[aria-selected] .staff-details {
        color: rgba(255,255,255,0.8) !important;
    }
    
    .select2-container--default .select2-selection--multiple .select2-selection__choice {
        background-color: #f8f9fa;
        border: 1px solid #dee2e6;
        padding: 3px 8px;
        margin-right: 5px;
        margin-top: 5px;
    }
    
    /* Fix for selected staff display */
    .select2-selection__choice {
        max-width: 100%;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    
    /* Badge styles */
    .staff-badge {
        font-family: monospace;
        font-weight: bold;
    }
</style>

<!-- Main Content -->
<div class="content-wrapper with-sidebar">
    <div class="container-fluid">
        <div class="main-content">
            <div class="row">
                <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h1 class="section-title">
                            <i class="fas fa-briefcase"></i> Staff Appointments
                        </h1>
                        <div>
                            <a href="/Armis2/admin_branch/index.php" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left"></i> Back to Dashboard
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-12">
                    <div class="card shadow-sm">
                        <div class="card-header bg-success text-white">
                            <h4 class="mb-0"><i class="fa fa-user-plus"></i> Staff Appointments</h4>
                        </div>
                        <div class="card-body">
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?= $successMessage ?? 'Appointments successful for all selected staff.' ?>
                </div>
            <?php endif; ?>
            <?php if ($errors): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        <?php foreach ($errors as $err): ?>
                            <li><?=htmlspecialchars($err)?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <!-- Step 1: Select current rank -->
            <form class="mb-4" id="rankForm" method="get">
                <div class="row">
                    <div class="col-md-6 mb-2">
                        <label class="form-label">Current Rank for Appointment *</label>
                        <select name="current_rank" id="current_rank" class="form-select" required>
                            <option value="">Select Current Rank...</option>
                            <?php foreach ($ranks as $r):
                                // Skip excluded ranks (Officer Cadet and Recruit)
                                if (in_array($r->rankName, $excludedRanks)) continue;
                                
                                // Get count of staff at this rank
                                $staffCount = isset($rankCounts[$r->rankID]) ? $rankCounts[$r->rankID] : 0;
                                ?>
                                <option value="<?=$r->rankID?>" <?=($currentRankId==$r->rankID)?'selected':''?>>
                                    <?=$r->rankName?> (<?=$staffCount?> staff)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Only staff members at this rank will be available for selection.</small>
                    </div>
                    <div class="col-md-2 mb-2 d-flex align-items-end">
                        <button type="submit" id="nextStepBtn" class="btn btn-primary w-100" <?=($currentRankId?'style="display:none;"':'')?>><i class="fa fa-arrow-right"></i> Next</button>
                        <?php if($currentRankId): ?><?php endif; ?>
                    </div>
                </div>
            </form>

            <!-- Step 2: Multi-Select + Panel -->
            <?php if ($currentRankId): ?>
            <form method="post" action="" id="appointmentForm">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="current_rank" value="<?=htmlspecialchars($currentRankId)?>">
                <div class="row mb-3">
                    <div class="col-md-12 mb-2">
                        <label class="form-label">Select Staff Members *</label>
                        <select name="selected_staff[]" id="selected_staff" class="form-select" multiple="multiple" required style="width:100%;"></select>
                        
                        <small class="text-muted">Staff members at rank: <strong><?=$currentRank->name?></strong>. Use search to filter.</small>
                    </div>
                </div>
                
                <div id="staffDetailsPanel"></div>
                <div class="row mb-3">
                    <div class="col-md-3 mb-2">
                        <label class="form-label">Appointment Type *</label>
                        <select name="appointment_type" id="appointment_type" class="form-select" required>
                            <option value="">Select Appointment Type</option>
                            <?php 
                            try {
                                $typesStmt = $pdo->query("SELECT * FROM appointment_types ORDER BY name");
                                while ($type = $typesStmt->fetch(PDO::FETCH_OBJ)) {
                                    echo '<option value="' . $type->id . '" data-is-temporary="' . $type->is_temporary . '" data-duration="' . $type->default_duration_days . '">' . 
                                         htmlspecialchars($type->name) . '</option>';
                                }
                            } catch (Exception $e) {
                                echo '<option value="">Error loading appointment types</option>';
                            }
                            ?>
                        </select>
                    </div>
                    <div class="col-md-3 mb-2">
                        <label class="form-label">Appointment Date *</label>
                        <input type="date" name="appt_date" id="appt_date" class="form-control" required value="<?=htmlspecialchars($_POST['appt_date'] ?? date('Y-m-d'))?>">
                    </div>
                    <div class="col-md-3 mb-2" id="end_date_container" style="display:none;">
                        <label class="form-label">End Date</label>
                        <input type="date" name="end_date" id="end_date" class="form-control" value="<?=htmlspecialchars($_POST['end_date'] ?? '')?>">
                        <small class="text-muted">Required for temporary appointments</small>
                    </div>
                    <div class="col-md-3 mb-2">
                        <label class="form-label">Approval Required?</label>
                        <div class="form-check form-switch mt-2">
                            <input class="form-check-input" type="checkbox" id="requires_approval" name="requires_approval">
                            <label class="form-check-label" for="requires_approval">Requires Approval</label>
                        </div>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-4 mb-2">
                        <label class="form-label">Bulk Assign Unit</label>
                        <select id="bulk_unit" class="form-select" style="width:100%;">
                            <option value="">Select Unit</option>
                            <?php foreach ($units as $u): ?>
                                <option value="<?=htmlspecialchars($u->unitID)?>"><?=htmlspecialchars($u->unitName)?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" id="apply_bulk_unit" class="btn btn-sm btn-outline-primary mt-2"><i class="fa fa-check"></i> Apply to All</button>
                    </div>
                    <div class="col-md-4 mb-2">
                        <label class="form-label">Bulk Position</label>
                        <input type="text" id="bulk_position" class="form-control" placeholder="e.g. Platoon Commander">
                        <button type="button" id="apply_bulk_position" class="btn btn-sm btn-outline-primary mt-2"><i class="fa fa-check"></i> Apply to All</button>
                    </div>
                    <div class="col-md-4 mb-2">
                        <label class="form-label">Bulk Comment</label>
                        <input type="text" id="bulk_comment" class="form-control" placeholder="Apply comment to all">
                        <button type="button" id="apply_bulk_comment" class="btn btn-sm btn-outline-primary mt-2"><i class="fa fa-check"></i> Apply to All</button>
                    </div>
                </div>
                <div class="text-end">
                    <button type="submit" id="submitBtn" name="appoint_staff" class="btn btn-primary px-5 py-2"><i class="fa fa-user-plus"></i> Appoint</button>
                </div>
            </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Back to Top Button -->
<button type="button" id="backToTopBtn" class="btn btn-secondary rounded-circle" style="position:fixed;bottom:30px;right:30px;display:none;z-index:999;">
    <i class="fa fa-arrow-up"></i>
</button>

<script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
const unitsData = <?=json_encode($units, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>;

function renderStaffPanels(selected) {
    const panel = $('#staffDetailsPanel');
    panel.empty();
    if (!selected || selected.length === 0) return;
    
    selected.forEach(svcNo => {
        // Get staff name from the selected option
        const selectedOption = $('#selected_staff option[value="' + svcNo + '"]');
        const staffText = selectedOption.text() || svcNo;
        
        // Extract name from the text if possible
        let staffName = svcNo;
        const nameMatch = staffText.match(/\d+\s*-\s*(.*?)(?:\s*\(|$)/);
        if (nameMatch && nameMatch[1]) {
            staffName = nameMatch[1].trim();
        }
        
        let unitOptions = '<option value="">Select Unit</option>';
        unitsData.forEach(u => {
            unitOptions += `<option value="${u.unitID}">${u.unitName}</option>`;
        });
        
        panel.append(`
            <div class="card mb-3 staff-detail-card" data-svcno="${svcNo}">
                <div class="card-header bg-light">
                    <h5 class="mb-0">
                        <span class="badge bg-primary me-2">${svcNo}</span>
                        ${staffName}
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-4 mb-2">
                            <label class="form-label mb-1">Unit *</label>
                            <select name="unit[${svcNo}]" class="form-select unit-select" style="width: 100%;" required>${unitOptions}</select>
                        </div>
                        <div class="col-md-4 mb-2">
                            <label class="form-label mb-1">Position</label>
                            <input type="text" name="position[${svcNo}]" class="form-control position-input" 
                                   placeholder="e.g. Platoon Commander" maxlength="100">
                        </div>
                        <div class="col-md-4 mb-2">
                            <label class="form-label mb-1">Comments</label>
                            <input type="text" name="comment[${svcNo}]" class="form-control comment-input" 
                                   maxlength="255" placeholder="Additional comments">
                        </div>
                    </div>
                </div>
            </div>
        `);
    });
    $('.unit-select').select2({ placeholder: "Select unit", allowClear: true, width: 'resolve' });
}

// Format staff display in dropdown results
function formatStaffResult(staff) {
    if (!staff.id || staff.loading) {
        return staff.text;
    }
    
    // Enhanced formatting for staff display with proper name display
    const serviceNumber = staff.service_number || staff.id;
    const lastName = staff.last_name || '';
    const firstName = staff.first_name || '';
    const unitName = staff.unit_name || 'No unit assigned';
    const rankName = staff.rank_name || '';
    
    return $(`
        <div class="d-flex align-items-center p-1">
            <div class="staff-badge bg-light text-primary px-2 py-1 rounded me-2 border fw-bold">
                ${serviceNumber}
            </div>
            <div class="staff-info flex-grow-1">
                <div class="staff-name fw-bold text-dark">${lastName}, ${firstName}</div>
                <div class="staff-details small text-muted">
                    ${rankName ? rankName + ' • ' : ''}${unitName}
                </div>
            </div>
        </div>
    `);
}

// Format selected staff in the input box
function formatStaffSelection(staff) {
    if (!staff.id) {
        return staff.text;
    }
    
    // For selected items, show service number and name clearly
    const serviceNumber = staff.service_number || staff.id;
    let displayName;
    
    if (staff.last_name && staff.first_name) {
        // If we have structured name data
        displayName = staff.last_name + ', ' + staff.first_name;
    } else if (staff.text) {
        // Extract name from text if in standard format (SN - Last, First)
        const match = staff.text.match(/\d+\s*-\s*(.*?)(?:\s*\(|$)/);
        if (match && match[1]) {
            displayName = match[1].trim();
        } else {
            // Use full text as fallback, removing service number
            displayName = staff.text.replace(/^\d+\s*-\s*/, '').replace(/\s*\(.*\)$/, '');
        }
    } else {
        // Fallback
        displayName = "Staff #" + serviceNumber;
    }
    
    return serviceNumber + ' - ' + displayName;
}

$(function() {
    // Helper function to get current rank ID consistently
    function getCurrentRankId() {
        // Try hidden input first (Step 2), then dropdown (Step 1), then PHP fallback
        let rankId = $('input[name="current_rank"]').val() || $('#current_rank').val() || '';
        <?php if ($currentRankId): ?>
        if (!rankId) {
            rankId = '<?=$currentRankId?>';
        }
        <?php endif; ?>
        return rankId;
    }
    
    // Select2 for staff multi-select with AJAX search
    $('#selected_staff').select2({
        placeholder: "Type to search for staff members...",
        allowClear: true,
        width: 'resolve',
        minimumInputLength: 1, // Require at least 1 character to search
        templateResult: formatStaffResult,
        templateSelection: formatStaffSelection,
        escapeMarkup: function(m) { return m; }, // Allow HTML in the formatting
        ajax: {
            url: 'search_staff_fixed.php',
            dataType: 'json',
            delay: 250,
            method: 'GET',  // Explicitly set method to GET
            data: function(params) {
                // Get rank ID using helper function
                const rankId = getCurrentRankId();
                
                // Make sure we're sending the correct parameters
                const queryData = {
                    q: params.term || '',  // Handle null/undefined
                    rank_id: rankId
                };
                
                return queryData;
            },
            processResults: function(data) {
                var results = [];
                if (Array.isArray(data)) {
                    if (data.length === 0) {
                        // No staff found message - removed for production
                    }
                    data.forEach(function(item) {
                        if (!item.service_number) {
                            console.error('Missing service_number in item:', item);
                            return;
                        }
                        var fullName = (item.last_name || '') + ', ' + (item.first_name || '');
                        var serviceNumber = item.service_number;
                        var unitInfo = item.unit_name ? ' (' + item.unit_name + ')' : '';
                        results.push({
                            id: serviceNumber,
                            text: serviceNumber + ' - ' + fullName + unitInfo,
                            service_number: serviceNumber,
                            last_name: item.last_name || '',
                            first_name: item.first_name || '',
                            unit_name: item.unit_name || '',
                            rank_name: item.rank_name || ''
                        });
                    });
                } else {
                    // Error handling for unexpected data type - removed for production
                }
                return {
                    results: results
                };
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', status, error);
                console.error('Response Text:', xhr.responseText);
                
                // Enhanced error handling with user feedback
                let errorMessage = 'An error occurred while searching for staff.';
                
                if (xhr.status === 0) {
                    errorMessage = 'Network error - please check your internet connection.';
                } else if (xhr.status === 401 || xhr.status === 403) {
                    errorMessage = 'Session expired - please log in again.';
                    setTimeout(() => {
                        window.location.href = '/Armis2/login.php';
                    }, 2000);
                } else if (xhr.status === 404) {
                    errorMessage = 'Search service not found - please contact system administrator.';
                    console.error('search_staff_fixed.php not found - check file path');
                } else if (xhr.status === 500) {
                    errorMessage = 'Server error occurred - please try again or contact support.';
                    console.error('Server error in search_staff_fixed.php - check logs');
                } else if (xhr.status >= 400) {
                    errorMessage = 'Request failed - please refresh and try again.';
                }
                
                // Show user-friendly error message
                $('#selected_staff').empty().append(new Option(errorMessage, '', false, false));
                
                // Show debug information for administrators
                if (xhr.responseText && xhr.responseText.indexOf('error') !== -1) {
                    console.error('Server response:', xhr.responseText);
                }
            },
            cache: true
        }
    });

    // Render staff panels on selection
    $('#selected_staff').on('change', function() {
        const selected = $(this).val() || [];
        renderStaffPanels(selected);
    });

    // Load staff for the selected rank on page load
    function loadStaffForRank(rankId, keepExisting = false) {
        if (!rankId) return;
        
        // Show loading indicator in the select box
        if (!keepExisting) {
            $('#selected_staff').empty().append(new Option('Loading staff members...', '', false, false));
        }
        
        // Make AJAX call to get all staff with this rank
        $.ajax({
            url: 'search_staff_fixed.php',
            data: { 
                rank_id: rankId,
                q: 'all' // Using 'all' to get all staff at this rank
            },
            type: 'GET',
            dataType: 'json',
            success: function(data) {
                if (!keepExisting) {
                    $('#selected_staff').empty();
                }
                
                if (data && Array.isArray(data) && data.length > 0) {
                    // Add each staff member as an option (but don't select them)
                    data.forEach(function(staff) {
                        // Determine ID and prepare data for the option
                        const serviceNumber = staff.service_number || staff.id;
                        
                        // Skip if missing critical data
                        if (!serviceNumber || (!staff.last_name && !staff.text)) {
                            console.error('Staff record missing required data:', staff);
                            return;
                        }
                        
                        // Prepare properly formatted option with staff data
                        const displayText = serviceNumber + ' - ' + (staff.last_name || '') + ', ' + (staff.first_name || '') + 
                                          (staff.unit_name ? ' (' + staff.unit_name + ')' : '');
                        
                        // Check if already exists
                        if ($('#selected_staff option[value="' + serviceNumber + '"]').length === 0) {
                            const option = new Option(displayText, serviceNumber, false, false);
                            $('#selected_staff').append(option);
                        }
                    });
                    
                    // Update dropdown with message showing count - removed for production
                } else {
                    // Show message if no staff found
                    $('#search_debug').removeClass('d-none')
                        .html('<div class="alert alert-warning mt-2 p-2">No staff members found at this rank.</div>');
                        
                    // Let's run a check to see if there really are no staff at this rank in the database
                    $.ajax({
                        url: 'test_staff_loading.php',
                        data: { rank_id: rankId, check_only: true },
                        type: 'GET',
                        dataType: 'json',
                        success: function(checkData) {
                            if (checkData && checkData.staff_count > 0) {
                                // There ARE staff in the database, but our search isn't finding them
                                $('#search_debug').append(
                                    '<div class="alert alert-danger mt-2 p-2">' +
                                    'Database contains ' + checkData.staff_count + ' staff at this rank, but search API returned none. ' +
                                    'This indicates a search API issue.' +
                                    '</div>' +
                                    '<div class="mt-2"><a href="test_staff_loading.php?rank_id=' + rankId + '" ' +
                                    'target="_blank" class="btn btn-sm btn-primary">Run Diagnostic Test</a></div>'
                                );
                            }
                        }
                    });
                }
            },
            error: function(xhr, status, error) {
                console.error('Error loading staff for rank:', error);
                console.error('Response:', xhr.responseText);
                $('#selected_staff').empty().append(new Option('Error loading staff members', '', false, false));
                // Basic error reporting for troubleshooting
                if (xhr.status === 404) {
                    console.error('search_staff.php not found');
                } else if (xhr.status === 500) {
                    console.error('Server error - check PHP logs');
                }
            }
        });
    }
    
    // Load staff on page load if rank is selected (disabled - let user search manually)
    <?php if ($currentRankId): ?>
    $(document).ready(function() {
        // Show helpful message instead of auto-loading
        $('#search_debug').removeClass('d-none')
            .html('<div class="alert alert-info mt-2 p-2">📋 Rank pre-selected. Use the search bar above to find and select staff members for appointments.</div>');
    });
    <?php else: ?>
    console.log('No current rank selected - user will need to select rank first');
    <?php endif; ?>
    
    // Initial render if POSTed back
    <?php if (!empty($_POST['selected_staff'])): ?>
        renderStaffPanels(<?=json_encode($_POST['selected_staff'])?>);
        $('#selected_staff').val(<?=json_encode($_POST['selected_staff'])?>).trigger('change');
    <?php endif; ?>

    // Loading state management
    function showLoading(element, message = 'Loading...') {
        $(element).html(`<i class="fas fa-spinner fa-spin"></i> ${message}`).prop('disabled', true);
    }
    
    function hideLoading(element, originalText) {
        $(element).html(originalText).prop('disabled', false);
    }
    
    // Enhanced bulk operations with loading states
    $('#apply_bulk_unit').on('click', function() {
        let unitID = $('#bulk_unit').val();
        if (!unitID) {
            alert('Please select a unit first.');
            return;
        }
        
        showLoading(this, 'Applying...');
        
        setTimeout(() => {
            $('.staff-detail-card').each(function() {
                $(this).find('.unit-select').val(unitID).trigger('change');
            });
            hideLoading($('#apply_bulk_unit'), '<i class="fa fa-check"></i> Apply to All');
            
            // Show success feedback
            const toast = $(`
                <div class="toast position-fixed top-0 end-0 m-3" style="z-index: 9999;">
                    <div class="toast-header bg-success text-white">
                        <i class="fas fa-check-circle me-2"></i>
                        <strong class="me-auto">Success</strong>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast"></button>
                    </div>
                    <div class="toast-body">
                        Unit applied to all selected staff members.
                    </div>
                </div>
            `);
            $('body').append(toast);
            toast.toast({delay: 3000}).toast('show');
            toast.on('hidden.bs.toast', () => toast.remove());
        }, 300);
    });

    // Bulk position assignment with validation
    $('#apply_bulk_position').on('click', function() {
        let position = $('#bulk_position').val().trim();
        if (!position) {
            alert('Please enter a position first.');
            return;
        }
        
        showLoading(this, 'Applying...');
        
        setTimeout(() => {
            $('.staff-detail-card .position-input').val(position);
            hideLoading($('#apply_bulk_position'), '<i class="fa fa-check"></i> Apply to All');
            
            // Show success feedback
            const toast = $(`
                <div class="toast position-fixed top-0 end-0 m-3" style="z-index: 9999;">
                    <div class="toast-header bg-success text-white">
                        <i class="fas fa-check-circle me-2"></i>
                        <strong class="me-auto">Success</strong>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast"></button>
                    </div>
                    <div class="toast-body">
                        Position "${position}" applied to all selected staff members.
                    </div>
                </div>
            `);
            $('body').append(toast);
            toast.toast({delay: 3000}).toast('show');
            toast.on('hidden.bs.toast', () => toast.remove());
        }, 300);
    });

    // Bulk comment assignment
    $('#apply_bulk_comment').on('click', function() {
        let comment = $('#bulk_comment').val().trim();
        if (!comment) {
            alert('Please enter a comment first.');
            return;
        }
        
        showLoading(this, 'Applying...');
        
        setTimeout(() => {
            $('.staff-detail-card .comment-input').val(comment);
            hideLoading($('#apply_bulk_comment'), '<i class="fa fa-check"></i> Apply to All');
            
            // Show success feedback
            const toast = $(`
                <div class="toast position-fixed top-0 end-0 m-3" style="z-index: 9999;">
                    <div class="toast-header bg-success text-white">
                        <i class="fas fa-check-circle me-2"></i>
                        <strong class="me-auto">Success</strong>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast"></button>
                    </div>
                    <div class="toast-body">
                        Comment applied to all selected staff members.
                    </div>
                </div>
            `);
            $('body').append(toast);
            toast.toast({delay: 3000}).toast('show');
            toast.on('hidden.bs.toast', () => toast.remove());
        }, 300);
    });

    // Bulk unit assignment
    $('#apply_bulk_unit').on('click', function() {
        let unitID = $('#bulk_unit').val();
        if (!unitID) return;
        $('.staff-detail-card').each(function() {
            $(this).find('.unit-select').val(unitID).trigger('change');
        });
    });

    // Bulk position assignment
    $('#apply_bulk_position').on('click', function() {
        let position = $('#bulk_position').val();
        if (!position) return;
        $('.staff-detail-card .position-input').val(position);
    });

    // Bulk comment assignment
    $('#apply_bulk_comment').on('click', function() {
        let comment = $('#bulk_comment').val();
        if (!comment) return;
        $('.staff-detail-card .comment-input').val(comment);
    });

    // Form validation before submission
    $('#appointmentForm').on('submit', function(e) {
        let isValid = true;
        let errors = [];
        
        // Clear previous validation states
        $('.is-invalid').removeClass('is-invalid');
        $('.invalid-feedback').remove();
        
        // Validate staff selection
        const selectedStaff = $('#selected_staff').val();
        if (!selectedStaff || selectedStaff.length === 0) {
            isValid = false;
            errors.push('Please select at least one staff member.');
            $('#selected_staff').next('.select2-container').addClass('is-invalid');
        }
        
        // Validate appointment type
        const appointmentType = $('#appointment_type').val();
        if (!appointmentType) {
            isValid = false;
            errors.push('Please select an appointment type.');
            $('#appointment_type').addClass('is-invalid');
        }
        
        // Validate appointment date
        const apptDate = $('#appt_date').val();
        if (!apptDate) {
            isValid = false;
            errors.push('Please select an appointment date.');
            $('#appt_date').addClass('is-invalid');
        } else {
            // Check if date is in the past
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            const selectedDate = new Date(apptDate);
            
            if (selectedDate < today) {
                isValid = false;
                errors.push('Appointment date cannot be in the past.');
                $('#appt_date').addClass('is-invalid');
            }
        }
        
        // Validate end date for temporary appointments
        const selectedOption = $('#appointment_type').find(':selected');
        const isTemporary = selectedOption.data('is-temporary') == 1;
        const endDate = $('#end_date').val();
        
        if (isTemporary && !endDate) {
            isValid = false;
            errors.push('End date is required for temporary appointments.');
            $('#end_date').addClass('is-invalid');
        } else if (isTemporary && endDate && apptDate) {
            const apptDateTime = new Date(apptDate);
            const endDateTime = new Date(endDate);
            
            if (endDateTime <= apptDateTime) {
                isValid = false;
                errors.push('End date must be after the appointment date.');
                $('#end_date').addClass('is-invalid');
            }
        }
        
        // Validate unit selection for each staff member
        let missingUnits = [];
        $('.staff-detail-card').each(function() {
            const svcNo = $(this).data('svcno');
            const unitSelect = $(this).find('.unit-select');
            
            if (!unitSelect.val()) {
                isValid = false;
                missingUnits.push(svcNo);
                unitSelect.addClass('is-invalid');
            }
        });
        
        if (missingUnits.length > 0) {
            errors.push(`Please select units for staff members: ${missingUnits.join(', ')}`);
        }
        
        // Show validation errors
        if (!isValid) {
            e.preventDefault();
            
            // Show error summary
            let errorHtml = '<div class="alert alert-danger alert-dismissible fade show mt-3" role="alert">';
            errorHtml += '<h6><i class="fas fa-exclamation-triangle"></i> Please correct the following errors:</h6>';
            errorHtml += '<ul class="mb-0">';
            errors.forEach(error => {
                errorHtml += `<li>${error}</li>`;
            });
            errorHtml += '</ul>';
            errorHtml += '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
            errorHtml += '</div>';
            
            // Remove existing validation alerts and add new one
            $('.alert-danger').remove();
            $('#appointmentForm').before(errorHtml);
            
            // Scroll to first error
            $('html, body').animate({
                scrollTop: $('.alert-danger').offset().top - 100
            }, 500);
            
            return false;
        }
        
        // Show loading state
        $('#submitBtn').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Processing...');
        
        return true;
    });

    // Auto-submit rank form on change (but don't auto-load staff)
    $('#current_rank').on('change', function() {
        const rankId = $(this).val();
        if (rankId) {
            // Show message that rank is selected
            $('#search_debug').removeClass('d-none')
                .html('<div class="alert alert-info mt-2 p-2">� Rank selected. Use the search bar above to find and select staff members.</div>');
            
            // If we're already in Step 2 (staff selection visible), just update the rank
            if ($('#selected_staff').length > 0 && $('#selected_staff').is(':visible') && $('input[name="current_rank"]').length > 0) {
                console.log('Updating rank for existing staff selection interface:', rankId);
                
                // Update the hidden rank input for Step 2
                $('input[name="current_rank"]').val(rankId);
                
                // Clear any existing selections to prevent confusion
                $('#selected_staff').val(null).trigger('change');
            } else {
                // Submit the form to reload with the selected rank (Step 1 → Step 2)
                $('#rankForm').submit();
            }
        } else {
            // Clear staff dropdown if no rank selected
            $('#selected_staff').empty();
            $('#search_debug').addClass('d-none');
        }
    });
    
    // Handle appointment type changes
    $('#appointment_type').on('change', function() {
        const selectedOption = $(this).find(':selected');
        const isTemporary = selectedOption.data('is-temporary') == 1;
        
        if (isTemporary) {
            $('#end_date_container').show();
            
            // Calculate default end date if there's a default duration
            const defaultDuration = selectedOption.data('duration');
            if (defaultDuration) {
                const startDate = $('#appt_date').val();
                if (startDate) {
                    const endDate = new Date(startDate);
                    endDate.setDate(endDate.getDate() + parseInt(defaultDuration));
                    $('#end_date').val(endDate.toISOString().split('T')[0]);
                }
            }
        } else {
            $('#end_date_container').hide();
            $('#end_date').val('');
        }
    });
    
    // Update end date when appointment date changes
    $('#appt_date').on('change', function() {
        const selectedOption = $('#appointment_type').find(':selected');
        const isTemporary = selectedOption.data('is-temporary') == 1;
        
        if (isTemporary) {
            const defaultDuration = selectedOption.data('duration');
            if (defaultDuration) {
                const startDate = $(this).val();
                if (startDate) {
                    const endDate = new Date(startDate);
                    endDate.setDate(endDate.getDate() + parseInt(defaultDuration));
                    $('#end_date').val(endDate.toISOString().split('T')[0]);
                }
            }
        }
    });
    
    // Test search directly - removed for production
    $('#test_search_link').on('click', function() {
        const rankId = getCurrentRankId();
        if (!rankId) {
            alert('Please select a rank first');
            return;
        }
        window.open('search_staff.php?test=1&q=test&rank_id=' + rankId, '_blank');
    });
    
    // Load all staff for the selected rank
    $('#loadAllStaffBtn').on('click', function() {
        const rankId = getCurrentRankId();
        if (!rankId) {
            alert('Please select a rank first');
            return;
        }
        
        // Show loading indicator
        $('#search_debug').removeClass('d-none')
            .html('<div class="alert alert-info mt-2 p-2">Loading all staff with rank ID: ' + rankId + '</div>');
        
        // Create a direct AJAX call to get all staff with this rank
        $.ajax({
            url: 'search_staff_fixed.php',
            data: { 
                rank_id: rankId,
                q: 'all' // Using a value to ensure it passes any checks
            },
            type: 'GET',
            dataType: 'json',
            success: function(data) {
                console.log('All staff for rank:', data);
                
                if (data && Array.isArray(data) && data.length > 0) {
                    // Clear existing selections
                    $('#selected_staff').empty();
                    
                    // Add each staff member as an option and select it
                    data.forEach(function(staff) {
                        // Use consistent data structure - search_staff.php returns service_number as id
                        const staffId = staff.service_number || staff.id;
                        const staffText = staff.text || (staffId + ' - ' + (staff.last_name || '') + ', ' + (staff.first_name || ''));
                        const option = new Option(staffText, staffId, true, true);
                        $('#selected_staff').append(option);
                    });
                    
                    // Trigger change to update the UI
                    $('#selected_staff').trigger('change');
                    
                    // Success message - removed for production
                } else {
                    $('#search_debug').append('<div class="alert alert-warning mt-2 p-2">No staff found with the selected rank</div>');
                }
            },
            error: function(xhr, status, error) {
                console.error('Error loading all staff:', error);
                $('#search_debug').append('<div class="alert alert-danger mt-2 p-2">Error loading staff: ' + error + '</div>');
            }
        });
    });
});
</script>
            </div>
        </div>
    </div>
</div>

<?php include dirname(__DIR__) . '/shared/footer.php'; ?>