<?php
// Define module constants
define('ARMIS_ADMIN_BRANCH', true);
define('ARMIS_DEVELOPMENT', true);

// Include admin branch authentication and database
require_once __DIR__ . '/includes/auth.php';
require_once dirname(__DIR__) . '/shared/database_connection.php';

// Require authentication
requireAuth();

$pageTitle = "Appointments - Admin Branch";
$moduleName = "Admin Branch";
$moduleIcon = "users-cog";
$currentPage = "appointments";

$sidebarLinks = [
    ['title' => 'Dashboard', 'url' => '/Armis2/admin_branch/index.php', 'icon' => 'tachometer-alt', 'page' => 'dashboard'],
    ['title' => 'Create Staff', 'url' => '/Armis2/admin_branch/create_staff.php', 'icon' => 'user-plus', 'page' => 'create_staff'],
    ['title' => 'Edit Staff', 'url' => '/Armis2/admin_branch/edit_staff.php', 'icon' => 'user-edit', 'page' => 'edit_staff'],
    ['title' => 'Promotions', 'url' => '/Armis2/admin_branch/promote_staff.php', 'icon' => 'arrow-up', 'page' => 'promotions'],
    ['title' => 'Appointments', 'url' => '/Armis2/admin_branch/appointments.php', 'icon' => 'briefcase', 'page' => 'appointments'],
    ['title' => 'Medals', 'url' => '/Armis2/admin_branch/medals.php', 'icon' => 'medal', 'page' => 'medals'],
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

// Use PDO for all DB operations
$pdo = getDbConnection();
$ranks = [];
$units = [];
try {
    // Get all ranks ordered by rank level (seniority)
    $ranksStmt = $pdo->query("SELECT id as rankID, name as rankName, level as rankIndex FROM ranks ORDER BY rankIndex ASC");
    $ranks = $ranksStmt->fetchAll(PDO::FETCH_OBJ);
    
    // Get all units ordered by name
    $unitsStmt = $pdo->query("SELECT id as unitID, name as unitName FROM units ORDER BY unitName ASC");
    $units = $unitsStmt->fetchAll(PDO::FETCH_OBJ);
    
    // Count total staff at each rank for display
    $rankCounts = [];
    $rankCountStmt = $pdo->query("SELECT rank_id, COUNT(*) as count FROM staff GROUP BY rank_id");
    while ($row = $rankCountStmt->fetch(PDO::FETCH_ASSOC)) {
        $rankCounts[$row['rank_id']] = $row['count'];
    }
} catch (Exception $e) {
    $errors[] = "Error fetching ranks or units: " . htmlspecialchars($e->getMessage());
}

// Exclude Officer Cadet and Recruit only
$excludedRanks = ['Officer Cadet', 'Recruit'];
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
    if (empty($apptDate)) $errors[] = "Please select the appointment date.";
    if (empty($appointmentTypeId)) $errors[] = "Please select an appointment type.";

    // Get appointment type details to check if it's temporary
    $isTemporary = false;
    if (!empty($appointmentTypeId)) {
        $typeStmt = $pdo->prepare("SELECT is_temporary FROM appointment_types WHERE id = ?");
        $typeStmt->execute([$appointmentTypeId]);
        $appointmentType = $typeStmt->fetch(PDO::FETCH_OBJ);
        $isTemporary = $appointmentType && $appointmentType->is_temporary;
    }

    // If it's a temporary appointment, we need an end date
    if ($isTemporary && empty($endDate)) {
        $errors[] = "End date is required for temporary appointments.";
    }

    foreach ($selectedStaff as $svcNo) {
        $unitId = trim($unitsSelected[$svcNo] ?? '');
        if (empty($unitId)) {
            $errors[] = "Please select a unit for staff member " . htmlspecialchars($svcNo) . ".";
        }
    }

    if (empty($errors)) {
        // Use PDO for all DB operations
        $pdo = getDbConnection();
        $selectStaffStmt = $pdo->prepare("SELECT id, service_number FROM staff WHERE service_number = ? LIMIT 1");
        $insertApptStmt = $pdo->prepare("INSERT INTO staff_appointment (staff_id, appointment_type_id, unit_id, service_number, appointment_date, start_date, end_date, position, comment, status, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $updateStaffStmt = $pdo->prepare("UPDATE staff SET unit_id = ? WHERE service_number = ?");
        
        foreach ($selectedStaff as $serviceNumber) {
            $unitId = htmlspecialchars(trim($unitsSelected[$serviceNumber]));
            $position = htmlspecialchars(trim($positions[$serviceNumber] ?? ''));
            $comment = htmlspecialchars(trim($comments[$serviceNumber] ?? ''));
            
            $selectStaffStmt->execute([$serviceNumber]);
            $staff = $selectStaffStmt->fetch(PDO::FETCH_OBJ);
            $staffId = $staff ? $staff->id : null;
            
            try {
                $insertApptStmt->execute([
                    $staffId,
                    $appointmentTypeId,
                    $unitId,
                    $serviceNumber,
                    $apptDate,
                    $apptDate, // start_date is same as appointment_date
                    $endDate,
                    $position,
                    $comment,
                    $status,
                    $createdBy,
                    $dateCreated
                ]);
                
                // Get the inserted appointment ID
                $appointmentId = $pdo->lastInsertId();
                
                // If appointment requires approval, create approval record
                if ($requiresApproval) {
                    $insertApprovalStmt = $pdo->prepare("INSERT INTO appointment_approvals (appointment_id, approver_id, status, created_at) VALUES (?, ?, 'pending', NOW())");
                    // Use current user as approver for now - in real implementation, you'd determine the appropriate approver
                    $insertApprovalStmt->execute([$appointmentId, $createdBy]);
                    
                    // Create notification for approver
                    $insertNotificationStmt = $pdo->prepare("INSERT INTO appointment_notifications (appointment_id, user_id, message, created_at) VALUES (?, ?, ?, NOW())");
                    $notificationMessage = "New appointment requires your approval for staff member {$serviceNumber}";
                    $insertNotificationStmt->execute([$appointmentId, $createdBy, $notificationMessage]);
                }
                
                // Update staff unit if appointment is approved
                if ($status === 'approved') {
                    $updateStaffStmt->execute([$unitId, $serviceNumber]);
                }
                
                // Log the appointment history
                $insertHistoryStmt = $pdo->prepare("INSERT INTO appointment_history (appointment_id, staff_id, field_changed, new_value, changed_by, changed_at) VALUES (?, ?, ?, ?, ?, NOW())");
                $insertHistoryStmt->execute([$appointmentId, $staffId, 'creation', 'Appointment created', $createdBy]);
                
            } catch (Exception $e) {
                $errors[] = "Error updating " . htmlspecialchars($serviceNumber) . ": " . htmlspecialchars($e->getMessage());
            }
        }
        
        if (empty($errors)) {
            $success = true;
            $successMessage = "Appointments " . ($requiresApproval ? "submitted for approval" : "successfully processed") . " for all selected staff.";
        }
    }
}

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
                <input type="hidden" name="current_rank" value="<?=htmlspecialchars($currentRankId)?>">
                <div class="row mb-3">
                    <div class="col-md-12 mb-2">
                        <label class="form-label">Select Staff Members *</label>
                        <select name="selected_staff[]" id="selected_staff" class="form-select" multiple="multiple" required style="width:100%;"></select>
                        
                        <!-- Debug Info Section -->
                        <div class="mt-2">
                            <strong>Debug Info:</strong> 
                            Current Rank ID: <?= $currentRankId ?> | 
                            Current Rank: <?= $currentRank ? $currentRank->name : 'None' ?>
                            <br>
                            <small class="text-muted">If you see a rank selected but no staff auto-load, there may be a JavaScript issue.</small>
                        </div>
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
                            <input type="text" name="position[${svcNo}]" class="form-control position-input" placeholder="e.g. Platoon Commander">
                        </div>
                        <div class="col-md-4 mb-2">
                            <label class="form-label mb-1">Comments</label>
                            <input type="text" name="comment[${svcNo}]" class="form-control comment-input">
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
    // Debug flag - enable for troubleshooting
    const debug = true;
    
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
        placeholder: "Select staff members",
        allowClear: true,
        width: 'resolve',
        minimumInputLength: 0, // Changed from 1 to 0 to allow empty searches
        templateResult: formatStaffResult,
        templateSelection: formatStaffSelection,
        escapeMarkup: function(m) { return m; }, // Allow HTML in the formatting
        ajax: {
            url: 'search_staff.php',
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
                
                if(debug) {
                    console.log('Search params:', queryData);
                    $('#search_debug').removeClass('d-none')
                      .html('<div class="alert alert-info mt-2 p-2">Searching for: "' + 
                            queryData.q + '", Rank ID: ' + queryData.rank_id + '</div>');
                }
                
                return queryData;
            },
            processResults: function(data) {
                if(debug) {
                    console.log('Search results:', data);
                    
                    if (data && Array.isArray(data)) {
                        // Process the search results
                        if (data.length === 0) {
                            console.log('No staff found for current search criteria');
                        }
                    } else if (data && data.error) {
                        console.error('Search error:', data.error);
                    }
                    }
                }
                
                // Ensure data is always an array and format correctly for Select2
                const results = [];
                if (Array.isArray(data)) {
                    if (data.length === 0) {
                        $('#search_debug').append('<div class="alert alert-warning mt-2 p-2">No staff found with this search criteria.</div>');
                    }
                    
                    data.forEach(function(item) {
                        if (!item.service_number) {
                            console.error('Missing service_number in item:', item);
                            $('#search_debug').append('<div class="alert alert-danger mt-2 p-2">Error: Missing service_number in results.</div>');
                            return;
                        }
                        
                        // Create a properly formatted record for Select2
                        const fullName = (item.last_name || '') + ', ' + (item.first_name || '');
                        const serviceNumber = item.service_number;
                        const unitInfo = item.unit_name ? ' (' + item.unit_name + ')' : '';
                        
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
                    $('#search_debug').append('<div class="alert alert-danger mt-2 p-2">Error: Expected array but got ' + (typeof data) + '</div>');
                }
                
                return {
                    results: results
                };
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', status, error);
                if(debug) {
                    $('#search_debug').append('<div class="alert alert-danger mt-2 p-2">AJAX Error: ' + status + ' - ' + error + '</div>');
                    
                    // Try to parse response text if available
                    if (xhr.responseText) {
                        try {
                            const response = JSON.parse(xhr.responseText);
                            $('#search_debug').append('<div class="alert alert-warning mt-2 p-2">Server response: ' + 
                                                     JSON.stringify(response) + '</div>');
                        } catch (e) {
                            // Show raw response if not JSON
                            if (xhr.responseText.length > 200) {
                                $('#search_debug').append('<div class="mt-2"><pre>' + xhr.responseText.substring(0, 200) + '...</pre></div>');
                            } else {
                                $('#search_debug').append('<div class="mt-2"><pre>' + xhr.responseText + '</pre></div>');
                            }
                        }
                    }
                    
                    const debugRankId = getCurrentRankId();
                    $('#search_debug').append('<div class="mt-2"><a href="test_staff_loading.php?rank_id=' + 
                                             debugRankId + '" target="_blank" class="btn btn-sm btn-danger">Run Diagnostic Tests</a> ' +
                                             '<a href="search_staff.php?test=1&q=test&rank_id=' + 
                                             debugRankId + '" target="_blank" class="btn btn-sm btn-secondary">Test Search Endpoint</a></div>');
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
            url: 'search_staff.php',
            data: { 
                rank_id: rankId,
                q: 'all' // Using 'all' to get all staff at this rank
            },
            type: 'GET',
            dataType: 'json',
            success: function(data) {
                console.log('Staff loaded for rank:', data);
                
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
                    
                    // Update dropdown with message showing count
                    $('#search_debug').removeClass('d-none')
                        .html('<div class="alert alert-success mt-2 p-2">' + data.length + ' staff members available at this rank</div>');
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
                $('#selected_staff').empty().append(new Option('Error loading staff members', '', false, false));
                $('#search_debug').removeClass('d-none')
                    .html('<div class="alert alert-danger mt-2 p-2">Error loading staff: ' + error + '</div>' +
                          '<div class="mt-2"><a href="test_staff_loading.php?rank_id=' + rankId + '" ' +
                          'target="_blank" class="btn btn-sm btn-primary">Run Diagnostic Test</a></div>');
            }
        });
    }
    
    // Load staff on page load if rank is selected
    <?php if ($currentRankId): ?>
    $(document).ready(function() {
        console.log('Auto-loading staff for rank <?=$currentRankId?> (<?=$currentRank->name?>)');
        
        // Show debug info immediately
        $('#search_debug').removeClass('d-none')
            .html('<div class="alert alert-info mt-2 p-2">🔄 AUTO-LOADING: Loading staff at rank: <?=$currentRank->name?> (ID: <?=$currentRankId?>)...</div>');
        
        // Small delay to ensure Select2 is fully initialized
        setTimeout(function() {
            if (typeof loadStaffForRank === 'function') {
                console.log('✅ Calling loadStaffForRank(<?=$currentRankId?>)');
                loadStaffForRank('<?=$currentRankId?>');
            } else {
                console.error('❌ loadStaffForRank function is not defined!');
                $('#search_debug').html('<div class="alert alert-danger mt-2 p-2">❌ ERROR: Auto-loading failed - function not defined!</div>');
            }
        }, 1000);
    });
    <?php else: ?>
    console.log('No current rank selected for auto-loading');
    <?php endif; ?>
    
    // Initial render if POSTed back
    <?php if (!empty($_POST['selected_staff'])): ?>
        renderStaffPanels(<?=json_encode($_POST['selected_staff'])?>);
        $('#selected_staff').val(<?=json_encode($_POST['selected_staff'])?>).trigger('change');
    <?php endif; ?>

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

    // Auto-submit rank form on change AND auto-load staff
    $('#current_rank').on('change', function() {
        const rankId = $(this).val();
        if (rankId) {
            // Show loading message immediately
            $('#search_debug').removeClass('d-none')
                .html('<div class="alert alert-info mt-2 p-2">🔄 Loading staff for selected rank...</div>');
            
            // If we're already in Step 2 (staff selection visible), auto-load immediately
            if ($('#selected_staff').length > 0 && $('#selected_staff').is(':visible')) {
                console.log('Auto-loading staff for newly selected rank:', rankId);
                
                // Auto-load staff for the new rank without form submission
                setTimeout(function() {
                    loadStaffForRank(rankId);
                }, 500);
                
                // Also update the hidden rank input for Step 2
                $('input[name="current_rank"]').val(rankId);
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
    
    // Test search directly
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
            url: 'search_staff.php',
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
                        const option = new Option(staff.text, staff.id, true, true);
                        $('#selected_staff').append(option);
                    });
                    
                    // Trigger change to update the UI
                    $('#selected_staff').trigger('change');
                    
                    $('#search_debug').append('<div class="alert alert-success mt-2 p-2">Loaded ' + data.length + ' staff members</div>');
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