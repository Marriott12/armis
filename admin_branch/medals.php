<?php
// Define module constants
define('ARMIS_ADMIN_BRANCH', true);
define('ARMIS_DEVELOPMENT', true);

// Include admin branch authentication and database
require_once __DIR__ . '/includes/auth.php';
require_once dirname(__DIR__) . '/shared/rbac.php';
require_once dirname(__DIR__) . '/shared/database_connection.php';

requireAuth();
requireModuleAccess('admin_branch');

$pageTitle = "Honors and Awards";
$moduleName = "Admin Branch";
$moduleIcon = "users-cog";
$currentPage = "medals";

$sidebarLinks = []; // set by shared nav include below
require_once __DIR__ . '/includes/sidebar_nav.php';

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

$pdo = getDbConnection();
$errors = [];
$success = false;

// honorId is a fixed-width varchar(2) primary key in the `honors` table.
function isValidHonorId($id) {
    return is_string($id) && preg_match('/^[A-Za-z0-9]{1,2}$/', $id);
}

// --- Import Honors (CSV) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import_medals']) && Token::check($_POST['csrf'] ?? '')) {
    if (!empty($_FILES['import_file']['tmp_name'])) {
        $file = fopen($_FILES['import_file']['tmp_name'], 'r');
        $header = fgetcsv($file);
        $imported = 0;
        while (($row = fgetcsv($file)) !== false) {
            $data = array_combine($header, $row);
            $honorId = trim($data['honorId'] ?? '');
            $honorDesc = trim($data['honorDesc'] ?? '');
            if ($honorDesc === '') continue;
            if (!isValidHonorId($honorId)) {
                $errors[] = "Skipped row: honorId must be 1-2 alphanumeric characters (got '".htmlspecialchars($honorId)."').";
                continue;
            }
            try {
                $stmt = $pdo->prepare("INSERT INTO honors (honorId, honorDesc, honorAuth) VALUES (?, ?, ?)");
                $stmt->execute([
                    $honorId,
                    $honorDesc,
                    $data['honorAuth'] ?? ''
                ]);
                $imported++;
            } catch (Exception $e) {
                $errors[] = "Error importing honor '".htmlspecialchars($honorId)."': ".htmlspecialchars($e->getMessage());
            }
        }
        fclose($file);
        $success = "$imported honors imported.";
    } else {
        $errors[] = "Please upload a CSV file.";
    }
}

// --- Export Honors (CSV) ---
if (isset($_GET['export_medals']) && Token::check($_GET['csrf'] ?? '')) {
    $filename = "honors_export_" . date("Ymd_His") . ".csv";
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="'.$filename.'"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['honorId','honorDesc','honorAuth','createdAt']);
    $stmt = $pdo->query("SELECT honorId, honorDesc, honorAuth, createdAt FROM honors ORDER BY honorDesc ASC");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($out, $row);
    }
    fclose($out);
    exit;
}

// --- Inline Honor Edit (AJAX) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_medal']) && Token::check($_POST['csrf'] ?? '')) {
    $honorId = trim($_POST['medal_id'] ?? '');
    $honorDesc = trim($_POST['name'] ?? '');
    $honorAuth = trim($_POST['description'] ?? '');

    if (!isValidHonorId($honorId)) $errors[] = "Invalid honor code.";
    if ($honorDesc === '') $errors[] = "Honor name cannot be empty.";

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("UPDATE honors SET honorDesc=?, honorAuth=? WHERE honorId=?");
            $stmt->execute([$honorDesc, $honorAuth, $honorId]);
            $success = "Honor updated successfully!";
        } catch (Exception $e) {
            $errors[] = "Error updating honor: " . htmlspecialchars($e->getMessage());
        }
    }
    if (isset($_POST['ajax'])) {
        header('Content-Type: application/json');
        echo json_encode(['success'=>$success, 'errors'=>$errors]);
        exit;
    }
}

// --- Bulk Delete (AJAX) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_delete']) && Token::check($_POST['csrf'] ?? '')) {
    $ids = $_POST['ids'] ?? [];
    $ids = array_values(array_filter($ids, 'isValidHonorId'));
    $deleted = 0;
    if ($ids) {
        $in = str_repeat('?,', count($ids)-1) . '?';
        try {
            $stmt = $pdo->prepare("DELETE FROM honors WHERE honorId IN ($in)");
            $stmt->execute($ids);
            $deleted = $stmt->rowCount();
            $success = "$deleted honors deleted.";
        } catch (Exception $e) {
            $errors[] = "Error deleting honors: " . htmlspecialchars($e->getMessage());
        }
    }
    header('Content-Type: application/json');
    echo json_encode(['success' => $success, 'errors' => $errors]);
    exit;
}

// --- Search/filter/sort logic ---
$search = trim($_GET['search'] ?? '');
$sort = in_array($_GET['sort'] ?? '', ['name', 'createdAt', 'awarded_count', 'last_awarded']) ? $_GET['sort'] : 'name';
$order = ($_GET['order'] ?? '') === 'desc' ? 'DESC' : 'ASC';

$where = [];
$params = [];
if ($search) {
    $where[] = "(h.honorDesc LIKE ? OR h.honorAuth LIKE ? OR h.honorId LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$whereSQL = $where ? "WHERE ".implode(' AND ', $where) : "";
$orderSQL = "";
switch($sort) {
    case "awarded_count":
        $orderSQL = "ORDER BY awarded_count $order";
        break;
    case "last_awarded":
        $orderSQL = "ORDER BY last_awarded $order";
        break;
    case "createdAt":
        $orderSQL = "ORDER BY h.createdAt $order";
        break;
    default:
        $orderSQL = "ORDER BY h.honorDesc $order";
}

// staff_awards.honorId is a proper foreign key back to honors.honorId
// (added via migrations/2026_08_06_staff_awards_honors_link.sql).
$sql = "SELECT h.*,
    (SELECT COUNT(*) FROM staff_awards sa WHERE sa.honorId = h.honorId) AS awarded_count,
    (SELECT MAX(sa.award_date) FROM staff_awards sa WHERE sa.honorId = h.honorId) AS last_awarded
    FROM honors h
    $whereSQL
    $orderSQL";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$medals = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<?php include dirname(__DIR__) . '/shared/header.php'; ?>
<?php include dirname(__DIR__) . '/shared/sidebar.php'; ?>
<div class="content-wrapper with-sidebar">
    <div class="container-fluid p-4">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <h2 class="mb-0"><i class="fa fa-medal"></i> Honors and Awards</h2>
            <div class="d-flex gap-2 flex-wrap">
                <form method="get" class="d-inline">
                    <input type="hidden" name="csrf" value="<?=htmlspecialchars($csrfToken)?>">
                    <button type="submit" name="export_medals" value="1" class="btn btn-outline-secondary"><i class="fa fa-download"></i> Export CSV</button>
                </form>
                <button class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#importModal"><i class="fa fa-upload"></i> Import CSV</button>
                <a href="create_medal.php" class="btn btn-outline-success"><i class="fa fa-plus"></i> Create Honor</a>
                <a href="assign_medal.php" class="btn btn-primary"><i class="fa fa-medal"></i> Assign Honor</a>
                <button class="btn btn-danger" id="bulkDeleteBtn" disabled><i class="fa fa-trash"></i> Bulk Delete</button>
            </div>
        </div>
        <!-- Import Modal -->
        <div class="modal fade" id="importModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form method="post" enctype="multipart/form-data">
                        <input type="hidden" name="csrf" value="<?=htmlspecialchars($csrfToken)?>">
                        <div class="modal-header">
                            <h5 class="modal-title">Import Honors (CSV)</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <input type="file" name="import_file" accept=".csv" class="form-control" required>
                            <div class="form-text">CSV columns: honorId,honorDesc,honorAuth (honorId: 1-2 alphanumeric characters)</div>
                        </div>
                        <div class="modal-footer">
                            <button type="submit" name="import_medals" value="1" class="btn btn-primary">Import</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <!-- Search/filter bar -->
        <form class="row g-2 mb-3" method="get" id="filterForm">
            <div class="col-md-4">
                <input type="text" class="form-control" name="search" value="<?=htmlspecialchars($search)?>" placeholder="Search code, name or authority">
            </div>
            <div class="col-md-2">
                <select name="sort" class="form-select" onchange="document.getElementById('filterForm').submit();">
                    <option value="name" <?=$sort==='name'?'selected':''?>>Sort: Name</option>
                    <option value="createdAt" <?=$sort==='createdAt'?'selected':''?>>Sort: Created At</option>
                    <option value="awarded_count" <?=$sort==='awarded_count'?'selected':''?>>Sort: Awarded Count</option>
                    <option value="last_awarded" <?=$sort==='last_awarded'?'selected':''?>>Sort: Last Awarded</option>
                </select>
            </div>
            <div class="col-md-2">
                <select name="order" class="form-select" onchange="document.getElementById('filterForm').submit();">
                    <option value="asc" <?=$order==='ASC'?'selected':''?>>Asc</option>
                    <option value="desc" <?=$order==='DESC'?'selected':''?>>Desc</option>
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-center">
                <button type="submit" class="btn btn-outline-secondary w-100"><i class="fa fa-search"></i> Search</button>
            </div>
        </form>
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

        <div class="card shadow-sm">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="fa fa-list"></i> All Available Honors</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="medalsTable">
                        <thead class="table-light">
                            <tr>
                                <th><input type="checkbox" id="checkAll"></th>
                                <th>Code</th>
                                <th>Name / Description</th>
                                <th>Authority</th>
                                <th>Awarded</th>
                                <th>Last Awarded</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach($medals as $medal): ?>
                            <tr data-medal-id="<?=htmlspecialchars($medal['honorId'])?>" class="medal-row">
                                <td><input type="checkbox" class="select-medal" value="<?=htmlspecialchars($medal['honorId'])?>"></td>
                                <td><span class="badge bg-secondary"><?=htmlspecialchars($medal['honorId'])?></span></td>
                                <td>
                                    <span class="medal-name"><?=htmlspecialchars($medal['honorDesc'])?></span>
                                    <input type="text" class="form-control form-control-sm d-none medal-name-edit" value="<?=htmlspecialchars($medal['honorDesc'])?>">
                                </td>
                                <td>
                                    <span class="medal-desc"><?=htmlspecialchars($medal['honorAuth'] ?? '')?></span>
                                    <input type="text" class="form-control form-control-sm d-none medal-desc-edit" value="<?=htmlspecialchars($medal['honorAuth'] ?? '')?>">
                                </td>
                                <td>
                                    <span class="badge bg-primary"><?=intval($medal['awarded_count'])?></span>
                                    <?php if ($medal['awarded_count'] > 0): ?>
                                        <a href="recipients.php?medal_id=<?=urlencode($medal['honorId'])?>" class="ms-1 text-decoration-underline" title="View Recipients">View</a>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if($medal['last_awarded']): ?>
                                        <span title="<?=htmlspecialchars($medal['last_awarded'])?>"><?=htmlspecialchars(date('Y-m-d', strtotime($medal['last_awarded'])))?></span>
                                    <?php else: ?>
                                        <span class="text-muted">Never</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-outline-secondary edit-medal-btn" title="Edit"><i class="fa fa-edit"></i></button>
                                    <button class="btn btn-sm btn-success save-medal-btn d-none" title="Save"><i class="fa fa-save"></i></button>
                                    <button class="btn btn-sm btn-danger cancel-medal-btn d-none" title="Cancel"><i class="fa fa-times"></i></button>
                                    <a href="assign_medal.php?medal_id=<?=urlencode($medal['honorId'])?>" class="btn btn-sm btn-primary" title="Assign"><i class="fa fa-medal"></i> Assign</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if(empty($medals)): ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted">No honors found. <a href="create_medal.php">Create one.</a></td>
                            </tr>
                        <?php endif;?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(function(){
    $('#medalsTable').on('click', '.edit-medal-btn', function(){
        let $tr = $(this).closest('tr');
        $tr.find('.medal-name, .medal-desc, .edit-medal-btn').addClass('d-none');
        $tr.find('.medal-name-edit, .medal-desc-edit, .save-medal-btn, .cancel-medal-btn').removeClass('d-none');
    });
    $('#medalsTable').on('click', '.cancel-medal-btn', function(){
        let $tr = $(this).closest('tr');
        $tr.find('.medal-name-edit').val($tr.find('.medal-name').text());
        $tr.find('.medal-desc-edit').val($tr.find('.medal-desc').text());
        $tr.find('.medal-name, .medal-desc, .edit-medal-btn').removeClass('d-none');
        $tr.find('.medal-name-edit, .medal-desc-edit, .save-medal-btn, .cancel-medal-btn').addClass('d-none');
    });
    $('#medalsTable').on('click', '.save-medal-btn', function(){
        let $tr = $(this).closest('tr');
        let medalId = $tr.data('medal-id');
        let name = $tr.find('.medal-name-edit').val();
        let desc = $tr.find('.medal-desc-edit').val();
        let $btn = $(this);
        $btn.prop('disabled', true);
        $.post('medals.php', {
            edit_medal: 1,
            medal_id: medalId,
            name: name,
            description: desc,
            csrf: <?=json_encode($csrfToken)?>,
            ajax: 1
        }, function(resp){
            $btn.prop('disabled', false);
            if(resp.success){
                $tr.find('.medal-name').text(name);
                $tr.find('.medal-desc').text(desc);
                $tr.find('.medal-name, .medal-desc, .edit-medal-btn').removeClass('d-none');
                $tr.find('.medal-name-edit, .medal-desc-edit, .save-medal-btn, .cancel-medal-btn').addClass('d-none');
            }
            if(resp.errors && resp.errors.length){
                alert(resp.errors.join("\n"));
            }
        },'json');
    });

    $('#checkAll').on('change', function(){
        $('.select-medal').prop('checked', this.checked).trigger('change');
    });
    $('#medalsTable').on('change', '.select-medal', function(){
        $('#bulkDeleteBtn').prop('disabled', $('.select-medal:checked').length === 0);
    });
    $('#bulkDeleteBtn').on('click', function(){
        let ids = $('.select-medal:checked').map(function(){return $(this).val();}).get();
        if (!ids.length) return;
        if (!confirm("Are you sure you want to delete the selected honors? This cannot be undone.")) return;
        $.post('medals.php', {bulk_delete:1, ids:ids, csrf:<?=json_encode($csrfToken)?>}, function(resp){
            if(resp.success){
                $('.select-medal:checked').closest('tr').fadeOut(function(){$(this).remove();});
                $('#bulkDeleteBtn').prop('disabled', true);
            }
            if(resp.errors && resp.errors.length){
                alert(resp.errors.join("\n"));
            }
        },'json');
    });
});
</script>
<style>
#medalsTable input[type="text"] { min-width: 120px;}
#medalsTable td { vertical-align: middle;}
#medalsTable .btn { margin-bottom: 2px;}
.badge.bg-primary { font-size: 1em;}
</style>
<?php include dirname(__DIR__) . '/shared/footer.php'; ?>