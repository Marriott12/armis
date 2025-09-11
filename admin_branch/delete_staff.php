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

// --- AJAX search endpoint: PUT THIS FIRST! ---
// DO NOT include template or any HTML before this!
if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {
    header('Content-Type: application/json');
    try {
        $pdo = getDbConnection();
        
        // Build ranks and units map
        $ranksStmt = $pdo->query("SELECT id as rankID, name as rankName FROM ranks");
        $ranks = $ranksStmt->fetchAll(PDO::FETCH_OBJ);
        $rankMap = [];
        foreach ($ranks as $r) $rankMap[$r->rankID] = $r->rankName;
        
        $unitsStmt = $pdo->query("SELECT id as unitID, name as unitName FROM units");
        $units = $unitsStmt->fetchAll(PDO::FETCH_OBJ);
        $unitMap = [];
        foreach ($units as $u) $unitMap[$u->unitID] = $u->unitName;

        $search = trim($_GET['search'] ?? '');
        $sql = "SELECT service_number as svcNo, first_name as fname, last_name as lname, rank_id, unit_id FROM staff";
        $params = [];
        if ($search !== '') {
            $sql .= " WHERE (service_number LIKE ? OR first_name LIKE ? OR last_name LIKE ?)";
            $searchParam = '%' . $search . '%';
            $params = [$searchParam, $searchParam, $searchParam];
        }
        $sql .= " ORDER BY rank_id ASC, last_name ASC, first_name ASC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $staffList = $stmt->fetchAll(PDO::FETCH_OBJ);
        
        $result = [];
        foreach ($staffList as $s) {
            $result[] = [
                'svcNo' => $s->svcNo ?? '',
                'name' => ($s->lname ?? '') . ' ' . ($s->fname ?? ''),
                'rank' => isset($rankMap[$s->rank_id]) ? $rankMap[$s->rank_id] : ('ID:' . $s->rank_id),
                'unit' => isset($unitMap[$s->unit_id]) ? $unitMap[$s->unit_id] : '',
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
    
    $ranksStmt = $pdo->query("SELECT id as rankID, name as rankName FROM ranks");
    $ranks = $ranksStmt->fetchAll(PDO::FETCH_OBJ);
    $rankMap = [];
    foreach ($ranks as $r) $rankMap[$r->rankID] = $r->rankName;
    
    $unitsStmt = $pdo->query("SELECT id as unitID, name as unitName FROM units");
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

// If a staff member is selected for deletion, fetch brief info for confirmation
if (isset($_GET['svcNo'])) {
    $svcNo = $_GET['svcNo'];
    try {
        $stmt = $pdo->prepare(
            "SELECT service_number as svcNo, first_name as fname, last_name as lname, 
                    rank_id, unit_id, NRC, DOB, gender, svcStatus 
             FROM staff 
             WHERE service_number = ?"
        );
        $stmt->execute([$svcNo]);
        $staff = $stmt->fetch(PDO::FETCH_OBJ);
        
        // Debug: log what columns we actually got
        if ($staff) {
            error_log("Staff object properties: " . print_r(get_object_vars($staff), true));
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
            $stmt = $pdo->prepare("SELECT * FROM staff WHERE service_number = ?");
            $stmt->execute([$svcNo]);
            $staff = $stmt->fetch(PDO::FETCH_OBJ);
            
            if (!$staff) {
                $errors[] = "Staff member not found.";
            } else {
                // Get additional staff information for logging
                $staffName = ($staff->lname ?? '') . ' ' . ($staff->fname ?? '');
                $rankName = $rankMap[$staff->rank_id] ?? 'Unknown';
                $unitName = $unitMap[$staff->unit_id] ?? 'Unknown';
                
                // Backup staff data as JSON
                $staffBackup = json_encode([
                    'svcNo' => $staff->svcNo ?? '',
                    'name' => $staffName,
                    'rank_id' => $staff->rank_id ?? null,
                    'unit_id' => $staff->unit_id ?? null,
                    'NRC' => $staff->NRC ?? '',
                    'DOB' => $staff->DOB ?? '',
                    'gender' => $staff->gender ?? '',
                    'svcStatus' => $staff->svcStatus ?? '',
                    'deleted_at' => date('Y-m-d H:i:s')
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
                
                // Delete the staff member
                $deleteStmt = $pdo->prepare("DELETE FROM staff WHERE service_number = ?");
                $deleteResult = $deleteStmt->execute([$svcNo]);
                $rowsAffected = $deleteStmt->rowCount();
                
                if ($deleteResult && $rowsAffected > 0) {
                    $success = true;
                    $staff = null;
                    error_log("Successfully deleted staff member: $svcNo by user " . ($_SESSION['user_id'] ?? 'unknown'));
                } else {
                    $errors[] = "Failed to delete staff member. No rows were affected.";
                    error_log("Delete failed - no rows affected for service number: $svcNo");
                }
            }
        } catch (Exception $e) {
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
$sidebarLinks = [
    ['title' => 'Dashboard', 'url' => '/Armis2/admin_branch/index.php', 'icon' => 'tachometer-alt', 'page' => 'dashboard'],
    ['title' => 'Staff Management', 'url' => '/Armis2/admin_branch/edit_staff.php', 'icon' => 'users', 'page' => 'staff'],
    ['title' => 'Create Staff', 'url' => '/Armis2/admin_branch/create_staff.php', 'icon' => 'user-plus', 'page' => 'create'],
    ['title' => 'Delete Staff', 'url' => '/Armis2/admin_branch/delete_staff.php', 'icon' => 'user-times', 'page' => 'delete'],
    ['title' => 'Promotions', 'url' => '/Armis2/admin_branch/promote_staff.php', 'icon' => 'arrow-up', 'page' => 'promotions'],
    ['title' => 'Appointments', 'url' => '/Armis2/admin_branch/appointments.php', 'icon' => 'user-tie', 'page' => 'appointments'],
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
                                        '<td><a href="?svcNo=' + encodeURIComponent(staff.svcNo) + '" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i> Delete</a></td>';
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
                            <td><?=htmlspecialchars($staff->svcNo ?? 'N/A')?></td>
                        </tr>
                        <tr>
                            <th scope="row">Name</th>
                            <td><?=htmlspecialchars(($staff->lname ?? '') . ' ' . ($staff->fname ?? ''))?></td>
                        </tr>
                        <tr>
                            <th scope="row">Rank</th>
                            <td><?php 
                                $rankId = $staff->rank_id ?? null;
                                echo htmlspecialchars($rankMap[$rankId] ?? ('ID:' . $rankId ?? 'Unknown'));
                            ?></td>
                        </tr>
                        <tr>
                            <th scope="row">Unit</th>
                            <td><?php 
                                $unitId = $staff->unit_id ?? null;
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