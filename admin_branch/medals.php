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

$pageTitle = "Medals";
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
            ['title' => 'Units', 'url' => '/Armis2/admin_branch/reports_units.php']
        ],
    ['title' => 'System Settings', 'url' => '/Armis2/admin_branch/system_settings.php', 'icon' => 'cogs', 'page' => 'settings']
    ],
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

$pdo = getDbConnection();
$errors = [];
$success = false;

// --- Import Medals (CSV) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import_medals']) && Token::check($_POST['csrf'] ?? '')) {
    if (!empty($_FILES['import_file']['tmp_name'])) {
        $file = fopen($_FILES['import_file']['tmp_name'], 'r');
        $header = fgetcsv($file);
        $imported = 0;
        while (($row = fgetcsv($file)) !== false) {
            $data = array_combine($header, $row);
            if (empty($data['name'])) continue;
            try {
                $stmt = $pdo->prepare("INSERT INTO medals (name, description, image_path, created_at) VALUES (?, ?, ?, ?)");
                $stmt->execute([
                    $data['name'],
                    $data['description'] ?? '',
                    $data['image_path'] ?? '',
                    $data['created_at'] ?? date('Y-m-d H:i:s')
                ]);
                $imported++;
            } catch (Exception $e) {
                $errors[] = "Error importing medal: ".htmlspecialchars($e->getMessage());
            }
        }
        fclose($file);
        $success = "$imported medals imported.";
    } else {
        $errors[] = "Please upload a CSV file.";
    }
}

// --- Export Medals (CSV) ---
if (isset($_GET['export_medals']) && Token::check($_GET['csrf'] ?? '')) {
    $filename = "medals_export_" . date("Ymd_His") . ".csv";
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="'.$filename.'"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['id','name','description','image_path','created_at']);
    $stmt = $pdo->query("SELECT id, name, description, image_path, created_at FROM medals ORDER BY name ASC");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($out, $row);
    }
    fclose($out);
    exit;
}

// --- Inline Medal Edit (AJAX) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_medal']) && Token::check($_POST['csrf'] ?? '')) {
    $medalId = intval($_POST['medal_id']);
    $name = trim($_POST['name'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    $image_path = trim($_POST['image_path'] ?? '');

    if ($name === '') $errors[] = "Medal name cannot be empty.";
    if ($image_path && !preg_match('/^.+\.(jpg|jpeg|png|gif)$/i', $image_path)) $errors[] = "Invalid image file path.";

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM medals WHERE id=?");
            $stmt->execute([$medalId]);
            $old = $stmt->fetch(PDO::FETCH_ASSOC);

            $stmt = $pdo->prepare("UPDATE medals SET name=?, description=?, image_path=? WHERE id=?");
            $stmt->execute([$name, $desc, $image_path, $medalId]);
            $success = "Medal updated successfully!";

            // Log audit trail
            $user = $_SESSION['username'] ?? 'admin';
            $stmt = $pdo->prepare("INSERT INTO medals_audit (medal_id, action, changed_by, before_json, after_json, changed_at) VALUES (?, 'update', ?, ?, ?, NOW())");
            $stmt->execute([
                $medalId, $user, json_encode($old), json_encode([
                    'name'=>$name, 'description'=>$desc, 'image_path'=>$image_path
                ])
            ]);
        } catch (Exception $e) {
            $errors[] = "Error updating medal: " . htmlspecialchars($e->getMessage());
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
    $ids = array_filter(array_map('intval', $ids));
    $deleted = 0;
    if ($ids) {
        $in = str_repeat('?,', count($ids)-1) . '?';
        try {
            $pdo->beginTransaction();
            foreach($ids as $id) {
                $stmt = $pdo->prepare("SELECT * FROM medals WHERE id=?");
                $stmt->execute([$id]);
                $old = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($old) {
                    $user = $_SESSION['username'] ?? 'admin';
                    $stmt2 = $pdo->prepare("INSERT INTO medals_audit (medal_id, action, changed_by, before_json, after_json, changed_at) VALUES (?, 'delete', ?, ?, '{}', NOW())");
                    $stmt2->execute([$id, $user, json_encode($old)]);
                }
            }
            $stmt = $pdo->prepare("DELETE FROM medals WHERE id IN ($in)");
            $stmt->execute($ids);
            $deleted = $stmt->rowCount();
            $pdo->commit();
            $success = "$deleted medals deleted.";
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = "Error deleting medals: " . htmlspecialchars($e->getMessage());
        }
    }
    header('Content-Type: application/json');
    echo json_encode(['success' => $success, 'errors' => $errors]);
    exit;
}

// --- Audit trail UI (AJAX) ---
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['audit_medal_id']) && ctype_digit($_GET['audit_medal_id'])) {
    $mid = (int)$_GET['audit_medal_id'];
    $rows = $pdo->prepare("SELECT * FROM medals_audit WHERE medal_id=? ORDER BY changed_at DESC");
    $rows->execute([$mid]);
    $trail = $rows->fetchAll(PDO::FETCH_ASSOC);
    header('Content-Type: application/json');
    echo json_encode(['trail'=>$trail]);
    exit;
}

// --- Search/filter/sort logic ---
$search = trim($_GET['search'] ?? '');
$sort = in_array($_GET['sort'] ?? '', ['name', 'created_at', 'awarded_count', 'last_awarded']) ? $_GET['sort'] : 'name';
$order = ($_GET['order'] ?? '') === 'desc' ? 'DESC' : 'ASC';

$where = [];
$params = [];
if ($search) {
    $where[] = "(m.name LIKE ? OR m.description LIKE ?)";
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
    case "created_at":
        $orderSQL = "ORDER BY m.created_at $order";
        break;
    default:
        $orderSQL = "ORDER BY m.name $order";
}

$sql = "SELECT m.*,
    (SELECT COUNT(*) FROM staff_medals sm WHERE sm.medal_id=m.id) AS awarded_count,
    (SELECT MAX(sm.award_date) FROM staff_medals sm WHERE sm.medal_id=m.id) AS last_awarded
    FROM medals m
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
            <h2 class="mb-0"><i class="fa fa-medal"></i> Medals</h2>
            <div class="d-flex gap-2 flex-wrap">
                <form method="get" class="d-inline">
                    <input type="hidden" name="csrf" value="<?=htmlspecialchars($csrfToken)?>">
                    <button type="submit" name="export_medals" value="1" class="btn btn-outline-secondary"><i class="fa fa-download"></i> Export CSV</button>
                </form>
                <button class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#importModal"><i class="fa fa-upload"></i> Import CSV</button>
                <a href="create_medal.php" class="btn btn-outline-success"><i class="fa fa-plus"></i> Create Medal</a>
                <a href="assign_medal.php" class="btn btn-primary"><i class="fa fa-medal"></i> Assign Medal</a>
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
                            <h5 class="modal-title">Import Medals (CSV)</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <input type="file" name="import_file" accept=".csv" class="form-control" required>
                            <div class="form-text">CSV columns: name,description,image_path,created_at</div>
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
                <input type="text" class="form-control" name="search" value="<?=htmlspecialchars($search)?>" placeholder="Search name or description">
            </div>
            <div class="col-md-2">
                <select name="sort" class="form-select" onchange="document.getElementById('filterForm').submit();">
                    <option value="name" <?=$sort==='name'?'selected':''?>>Sort: Name</option>
                    <option value="created_at" <?=$sort==='created_at'?'selected':''?>>Sort: Created At</option>
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
                <h5 class="mb-0"><i class="fa fa-list"></i> All Available Medals</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="medalsTable">
                        <thead class="table-light">
                            <tr>
                                <th><input type="checkbox" id="checkAll"></th>
                                <th>Image</th>
                                <th>Name</th>
                                <th>Description</th>
                                <th>Awarded</th>
                                <th>Last Awarded</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach($medals as $medal): ?>
                            <tr data-medal-id="<?=$medal['id']?>" class="medal-row">
                                <td><input type="checkbox" class="select-medal" value="<?=$medal['id']?>"></td>
                                <td>
                                    <?php if(!empty($medal['image_path'])): ?>
                                        <img src="<?=htmlspecialchars($medal['image_path'])?>" alt="Medal Image" style="height:40px;max-width:70px;">
                                    <?php else: ?>
                                        <span class="text-muted">No Image</span>
                                    <?php endif; ?>
                                    <input type="text" class="form-control form-control-sm d-none medal-image-edit mt-2" value="<?=htmlspecialchars($medal['image_path'] ?? '')?>" placeholder="Image file (jpg/png/gif)">
                                    <small class="d-none text-muted medal-image-label">Image Path</small>
                                </td>
                                <td>
                                    <span class="medal-name"><?=htmlspecialchars($medal['name'])?></span>
                                    <input type="text" class="form-control form-control-sm d-none medal-name-edit" value="<?=htmlspecialchars($medal['name'])?>">
                                </td>
                                <td>
                                    <span class="medal-desc"><?=htmlspecialchars($medal['description'])?></span>
                                    <textarea class="form-control form-control-sm d-none medal-desc-edit"><?=htmlspecialchars($medal['description'])?></textarea>
                                </td>
                                <td>
                                    <span class="badge bg-primary"><?=intval($medal['awarded_count'])?></span>
                                    <?php if ($medal['awarded_count'] > 0): ?>
                                        <a href="recipients.php?medal_id=<?=$medal['id']?>" class="ms-1 text-decoration-underline" title="View Recipients">View</a>
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
                                    <a href="assign_medal.php?medal_id=<?=$medal['id']?>" class="btn btn-sm btn-primary" title="Assign"><i class="fa fa-medal"></i> Assign</a>
                                    <button class="btn btn-sm btn-outline-info audit-trail-btn" title="Audit Trail"><i class="fa fa-history"></i></button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if(empty($medals)): ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted">No medals found. <a href="create_medal.php">Create one.</a></td>
                            </tr>
                        <?php endif;?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <!-- Audit Trail Modal -->
        <div class="modal fade" id="auditTrailModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Audit Trail</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body" id="auditTrailContent">
                        <div class="text-center text-muted">Loading...</div>
                    </div>
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
        $tr.find('.medal-name-edit, .medal-desc-edit, .medal-image-edit, .medal-image-label, .save-medal-btn, .cancel-medal-btn').removeClass('d-none');
    });
    $('#medalsTable').on('click', '.cancel-medal-btn', function(){
        let $tr = $(this).closest('tr');
        $tr.find('.medal-name-edit').val($tr.find('.medal-name').text());
        $tr.find('.medal-desc-edit').val($tr.find('.medal-desc').text());
        $tr.find('.medal-image-edit').val($tr.find('img').attr('src') || '');
        $tr.find('.medal-name, .medal-desc, .edit-medal-btn').removeClass('d-none');
        $tr.find('.medal-name-edit, .medal-desc-edit, .medal-image-edit, .medal-image-label, .save-medal-btn, .cancel-medal-btn').addClass('d-none');
    });
    $('#medalsTable').on('click', '.save-medal-btn', function(){
        let $tr = $(this).closest('tr');
        let medalId = $tr.data('medal-id');
        let name = $tr.find('.medal-name-edit').val();
        let desc = $tr.find('.medal-desc-edit').val();
        let image_path = $tr.find('.medal-image-edit').val();
        let $btn = $(this);
        $btn.prop('disabled', true);
        $.post('medals.php', {
            edit_medal: 1,
            medal_id: medalId,
            name: name,
            description: desc,
            image_path: image_path,
            csrf: <?=json_encode($csrfToken)?>,
            ajax: 1
        }, function(resp){
            $btn.prop('disabled', false);
            if(resp.success){
                $tr.find('.medal-name').text(name);
                $tr.find('.medal-desc').text(desc);
                if(image_path){
                    if($tr.find('img').length){
                        $tr.find('img').attr('src', image_path);
                    } else {
                        $tr.find('td:eq(1)').html('<img src="'+image_path+'" alt="Medal Image" style="height:40px;max-width:70px;">');
                    }
                } else {
                    $tr.find('td:eq(1)').html('<span class="text-muted">No Image</span>');
                }
                $tr.find('.medal-name, .medal-desc, .edit-medal-btn').removeClass('d-none');
                $tr.find('.medal-name-edit, .medal-desc-edit, .medal-image-edit, .medal-image-label, .save-medal-btn, .cancel-medal-btn').addClass('d-none');
            }
            if(resp.errors && resp.errors.length){
                alert(resp.errors.join("\n"));
            }
        },'json');
    });
    $('#medalsTable').on('click', '.edit-medal-btn', function(){
        let $tr = $(this).closest('tr');
        $tr.find('.medal-image-label').removeClass('d-none');
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
        if (!confirm("Are you sure you want to delete the selected medals? This cannot be undone.")) return;
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

    $('#medalsTable').on('click', '.audit-trail-btn', function(){
        let $tr = $(this).closest('tr');
        let medalId = $tr.data('medal-id');
        $('#auditTrailContent').html('<div class="text-center text-muted">Loading...</div>');
        var modal = new bootstrap.Modal(document.getElementById('auditTrailModal'));
        modal.show();
        $.get('medals.php', {audit_medal_id: medalId}, function(resp){
            if (!resp.trail || !resp.trail.length) {
                $('#auditTrailContent').html('<div class="text-muted">No audit trail found for this medal.</div>');
                return;
            }
            let html = '<table class="table table-sm"><thead><tr><th>When</th><th>Who</th><th>Action</th><th>Before</th><th>After</th></tr></thead><tbody>';
            resp.trail.forEach(function(row){
                html += '<tr>';
                html += '<td>'+row.changed_at+'</td>';
                html += '<td>'+row.changed_by+'</td>';
                html += '<td>'+row.action+'</td>';
                html += '<td><pre style="max-width:220px;white-space:pre-wrap;word-break:break-all;">'+escapeHtml(row.before_json)+'</pre></td>';
                html += '<td><pre style="max-width:220px;white-space:pre-wrap;word-break:break-all;">'+escapeHtml(row.after_json)+'</pre></td>';
                html += '</tr>';
            });
            html += '</tbody></table>';
            $('#auditTrailContent').html(html);
        },'json');
    });
    function escapeHtml(text) {
        return text ? text.replace(/[&<>"']/g, function(m) {
            return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m];
        }) : '';
    }
});
</script>
<style>
#medalsTable input[type="text"], #medalsTable textarea { min-width: 120px;}
#medalsTable .medal-image-edit { max-width: 170px;}
#medalsTable td { vertical-align: middle;}
#medalsTable .btn { margin-bottom: 2px;}
.badge.bg-primary { font-size: 1em;}
</style>
<?php include dirname(__DIR__) . '/shared/footer.php'; ?>