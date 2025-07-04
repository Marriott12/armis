<?php
require_once '../init.php';
require_once $abs_us_root . $us_url_root . 'users/includes/template/prep.php';

if (!securePage($_SERVER['PHP_SELF'])) { die(); }

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

// Fetch medals for dropdown
$medals = $db->query("SELECT id, medName FROM medals ORDER BY medName ASC")->results();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Token::check($_POST['csrf'] ?? '')) {
        $errors[] = "Invalid CSRF token.";
    } else {
        $medalId = trim($_POST['medal_id'] ?? '');
        $awardDate = trim($_POST['award_date'] ?? '');
        $selectedStaff = $_POST['selected_staff'] ?? [];
        $auth = trim($_POST['auth'] ?? '');
        $remark = trim($_POST['remark'] ?? '');

        if ($medalId === '') $errors[] = "Please select a medal.";
        if ($awardDate === '') $errors[] = "Please select the award date.";
        if (empty($selectedStaff)) $errors[] = "Please select at least one staff member.";
        if ($auth === '') $errors[] = "Please enter the authority.";

        if (empty($errors)) {
            try {
                foreach ($selectedStaff as $svcNo) {
                    $db->insert('staff_medal', [
                        'medID'      => $medalId,
                        'svcNo'      => $svcNo,
                        'issueDate'  => $awardDate,
                        'auth'       => $auth,
                        'comment'    => $remark,
                        'createdBy'  => $user->data()->username ?? 'admin',
                        'dateCreated'=> date('Y-m-d H:i:s')
                    ]);
                }
                $success = "Medal assigned successfully.";
            } catch (Exception $e) {
                $errors[] = "Error assigning medal: " . htmlspecialchars($e->getMessage());
            }
        }
    }
}
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" />

<div class="container my-5">
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
            <form method="post" action="" autocomplete="off">
                <input type="hidden" name="csrf" value="<?=htmlspecialchars($csrfToken)?>">
                <div class="mb-3">
                    <label for="medal_id" class="form-label">Medal <span class="text-danger">*</span></label>
                    <select name="medal_id" id="medal_id" class="form-select" required>
                        <option value="">Select Medal...</option>
                        <?php foreach ($medals as $medal): ?>
                            <option value="<?=htmlspecialchars($medal->id)?>" <?=isset($_POST['medal_id']) && $_POST['medal_id']==$medal->id?'selected':''?>><?=htmlspecialchars($medal->medName)?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label for="award_date" class="form-label">Award Date <span class="text-danger">*</span></label>
                    <input type="date" class="form-control" id="award_date" name="award_date" required value="<?=htmlspecialchars($_POST['award_date'] ?? date('Y-m-d'))?>">
                </div>
                <div class="mb-3">
                    <label for="auth" class="form-label">Authority <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="auth" name="auth" required value="<?=htmlspecialchars($_POST['auth'] ?? '')?>">
                </div>
                <div class="mb-3">
                    <label for="selected_staff" class="form-label">Select Staff Members <span class="text-danger">*</span></label>
                    <select name="selected_staff[]" id="selected_staff" class="form-select" multiple="multiple" required style="width:100%;">
                        <!-- Options loaded dynamically via AJAX -->
                    </select>
                    <small class="text-muted">Type Service Number or name to search staff.</small>
                </div>
                <div class="mb-3">
                    <label for="remark" class="form-label">Remarks</label>
                    <input type="text" class="form-control" id="remark" name="remark" value="<?=htmlspecialchars($_POST['remark'] ?? '')?>">
                </div>
                <button type="submit" class="btn btn-success"><i class="fa fa-medal"></i> Assign Medal</button>
            </form>
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
                q: params.term
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
</script>

<?php require_once $abs_us_root . $us_url_root . 'users/includes/html_footer.php'; ?>