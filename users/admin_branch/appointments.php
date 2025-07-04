<?php
require_once '../init.php';
require_once $abs_us_root . $us_url_root . 'users/includes/template/prep.php';

if (!securePage($_SERVER['PHP_SELF'])) { die(); }

// Fetch all ranks and units
$ranks = $db->query("SELECT rankID, rankName, rankIndex FROM ranks ORDER BY rankIndex ASC")->results();
$units = $db->query("SELECT unitID, unitName FROM units ORDER BY unitName ASC")->results();

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
    $currentRank = $db->query("SELECT * FROM ranks WHERE rankID = ?", [$currentRankId])->first();
}

// Step 2: Handle form submission for appointments
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['appoint_staff'])) {
    $selectedStaff = $_POST['selected_staff'] ?? [];
    $apptDate = $_POST['appt_date'] ?? '';
    $unitsSelected = $_POST['unit'] ?? [];
    $comments = $_POST['comment'] ?? [];
    $createdBy = $user->data()->id ?? 0;
    $dateCreated = date('Y-m-d H:i:s');

    if (empty($selectedStaff)) $errors[] = "Please select at least one staff member.";
    if (empty($apptDate)) $errors[] = "Please select the appointment date.";

    foreach ($selectedStaff as $svcNo) {
        $unitID = trim($unitsSelected[$svcNo] ?? '');
        if (empty($unitID)) {
            $errors[] = "Please select a unit for staff member " . htmlspecialchars($svcNo) . ".";
        }
    }

    if (empty($errors)) {
        foreach ($selectedStaff as $svcNo) {
            $unitID = htmlspecialchars(trim($unitsSelected[$svcNo]));
            $comment = htmlspecialchars(trim($comments[$svcNo] ?? ''));
            try {
                $db->insert('staff_appointment', [
                    'svcNo' => $svcNo,
                    'unitID' => $unitID,
                    'apptDate' => $apptDate,
                    'comment' => $comment,
                    'createdBy' => $createdBy,
                    'dateCreated' => $dateCreated
                ]);
                $db->update('staff', $svcNo, [
                    'unitID' => $unitID
                ], 'svcNo');
            } catch (Exception $e) {
                $errors[] = "Error updating " . htmlspecialchars($svcNo) . ": " . htmlspecialchars($e->getMessage());
            }
        }
        if (empty($errors)) $success = true;
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
                        <select name="selected_staff[]" id="selected_staff" class="form-select" multiple="multiple" required style="width:100%;">
                            <!-- Options loaded dynamically via AJAX -->
                        </select>
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
            <?php elseif ($currentRankId): ?>
                <div class="alert alert-warning">No staff found for the selected rank.</div>
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
                        <div class="col-md-4 mb-2">
                            <strong>${svcNo}</strong>
                        </div>
                        <div class="col-md-4 mb-2">
                            <label class="form-label mb-0">Unit</label>
                            <select name="unit[${svcNo}]" class="form-select unit-select" style="width: 100%;">${unitOptions}</select>
                        </div>
                        <div class="col-md-4 mb-2">
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
        $('.staff-detail-card input[type="text"]').val(comment);
    });

    // Back to Top button
    $(window).scroll(function() {
        if ($(this).scrollTop() > 200) {
            $('#backToTopBtn').fadeIn();
        } else {
            $('#backToTopBtn').fadeOut();
        }
    });
    $('#backToTopBtn').click(function() {
        $('html, body').animate({scrollTop: 0}, 400);
        return false;
    });

    // Auto-submit rank form on change
    $('#current_rank').on('change', function() {
        $('#rankForm').submit();
    });
});
</script>

<?php require_once $abs_us_root . $us_url_root . 'users/includes/html_footer.php'; ?>