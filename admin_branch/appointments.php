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
$currentPage = "Appointments";

$sidebarLinks = [
    ['title' => 'Dashboard', 'url' => '/Armis2/admin_branch/index.php', 'icon' => 'tachometer-alt', 'page' => 'dashboard'],
    ['title' => 'Create Staff', 'url' => '/Armis2/admin_branch/create_staff.php', 'icon' => 'user-plus', 'page' => 'create_staff'],
    ['title' => 'Edit Staff', 'url' => '/Armis2/admin_branch/edit_staff.php', 'icon' => 'user-edit', 'page' => 'edit_staff'],
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
    $ranksStmt = $pdo->query("SELECT id as rankID, name as rankName, level as rankIndex FROM ranks ORDER BY rankIndex ASC");
    $ranks = $ranksStmt->fetchAll(PDO::FETCH_OBJ);
    $unitsStmt = $pdo->query("SELECT id as unitID, name as unitName FROM units ORDER BY unitName ASC");
    $units = $unitsStmt->fetchAll(PDO::FETCH_OBJ);
} catch (Exception $e) {
    $errors[] = "Error fetching ranks or units: " . htmlspecialchars($e->getMessage());
}

// Exclude Officer Cadet and Recruit
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
    $apptDate = $_POST['appt_date'] ?? '';
    $unitsSelected = $_POST['unit'] ?? [];
    $positions = $_POST['position'] ?? [];
    $comments = $_POST['comment'] ?? [];
    $createdBy = $_SESSION['user_id'] ?? 0;
    $dateCreated = date('Y-m-d H:i:s');

    if (empty($selectedStaff)) $errors[] = "Please select at least one staff member.";
    if (empty($apptDate)) $errors[] = "Please select the appointment date.";

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
        $insertApptStmt = $pdo->prepare("INSERT INTO staff_appointment (staff_id, appointment_id, unit_id, service_number, appointment_date, comment, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $updateStaffStmt = $pdo->prepare("UPDATE staff SET unit_id = ? WHERE service_number = ?");
        foreach ($selectedStaff as $serviceNumber) {
            $unitId = htmlspecialchars(trim($unitsSelected[$serviceNumber]));
            $position = htmlspecialchars(trim($positions[$serviceNumber] ?? ''));
            $comment = htmlspecialchars(trim($comments[$serviceNumber] ?? ''));
            $fullComment = $position ? "[Position: $position] $comment" : $comment;
            $selectStaffStmt->execute([$serviceNumber]);
            $staff = $selectStaffStmt->fetch(PDO::FETCH_OBJ);
            $staffId = $staff ? $staff->id : null;
            try {
                $insertApptStmt->execute([
                    $staffId,
                    null, // appointment_id, set if you have appointment types
                    $unitId,
                    $serviceNumber,
                    $apptDate,
                    $fullComment,
                    $createdBy,
                    $dateCreated
                ]);
                $updateStaffStmt->execute([$unitId, $serviceNumber]);
            } catch (Exception $e) {
                $errors[] = "Error updating " . htmlspecialchars($serviceNumber) . ": " . htmlspecialchars($e->getMessage());
            }
        }
        if (empty($errors)) $success = true;
    }
}

include dirname(__DIR__) . '/shared/header.php';
include dirname(__DIR__) . '/shared/sidebar.php';
?>

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
                <div class="alert alert-success">Appointments successful for all selected staff.</div>
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
                                if (in_array($r->rankID, $excludedRankIds)) continue;
                                ?>
                                <option value="<?=$r->rankID?>" <?=($currentRankId==$r->rankID)?'selected':''?>><?=$r->rankName?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2 mb-2 d-flex align-items-end">
                        <button type="submit" id="nextStepBtn" class="btn btn-primary w-100" <?=($currentRankId?'style="display:none;"':'')?>><i class="fa fa-arrow-right"></i> Next</button>
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
                        <small class="text-muted">Type Service Number or name to search staff.</small>
                    </div>
                </div>
                <div id="staffDetailsPanel"></div>
                <div class="row mb-3">
                    <div class="col-md-4 mb-2">
                        <label class="form-label">Appointment Date *</label>
                        <input type="date" name="appt_date" class="form-control" required value="<?=htmlspecialchars($_POST['appt_date'] ?? date('Y-m-d'))?>">
                    </div>
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

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
const unitsData = <?=json_encode($units, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>;

function renderStaffPanels(selected) {
    const panel = $('#staffDetailsPanel');
    panel.empty();
    if (!selected || selected.length === 0) return;
    selected.forEach(svcNo => {
        let unitOptions = '<option value="">Select Unit</option>';
        unitsData.forEach(u => {
            unitOptions += `<option value="${u.unitID}">${u.unitName}</option>`;
        });
        panel.append(`
            <div class="card mb-3 staff-detail-card" data-svcno="${svcNo}">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-3 mb-2">
                            <strong>${svcNo}</strong>
                        </div>
                        <div class="col-md-3 mb-2">
                            <label class="form-label mb-0">Unit</label>
                            <select name="unit[${svcNo}]" class="form-select unit-select" style="width: 100%;">${unitOptions}</select>
                        </div>
                        <div class="col-md-3 mb-2">
                            <label class="form-label mb-0">Position Appointed To</label>
                            <input type="text" name="position[${svcNo}]" class="form-control" placeholder="e.g. Platoon Commander">
                        </div>
                        <div class="col-md-3 mb-2">
                            <label class="form-label mb-0">Comment</label>
                            <input type="text" name="comment[${svcNo}]" class="form-control">
                        </div>
                    </div>
                </div>
            </div>
        `);
    });
    $('.unit-select').select2({ placeholder: "Select unit", allowClear: true, width: 'resolve' });
}



$(function() {
    // Select2 for staff multi-select with AJAX search
    $('#selected_staff').select2({
        placeholder: "Select staff members",
        allowClear: true,
        width: 'resolve',
        minimumInputLength: 1,
        ajax: {
            url: 'search_staff.php',
            dataType: 'json',
            delay: 250,
            data: function(params) {
                return {
                    q: params.term,
                    rankID: $('#current_rank').val()
                };
            },
            processResults: function(data) {
                return {
                    results: data
                };
            },
            cache: true
        }
    });

    // Render staff panels on selection
    $('#selected_staff').on('change', function() {
        const selected = $(this).val() || [];
        renderStaffPanels(selected);
    });

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

    // Bulk comment assignment
    $('#apply_bulk_comment').on('click', function() {
        let comment = $('#bulk_comment').val();
        if (!comment) return;
        $('.staff-detail-card input[name^="comment["]').val(comment);
    });

    // Auto-submit rank form on change
    $('#current_rank').on('change', function() {
        $('#rankForm').submit();
    });
});
</script>
            </div>
        </div>
    </div>
</div>

<?php include dirname(__DIR__) . '/shared/footer.php'; ?>