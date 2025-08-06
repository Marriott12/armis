<?php
// Define module constants
define('ARMIS_ADMIN_BRANCH', true);
define('ARMIS_DEVELOPMENT', true); // Set to false in production

// Include admin branch authentication and database
require_once __DIR__ . '/includes/auth.php';

// Include RBAC system
require_once dirname(__DIR__) . '/shared/rbac.php';

// Require authentication and admin privileges
requireAuth();

// Check if user has access to admin_branch module
requireModuleAccess('admin_branch');

$pageTitle = "Award Medal";
$moduleName = "Admin Branch";
$moduleIcon = "users-cog";
$currentPage = "medals";

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
        ]
    ],
    ['title' => 'System Settings', 'url' => '/Armis2/admin_branch/system_settings.php', 'icon' => 'cogs', 'page' => 'settings']
];

// CSRF Token
if (!isset($_SESSION)) { session_start(); }
if (!function_exists('Token')) {
    class Token {
        public static function generate() {
            if (!isset($_SESSION['csrf_token'])) {
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            }
            return $_SESSION['csrf_token'];
        }
        public static function check($token) {
            return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
        }
    }
}
$csrfToken = Token::generate();

$errors = [];
$success = false;
$pdo = getDbConnection();
$medals = [];
try {
    // Fetch medals and ensure unique by name (or id if you prefer)
    $stmt = $pdo->query("SELECT id, name, description, image_path FROM medals ORDER BY name ASC");
    $allMedals = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Remove duplicate medals by name (or by id as key)
    $uniqueMedals = [];
    foreach ($allMedals as $medal) {
        // Use name as key (case-insensitive) to avoid duplicates
        $key = strtolower(trim($medal['name']));
        if (!isset($uniqueMedals[$key])) {
            $uniqueMedals[$key] = (object)$medal;
        }
    }
    $medals = array_values($uniqueMedals);
} catch (Exception $e) {
    $errors[] = "Error fetching medals: " . htmlspecialchars($e->getMessage());
}

$BULK_CONFIRMATION_THRESHOLD = 5;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Token::check($_POST['csrf'] ?? '')) {
        $errors[] = "Invalid CSRF token.";
    } else {
        $medalId = trim($_POST['medal_id'] ?? '');
        $awardDate = trim($_POST['award_date'] ?? '');
        $selectedStaff = $_POST['selected_staff'] ?? [];
        $auth = trim($_POST['auth'] ?? '');
        $remark = trim($_POST['remark'] ?? '');
        $gazetteReference = trim($_POST['gazette_reference'] ?? '');
        $barNumber = trim($_POST['bar_number'] ?? '');

        if (!in_array($_SESSION['role'] ?? '', ['admin', 'branch-admin', 'hr'])) {
            $errors[] = "You do not have permission to assign medals.";
        }
        if (!ctype_digit($medalId)) $errors[] = "Invalid medal selected.";
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $awardDate)) $errors[] = "Invalid award date.";
        if (empty($selectedStaff) || !is_array($selectedStaff)) $errors[] = "Please select at least one staff member.";
        foreach ($selectedStaff as $sid) {
            if (!ctype_digit($sid)) $errors[] = "Invalid staff selection.";
        }
        if ($auth === '') $errors[] = "Please enter the authority.";
        if (count($selectedStaff) !== count(array_unique($selectedStaff))) {
            $errors[] = "Duplicate staff selected.";
        }

        $staffInfoList = [];
        foreach ($selectedStaff as $staffId) {
            $stmt = $pdo->prepare("SELECT service_number FROM staff WHERE id = ?");
            $stmt->execute([$staffId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row || empty($row['service_number'])) {
                $errors[] = "Staff member with ID $staffId not found or missing service number.";
                continue;
            }
            $service_number = $row['service_number'];
            $stmt2 = $pdo->prepare("SELECT COUNT(*) FROM staff_medals WHERE staff_id = ? AND medal_id = ?");
            $stmt2->execute([$staffId, $medalId]);
            $alreadyAwarded = $stmt2->fetchColumn();
            if ($alreadyAwarded > 0) {
                $errors[] = "Staff member {$service_number} has already been awarded this medal.";
            }
            $staffInfoList[] = [
                'staff_id' => $staffId,
                'service_number' => $service_number,
            ];
        }

        if (empty($errors)) {
            try {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare("INSERT INTO staff_medals (staff_id, service_number, medal_id, award_date, citation, gazette_reference, bar_number, created_by, created_at, authority) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $createdBy = $_SESSION['username'] ?? 'admin';
                $now = date('Y-m-d H:i:s');
                foreach ($staffInfoList as $info) {
                    $stmt->execute([
                        $info['staff_id'],
                        $info['service_number'],
                        $medalId,
                        $awardDate,
                        $remark,
                        $gazetteReference,
                        $barNumber,
                        $createdBy,
                        $now,
                        $auth
                    ]);
                }
                $pdo->commit();
                $success = "Medal assigned successfully.";
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                $csrfToken = $_SESSION['csrf_token'];
                $_POST = [];
            } catch (Exception $e) {
                $pdo->rollBack();
                $errors[] = "Error assigning medal: " . htmlspecialchars($e->getMessage());
            }
        }
    }
}
?>

<?php include dirname(__DIR__) . '/shared/header.php'; ?>
<?php include dirname(__DIR__) . '/shared/sidebar.php'; ?>
<div class="content-wrapper with-sidebar">
    <div class="container-fluid p-4">
        <div class="mb-3">
            <a href="medals.php" class="btn btn-outline-secondary"><i class="fa fa-arrow-left"></i> Back to Medals List</a>
        </div>
        <div class="card shadow-sm">
        <div class="card-header bg-info text-white">
            <h4 class="mb-0"><i class="fa fa-medal"></i> Assign Medal to Staff</h4>
        </div>
        <div class="card-body">
            <?php if ($success): ?>
                <div class="alert alert-success"><?=htmlspecialchars($success)?></div>
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
            <div class="mb-3">
                <ul class="stepper mb-0">
                    <li class="step active">Select Medal</li>
                    <li class="step <?=isset($_POST['medal_id'])?'active':''?>">Select Staff</li>
                    <li class="step <?=isset($_POST['selected_staff'])?'active':''?>">Details</li>
                    <li class="step">Confirm</li>
                    <li class="step <?=($success?'active':'')?>">Success</li>
                </ul>
            </div>
            <form method="post" action="" autocomplete="off" aria-label="Assign Medal Form" id="assignMedalForm">
                <input type="hidden" name="csrf" value="<?=htmlspecialchars($csrfToken)?>">
                <div class="mb-3">
                    <label for="medal_id" class="form-label" aria-label="Medal">Medal <span class="text-danger">*</span></label>
                    <select name="medal_id" id="medal_id" class="form-select" required aria-required="true">
                        <option value="">Select Medal...</option>
                        <?php foreach ($medals as $medal): ?>
                            <option value="<?=htmlspecialchars($medal->id)?>" <?=isset($_POST['medal_id']) && $_POST['medal_id']==$medal->id?'selected':''?>>
                                <?=htmlspecialchars($medal->name)?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (!empty($medals)): ?>
                    <div class="mt-2">
                        <?php foreach ($medals as $medal): ?>
                            <span class="badge bg-secondary mx-1" title="<?=htmlspecialchars($medal->description)?>">
                                <?php if(!empty($medal->image_path)): ?>
                                    <img src="<?=htmlspecialchars($medal->image_path)?>" alt="Medal" height="22" style="vertical-align:middle;">
                                <?php endif; ?>
                                <?=htmlspecialchars($medal->name)?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="mb-3">
                    <label for="award_date" class="form-label" aria-label="Award Date">Award Date <span class="text-danger">*</span></label>
                    <input type="date" class="form-control" id="award_date" name="award_date" required aria-required="true" min="1900-01-01" max="<?=date('Y-m-d')?>" value="<?=htmlspecialchars($_POST['award_date'] ?? date('Y-m-d'))?>">
                </div>
                <div class="mb-3">
                    <label for="auth" class="form-label" aria-label="Authority">Authority <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="auth" name="auth" required aria-required="true" value="<?=htmlspecialchars($_POST['auth'] ?? '')?>">
                </div>
                <div class="mb-3">
                    <label for="selected_staff" class="form-label" aria-label="Select Staff Members">Select Staff Members <span class="text-danger">*</span></label>
                    <select name="selected_staff[]" id="selected_staff" class="form-select" multiple required aria-required="true" style="width:100%;"></select>
                    <small class="text-muted">Type Service Number or name to search staff.</small>
                    <div id="staff-loading" class="spinner-border text-primary d-none mt-1" role="status" style="width:1.5rem;height:1.5rem;"><span class="visually-hidden">Loading...</span></div>
                </div>
                <div id="staff-counter" class="form-text" aria-live="polite"></div>
                <div class="mb-3">
                    <label for="remark" class="form-label" aria-label="Citation or Remarks">Citation / Remarks</label>
                    <input type="text" class="form-control" id="remark" name="remark" value="<?=htmlspecialchars($_POST['remark'] ?? '')?>">
                </div>
                <div class="mb-3">
                    <label for="gazette_reference" class="form-label">Gazette Reference</label>
                    <input type="text" class="form-control" id="gazette_reference" name="gazette_reference" value="<?=htmlspecialchars($_POST['gazette_reference'] ?? '')?>">
                </div>
                <div class="mb-3">
                    <label for="bar_number" class="form-label">Bar Number</label>
                    <input type="text" class="form-control" id="bar_number" name="bar_number" value="<?=htmlspecialchars($_POST['bar_number'] ?? '')?>">
                </div>
                <div class="text-end">
                    <button type="button" id="showConfirmModal" class="btn btn-primary px-5 py-2" aria-label="Review and confirm medal assignment" disabled><i class="fa fa-medal"></i> Assign Medal</button>
                </div>
                <div class="modal fade" id="confirmModal" tabindex="-1" aria-labelledby="confirmModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="confirmModalLabel">Confirm Medal Assignment</h5>
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
                <div class="modal fade" id="bulkConfirmModal" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Bulk Medal Assignment Confirmation</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <p>You are about to assign medals to <span id="bulkCount"></span> staff. Are you sure?</p>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="button" id="bulkConfirmSubmitBtn" class="btn btn-primary">Yes, Confirm</button>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
<style>
.stepper { list-style: none; padding: 0; display: flex; gap: 10px; }
.step { padding: 4px 10px; border-radius: 12px; background: #eee; color: #333; }
.step.active { background: #17a2b8; color: #fff; font-weight: bold; }
.select2-container--bootstrap-5 .select2-selection--multiple .select2-selection__choice {
    background-color: #0d6efd;
    border-color: #0d6efd;
    color: #fff;
    font-size: 1em;
}
.select2-container--bootstrap-5 .select2-selection--multiple .select2-selection__choice__remove {
    color: #fff;
    margin-right: 6px;
    font-weight: bold;
    cursor: pointer;
}
</style>
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
$('#selected_staff').select2({
    theme: 'bootstrap-5',
    placeholder: "Type Service Number or name to search staff.",
    allowClear: true,
    width: '100%',
    minimumInputLength: 1,
    multiple: true,
    ajax: {
        url: 'search_staff.php',
        dataType: 'json',
        delay: 250,
        data: function(params) {
            $('#staff-loading').removeClass('d-none');
            return { q: params.term || '' };
        },
        processResults: function(data) {
            $('#staff-loading').addClass('d-none');
            return { results: data };
        }
    },
    templateResult: function(item) {
        if (item.loading) return item.text;
        // Display as: 007414 - Lieutenant Numba Marriott Gift
        return `<span><strong>${item.service_number}</strong> - ${item.rank_name ? item.rank_name + ' ' : ''}${item.last_name} ${item.first_name}</span>`;
    },
    templateSelection: function(item) {
        if (!item.id || !item.service_number) return item.text || item.id;
        return `${item.service_number} - ${item.rank_name ? item.rank_name + ' ' : ''}${item.last_name} ${item.first_name}`;
    },
    escapeMarkup: function(markup) { return markup; },
    closeOnSelect: false // keep input open for more selection
});
$('#selected_staff').on('change', function() {
    let selected = $(this).select2('data');
    $('#staff-counter').text(selected.length + ' staff selected');
});
function enableAssignButton() {
    let allFilled = $('#medal_id').val() && $('#award_date').val() && $('#auth').val() && $('#selected_staff').val().length > 0;
    $('#showConfirmModal').prop('disabled', !allFilled);
}
$('#medal_id, #award_date, #auth').on('input', enableAssignButton);
$('#selected_staff').on('change', enableAssignButton);
$('#showConfirmModal').on('click', function() {
    let selected = $('#selected_staff').select2('data') || [];
    if (selected.length >= <?=json_encode($BULK_CONFIRMATION_THRESHOLD)?>) {
        $('#bulkCount').text(selected.length);
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
    selected.forEach(function(staff){
        summary += `<li class="list-group-item">${staff.service_number} - ${staff.rank_name ? staff.rank_name + ' ' : ''}${staff.last_name} ${staff.first_name} <br><strong>Medal:</strong> ${$('#medal_id option:selected').text()} <br><strong>Authority:</strong> ${$('#auth').val() || '-'}</li>`;
    });
    summary += '</ul>';
    $('#confirmSummary').html(summary);
}
$('#confirmSubmitBtn').on('click', function() {
    $('#assignMedalForm').submit();
});
$('#assignMedalForm').on('submit', function() {
    $('#assignMedalBtn').prop('disabled', true).text('Assigning...');
});
$(function() {
    enableAssignButton();
});
</script>
<?php include dirname(__DIR__) . '/shared/footer.php'; ?>