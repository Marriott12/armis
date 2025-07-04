<?php
require_once '../init.php';
require_once $abs_us_root . $us_url_root . 'users/includes/template/prep.php';

// RBAC: Only allow users with admin permission (permission_id = 1)
if (!isset($user) || !$user->isLoggedIn()) {
    die("Access denied.");
}
$db = DB::getInstance();
$adminPermissionQ = $db->query("SELECT id FROM user_permission_matches WHERE user_id = ? AND permission_id = 1", [$user->data()->id]);
if ($adminPermissionQ->count() == 0) {
    die("Access denied.");
}

// Fetch all ranks for dropdowns (ordered by rankIndex ASC)
$ranks = $db->query("SELECT rankID, rankName, rankIndex FROM ranks ORDER BY rankIndex ASC")->results();

// Exclude certain ranks
$excludedRanks = ['Officer Cadet', 'Recruit', 'Mister', 'Miss'];
$excludedRankIds = array_map(function($r) use ($excludedRanks) {
    return in_array($r->rankName, $excludedRanks) ? $r->rankID : null;
}, $ranks);
$excludedRankIds = array_filter($excludedRankIds);

$errors = [];
$success = false;
$successMessages = [];

// Step 1: Select current rank
$currentRankId = $_POST['current_rank'] ?? $_GET['current_rank'] ?? '';
$currentRank = null;
$category = null; // "Officer" or "NCO"
if ($currentRankId) {
    $currentRank = $db->query("SELECT * FROM ranks WHERE rankID = ?", [$currentRankId])->first();
    if ($currentRank) {
        $category = ($currentRank->rankIndex >= 1 && $currentRank->rankIndex <= 13) ? 'Officer' : (($currentRank->rankIndex >= 15 && $currentRank->rankIndex <= 26) ? 'NCO' : null);
    }
}
//Preserve selected staff from previous submission
if (isset($_POST['selected_staff_preserve']) && !isset($_POST['selected_staff'])) {
    $_POST['selected_staff'] = json_decode($_POST['selected_staff_preserve'], true) ?? [];
}
// Step 2: Handle form submission for promotion/demotion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['promote_staff'])) {
    $selectedStaff = $_POST['selected_staff'] ?? [];
    $promotionType = strtolower(trim($_POST['promotion_type'] ?? ''));
    $nextRankId = $_POST['next_rank'] ?? '';
    $promotionDate = $_POST['promotion_date'] ?? '';
    $promotionAuthority = $_POST['promotion_authority'] ?? '';
    $promotionRemark = $_POST['promotion_remark'] ?? '';

    if (empty($selectedStaff)) $errors[] = "Please select at least one staff member.";
    if (empty($nextRankId)) $errors[] = "Please select the next rank.";
    if (empty($promotionType)) $errors[] = "Please select Promotion or Reversion.";
    if (empty($promotionDate)) $errors[] = "Please select the date.";
    if (empty($promotionAuthority)) $errors[] = "Please enter the authority.";

    // Validate next rank
    $nextRankObj = $db->query("SELECT * FROM ranks WHERE rankID = ?", [$nextRankId])->first();
    if (!$nextRankObj) $errors[] = "Invalid next rank selected.";

    // Validate all selected staff are at the current rank
    foreach ($selectedStaff as $svcNo) {
        $staff = $db->query("SELECT * FROM staff WHERE svcNo = ?", [$svcNo])->first();
        if (!$staff || $staff->rankID != $currentRankId) {
            $errors[] = "Staff member $svcNo is not at the selected current rank.";
        }
    }

    if (empty($errors)) {
        foreach ($selectedStaff as $svcNo) {
            try {
                $newRankName = $nextRankObj->rankName;
                $isTemporal = stripos($newRankName, 'Temporal') !== false;

                // Determine WEF and rank fields based on type and temporal
                $subWef = $tempWef = $subRank = $tempRank = null;

                if ($promotionType === 'promotion') {
                    if ($isTemporal) {
                        $tempWef  = $promotionDate;
                        $tempRank = $newRankName;
                    } else {
                        $subWef  = $promotionDate;
                        $subRank = $newRankName;
                    }
                } else { // Reversion/Demotion
                    // Clear the temporal or substantive fields if reverting
                    if ($isTemporal) {
                        $tempWef  = null;
                        $tempRank = null;
                    } else {
                        $subWef  = null;
                        $subRank = null;
                    }
                }

                // Update staff table
                $db->update('staff', $svcNo, [
                    'rankID'   => $nextRankId,
                    'subRank'  => $subRank,
                    'tempRank' => $tempRank,
                    'subWef'   => $subWef,
                    'tempWef'  => $tempWef
                ], 'svcNo');

                // Insert into staff_promotions
                $db->insert('staff_promotions', [
                    'svcNo'       => $svcNo,
                    'currentRank' => $currentRankId,
                    'dateFrom'    => date('Y-m-d'),
                    'dateTo'      => $promotionDate,
                    'type'        => $promotionType,
                    'newRank'     => $nextRankId,
                    'authority'   => $promotionAuthority,
                    'remark'      => $promotionRemark,
                ]);

                // Add success message
                $successMessages[] = "$svcNo promoted to $newRankName. Fields updated: "
                    . ($subRank ? "subRank/subWef" : "tempRank/tempWef");

            } catch (Exception $e) {
                $errors[] = "Error updating $svcNo: " . $e->getMessage();
            }
        }

        if (empty($errors)) $success = true;
    }
}

// Step 3: Prepare next/previous rank for display and submission
$nextRankObj = null;
$nextRankName = '';
$nextRankIdValue = '';
if ($currentRank && isset($_POST['promotion_type'])) {
    $promotionType = strtolower(trim($_POST['promotion_type']));
    // For Officers: rankIndex 1-13, for NCO: 15-26
    $validRange = [];
    if ($category === 'Officer') {
        $validRange = range(1, 13);
    } elseif ($category === 'NCO') {
        $validRange = range(15, 26);
    }

    // For Promotion: get the next higher rank in the valid range (lower rankIndex)
    if ($promotionType === 'promotion' && !empty($validRange)) {
        foreach ($ranks as $r) {
            if (
                $r->rankIndex < $currentRank->rankIndex &&
                !in_array($r->rankID, $excludedRankIds) &&
                in_array($r->rankIndex, $validRange)
            ) {
                if ($nextRankObj === null || $r->rankIndex > $nextRankObj->rankIndex) {
                    $nextRankObj = $r;
                }
            }
        }
    }
    // For Reversion/Demotion: get the next lower rank in the valid range (higher rankIndex)
    elseif (in_array($promotionType, ['reversion', 'demotion']) && !empty($validRange)) {
        foreach ($ranks as $r) {
            if (
                $r->rankIndex > $currentRank->rankIndex &&
                !in_array($r->rankID, $excludedRankIds) &&
                in_array($r->rankIndex, $validRange)
            ) {
                if ($nextRankObj === null || $r->rankIndex < $nextRankObj->rankIndex) {
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
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" />

<div class="container my-5">
    <div class="mb-3">
        <a href="../admin_branch.php" class="btn btn-outline-secondary">
            <i class="fa fa-arrow-left"></i> Back to Admin Branch
        </a>
    </div>
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

            <!-- Step 1: Select current rank -->
            <form class="mb-4" id="rankForm" method="get" autocomplete="off">
                <div class="row">
                    <div class="col-md-6 mb-2">
                        <label class="form-label">Current Rank Being Promoted / Reversed *</label>
                        <select name="current_rank" id="current_rank" class="form-select" required>
                            <option value="">Select Current Rank...</option>
                            <?php
                            // Filter only Officer or NCO ranks for dropdown
                            foreach ($ranks as $r):
                                if (in_array($r->rankID, $excludedRankIds)) continue;
                                $isValid = ($r->rankIndex >= 1 && $r->rankIndex <= 13) || ($r->rankIndex >= 15 && $r->rankIndex <= 26);
                                if (!$isValid) continue;
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

            <!-- Step 2: Bulk Promotion/Demotion Form -->
            <?php if ($currentRankId && $category): ?>
            <form method="post" action="" id="promotionForm" autocomplete="off">
                <input type="hidden" name="current_rank" value="<?=htmlspecialchars($currentRankId)?>">
                <div class="row mb-3">
                    <div class="col-md-12 mb-2">
                        <label class="form-label">Select Staff Members *</label>
                        <select name="selected_staff[]" id="selected_staff" class="form-select" multiple="multiple" required style="width:100%;">
                            <!-- Options loaded dynamically via AJAX -->
                        </select>
                        <small class="text-muted">Type Service Number or name to search staff.</small>
                    </div>
                </div>
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
                        <label class="form-label">Authority *</label>
                        <input type="text" name="promotion_authority" id="promotion_authority" class="form-control" required value="<?=htmlspecialchars($_POST['promotion_authority'] ?? '')?>">
                        <button type="button" id="apply_bulk_authority" class="btn btn-sm btn-outline-primary mt-2"><i class="fa fa-check"></i> Apply to All</button>
                    </div>
                    <div class="col-md-6 mb-2">
                        <label class="form-label">Remarks</label>
                        <input type="text" name="promotion_remark" id="promotion_remark" class="form-control" value="<?=htmlspecialchars($_POST['promotion_remark'] ?? '')?>">
                        <button type="button" id="apply_bulk_remark" class="btn btn-sm btn-outline-primary mt-2"><i class="fa fa-check"></i> Apply to All</button>
                    </div>
                </div>
                <div class="text-end">
                    <button type="submit" id="submitBtn" name="promote_staff" class="btn btn-primary px-5 py-2"><i class="fa fa-arrow-up"></i> Promote/Demote</button>
                </div>
            </form>
            <?php elseif ($currentRankId && !$category): ?>
                <div class="alert alert-warning">No valid Officer or NCO rank selected.</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
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

// Auto-submit rank form on change
$('#current_rank').on('change', function() {
    $('#rankForm').submit();
});

// Bulk authority/remark assignment
$('#apply_bulk_authority').on('click', function() {
    let authority = $('#promotion_authority').val();
    if (!authority) return;
    $('input[name="promotion_authority"]').val(authority);
});
$('#apply_bulk_remark').on('click', function() {
    let remark = $('#promotion_remark').val();
    if (!remark) return;
    $('input[name="promotion_remark"]').val(remark);
});

// Auto-refresh next/previous rank on promotion_type change
$('#promotion_type').on('change', function() {
    $('#promotionForm').submit();
});

// Auto-refresh next/previous rank on promotion_type change, preserving selected staff
$('#promotion_type').on('change', function() {
    // Preserve selected staff before submit
    let selectedStaff = $('#selected_staff').val();
    // Set a hidden input with selected staff before submitting
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

// On document ready, restore selected staff if preserved
$(function() {
    let preserved = $('input[name="selected_staff_preserve"]').val();
    if (preserved) {
        let selected = JSON.parse(preserved);
        // Wait until Select2 is initialized (by AJAX)
        let interval = setInterval(function() {
            if ($('#selected_staff').hasClass('select2-hidden-accessible')) {
                $('#selected_staff').val(selected).trigger('change');
                clearInterval(interval);
            }
        }, 100);
    }
});
</script>

<?php require_once $abs_us_root . $us_url_root . 'users/includes/html_footer.php'; ?>