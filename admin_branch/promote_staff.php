<?php
define('ARMIS_ADMIN_BRANCH', true);
define('ARMIS_DEVELOPMENT', true);

require_once __DIR__ . '/includes/auth.php';
require_once dirname(__DIR__) . '/shared/database_connection.php';
requireAuth();

$pageTitle = "Staff Promotion - Admin Branch";
$moduleName = "Admin Branch";
$moduleIcon = "users-cog";
$currentPage = "promotions";

$sidebarLinks = [
    ['title' => 'Dashboard', 'url' => '/Armis2/admin_branch/index.php', 'icon' => 'tachometer-alt', 'page' => 'dashboard'],
    ['title' => 'Staff Management', 'url' => '/Armis2/admin_branch/edit_staff.php', 'icon' => 'users', 'page' => 'staff'],
    ['title' => 'Create Staff', 'url' => '/Armis2/admin_branch/create_staff.php', 'icon' => 'user-plus', 'page' => 'create'],
    ['title' => 'Promotions', 'url' => '/Armis2/admin_branch/promote_staff.php', 'icon' => 'arrow-up', 'page' => 'promotions'],
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

$pdo = getDbConnection();
$errors = [];
$success = false;
$successMessages = [];
$ranks = [];
$eligibleStaff = [];
$showStaffProfileModal = false;

// === ENHANCEMENTS CONFIG === //
$BULK_CONFIRMATION_THRESHOLD = 5; // Confirm again if >X staff promoted
$ENABLE_PROFILE_POPUP = true;     // Clicking staff row shows modal
$ENABLE_EXPORT_REPORT  = true;    // Allow promotion report download

// --- Eligibility logic (commented out for now) ---

/*function checkEligibility($staff, $pdo) {
    // Example eligibility logic: must have been at rank for >12 months and completed course "PROMO101"
    $minMonths = 12;
    $requiredCourse = "PROMO101";
    $attestDate = $staff->attestDate ?? null;
    $currentRankId = $staff->rank_id ?? null;
    $serviceNumber = $staff->service_number ?? null;

    $eligible = true;
    $eligibilityReasons = [];

    // Check time-in-rank (assume subWef is the date they got the current rank)
    $subWef = $staff->subWef ?? null;
    $dateRanked = $subWef ?: $attestDate;
    if ($dateRanked) {
        $monthsInRank = (new DateTime())->diff(new DateTime($dateRanked))->m + 
                        ((new DateTime())->diff(new DateTime($dateRanked))->y * 12);
        if ($monthsInRank < $minMonths) {
            $eligible = false;
            $eligibilityReasons[] = "Less than {$minMonths} months in current rank";
        }
    }

    // Check course completion
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM staff_courses WHERE service_number = ? AND course_code = ?");
    $stmt->execute([$serviceNumber, $requiredCourse]);
    $courseCompleted = $stmt->fetchColumn();
    if (!$courseCompleted) {
        $eligible = false;
        $eligibilityReasons[] = "Required course not completed";
    }

    // Check discipline (no pending disciplinary actions)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM staff_discipline WHERE service_number = ? AND status = 'Pending'");
    $stmt->execute([$serviceNumber]);
    $disciplinePending = $stmt->fetchColumn();
    if ($disciplinePending > 0) {
        $eligible = false;
        $eligibilityReasons[] = "Pending disciplinary action";
    }

    return [
        'eligible' => $eligible,
        'reasons' => $eligibilityReasons
    ];
}*/


try {
    $ranksStmt = $pdo->query("SELECT id as rankID, name as rankName, level FROM ranks WHERE name NOT IN ('Officer Cadet', 'Recruit', 'Mister', 'Miss') ORDER BY level ASC");
    $ranks = $ranksStmt->fetchAll(PDO::FETCH_OBJ);
} catch (Exception $e) {
    $errors[] = "Error fetching ranks: " . htmlspecialchars($e->getMessage());
}

$currentRankId = $_POST['current_rank'] ?? $_GET['current_rank'] ?? '';
$currentRank = null;
if ($currentRankId) {
    $stmt = $pdo->prepare("SELECT id as rankID, name as rankName, level FROM ranks WHERE id = ? LIMIT 1");
    $stmt->execute([$currentRankId]);
    $currentRank = $stmt->fetch(PDO::FETCH_OBJ);
}
$category = null;
if ($currentRank) {
    $category = ($currentRank->level >= 1 && $currentRank->level <= 13) ? 'Officer' : (($currentRank->level >= 15 && $currentRank->level <= 26) ? 'NCO' : null);
}

// Get eligible staff at selected rank (for now, include all staff at the rank)
$staffCount = 0;
if ($currentRankId && $category) {
    $stmt = $pdo->prepare("SELECT * FROM staff WHERE rank_id = ?");
    $stmt->execute([$currentRankId]);
    $staffRows = $stmt->fetchAll(PDO::FETCH_OBJ);
    foreach ($staffRows as $staff) {
        // $eligibility = checkEligibility($staff, $pdo);
        // $staff->eligible = $eligibility['eligible'];
        // $staff->eligibilityReasons = $eligibility['reasons'];
        // For now, treat all as eligible:
        $staff->eligible = true;
        $staff->eligibilityReasons = [];
        $eligibleStaff[] = $staff;
    }
    $staffCount = count($eligibleStaff);
}

// Handle promotion submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['promote_staff'])) {
    $selectedStaff = $_POST['selected_staff'] ?? [];
    $promotionType = strtolower(trim($_POST['promotion_type'] ?? ''));
    $nextRankId = $_POST['next_rank'] ?? '';
    $promotionDate = $_POST['promotion_date'] ?? '';
    $perStaffAuthority = $_POST['promotion_authority'] ?? [];
    $perStaffRemark = $_POST['promotion_remark'] ?? [];
    $userId = $_SESSION['user_id'] ?? 0;
    $timestamp = date('Y-m-d H:i:s');

    if (empty($selectedStaff)) $errors[] = "Please select at least one staff member.";
    if (empty($nextRankId)) $errors[] = "Please select the next rank.";
    if (empty($promotionType)) $errors[] = "Please select Promotion or Reversion.";
    if (empty($promotionDate)) $errors[] = "Please select the date.";

    // Permission check: Only privileged users
    if (!in_array($_SESSION['role'] ?? '', ['admin', 'branch-admin', 'hr'])) {
        $errors[] = "You do not have permission to promote staff.";
    }

    // Validate next rank
    $stmt = $pdo->prepare("SELECT id as rankID, name as rankName, level FROM ranks WHERE id = ? LIMIT 1");
    $stmt->execute([$nextRankId]);
    $nextRankObj = $stmt->fetch(PDO::FETCH_OBJ);
    if (!$nextRankObj) $errors[] = "Invalid next rank selected.";

    // Validate all selected staff
    $staffIdMap = [];
    foreach ($selectedStaff as $serviceNumber) {
        $stmt = $pdo->prepare("SELECT * FROM staff WHERE service_number = ? LIMIT 1");
        $stmt->execute([$serviceNumber]);
        $staff = $stmt->fetch(PDO::FETCH_OBJ);
        if (!$staff || $staff->rank_id != $currentRankId) {
            $errors[] = "Staff member $serviceNumber is not at the selected current rank.";
            continue;
        }
        $staffIdMap[$serviceNumber] = $staff->id;
        if (empty($perStaffAuthority[$serviceNumber])) {
            $errors[] = "Authority is required for staff member $serviceNumber.";
        }
    }

    // Prevent duplicate staff selection
    if (count($selectedStaff) !== count(array_unique($selectedStaff))) {
        $errors[] = "Duplicate staff selected.";
    }

    // Prevent promoting staff who already have pending promotion
    foreach ($selectedStaff as $serviceNumber) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM staff_promotions WHERE staff_id = ? AND date_to >= ? AND type = 'promotion' AND new_rank = ?");
        $staffId = $staffIdMap[$serviceNumber] ?? null;
        if ($staffId) {
            $stmt->execute([$staffId, $promotionDate, $nextRankId]);
            $pendingPromotion = $stmt->fetchColumn();
            if ($pendingPromotion > 0) {
                $errors[] = "Staff member $serviceNumber already has pending promotion to this rank.";
            }
        }
    }

    if ($ENABLE_EXPORT_REPORT && isset($_POST['export_report'])) {
        // Export logic, see below
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            foreach ($selectedStaff as $serviceNumber) {
                $staffId = $staffIdMap[$serviceNumber] ?? null;
                if (!$staffId) throw new Exception("Could not resolve staff ID for $serviceNumber");
                $newRankName = $nextRankObj->rankName;
                $isTemporal = stripos($newRankName, 'Temporal') !== false;

                $subWef = $tempWef = $subRank = $tempRank = null;

                if ($promotionType === 'promotion') {
                    if ($isTemporal) {
                        $tempWef  = $promotionDate;
                        $tempRank = $newRankName;
                    } else {
                        $subWef  = $promotionDate;
                        $subRank = $newRankName;
                    }
                } else {
                    if ($isTemporal) {
                        $tempWef  = null;
                        $tempRank = null;
                    } else {
                        $subWef  = null;
                        $subRank = null;
                    }
                }
                $stmt = $pdo->prepare("SELECT * FROM staff WHERE service_number = ? LIMIT 1");
                $stmt->execute([$serviceNumber]);
                $beforeStaff = $stmt->fetch(PDO::FETCH_ASSOC);

                $updateStmt = $pdo->prepare("UPDATE staff SET rank_id = ?, subRank = ?, tempRank = ?, subWef = ?, tempWef = ? WHERE service_number = ?");
                $updateStmt->execute([
                    $nextRankId,
                    $subRank,
                    $tempRank,
                    $subWef,
                    $tempWef,
                    $serviceNumber
                ]);

                $insertStmt = $pdo->prepare("INSERT INTO staff_promotions (staff_id, current_rank, date_from, date_to, type, new_rank, authority, remark, created_by, created_at, before_json, user_ip, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $insertStmt->execute([
                    $staffId,
                    $currentRankId,
                    date('Y-m-d'),
                    $promotionDate,
                    $promotionType,
                    $nextRankId,
                    $perStaffAuthority[$serviceNumber] ?? '',
                    $perStaffRemark[$serviceNumber] ?? '',
                    $userId,
                    $timestamp,
                    json_encode($beforeStaff),
                    $_SERVER['REMOTE_ADDR'] ?? '',
                    $_SERVER['HTTP_USER_AGENT'] ?? ''
                ]);

                $successMessages[] = "$serviceNumber promoted to $newRankName. Fields updated: "
                    . ($subRank ? "subRank/subWef" : "tempRank/tempWef");
            }
            $pdo->commit();
            $success = true;
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = "Transaction failed: " . $e->getMessage();
            error_log("Promotion transaction failed: " . $e->getMessage());
        }
    }
}

// Prepare next/previous rank for display and submission
$nextRankObj = null;
$nextRankName = '';
$nextRankIdValue = '';
if ($currentRank && isset($_POST['promotion_type'])) {
    $promotionType = strtolower(trim($_POST['promotion_type']));
    $validRange = $category === 'Officer' ? range(1, 13) : ($category === 'NCO' ? range(15, 26) : []);
    $currentRankLevel = $currentRank->level ?? null;
    if ($promotionType === 'promotion' && $currentRankLevel !== null) {
        foreach ($ranks as $r) {
            if ($r->level < $currentRankLevel && in_array($r->level, $validRange)) {
                if ($nextRankObj === null || $r->level > $nextRankObj->level) {
                    $nextRankObj = $r;
                }
            }
        }
    } elseif (in_array($promotionType, ['reversion', 'demotion']) && $currentRankLevel !== null) {
        foreach ($ranks as $r) {
            if ($r->level > $currentRankLevel && in_array($r->level, $validRange)) {
                if ($nextRankObj === null || $r->level < $nextRankObj->level) {
                    $nextRankObj = $r;
                }
            }
        }
    }
    if ($nextRankObj) {
        $nextRankName = $nextRankObj->rankName;
        $nextRankIdValue = $nextRankObj->rankID;
    }
}

include dirname(__DIR__) . '/shared/header.php';
include dirname(__DIR__) . '/shared/sidebar.php';
?>

<div class="content-wrapper with-sidebar">
    <div class="container-fluid">
        <div class="main-content">
            <div class="card shadow-sm">
                <div class="card-header bg-success text-white">
                    <h4 class="mb-0"><i class="fa fa-arrow-up"></i> Staff Promotion / Reversion</h4>
                </div>
                <div class="card-body">
                    <?php if ($success): ?>
                        <div class="alert alert-success">
                            Promotion/Demotion successful for all selected staff.
                            <?php if (!empty($successMessages)): ?>
                                <ul class="mb-0">
                                    <?php foreach ($successMessages as $msg): ?>
                                        <li><?=htmlspecialchars($msg)?></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
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

                    <!-- Stepper bar (UX enhancement) -->
                    <div class="mb-3">
                        <ul class="stepper mb-0">
                            <li class="step active">Select Rank</li>
                            <li class="step <?=($currentRankId?'active':'')?>">Select Staff</li>
                            <li class="step <?=($nextRankIdValue?'active':'')?>">Details</li>
                            <li class="step">Confirm</li>
                            <li class="step <?=($success?'active':'')?>">Success</li>
                        </ul>
                    </div>

                    <!-- Step 1: Select current rank -->
                    <form class="mb-4" id="rankForm" method="get" autocomplete="off">
                        <div class="row">
                            <div class="col-md-6 mb-2">
                                <label class="form-label">Current Rank Being Promoted / Reversed *</label>
                                <select name="current_rank" id="current_rank" class="form-select" required>
                                    <option value="">Select Current Rank...</option>
                                    <?php foreach ($ranks as $r): ?>
                                        <option value="<?=$r->rankID?>" <?=($currentRankId==$r->rankID)?'selected':''?>><?=$r->rankName?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4 mb-2">
                                <?php if ($staffCount>0 && $currentRank): ?>
                                    <div class="alert alert-info mb-0">
                                        <strong><?=htmlspecialchars($staffCount)?></strong>
                                        staff member<?=($staffCount!=1?'s':'')?> at rank <strong><?=htmlspecialchars($currentRank->name ?? '')?></strong> found.
                                    </div>
                                <?php elseif ($currentRank): ?>
                                    <div class="alert alert-warning mb-0">
                                        No staff found at rank <strong><?=htmlspecialchars($currentRank->name ?? '')?></strong>.
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </form>

                    <!-- Step 2: Bulk Promotion/Demotion Form -->
                    <?php if ($currentRankId && $category && $staffCount>0): ?>
                    <form method="post" action="" id="promotionForm" autocomplete="off">
                        <input type="hidden" name="current_rank" value="<?=htmlspecialchars($currentRankId)?>">
                        <div class="row mb-3">
                            <div class="col-md-12 mb-2">
                                <label class="form-label">Select Staff Members *</label>
                                <select name="selected_staff[]" id="selected_staff" class="form-select select2-bootstrap5" multiple="multiple" required style="width:100%; min-width: 250px;">
                                    <?php foreach ($eligibleStaff as $staff): ?>
                                        <?php if ($staff->eligible): ?>
                                            <option value="<?=htmlspecialchars($staff->service_number)?>">
                                                <?=htmlspecialchars($staff->service_number . ' - ' . $staff->last_name . ', ' . $staff->first_name . ' (' . $staff->unit_id . ')')?>
                                            </option>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </select>
                                <small class="text-muted">Type Service Number or name to search staff at this rank.</small>
                                <!-- Profile pop-up enhancement -->
                                <?php if ($ENABLE_PROFILE_POPUP): ?>
                                <div id="staffProfileModal" class="modal fade" tabindex="-1">
                                    <div class="modal-dialog modal-lg">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Staff Profile</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body" id="staffProfileContent">
                                                <!-- Filled by JS -->
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div id="staffDetailsPanel"></div>
                        <div class="row mb-3">
                            <div class="col-md-4 mb-2">
                                <label class="form-label">Promotion / Reversion *</label>
                                <select name="promotion_type" id="promotion_type" class="form-select" required>
                                    <option value="">Select Type</option>
                                    <option value="Promotion" <?=isset($_POST['promotion_type']) && strtolower($_POST['promotion_type'])=='promotion'?'selected':''?>>Promotion</option>
                                    <option value="Reversion" <?=isset($_POST['promotion_type']) && strtolower($_POST['promotion_type'])=='reversion'?'selected':''?>>Reversion/Demotion</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-2">
                                <label class="form-label">Next / Previous Rank *</label>
                                <input type="text" class="form-control" value="<?=htmlspecialchars($nextRankName)?>" readonly>
                                <input type="hidden" name="next_rank" id="next_rank" value="<?=htmlspecialchars($nextRankIdValue)?>">
                            </div>
                            <div class="col-md-4 mb-2">
                                <label class="form-label">Date of Promotion *</label>
                                <input type="date" name="promotion_date" class="form-control" required value="<?=htmlspecialchars($_POST['promotion_date'] ?? date('Y-m-d'))?>">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6 mb-2">
                                <label class="form-label">Bulk Authority <span class="text-muted" aria-label="Apply this authority to all staff" data-bs-toggle="tooltip" title="Apply this authority to all selected staff."><i class="fa fa-info-circle"></i></span></label>
                                <input type="text" id="bulk_authority" class="form-control" placeholder="Apply authority to all">
                                <button type="button" id="apply_bulk_authority" class="btn btn-sm btn-outline-primary mt-2"><i class="fa fa-check"></i> Apply to All</button>
                            </div>
                            <div class="col-md-6 mb-2">
                                <label class="form-label">Bulk Remark <span class="text-muted" aria-label="Apply this remark to all staff" data-bs-toggle="tooltip" title="Apply this remark to all selected staff."><i class="fa fa-info-circle"></i></span></label>
                                <input type="text" id="bulk_remark" class="form-control" placeholder="Apply remark to all">
                                <button type="button" id="apply_bulk_remark" class="btn btn-sm btn-outline-primary mt-2"><i class="fa fa-check"></i> Apply to All</button>
                            </div>
                        </div>
                        <div class="text-end">
                            <button type="button" id="showConfirmModal" class="btn btn-primary px-5 py-2" aria-label="Review and confirm promotion/demotion" disabled><i class="fa fa-arrow-up"></i> Promote/Demote</button>
                            <?php if ($ENABLE_EXPORT_REPORT): ?>
                                <button type="submit" name="export_report" value="1" class="btn btn-outline-success ms-3">Export Promotion Report</button>
                            <?php endif; ?>
                        </div>
                        <div class="modal fade" id="confirmModal" tabindex="-1" aria-labelledby="confirmModalLabel" aria-hidden="true">
                        <div class="modal-dialog modal-lg">
                            <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="confirmModalLabel">Confirm Promotion/Demotion</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <div id="confirmSummary"></div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="button" id="confirmSubmitBtn" class="btn btn-primary">Confirm</button>
                            </div>
                            </div>
                        </div>
                        </div>
                        <!-- Bulk confirmation modal -->
                        <div class="modal fade" id="bulkConfirmModal" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Bulk Promotion Confirmation</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <p>You are about to promote <?=count($_POST['selected_staff']??[])?> staff. Are you sure?</p>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                        <button type="button" id="bulkConfirmSubmitBtn" class="btn btn-primary">Yes, Confirm</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                    <?php elseif ($currentRankId && !$category): ?>
                        <div class="alert alert-warning">No valid Officer or NCO rank selected.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.stepper { list-style: none; padding: 0; display: flex; gap: 10px; }
.step { padding: 4px 10px; border-radius: 12px; background: #eee; color: #333; }
.step.active { background: #28a745; color: #fff; font-weight: bold; }
</style>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
const staffDetailsCache = {};
let staffSearchLoading = false;

function enablePromoteButton() {
    let allFilled = true;
    $('.authority-input').each(function() { if (!$(this).val()) allFilled = false; });
    $('#showConfirmModal').prop('disabled', !allFilled);
}

$('#selected_staff').on('change', function() {
    const selected = $(this).val() || [];
    renderStaffPanels(selected);
    enablePromoteButton();
});

function renderStaffPanels(selected) {
    const panel = $('#staffDetailsPanel');
    panel.empty();
    if (!selected || selected.length === 0) {
        $('#showConfirmModal').prop('disabled', true);
        return;
    }
    selected.forEach(svcNo => {
        let staff = staffDetailsCache[svcNo] || {id: svcNo, text: svcNo};
        panel.append(`
            <div class="card mb-2 staff-detail-card" data-svcno="${svcNo}">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-3 mb-2 profile-trigger" style="cursor:pointer;" data-svcno="${svcNo}">
                            <strong>${staff.text}</strong>
                            <br>
                            <small class="text-muted">Click for profile</small>
                        </div>
                        <div class="col-md-3 mb-2">
                            <label class="form-label mb-0">Authority <span class="text-danger">*</span></label>
                            <input type="text" name="authority[${svcNo}]" class="form-control authority-input" aria-label="Authority for ${staff.text}" required>
                        </div>
                        <div class="col-md-3 mb-2">
                            <label class="form-label mb-0">Remark</label>
                            <input type="text" name="remark[${svcNo}]" class="form-control remark-input" aria-label="Remark for ${staff.text}">
                        </div>
                        <div class="col-md-3 mb-2">
                            <span class="badge bg-info">Current Unit: <span class="current-unit">${staff.unit_name || ''}</span></span>
                        </div>
                    </div>
                </div>
            </div>
        `);
    });
    enablePromoteButton();
    $('.authority-input').on('input', enablePromoteButton);

    // Profile pop-up
    $('.profile-trigger').on('click', function() {
        let svcNo = $(this).data('svcno');
        showStaffProfile(svcNo);
    });
}

function showStaffProfile(svcNo) {
    $.get('ajax_staff_profile.php', { service_number: svcNo }, function(data) {
        $('#staffProfileContent').html(data);
        var modal = new bootstrap.Modal(document.getElementById('staffProfileModal'));
        modal.show();
    }).fail(function() {
        $('#staffProfileContent').html('<div class="alert alert-danger">Failed to load profile.</div>');
        var modal = new bootstrap.Modal(document.getElementById('staffProfileModal'));
        modal.show();
    });
}

$('#apply_bulk_authority').on('click', function() {
    let authority = $('#bulk_authority').val();
    if (!authority) return;
    $('.authority-input').val(authority);
    enablePromoteButton();
});
$('#apply_bulk_remark').on('click', function() {
    let remark = $('#bulk_remark').val();
    if (!remark) return;
    $('.remark-input').val(remark);
});

$('#showConfirmModal').on('click', function() {
    let selected = $('#selected_staff').val() || [];
    if (selected.length >= <?=json_encode($BULK_CONFIRMATION_THRESHOLD)?>) {
        var modal = new bootstrap.Modal(document.getElementById('bulkConfirmModal'));
        modal.show();
        $('#bulkConfirmSubmitBtn').off('click').on('click', function() {
            renderConfirmSummary(selected);
            var confirmModal = new bootstrap.Modal(document.getElementById('confirmModal'));
            confirmModal.show();
            modal.hide();
        });
        return;
    }
    renderConfirmSummary(selected);
    var modal = new bootstrap.Modal(document.getElementById('confirmModal'));
    modal.show();
});

function renderConfirmSummary(selected) {
    let summary = '<ul class="list-group">';
    selected.forEach(svcNo => {
        let staff = staffDetailsCache[svcNo] || {id: svcNo, text: svcNo};
        let authority = $(`input[name='authority[${svcNo}]']`).val();
        let remark = $(`input[name='remark[${svcNo}]']`).val();
        summary += `<li class="list-group-item">${staff.text} <br><strong>Authority:</strong> ${authority || '-'} <br><strong>Remark:</strong> ${remark || '-'}</li>`;
    });
    summary += '</ul>';
    $('#confirmSummary').html(summary);
}

$('#confirmSubmitBtn').on('click', function() {
    $("#promotionForm input[name^='promotion_authority']").remove();
    $("#promotionForm input[name^='promotion_remark']").remove();
    const selected = $('#selected_staff').val() || [];
    selected.forEach(svcNo => {
        let authority = $(`input[name='authority[${svcNo}]']`).val();
        let remark = $(`input[name='remark[${svcNo}]']`).val();
        $('<input>').attr({type:'hidden', name:`promotion_authority[${svcNo}]`, value: authority}).appendTo('#promotionForm');
        $('<input>').attr({type:'hidden', name:`promotion_remark[${svcNo}]`, value: remark}).appendTo('#promotionForm');
    });
    $('#promotionForm').submit();
});

$('#current_rank').on('change', function() {
    $('#rankForm').submit();
});

$('#promotion_type').on('change', function() {
    let selectedStaff = $('#selected_staff').val();
    if ($('#selected_staff_preserve').length === 0) {
        $('<input>').attr({
            type: 'hidden',
            id: 'selected_staff_preserve',
            name: 'selected_staff_preserve'
        }).appendTo('#promotionForm');
    }
    $('#selected_staff_preserve').val(JSON.stringify(selectedStaff));
    $('#promotionForm').submit();
});

$(function() {
    let preserved = $('input[name="selected_staff_preserve"]').val();
    if (preserved) {
        let selected = JSON.parse(preserved);
        let interval = setInterval(function() {
            if ($('#selected_staff').hasClass('select2-hidden-accessible')) {
                $('#selected_staff').val(selected).trigger('change');
                clearInterval(interval);
            }
        }, 100);
    }
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.forEach(function (tooltipTriggerEl) {
        new bootstrap.Tooltip(tooltipTriggerEl);
    });
    enablePromoteButton();
});
</script>
<?php include dirname(__DIR__) . '/shared/footer.php'; ?>