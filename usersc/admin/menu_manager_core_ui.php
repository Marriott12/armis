<?php
// UI rendering for menu manager (uses logic from other modules)
global $db;

$search = $_GET['search'] ?? '';
$menus = $db->query(
    "SELECT * FROM menus WHERE deleted=0 " .
    ($search ? "AND label LIKE ? " : "") .
    "ORDER BY parent, display_order",
    $search ? ["%$search%"] : []
)->results(true);

$deletedMenus = $db->query("SELECT * FROM menus WHERE deleted=1 ORDER BY parent, display_order")->results(true);
$permissions = $db->query("SELECT * FROM permissions")->results(true);

function menuOptions($menus, $prefix = '', $parent = 0, $exclude = null) {
    foreach ($menus as $m) {
        if ($m['parent'] == $parent && $m['id'] != $exclude) {
            echo "<option value='{$m['id']}'>{$prefix}" . htmlspecialchars($m['label']) . "</option>";
            menuOptions($menus, $prefix . '--', $m['id'], $exclude);
        }
    }
}

$editMenu = null;
$editPerms = [];
if (isset($_GET['edit'])) {
    $editMenu = $db->query("SELECT * FROM menus WHERE id = ?", [$_GET['edit']])->first();
    $editPerms = $db->query("SELECT permission_id FROM permission_page_matches WHERE page = ?", [$_GET['edit']])->results();
    $editPerms = array_map(function($a){ return is_object($a) ? $a->permission_id : $a['permission_id']; }, $editPerms);
}

$activityLog = $db->query("SELECT * FROM logs WHERE logtype LIKE 'menu_%' ORDER BY logdate DESC LIMIT 20")->results();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>ARMIS Menu Manager</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.2/Sortable.min.css" />
    <style>
        .form-section { background: #f8f9fa; border-radius: 8px; padding: 2em; margin-bottom: 2em; }
        .table thead { background: #355E3B; color: #FFD700; }
        .btn-xs { font-size: .8em; padding: .2em .6em; }
        .sortable-ghost { background: #ffeaa7!important; }
        .drag-handle { cursor: grab; }
        .search-bar { max-width: 340px; display: inline-block; }
        .icon-preview { font-size: 1.4em; vertical-align: middle; margin-right: 2px; }
    </style>
</head>
<body>
<div class="container mt-4">
    <h2 class="mb-4">ARMIS Admin - Menu Manager</h2>
    <div class="mb-2">
        <form class="row g-3" method="get" action="">
            <div class="col-auto">
                <input type="text" name="search" placeholder="Search label..." class="form-control search-bar" value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="col-auto">
                <button class="btn btn-primary">Search</button>
                <a href="menu_manager.php" class="btn btn-secondary">Reset</a>
            </div>
        </form>
    </div>
    <div class="form-section">
        <h5><?= $editMenu ? "Edit" : "Add" ?> Menu Item</h5>
        <form method="post" class="row g-3" autocomplete="off" enctype="multipart/form-data">
            <?php if($editMenu): ?>
                <input type="hidden" name="menu_id" value="<?= $editMenu->id ?>">
            <?php endif; ?>
            <div class="col-md-3">
                <label class="form-label">Label</label>
                <input type="text" name="label" value="<?= htmlspecialchars($editMenu->label ?? '') ?>" class="form-control" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Menu Title</label>
                <input type="text" name="menu_title" value="<?= htmlspecialchars($editMenu->menu_title ?? 'main') ?>" class="form-control" required>
            </div>
            <div class="col-md-2">
                <label class="form-label">Parent</label>
                <select name="parent" class="form-select">
                    <option value="0">Top</option>
                    <?php menuOptions($menus, '', 0, $editMenu->id ?? null); ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Order</label>
                <input type="number" name="display_order" value="<?= htmlspecialchars($editMenu->display_order ?? 0) ?>" class="form-control">
            </div>
            <div class="col-md-2">
                <label class="form-label">Icon Class 
                  <a href="#" onclick="openIconPicker();return false;" class="ms-1">(pick)</a> 
                  <span id="iconPreview"></span>
                </label>
                <input type="text" id="iconClassInput" name="icon_class" value="<?= htmlspecialchars($editMenu->icon_class ?? '') ?>" class="form-control" placeholder="fa fa-dashboard">
            </div>
            <div class="col-md-4">
                <label class="form-label">Link</label>
                <input type="text" name="link" value="<?= htmlspecialchars($editMenu->link ?? '') ?>" class="form-control">
            </div>
            <div class="col-md-2">
                <label class="form-label">Dropdown?</label>
                <input type="checkbox" name="dropdown" value="1" <?= !empty($editMenu->dropdown) ? 'checked' : '' ?>>
            </div>
            <div class="col-md-2">
                <label class="form-label">Logged In?</label>
                <input type="checkbox" name="logged_in" value="1" <?= !isset($editMenu) || !empty($editMenu->logged_in) ? 'checked' : '' ?>>
            </div>
            <div class="col-md-2">
                <label class="form-label">Visible From</label>
                <input type="datetime-local" name="visible_from" value="<?= isset($editMenu->visible_from) ? date('Y-m-d\TH:i', strtotime($editMenu->visible_from)) : '' ?>" class="form-control">
            </div>
            <div class="col-md-2">
                <label class="form-label">Visible To</label>
                <input type="datetime-local" name="visible_to" value="<?= isset($editMenu->visible_to) ? date('Y-m-d\TH:i', strtotime($editMenu->visible_to)) : '' ?>" class="form-control">
            </div>
            <div class="col-md-12">
                <label class="form-label">Permissions (RBAC)</label><br>
                <?php foreach($permissions as $perm): ?>
                    <label class="form-check-label me-3">
                        <input type="checkbox" class="form-check-input" name="permissions[]" value="<?= $perm['id'] ?>"
                            <?= in_array($perm['id'], $editPerms) ? 'checked' : '' ?>>
                        <?= htmlspecialchars($perm['name']) ?>
                    </label>
                <?php endforeach; ?>
            </div>
            <div class="col-12">
                <button class="btn btn-success" name="save_menu" value="1">Save</button>
                <?php if ($editMenu): ?>
                    <a href="menu_manager.php" class="btn btn-secondary">Cancel</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
    <!-- Menu Listing with Drag-and-drop -->
    <h5>All Menu Items (Drag to Reorder)</h5>
    <form method="post" id="bulkForm">
        <table class="table table-sm table-bordered align-middle" id="menuTable">
            <thead><tr>
                <th><input type="checkbox" onclick="toggleAll(this)"></th>
                <th></th><th>ID</th><th>Label</th><th>Link</th><th>Parent</th><th>Order</th><th>Icon</th><th>Dropdown</th>
                <th>Logged In</th><th>Permissions</th><th>Actions</th>
            </tr></thead>
            <tbody>
            <?php foreach($menus as $m): ?>
                <tr data-id="<?= $m['id'] ?>" data-parent="<?= $m['parent'] ?>">
                    <td><input type="checkbox" name="menu_ids[]" value="<?= $m['id'] ?>"></td>
                    <td class="drag-handle">&#9776;</td>
                    <td><?= $m['id'] ?></td>
                    <td><?= htmlspecialchars($m['label']) ?></td>
                    <td><?= htmlspecialchars($m['link']) ?></td>
                    <td><?= $m['parent'] ?></td>
                    <td><?= $m['display_order'] ?></td>
                    <td><span class="icon-preview"><i class="<?= htmlspecialchars($m['icon_class']) ?>"></i></span></td>
                    <td><?= $m['dropdown'] ? "Yes" : "" ?></td>
                    <td><?= $m['logged_in'] ? "Yes" : "No" ?></td>
                    <td>
                        <?php
                        $perms = $db->query("SELECT p.name FROM permission_page_matches ppm JOIN permissions p ON ppm.permission_id=p.id WHERE ppm.page=?",[$m['id']])->results();
                        echo implode(', ', array_map(function($a){return is_object($a)?$a->name:$a['name'];}, $perms));
                        ?>
                    </td>
                    <td>
                        <a href="?edit=<?= $m['id'] ?>" class="btn btn-primary btn-xs">Edit</a>
                        <form method="post" action="" style="display:inline" onsubmit="return confirm('Delete this menu item?');">
                            <button class="btn btn-danger btn-xs" name="delete_menu" value="<?= $m['id'] ?>">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <div class="mb-2">
            <button class="btn btn-danger btn-xs" name="bulk_action" value="delete" onclick="return confirm('Delete selected?')">Bulk Delete</button>
            <button class="btn btn-success btn-xs" name="bulk_action" value="restore">Bulk Restore</button>
        </div>
    </form>
    <div class="alert alert-info" style="font-size:.9em">
        <b>Tip:</b> Drag the <span style="font-size:1.2em;">&#9776;</span> handle to re-order menu items. Order and parent changes are saved automatically.
    </div>
    <?php if($deletedMenus): ?>
    <h5 class="mt-4">Recently Deleted Menu Items</h5>
    <table class="table table-bordered table-sm">
        <thead><tr><th>ID</th><th>Label</th><th>Link</th><th>Parent</th><th>Order</th><th>Restore</th></tr></thead>
        <tbody>
        <?php foreach($deletedMenus as $dm): ?>
            <tr>
                <td><?= $dm['id'] ?></td>
                <td><?= htmlspecialchars($dm['label']) ?></td>
                <td><?= htmlspecialchars($dm['link']) ?></td>
                <td><?= $dm['parent'] ?></td>
                <td><?= $dm['display_order'] ?></td>
                <td>
                    <form method="post" action="">
                        <button class="btn btn-success btn-xs" name="restore_menu" value="<?= $dm['id'] ?>">Restore</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
    <h5 class="mt-4">Recent Menu Activity</h5>
    <table class="table table-bordered table-sm">
        <thead><tr><th>Time</th><th>User</th><th>Action</th><th>Note</th><th>IP</th><th>Metadata</th></tr></thead>
        <tbody>
        <?php foreach($activityLog as $log): ?>
            <tr>
                <td><?= htmlspecialchars($log->logdate) ?></td>
                <td>
                    <?php
                    $u = $db->query("SELECT fname, lname FROM users WHERE id = ?",[$log->user_id])->first();
                    echo htmlspecialchars($u ? $u->fname . ' ' . $u->lname : 'User#'.$log->user_id);
                    ?>
                </td>
                <td><?= htmlspecialchars($log->logtype) ?></td>
                <td style="max-width: 350px; overflow-x: auto;"><small><?= htmlspecialchars($log->lognote) ?></small></td>
                <td><?= htmlspecialchars($log->ip) ?></td>
                <td><small><?= htmlspecialchars($log->metadata ?? '') ?></small></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<div id="iconPickerModal" style="display:none;position:fixed;top:10%;left:10%;width:80%;height:80%;background:#fff;z-index:9999;border:2px solid #aaa;overflow:auto;padding:1em;">
    <div>
        <input type="text" id="iconSearch" placeholder="Search icon..." class="form-control mb-2" autofocus>
        <button class="btn btn-secondary btn-xs" onclick="closeIconPicker()">Close</button>
    </div>
    <div id="iconList" style="max-height:70vh;overflow:auto;"></div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.2/Sortable.min.js"></script>
<script>
    // Drag-and-drop order using Sortable.js
    let table = document.getElementById('menuTable').getElementsByTagName('tbody')[0];
    new Sortable(table, {
        handle: '.drag-handle',
        animation: 150,
        onEnd: function (evt) {
            let rows = table.querySelectorAll('tr');
            let order = {};
            rows.forEach((row, i) => {
                order[row.dataset.id] = {order: i+1, parent: row.dataset.parent};
            });
            fetch('menu_manager.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'order_update=' + encodeURIComponent(JSON.stringify(order))
            }).then(r=>r.json()).then(data=>{
                if(data.status==='success'){
                    location.reload();
                }
            });
        }
    });
    function toggleAll(cb) {
        document.querySelectorAll('input[name="menu_ids[]"]').forEach(x=>x.checked=cb.checked);
    }
    // Icon picker logic
    function openIconPicker() {
        document.getElementById('iconPickerModal').style.display = 'block';
        loadIcons('');
        document.getElementById('iconSearch').focus();
    }
    function closeIconPicker() {
        document.getElementById('iconPickerModal').style.display = 'none';
    }
    document.getElementById('iconSearch').oninput = function() {
        loadIcons(this.value);
    };
    function loadIcons(q) {
        // For demo: hardcoded subset of FontAwesome/Bootstrap icons
        let icons = [
            "fa fa-home","fa fa-user","fa fa-cog","fa fa-book","fa fa-dashboard","fa fa-users","fa fa-sign-in","fa fa-sign-out","fa fa-university","fa fa-bar-chart","fa fa-graduation-cap","fa fa-flag"
        ];
        let html = '';
        icons.forEach(ic=>{
            if(!q || ic.indexOf(q) !== -1) {
                html += `<span style="cursor:pointer;display:inline-block;width:110px;margin:2px;padding:3px;border:1px solid #ddd;" onclick="selectIcon('${ic}')"><i class="${ic}" style="font-size:1.8em"></i><br>${ic}</span>`;
            }
        });
        document.getElementById('iconList').innerHTML = html || '<div>No icons found.</div>';
    }
    function selectIcon(className) {
        document.getElementById('iconClassInput').value = className;
        document.getElementById('iconPreview').innerHTML = `<i class="${className}"></i>`;
        closeIconPicker();
    }
    // Live icon preview
    document.getElementById('iconClassInput').oninput = function(){
        document.getElementById('iconPreview').innerHTML = `<i class="${this.value}"></i>`;
    };
</script>
</body>
</html>