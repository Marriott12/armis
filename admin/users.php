<?php
/**
 * ARMIS System Administration — Role & Branch Assignment
 *
 * CHANGELOG (branch-scoping upgrade): this file previously queried a
 * `users` table that does not exist anywhere in the ARMIS schema (login
 * credentials, role, and account status all live on `staff` — see
 * armis1.sql). Every query here ran against a phantom table and would have
 * thrown a fatal PDO exception the moment this page was hit. It has been
 * rewritten end-to-end against the real `staff` table, and extended to be
 * the canonical screen for assigning the new branch-scoped roles
 * (cc/soi/soii/soiii/dg/ag) together with a Branch, which is what actually
 * grants a person their record-alteration scope (see shared/rbac.php).
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/shared/database_connection.php';
require_once dirname(__DIR__) . '/shared/rbac.php';

try {
    $pdo = getDbConnection();
} catch (Exception $e) {
    error_log("Database connection failed in admin/users.php: " . $e->getMessage());
    die("Database connection failed. Please check your configuration.");
}

$pageTitle = "Role & Branch Assignment";
$moduleName = "System Admin";
$moduleIcon = "users";
$currentPage = "users";

require_once __DIR__ . '/includes/sidebar_nav.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . dirname($_SERVER['PHP_SELF']) . '/../login.php');
    exit();
}

requireModuleAccess('admin');
requireBranchAdmin(); // this page reassigns roles/branches - system-admin only, not just "admin module" viewers
logAccess('admin', 'users_view', true);

$message = '';
$messageType = '';
$allRoles = getAllRoles(true);
$allBranches = getAllBranches(true);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf)) {
        $message = "Invalid CSRF token. Please reload the page and try again.";
        $messageType = "danger";
    } else {
        try {
            switch ($_POST['action']) {
                case 'update_role_branch':
                    $svcNo = trim($_POST['svcNo'] ?? '');
                    $newRole = $_POST['new_role'] ?? '';
                    $newBranchId = ($_POST['new_branch_id'] ?? '') !== '' ? (int)$_POST['new_branch_id'] : null;

                    if (!isset($allRoles[$newRole])) {
                        throw new Exception("Unknown role selected.");
                    }
                    if ($allRoles[$newRole]['is_branch_assignable'] && !$newBranchId) {
                        throw new Exception("The role '" . $allRoles[$newRole]['name'] . "' requires a branch to be selected.");
                    }

                    $stmt = $pdo->prepare("UPDATE staff SET role = :role, branch_id = :branch_id WHERE svcNo = :svcNo");
                    $stmt->execute(['role' => $newRole, 'branch_id' => $newBranchId, 'svcNo' => $svcNo]);

                    $message = "Role/branch updated for $svcNo.";
                    $messageType = "success";
                    logAccess('admin', 'user_role_update', true, "Set svcNo=$svcNo role=$newRole branch_id=" . ($newBranchId ?? 'null'));
                    break;

                case 'toggle_status':
                    $svcNo = trim($_POST['svcNo'] ?? '');
                    $newStatus = $_POST['new_status'] ?? '';
                    if (!in_array($newStatus, ['Active', 'Inactive', 'Suspended', 'Pending'], true)) {
                        throw new Exception("Invalid status.");
                    }
                    $stmt = $pdo->prepare("UPDATE staff SET accStatus = :status WHERE svcNo = :svcNo");
                    $stmt->execute(['status' => $newStatus, 'svcNo' => $svcNo]);

                    $message = "Account status updated for $svcNo.";
                    $messageType = "success";
                    logAccess('admin', 'user_status_update', true, "Set svcNo=$svcNo accStatus=$newStatus");
                    break;

                case 'reset_password':
                    $svcNo = trim($_POST['svcNo'] ?? '');
                    $tempPassword = 'Armis' . random_int(100000, 999999);
                    $hashedPassword = password_hash($tempPassword, PASSWORD_DEFAULT);

                    $stmt = $pdo->prepare("UPDATE staff SET password = :pw, isFirstLogin = 1, passwordChangedAt = NOW() WHERE svcNo = :svcNo");
                    $stmt->execute(['pw' => $hashedPassword, 'svcNo' => $svcNo]);

                    $message = "Password reset for $svcNo. Temporary password: $tempPassword (share this securely, it will not be shown again).";
                    $messageType = "info";
                    logAccess('admin', 'password_reset', true, "Reset password for svcNo=$svcNo");
                    break;
            }
        } catch (Exception $e) {
            $message = "Error: " . $e->getMessage();
            $messageType = "danger";
            error_log("Admin Users Error: " . $e->getMessage());
        }
    }
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

// Stats - all against the real staff table/columns
$userStats = ['total_users' => 0, 'active_users' => 0, 'privileged_users' => 0, 'new_users_month' => 0];
$userList = [];

try {
    $stmt = $pdo->query("SELECT
        COUNT(*) AS total_users,
        SUM(CASE WHEN accStatus = 'Active' THEN 1 ELSE 0 END) AS active_users,
        SUM(CASE WHEN role IS NOT NULL AND role <> 'user' THEN 1 ELSE 0 END) AS privileged_users,
        SUM(CASE WHEN dateCreated >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) AS new_users_month
        FROM staff
        WHERE username IS NOT NULL");
    $userStats = $stmt->fetch(PDO::FETCH_ASSOC);

    // Only staff who actually have login credentials (username set) are
    // "system users" in the sense this screen manages - most rank-and-file
    // staff records have no username/password at all.
    $stmt = $pdo->query("SELECT
        s.svcNo, s.username, s.officialEmail, s.role, s.accStatus, s.dateCreated, s.lastLogin,
        s.fName, s.mName, s.lName, s.rankId, s.branch_id, b.name AS branch_name
        FROM staff s
        LEFT JOIN branches b ON b.id = s.branch_id
        WHERE s.username IS NOT NULL
        ORDER BY s.dateCreated DESC");
    $userList = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("User data fetch error: " . $e->getMessage());
    $message = "Could not load staff/user data: " . $e->getMessage();
    $messageType = "danger";
}

include dirname(__DIR__) . '/shared/header.php';
include dirname(__DIR__) . '/shared/sidebar.php';
?>

<!-- Main Content -->
<div class="content-wrapper with-sidebar">
    <div class="container-fluid">
        <div class="main-content">
            <div class="row mb-4">
                <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h1 class="admin-section-title">
                                <i class="fas fa-users text-primary"></i> Role & Branch Assignment
                            </h1>
                            <p class="text-muted mb-0">Assign roles and branches to staff with login accounts. New staff accounts are created from Admin Branch &rarr; Create Staff.</p>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($message): ?>
            <div class="alert alert-<?= htmlspecialchars($messageType) ?> alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <?php endif; ?>

            <div class="row g-4 mb-5">
                <div class="col-xl-3 col-lg-6">
                    <div class="card bg-primary text-white h-100">
                        <div class="card-body">
                            <h6 class="card-title text-white-75">Total Login Accounts</h6>
                            <h2 class="display-6 text-white"><?= (int)($userStats['total_users'] ?? 0) ?></h2>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-lg-6">
                    <div class="card bg-success text-white h-100">
                        <div class="card-body">
                            <h6 class="card-title text-white-75">Active</h6>
                            <h2 class="display-6 text-white"><?= (int)($userStats['active_users'] ?? 0) ?></h2>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-lg-6">
                    <div class="card bg-warning text-white h-100">
                        <div class="card-body">
                            <h6 class="card-title text-white-75">Privileged Roles</h6>
                            <h2 class="display-6 text-white"><?= (int)($userStats['privileged_users'] ?? 0) ?></h2>
                            <small class="text-white-75">Not the default 'user' role</small>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-lg-6">
                    <div class="card bg-info text-white h-100">
                        <div class="card-body">
                            <h6 class="card-title text-white-75">New This Month</h6>
                            <h2 class="display-6 text-white"><?= (int)($userStats['new_users_month'] ?? 0) ?></h2>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="fas fa-list"></i> Accounts</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>Svc No</th>
                                    <th>Account</th>
                                    <th>Staff</th>
                                    <th>Role</th>
                                    <th>Branch</th>
                                    <th>Status</th>
                                    <th>Last Login</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($userList as $u): ?>
                                <tr>
                                    <td><?= htmlspecialchars($u['svcNo']) ?></td>
                                    <td>
                                        <strong><?= htmlspecialchars($u['username']) ?></strong><br>
                                        <small class="text-muted"><?= htmlspecialchars($u['officialEmail'] ?? '') ?></small>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars(trim(($u['lName'] ?? '') . ' ' . ($u['fName'] ?? '') . ' ' . ($u['mName'] ?? ''))) ?><br>
                                        <small class="text-muted"><?= htmlspecialchars($u['rankId'] ?? '') ?></small>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= ($u['role'] ?? 'user') === 'user' ? 'secondary' : 'primary' ?>">
                                            <?= htmlspecialchars($allRoles[$u['role']]['name'] ?? $u['role'] ?? 'user') ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($u['branch_name'] ?? '—') ?></td>
                                    <td>
                                        <span class="badge bg-<?= ($u['accStatus'] ?? '') === 'Active' ? 'success' : 'secondary' ?>">
                                            <?= htmlspecialchars($u['accStatus'] ?? 'Unknown') ?>
                                        </span>
                                    </td>
                                    <td><small><?= $u['lastLogin'] ? date('M j, Y H:i', strtotime($u['lastLogin'])) : 'Never' ?></small></td>
                                    <td>
                                        <button class="btn btn-outline-primary btn-sm"
                                                onclick='openEdit(<?= json_encode($u) ?>)'
                                                data-bs-toggle="modal" data-bs-target="#editUserModal">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-outline-warning btn-sm" onclick="resetPassword('<?= htmlspecialchars($u['svcNo']) ?>')">
                                            <i class="fas fa-key"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($userList)): ?>
                                <tr><td colspan="8" class="text-center text-muted py-4">No staff with login accounts found.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="editUserModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Assign Role & Branch</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST">
        <div class="modal-body">
          <input type="hidden" name="action" value="update_role_branch">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
          <input type="hidden" name="svcNo" id="edit_svcNo">

          <div class="mb-3">
            <label class="form-label">Service Number</label>
            <input type="text" class="form-control" id="edit_svcNo_display" readonly>
          </div>

          <div class="mb-3">
            <label class="form-label">Role</label>
            <select class="form-select" name="new_role" id="edit_role">
              <?php foreach ($allRoles as $code => $r): ?>
                <option value="<?= htmlspecialchars($code) ?>"><?= htmlspecialchars($r['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="mb-3">
            <label class="form-label">Branch</label>
            <select class="form-select" name="new_branch_id" id="edit_branch_id">
              <option value="">— None —</option>
              <?php foreach ($allBranches as $id => $b): ?>
                <option value="<?= (int)$id ?>"><?= htmlspecialchars($b['name']) ?><?= !empty($b['is_org_wide']) ? ' (Army-wide)' : '' ?></option>
              <?php endforeach; ?>
            </select>
            <small class="text-muted">Required for Chief Clerk, Staff Officer I/II/III and Director General. Not required for Adjutant General (org-wide by role) or System Administrator.</small>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>

<form method="POST" id="resetPasswordForm" style="display:none;">
    <input type="hidden" name="action" value="reset_password">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
    <input type="hidden" name="svcNo" id="reset_svcNo">
</form>

<script>
function openEdit(u) {
    document.getElementById('edit_svcNo').value = u.svcNo;
    document.getElementById('edit_svcNo_display').value = u.svcNo;
    document.getElementById('edit_role').value = u.role || 'user';
    document.getElementById('edit_branch_id').value = u.branch_id || '';
}
function resetPassword(svcNo) {
    if (confirm('Reset the password for ' + svcNo + '? A temporary password will be generated.')) {
        document.getElementById('reset_svcNo').value = svcNo;
        document.getElementById('resetPasswordForm').submit();
    }
}
</script>

<?php include dirname(__DIR__) . '/shared/footer.php'; ?>
