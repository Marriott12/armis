<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include configuration and database
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/shared/database_connection.php';

// Include RBAC system
require_once dirname(__DIR__) . '/shared/rbac.php';

// Initialize global database connection
try {
    $pdo = getDbConnection();
} catch (Exception $e) {
    error_log("Database connection failed in admin/users.php: " . $e->getMessage());
    die("Database connection failed. Please check your configuration.");
} Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include RBAC system
require_once dirname(__DIR__) . '/shared/rbac.php';
require_once dirname(__DIR__) . '/shared/database_connection.php';

$pageTitle = "User Management";
$moduleName = "System Admin";
$moduleIcon = "users";
$currentPage = "users";

$sidebarLinks = [
    ['title' => 'Dashboard', 'url' => '/Armis2/admin/index.php', 'icon' => 'tachometer-alt', 'page' => 'dashboard'],
    ['title' => 'User Management', 'url' => '/Armis2/admin/users.php', 'icon' => 'users', 'page' => 'users'],
    ['title' => 'System Settings', 'url' => '/Armis2/admin/settings.php', 'icon' => 'cogs', 'page' => 'settings'],
    ['title' => 'Database Management', 'url' => '/Armis2/admin/database.php', 'icon' => 'database', 'page' => 'database'],
    ['title' => 'Security Center', 'url' => '/Armis2/admin/security.php', 'icon' => 'shield-alt', 'page' => 'security'],
    ['title' => 'System Reports', 'url' => '/Armis2/admin/reports.php', 'icon' => 'chart-bar', 'page' => 'reports']
];

// Check if user is logged in and has admin privileges
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . dirname($_SERVER['PHP_SELF']) . '/../login.php');
    exit();
}

// Check if user has access to admin module
requireModuleAccess('admin');

// Log access
logAccess('admin', 'users_view', true);

// Handle user actions (create, edit, delete, role changes)
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo = getDbConnection();
        
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'update_role':
                    $userId = intval($_POST['user_id']);
                    $newRole = $_POST['new_role'];
                    
                    $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
                    $stmt->execute([$newRole, $userId]);
                    
                    $message = "User role updated successfully.";
                    $messageType = "success";
                    logAccess('admin', 'user_role_update', true, "Updated user ID $userId role to $newRole");
                    break;
                    
                case 'toggle_status':
                    $userId = intval($_POST['user_id']);
                    $newStatus = $_POST['new_status'];
                    
                    $stmt = $pdo->prepare("UPDATE users SET accStatus = ? WHERE id = ?");
                    $stmt->execute([$newStatus, $userId]);
                    
                    $message = "User status updated successfully.";
                    $messageType = "success";
                    logAccess('admin', 'user_status_update', true, "Updated user ID $userId status to $newStatus");
                    break;
                    
                case 'reset_password':
                    $userId = intval($_POST['user_id']);
                    $tempPassword = 'temp' . rand(1000, 9999);
                    $hashedPassword = password_hash($tempPassword, PASSWORD_DEFAULT);
                    
                    $stmt = $pdo->prepare("UPDATE users SET password = ?, password_reset_required = 1 WHERE id = ?");
                    $stmt->execute([$hashedPassword, $userId]);
                    
                    $message = "Password reset successfully. Temporary password: $tempPassword";
                    $messageType = "info";
                    logAccess('admin', 'password_reset', true, "Reset password for user ID $userId");
                    break;
            }
        }
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
        $messageType = "danger";
        error_log("Admin Users Error: " . $e->getMessage());
    }
}

// Get user statistics and list
$userStats = [];
$userList = [];

try {
    $pdo = getDbConnection();
    
    // Get user statistics
    $stmt = $pdo->query("SELECT 
        COUNT(*) as total_users,
        SUM(CASE WHEN accStatus = 'active' THEN 1 ELSE 0 END) as active_users,
        SUM(CASE WHEN accStatus = 'inactive' THEN 1 ELSE 0 END) as inactive_users,
        SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) as admin_users,
        SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as new_users_month
        FROM users");
    $userStats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get user list with details
    $stmt = $pdo->query("SELECT 
        u.id, u.username, u.email, u.role, u.accStatus, u.created_at, u.last_login,
        s.fname, s.lname, s.svcNo, s.rank, s.unit
        FROM users u 
        LEFT JOIN staff s ON u.id = s.user_id 
        ORDER BY u.created_at DESC");
    $userList = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log("User data fetch error: " . $e->getMessage());
}

include dirname(__DIR__) . '/shared/header.php';
include dirname(__DIR__) . '/shared/sidebar.php';
?>

<!-- Main Content -->
<div class="content-wrapper with-sidebar">
    <div class="container-fluid">
        <div class="main-content">
            <!-- Header Section -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h1 class="admin-section-title">
                                <i class="fas fa-users text-primary"></i> User Management
                            </h1>
                            <p class="text-muted mb-0">Manage system users, roles, and access permissions</p>
                        </div>
                        <div>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createUserModal">
                                <i class="fas fa-plus"></i> Create User
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Alert Messages -->
            <?php if ($message): ?>
            <div class="alert alert-<?= $messageType ?> alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <!-- User Statistics -->
            <div class="row g-4 mb-5">
                <div class="col-xl-3 col-lg-6">
                    <div class="card bg-primary text-white h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title text-white-75">Total Users</h6>
                                    <h2 class="display-6 text-white"><?= $userStats['total_users'] ?? 0 ?></h2>
                                    <small class="text-white-75">All registered users</small>
                                </div>
                                <div class="text-white-50">
                                    <i class="fas fa-users fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-lg-6">
                    <div class="card bg-success text-white h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title text-white-75">Active Users</h6>
                                    <h2 class="display-6 text-white"><?= $userStats['active_users'] ?? 0 ?></h2>
                                    <small class="text-white-75">Currently active accounts</small>
                                </div>
                                <div class="text-white-50">
                                    <i class="fas fa-user-check fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-lg-6">
                    <div class="card bg-warning text-white h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title text-white-75">Admin Users</h6>
                                    <h2 class="display-6 text-white"><?= $userStats['admin_users'] ?? 0 ?></h2>
                                    <small class="text-white-75">Administrative accounts</small>
                                </div>
                                <div class="text-white-50">
                                    <i class="fas fa-user-shield fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-lg-6">
                    <div class="card bg-info text-white h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title text-white-75">New This Month</h6>
                                    <h2 class="display-6 text-white"><?= $userStats['new_users_month'] ?? 0 ?></h2>
                                    <small class="text-white-75">Recently created accounts</small>
                                </div>
                                <div class="text-white-50">
                                    <i class="fas fa-user-plus fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- User List -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-list"></i> User Directory
                    </h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>ID</th>
                                    <th>User Info</th>
                                    <th>Staff Details</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                    <th>Last Login</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($userList as $user): ?>
                                <tr>
                                    <td><?= htmlspecialchars($user['id']) ?></td>
                                    <td>
                                        <div>
                                            <strong><?= htmlspecialchars($user['username']) ?></strong><br>
                                            <small class="text-muted"><?= htmlspecialchars($user['email']) ?></small>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($user['fname'] || $user['lname']): ?>
                                        <div>
                                            <strong><?= htmlspecialchars(($user['fname'] ?? '') . ' ' . ($user['lname'] ?? '')) ?></strong><br>
                                            <small class="text-muted">
                                                <?= htmlspecialchars($user['svcNo'] ?? 'N/A') ?> | 
                                                <?= htmlspecialchars($user['rank'] ?? 'N/A') ?><br>
                                                <?= htmlspecialchars($user['unit'] ?? 'N/A') ?>
                                            </small>
                                        </div>
                                        <?php else: ?>
                                        <span class="text-muted">No staff record</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= $user['role'] === 'admin' ? 'danger' : ($user['role'] === 'supervisor' ? 'warning' : 'primary') ?>">
                                            <?= htmlspecialchars(ucfirst($user['role'])) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= $user['accStatus'] === 'active' ? 'success' : 'secondary' ?>">
                                            <?= htmlspecialchars(ucfirst($user['accStatus'])) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <small><?= date('M j, Y', strtotime($user['created_at'])) ?></small>
                                    </td>
                                    <td>
                                        <small><?= $user['last_login'] ? date('M j, Y H:i', strtotime($user['last_login'])) : 'Never' ?></small>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm" role="group">
                                            <button class="btn btn-outline-primary btn-sm" 
                                                    onclick="editUser(<?= $user['id'] ?>, '<?= htmlspecialchars($user['username']) ?>', '<?= htmlspecialchars($user['role']) ?>', '<?= htmlspecialchars($user['accStatus']) ?>')"
                                                    data-bs-toggle="modal" data-bs-target="#editUserModal">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-outline-warning btn-sm" 
                                                    onclick="resetPassword(<?= $user['id'] ?>, '<?= htmlspecialchars($user['username']) ?>')">
                                                <i class="fas fa-key"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Edit User Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_role">
                    <input type="hidden" name="user_id" id="edit_user_id">
                    
                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input type="text" class="form-control" id="edit_username" readonly>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Role</label>
                        <select class="form-select" name="new_role" id="edit_role">
                            <option value="user">User</option>
                            <option value="supervisor">Supervisor</option>
                            <option value="admin">Administrator</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="new_status" id="edit_status">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editUser(id, username, role, status) {
    document.getElementById('edit_user_id').value = id;
    document.getElementById('edit_username').value = username;
    document.getElementById('edit_role').value = role;
    document.getElementById('edit_status').value = status;
}

function resetPassword(userId, username) {
    if (confirm(`Are you sure you want to reset the password for user "${username}"?`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="reset_password">
            <input type="hidden" name="user_id" value="${userId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}
</script>

<?php include dirname(__DIR__) . '/shared/footer.php'; ?>
