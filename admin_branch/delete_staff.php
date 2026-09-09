<?php
// Start session and include proper authentication
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include admin branch authentication and database
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/analytics.php';

// Require authentication and admin branch access
requireAuth();

// Check if user has access to admin_branch module
requireModuleAccess('admin_branch');

// Check specific permission for deleting staff
if (!hasPermission(PERM_DELETE_STAFF)) {
    header('HTTP/1.1 403 Forbidden');
    die('Access denied. You do not have permission to delete staff records.');
}

// Generate CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

// Child tables that reference staff.svcNo. Used to (a) show the operator
// what will be cascade-deleted and (b) snapshot every related row into
// deletion_log.staff_data_backup before the delete happens, since the
// FKs in armis1_delete_staff_schema_fix.sql hard-cascade with no trace
// of their own.
const STAFF_CHILD_TABLES = [
    'staff_appointment',
    'staff_awards',
    'staff_conduct',
    'staff_course',
    'staff_deployments',
    'staff_disciplinary',
    'staff_edit_log',
    'staff_operation',
    'staff_promotion',
    'staff_skills',
];

// --- AJAX search endpoint: PUT THIS FIRST! ---
// DO NOT include template or any HTML before this!
if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {
    header('Content-Type: application/json');
    require_once dirname(__DIR__) . '/shared/rank_levels.php';
    try {
        $pdo = getDbConnection();

        // Build ranks and units map.
        // `rank` has no separate id/name columns - rankId IS the code
        // (e.g. 'Capt', 'Sgt'), same convention used elsewhere in the app.
        $ranksStmt = $pdo->query("SELECT rankId as rankID, rankId as rankName FROM `rank`");
        $ranks = $ranksStmt->fetchAll(PDO::FETCH_OBJ);
        $rankMap = [];
        foreach ($ranks as $r) $rankMap[$r->rankID] = $r->rankName;

        // `unit` has no `code` column - unitId is the human-readable code.
        $unitsStmt = $pdo->query("SELECT unitId as unitID, unitId as unitName FROM `unit`");
        $units = $unitsStmt->fetchAll(PDO::FETCH_OBJ);
        $unitMap = [];
        foreach ($units as $u) $unitMap[$u->unitID] = $u->unitName;

        $search = trim($_GET['search'] ?? '');
        // Category (Officer/NCO/CE), same as reports_seniority.php's
        // roster grouping — this listing previously had no category
        // awareness at all.
        $categoryMap = ['officers' => 'Officer', 'ncos' => 'NCO', 'ce' => 'Civilian Employee'];
        $categoryFilter = $categoryMap[$_GET['category'] ?? ''] ?? null;

        $sql = "SELECT s.svcNo as svcNo, s.fName as fname, s.mName as mname, s.lName as lname,
                       s.rankId, s.unitId, " . getRankCategoryCaseSQL('r') . " as category
                FROM staff s LEFT JOIN `rank` r ON s.rankId = r.rankId";
        $params = [];
        $conditions = [];
        if ($search !== '') {
            $conditions[] = "(s.svcNo LIKE ? OR s.fName LIKE ? OR s.mName LIKE ? OR s.lName LIKE ?)";
            $searchParam = '%' . $search . '%';
            array_push($params, $searchParam, $searchParam, $searchParam, $searchParam);
        }
        if ($categoryFilter !== null) {
            $conditions[] = getRankCategorySQL($categoryFilter, 'r');
        }
        if (!empty($conditions)) {
            $sql .= " WHERE " . implode(' AND ', $conditions);
        }
        $sql .= " ORDER BY r.rankIndex ASC, s.lName ASC, s.fName ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $staffList = $stmt->fetchAll(PDO::FETCH_OBJ);

        $result = [];
        foreach ($staffList as $s) {
            $middle = trim($s->mname ?? '');
            $fullName = trim(($s->lname ?? '') . ' ' . ($s->fname ?? '') . ($middle !== '' ? ' ' . $middle : ''));
            $result[] = [
                'svcNo' => $s->svcNo ?? '',
                'name' => $fullName,
                'rank' => isset($rankMap[$s->rankId]) ? $rankMap[$s->rankId] : ('ID:' . $s->rankId),
                'unit' => isset($unitMap[$s->unitId]) ? $unitMap[$s->unitId] : '',
                'category' => $s->category ?? 'Unknown',
            ];
        }
        echo json_encode($result);
    } catch (Exception $e) {
        error_log("AJAX search error: " . $e->getMessage());
        echo json_encode(['error' => 'Search failed']);
    }
    exit;
}

// --- CSRF helper ---
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
function csrf_token() { return $_SESSION['csrf_token']; }

// --- Lookup for ranks and units (for delete confirmation) ---
try {
    $pdo = getDbConnection();

    $ranksStmt = $pdo->query("SELECT rankId as rankID, rankId as rankName FROM `rank`");
    $ranks = $ranksStmt->fetchAll(PDO::FETCH_OBJ);
    $rankMap = [];
    foreach ($ranks as $r) $rankMap[$r->rankID] = $r->rankName;

    // Real table is `unit` (singular); it has no `id`/`name` columns.
    $unitsStmt = $pdo->query("SELECT unitId as unitID, unitId as unitName FROM `unit`");
    $units = $unitsStmt->fetchAll(PDO::FETCH_OBJ);
    $unitMap = [];
    foreach ($units as $u) $unitMap[$u->unitID] = $u->unitName;
} catch (Exception $e) {
    error_log("Failed to load ranks/units: " . $e->getMessage());
    $rankMap = [];
    $unitMap = [];
}

$errors = [];
$success = false;
$staff = null;
$relatedCounts = [];

// If a staff member is selected for deletion, fetch brief info for confirmation
if (isset($_GET['svcNo'])) {
    $svcNo = $_GET['svcNo'];
    try {
        $stmt = $pdo->prepare(
            "SELECT svcNo as svcNo, fName as fname, mName as mname, lName as lname, 
                    rankId, unitId, NRC, DOB, gender, svcStatus 
             FROM staff 
             WHERE svcNo = ?"
        );
        $stmt->execute([$svcNo]);
        $staff = $stmt->fetch(PDO::FETCH_OBJ);

        // Show the operator what else will be cascade-deleted with this
        // staff member, so "type DELETE to confirm" is an informed choice.
        if ($staff) {
            foreach (STAFF_CHILD_TABLES as $table) {
                $countStmt = $pdo->prepare("SELECT COUNT(*) FROM `$table` WHERE svcNo = ?");
                $countStmt->execute([$svcNo]);
                $count = (int)$countStmt->fetchColumn();
                if ($count > 0) {
                    $relatedCounts[$table] = $count;
                }
            }
        }
    } catch (Exception $e) {
        error_log("Failed to fetch staff for deletion: " . $e->getMessage());
        $errors[] = "Failed to load staff member details.";
    }
}

// Handle deletion post: CSRF + (remove staff) + Log + Error safe
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_svcNo'])) {
    $svcNo = $_POST['delete_svcNo'];
    $reason = $_POST['delete_reason'] ?? null;
    $csrf = $_POST['csrf_token'] ?? '';
    $typed_delete = $_POST['type_delete'] ?? '';
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf)) {
        $errors[] = "Invalid CSRF token. Please reload the page and try again.";
    } elseif ($typed_delete !== 'DELETE') {
        $errors[] = "You must type DELETE to confirm.";
    } else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM staff WHERE svcNo = ?");
            $stmt->execute([$svcNo]);
            $staff = $stmt->fetch(PDO::FETCH_OBJ);

            if (!$staff) {
                $errors[] = "Staff member not found.";
            } elseif (function_exists('canAlterRecord') && !canAlterRecord($staff->branch_id ?? null)) {
                $errors[] = "You are not permitted to delete records for this branch.";
                error_log("Branch RBAC denied: role '" . ($_SESSION['role'] ?? 'unknown') . "' attempted to delete svcNo $svcNo (branch " . ($staff->branch_id ?? 'none') . ")");
            } else {
                // NOTE: SELECT * returns real column names, which are
                // fName/mName/lName (capital N) - not fname/mname/lname.
                $middleName = trim($staff->mName ?? '');
                $staffName = trim(
                    ($staff->lName ?? '') . ' ' . ($staff->fName ?? '') .
                    ($middleName !== '' ? ' ' . $middleName : '')
                );
                $rankName = $rankMap[$staff->rankId] ?? 'Unknown';
                $unitName = $unitMap[$staff->unitId] ?? 'Unknown';

                $pdo->beginTransaction();

                // Snapshot every related row across all child tables before
                // the cascade delete removes them, so the audit trail keeps
                // a full record of what existed (courses, awards,
                // disciplinary history, deployments, etc.), not just the
                // staff row itself.
                $relatedData = [];
                foreach (STAFF_CHILD_TABLES as $table) {
                    $childStmt = $pdo->prepare("SELECT * FROM `$table` WHERE svcNo = ?");
                    $childStmt->execute([$svcNo]);
                    $rows = $childStmt->fetchAll(PDO::FETCH_ASSOC);
                    if (!empty($rows)) {
                        $relatedData[$table] = $rows;
                    }
                }

                // Backup staff data (+ related records) as JSON
                $staffBackup = json_encode([
                    'staff' => (array)$staff,
                    'related_records' => $relatedData,
                    'deleted_at' => date('Y-m-d H:i:s'),
                ]);

                // Log to deletion_log table (specific audit trail)
                $logStmt = $pdo->prepare("
                    INSERT INTO deletion_log (svcNo, staff_name, rank_name, unit_name, deleted_by, deleted_by_username, user_ip, user_agent, reason, staff_data_backup) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $logStmt->execute([
                    $svcNo,
                    $staffName,
                    $rankName,
                    $unitName,
                    $_SESSION['user_id'] ?? 0,
                    $_SESSION['username'] ?? 'unknown',
                    $_SERVER['REMOTE_ADDR'] ?? '',
                    $_SERVER['HTTP_USER_AGENT'] ?? '',
                    $reason,
                    $staffBackup
                ]);

                // Also log to activity_log table (general audit trail)
                $activityStmt = $pdo->prepare("
                    INSERT INTO activity_log (user_id, username, action, details, ip_address, user_agent) 
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $activityStmt->execute([
                    $_SESSION['user_id'] ?? 0,
                    $_SESSION['username'] ?? 'unknown',
                    'staff_deletion',
                    "Deleted staff member: $svcNo ($staffName)" . ($reason ? " - Reason: $reason" : ""),
                    $_SERVER['REMOTE_ADDR'] ?? '',
                    $_SERVER['HTTP_USER_AGENT'] ?? ''
                ]);

                // Delete the staff member. With armis1_delete_staff_schema_fix.sql
                // applied, this cascades to every child table listed in
                // STAFF_CHILD_TABLES automatically - no manual child deletes
                // needed and no orphaned rows left behind.
                $deleteStmt = $pdo->prepare("DELETE FROM staff WHERE svcNo = ?");
                $deleteResult = $deleteStmt->execute([$svcNo]);
                $rowsAffected = $deleteStmt->rowCount();

                if ($deleteResult && $rowsAffected > 0) {
                    $pdo->commit();
                    $success = true;
                    $staff = null;
                    $relatedCounts = [];
                    error_log("Successfully deleted staff member: $svcNo by user " . ($_SESSION['user_id'] ?? 'unknown'));

                    // Notify system admins — deletion is destructive and
                    // irreversible, worth an oversight notification even
                    // for the admin who performed it (excluded below,
                    // since they already know).
                    require_once dirname(__DIR__) . '/shared/notifications_helper.php';
                    notifyRoles(
                        ['admin', 'superadmin'],
                        'admin_branch',
                        'staff_deleted',
                        'Staff record deleted',
                        "$staffName (svcNo $svcNo) was deleted." . ($reason ? " Reason: $reason" : ''),
                        null, // no profile to link to anymore
                        $_SESSION['user_id'] ?? null
                    );
                } else {
                    $pdo->rollBack();
                    $errors[] = "Failed to delete staff member. No rows were affected.";
                    error_log("Delete failed - no rows affected for service number: $svcNo");
                }
            }
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("Delete error by user " . ($_SESSION['user_id'] ?? 'unknown') . ": " . $e->getMessage());
            $errors[] = "Error deleting staff: " . $e->getMessage();
        }
    }
}

// Log page access with enhanced analytics
logActivity('delete_staff_access', 'Accessed Create Staff page');

$pageTitle = "Delete Staff - Admin Branch";
$moduleName = "Admin Branch";
$moduleIcon = "user-plus";
$currentPage = "delete";

// Sidebar navigation
$sidebarLinks = []; // set by shared nav include below
require_once __DIR__ . '/includes/sidebar_nav.php';

// Ensure shared admin branch CSS is loaded
echo '<link rel="stylesheet" href="/Armis2/assets/css/admin_branch.css">';
include dirname(__DIR__) . '/shared/header.php';
include dirname(__DIR__) . '/shared/sidebar.php';
?>
<!-- Main Content -->
<div class="content-wrapper with-sidebar">
    <div class="container-fluid">
        <div class="main-content">
            <!-- Breadcrumb -->
            <nav aria-label="breadcrumb" class="mb-3">
                <ol class="breadcrumb bg-light py-1 px-3 rounded">
                    <li class="breadcrumb-item"><a href="/Armis2/">Home</a></li>
                    <li class="breadcrumb-item"><a href="/Armis2/admin_branch/">Admin Branch</a></li>
                    <li class="breadcrumb-item active">Delete Staff</li>
                </ol>
            </nav>

            <!-- Main Form Content -->
            <div class="col-12">
                <div class="card shadow-sm">
                    <div class="card-header bg-danger text-white">
                        <h4 class="mb-0"><i class="fas fa-user-times"></i> Delete Staff Member</h4>
                    </div>
                    <div class="card-body">
            <?php if ($success): ?>
                <div class="alert alert-success" role="alert">Staff member deleted successfully and logged.</div>
            <?php endif; ?>
            <?php if ($errors): ?>
                <div class="alert alert-danger" role="alert">
                    <ul class="mb-0">
                        <?php foreach ($errors as $err): ?>
                            <li><?=htmlspecialchars($err ?? 'Unknown error')?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if (!$staff): ?>
                <!-- Category tabs — same Officers/NCOs/CEs pattern as
                     reports_seniority.php, so staff can be narrowed
                     down by category before searching by name. -->
                <ul class="nav nav-tabs mb-3" id="categoryTabs" role="tablist">
                    <?php foreach (['' => 'All', 'officers' => 'Officers', 'ncos' => 'NCOs', 'ce' => 'Civilian Employees'] as $key => $label): ?>
                        <li class="nav-item" role="presentation">
                            <a class="nav-link<?= $key === '' ? ' active' : '' ?>" href="#" data-category="<?= htmlspecialchars($key) ?>" role="tab">
                                <?= htmlspecialchars($label) ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <!-- Live Search Form -->
                <form id="searchForm" class="mb-4" aria-label="Search Staff" autocomplete="off" onsubmit="return false;">
                    <div class="input-group">
                        <input type="text" name="search" id="searchStaff" class="form-control" placeholder="Search by Service No, Surname or First name..." autocomplete="off" aria-label="Search Staff Member">
                        <button class="btn btn-primary" type="submit" tabindex="-1"><i class="fa fa-search"></i> Search</button>
                    </div>
                </form>
                <!-- Results Table -->
                <div class="table-responsive" role="region" aria-label="Staff List">
                    <table class="table table-striped align-middle" id="staffResultsTable">
                        <thead>
                            <tr>
                                <th scope="col">Service No</th>
                                <th scope="col">Name</th>
                                <th scope="col">Rank</th>
                                <th scope="col">Category</th>
                                <th scope="col">Unit</th>
                                <th scope="col">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr><td colspan="6" class="text-center text-muted">Loading staff...</td></tr>
                        </tbody>
                    </table>
                </div>
                <script>
                const searchInput = document.getElementById('searchStaff');
                const resultsTable = document.getElementById('staffResultsTable').getElementsByTagName('tbody')[0];
                let activeCategory = '';
                let typingTimer;
                const categoryBadge = { 'Officer': 'primary', 'NCO': 'info', 'CE': 'secondary' };
                function fetchResults(query) {
                    resultsTable.innerHTML = `<tr><td colspan="6" class="text-center text-muted">Loading staff...</td></tr>`;
                    fetch('?ajax=1&search=' + encodeURIComponent(query) + '&category=' + encodeURIComponent(activeCategory))
                        .then(r => r.json())
                        .then(data => {
                            resultsTable.innerHTML = '';
                            if (!Array.isArray(data) || data.length === 0) {
                                resultsTable.innerHTML = '<tr><td colspan="6" class="text-center text-muted">No staff found.</td></tr>';
                            } else {
                                data.forEach(function(staff) {
                                    const tr = document.createElement('tr');
                                    const badgeColor = categoryBadge[staff.category] || 'light';
                                    tr.innerHTML =
                                        '<td>' + staff.svcNo + '</td>' +
                                        '<td>' + staff.name + '</td>' +
                                        '<td>' + staff.rank + '</td>' +
                                        '<td><span class="badge bg-' + badgeColor + '">' + staff.category + '</span></td>' +
                                        '<td>' + staff.unit + '</td>' +
                                        '<td><a href="?svcNo=' + encodeURIComponent(staff.svcNo) + '" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i> Delete</a></td>';
                                    resultsTable.appendChild(tr);
                                });
                            }
                        })
                        .catch(err => {
                            resultsTable.innerHTML = '<tr><td colspan="6" class="text-center text-danger">AJAX Error: ' + err + '</td></tr>';
                        });
                }
                fetchResults('');
                searchInput.addEventListener('input', function() {
                    clearTimeout(typingTimer);
                    typingTimer = setTimeout(function() {
                        fetchResults(searchInput.value);
                    }, 250);
                });
                document.querySelectorAll('#categoryTabs .nav-link').forEach(function(tab) {
                    tab.addEventListener('click', function(e) {
                        e.preventDefault();
                        document.querySelectorAll('#categoryTabs .nav-link').forEach(t => t.classList.remove('active'));
                        this.classList.add('active');
                        activeCategory = this.dataset.category;
                        fetchResults(searchInput.value);
                    });
                });
                </script>
            <?php else: ?>
                <!-- Deletion Confirmation -->
                <div class="alert alert-warning mb-4" role="alert">
                    <h5 class="mb-2"><i class="fa fa-exclamation-triangle"></i> Confirm Deletion</h5>
                    <p>Are you sure you want to delete the following staff member? <strong>This action cannot be undone.</strong></p>
                    <p>
                      <span class="fw-bold">Type <kbd>DELETE</kbd> below to confirm.</span>
                    </p>
                </div>

                <?php if (!empty($relatedCounts)): ?>
                <div class="alert alert-danger mb-4" role="alert">
                    <h6 class="alert-heading"><i class="fas fa-exclamation-circle"></i> This will also permanently delete related records</h6>
                    <ul class="mb-2">
                        <?php foreach ($relatedCounts as $table => $count): ?>
                            <li><?= $count ?> row(s) in <code><?= htmlspecialchars($table) ?></code></li>
                        <?php endforeach; ?>
                    </ul>
                    <p class="mb-0">A full backup of these records is saved to the deletion log before they're removed, in case they need to be restored.</p>
                </div>
                <?php endif; ?>

                <div class="mb-4">
                    <table class="table table-bordered w-auto">
                        <tr>
                            <th scope="row">Service No</th>
                            <td><?=htmlspecialchars($staff->svcNo ?? 'N/A')?></td>
                        </tr>
                        <tr>
                            <th scope="row">Name</th>
                            <td><?php
                                $confirmMiddle = trim($staff->mname ?? '');
                                $confirmName = trim(($staff->lname ?? '') . ' ' . ($staff->fname ?? '') . ($confirmMiddle !== '' ? ' ' . $confirmMiddle : ''));
                                echo htmlspecialchars($confirmName !== '' ? $confirmName : 'N/A');
                            ?></td>
                        </tr>
                        <tr>
                            <th scope="row">Rank</th>
                            <td><?php 
                                $rankId = $staff->rankId ?? null;
                                echo htmlspecialchars($rankMap[$rankId] ?? ('ID:' . $rankId ?? 'Unknown'));
                            ?></td>
                        </tr>
                        <tr>
                            <th scope="row">Unit</th>
                            <td><?php 
                                $unitId = $staff->unitId ?? null;
                                echo htmlspecialchars($unitMap[$unitId] ?? 'N/A');
                            ?></td>
                        </tr>
                        <tr>
                            <th scope="row">NRC</th>
                            <td><?=htmlspecialchars($staff->NRC ?? 'N/A')?></td>
                        </tr>
                        <tr>
                            <th scope="row">DOB</th>
                            <td><?=htmlspecialchars($staff->DOB ?? 'N/A')?></td>
                        </tr>
                        <tr>
                            <th scope="row">Gender</th>
                            <td><?=htmlspecialchars($staff->gender ?? 'N/A')?></td>
                        </tr>
                        <tr>
                            <th scope="row">Status</th>
                            <td><?=htmlspecialchars($staff->svcStatus ?? 'N/A')?></td>
                        </tr>
                    </table>
                </div>
                <form method="post" autocomplete="off" aria-label="Confirm Delete Staff">
                    <input type="hidden" name="delete_svcNo" value="<?=htmlspecialchars($staff->svcNo ?? '')?>">
                    <input type="hidden" name="csrf_token" value="<?=htmlspecialchars($csrfToken ?? '')?>">
                    <div class="mb-3">
                        <label for="delete_reason" class="form-label">Reason for Deletion (optional)</label>
                        <input type="text" class="form-control" name="delete_reason" id="delete_reason" maxlength="255" placeholder="Reason (optional)">
                    </div>
                    <div class="mb-3">
                      <label for="type_delete" class="form-label">
                        Please type <kbd>DELETE</kbd> to confirm:
                      </label>
                      <input type="text" class="form-control" name="type_delete" id="type_delete" required pattern="DELETE" autocomplete="off" aria-required="true">
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-danger px-4"
                                onclick="return document.getElementById('type_delete').value === 'DELETE';">
                            <i class="fas fa-trash"></i> Confirm Delete
                        </button>
                        <a href="delete_staff.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
                <script>
                document.querySelector('form[method="post"]').addEventListener('submit', function(e){
                    if(document.getElementById('type_delete').value !== 'DELETE'){
                        alert('You must type DELETE exactly to confirm.');
                        e.preventDefault();
                    }
                });
                </script>
            <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include dirname(__DIR__) . '/shared/footer.php'; ?>