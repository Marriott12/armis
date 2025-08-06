<?php
require_once '../init.php';

// --- AJAX search endpoint: PUT THIS FIRST! ---
// DO NOT include template or any HTML before this!
if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {
    header('Content-Type: application/json');
    // Build ranks and units map
    $ranks = $db->query("SELECT rankID, rankName FROM ranks")->results();
    $rankMap = [];
    foreach ($ranks as $r) $rankMap[$r->rankID] = $r->rankName;
    $units = $db->query("SELECT unitID, unitName FROM units")->results();
    $unitMap = [];
    foreach ($units as $u) $unitMap[$u->unitID] = $u->unitName;

    $search = trim($_GET['search'] ?? '');
    $sql = "SELECT svcNo, fname, lname, rankID, unitID FROM staff";
    $params = [];
    if ($search !== '') {
        $sql .= " WHERE (svcNo LIKE ? OR fname LIKE ? OR lname LIKE ?)";
        $searchParam = '%' . $search . '%';
        $params = [$searchParam, $searchParam, $searchParam];
    }
    $sql .= " ORDER BY rankID ASC, lname ASC, fname ASC";
    $staffList = $db->query($sql, $params)->results();
    $result = [];
    foreach ($staffList as $s) {
        $result[] = [
            'svcNo' => $s->svcNo,
            'name' => $s->lname . ' ' . $s->fname,
            'rank' => isset($rankMap[$s->rankID]) ? $rankMap[$s->rankID] : ('ID:' . $s->rankID),
            'unit' => isset($unitMap[$s->unitID]) ? $unitMap[$s->unitID] : '',
        ];
    }
    echo json_encode($result);
    exit;
}

require_once $abs_us_root . $us_url_root . 'users/includes/template/prep.php';

global $user;
if (!securePage($_SERVER['PHP_SELF'])) { die(); }
if ($user->data()->permissions != 1) die("Unauthorized.");

// --- CSRF helper ---
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
function csrf_token() { return $_SESSION['csrf_token']; }

// --- Lookup for ranks and units (for delete confirmation) ---
$ranks = $db->query("SELECT rankID, rankName FROM ranks")->results();
$rankMap = [];
foreach ($ranks as $r) $rankMap[$r->rankID] = $r->rankName;
$units = $db->query("SELECT unitID, unitName FROM units")->results();
$unitMap = [];
foreach ($units as $u) $unitMap[$u->unitID] = $u->unitName;

$errors = [];
$success = false;
$staff = null;

// If a staff member is selected for deletion, fetch brief info for confirmation
if (isset($_GET['svcNo'])) {
    $svcNo = $_GET['svcNo'];
    $staff = $db->query(
        "SELECT s.svcNo, s.fname, s.lname, s.rankID, s.unitID, s.NRC, s.DOB, s.gender, s.svcStatus 
         FROM staff s 
         WHERE s.svcNo = ?",
        [$svcNo]
    )->first();
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
        $staff = $db->query("SELECT * FROM staff WHERE svcNo = ?", [$svcNo])->first();
        if (!$staff) {
            $errors[] = "Staff member not found.";
        } else {
            try {
                $db->insert('deletion_log', [
                    'svcNo' => $svcNo,
                    'deleted_by' => $user->data()->id ?? 0,
                    'deleted_at' => date('Y-m-d H:i:s'),
                    'user_ip' => $_SERVER['REMOTE_ADDR'],
                    'reason' => $reason,
                ]);
                $db->query("DELETE FROM staff WHERE svcNo = ?", [$svcNo]);
                $success = true;
                $staff = null;
            } catch (Exception $e) {
                error_log("Delete error by user {$user->data()->id}: " . $e->getMessage());
                $errors[] = "Error deleting staff. Please contact admin.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Delete Staff Member | Zambia Army</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/css/bootstrap.min.css" />
</head>
<body>
<div class="container my-5">
    <div class="mb-3">
        <a href="../admin_branch.php" class="btn btn-outline-secondary" aria-label="Back to Admin Branch">
            <i class="fa fa-arrow-left"></i> Back to Admin Branch
        </a>
    </div>
    <div class="card shadow-sm">
        <div class="card-header bg-danger text-white">
            <h4 class="mb-0"><i class="fa fa-user-times"></i> Delete Staff Member</h4>
        </div>
        <div class="card-body">
            <?php if ($success): ?>
                <div class="alert alert-success" role="alert">Staff member deleted successfully and logged.</div>
            <?php endif; ?>
            <?php if ($errors): ?>
                <div class="alert alert-danger" role="alert">
                    <ul class="mb-0">
                        <?php foreach ($errors as $err): ?>
                            <li><?=htmlspecialchars($err)?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if (!$staff): ?>
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
                                <th scope="col">Unit</th>
                                <th scope="col">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr><td colspan="5" class="text-center text-muted">Loading staff...</td></tr>
                        </tbody>
                    </table>
                </div>
                <script>
                const searchInput = document.getElementById('searchStaff');
                const resultsTable = document.getElementById('staffResultsTable').getElementsByTagName('tbody')[0];
                let typingTimer;
                function fetchResults(query) {
                    resultsTable.innerHTML = `<tr><td colspan="5" class="text-center text-muted">Loading staff...</td></tr>`;
                    fetch('?ajax=1&search=' + encodeURIComponent(query))
                        .then(r => r.json())
                        .then(data => {
                            resultsTable.innerHTML = '';
                            if (!Array.isArray(data) || data.length === 0) {
                                resultsTable.innerHTML = '<tr><td colspan="5" class="text-center text-muted">No staff found.</td></tr>';
                            } else {
                                data.forEach(function(staff) {
                                    const tr = document.createElement('tr');
                                    tr.innerHTML =
                                        '<td>' + staff.svcNo + '</td>' +
                                        '<td>' + staff.name + '</td>' +
                                        '<td>' + staff.rank + '</td>' +
                                        '<td>' + staff.unit + '</td>' +
                                        '<td><a href="?svcNo=' + encodeURIComponent(staff.svcNo) + '" class="btn btn-danger btn-sm"><i class="fa fa-trash"></i> Delete</a></td>';
                                    resultsTable.appendChild(tr);
                                });
                            }
                        })
                        .catch(err => {
                            resultsTable.innerHTML = '<tr><td colspan="5" class="text-center text-danger">AJAX Error: ' + err + '</td></tr>';
                        });
                }
                fetchResults('');
                searchInput.addEventListener('input', function() {
                    clearTimeout(typingTimer);
                    typingTimer = setTimeout(function() {
                        fetchResults(searchInput.value);
                    }, 250);
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
                <div class="mb-4">
                    <table class="table table-bordered w-auto">
                        <tr>
                            <th scope="row">Service No</th>
                            <td><?=htmlspecialchars($staff->svcNo)?></td>
                        </tr>
                        <tr>
                            <th scope="row">Name</th>
                            <td><?=htmlspecialchars($staff->lname . ' ' . $staff->fname)?></td>
                        </tr>
                        <tr>
                            <th scope="row">Rank</th>
                            <td><?=htmlspecialchars($rankMap[$staff->rankID] ?? ('ID:'.$staff->rankID))?></td>
                        </tr>
                        <tr>
                            <th scope="row">Unit</th>
                            <td><?=htmlspecialchars($unitMap[$staff->unitID] ?? '')?></td>
                        </tr>
                        <tr>
                            <th scope="row">NRC</th>
                            <td><?=htmlspecialchars($staff->NRC)?></td>
                        </tr>
                        <tr>
                            <th scope="row">DOB</th>
                            <td><?=htmlspecialchars($staff->DOB)?></td>
                        </tr>
                        <tr>
                            <th scope="row">Gender</th>
                            <td><?=htmlspecialchars($staff->gender)?></td>
                        </tr>
                        <tr>
                            <th scope="row">Status</th>
                            <td><?=htmlspecialchars($staff->svcStatus)?></td>
                        </tr>
                    </table>
                </div>
                <form method="post" autocomplete="off" aria-label="Confirm Delete Staff">
                    <input type="hidden" name="delete_svcNo" value="<?=htmlspecialchars($staff->svcNo)?>">
                    <input type="hidden" name="csrf_token" value="<?=csrf_token()?>">
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
                            <i class="fa fa-trash"></i> Confirm Delete
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
</body>
</html>