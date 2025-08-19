<?php
// Start session if not already started
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
                    
                    // Validate role exists in RBAC
                    if (array_key_exists($newRole, ARMIS_ROLES)) {
                        $stmt = $pdo->prepare("UPDATE staff SET role = ? WHERE id = ?");
                        $stmt->execute([$newRole, $userId]);
                        $message = "User role updated successfully.";
                        $messageType = "success";
                        logAccess('admin', "role_update_user_{$userId}_to_{$newRole}", true);
                    } else {
                        $message = "Invalid role specified.";
                        $messageType = "danger";
                    }
                    break;
                    
                case 'toggle_status':
                    $userId = intval($_POST['user_id']);
                    $currentStatus = $_POST['current_status'];
                    $newStatus = ($currentStatus === 'active') ? 'inactive' : 'active';
                    
                    $stmt = $pdo->prepare("UPDATE staff SET accStatus = ? WHERE id = ?");
                    $stmt->execute([$newStatus, $userId]);
                    $message = "User status updated successfully.";
                    $messageType = "success";
                    logAccess('admin', "status_update_user_{$userId}_to_{$newStatus}", true);
                    break;
            }
        }
    } catch (Exception $e) {
        $message = "Error updating user: " . $e->getMessage();
        $messageType = "danger";
        error_log("Admin users error: " . $e->getMessage());
    }
}

// Fetch users with pagination and search
$search = $_GET['search'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

try {
    $pdo = getDbConnection();
    
    // Count total users for pagination
    $countSql = "SELECT COUNT(*) FROM staff WHERE 1=1";
    $params = [];
    
    if ($search) {
        $countSql .= " AND (username LIKE ? OR first_name LIKE ? OR last_name LIKE ? OR service_number LIKE ?)";
        $searchParam = "%{$search}%";
        $params = [$searchParam, $searchParam, $searchParam, $searchParam];
    }
    
    $stmt = $pdo->prepare($countSql);
    $stmt->execute($params);
    $totalUsers = $stmt->fetchColumn();
    $totalPages = ceil($totalUsers / $limit);
    
    // Fetch users
    $sql = "SELECT s.*, r.name as rank_name, r.abbreviation as rank_abbr, 
                   u.name as unit_name, c.name as corps_name
            FROM staff s
            LEFT JOIN ranks r ON s.rank_id = r.id
            LEFT JOIN units u ON s.unit_id = u.id
            LEFT JOIN corps c ON s.corps = c.name
            WHERE 1=1";
    
    if ($search) {
        $sql .= " AND (s.username LIKE ? OR s.first_name LIKE ? OR s.last_name LIKE ? OR s.service_number LIKE ?)";
    }
    
    $sql .= " ORDER BY s.last_login DESC LIMIT {$limit} OFFSET {$offset}";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $users = $stmt->fetchAll();
    
} catch (Exception $e) {
    $users = [];
    $totalUsers = 0;
    $totalPages = 0;
    error_log("Failed to fetch users: " . $e->getMessage());
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
                    <div class="d-flex justify-content-between align-items-center flex-wrap">
                        <div>
                            <h1 class="admin-section-title">
                                <i class="fas fa-users text-primary"></i> User Management
                            </h1>
                            <p class="text-muted mb-0">Manage system users, roles, and permissions</p>
                        </div>
                        <div class="d-flex gap-2">
                            <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#bulkActionsModal">
                                <i class="fas fa-tasks"></i> Bulk Actions
                            </button>
                            <a href="/Armis2/admin_branch/create_staff.php" class="btn btn-primary btn-sm">
                                <i class="fas fa-user-plus"></i> Add User
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Alert Messages -->
            <?php if ($message): ?>
            <div class="row mb-3">
                <div class="col-12">
                    <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Search and Filters -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-search"></i> Search & Filter Users</h5>
                        </div>
                        <div class="card-body">
                            <form method="GET" action="">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label for="search" class="form-label">Search Users</label>
                                        <input type="text" class="form-control" id="search" name="search" 
                                               value="<?php echo htmlspecialchars($search); ?>" 
                                               placeholder="Search by username, name, or service number...">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">&nbsp;</label>
                                        <div class="d-grid">
                                            <button type="submit" class="btn btn-primary">
                                                <i class="fas fa-search"></i> Search
                                            </button>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">&nbsp;</label>
                                        <div class="d-grid">
                                            <a href="?" class="btn btn-outline-secondary">
                                                <i class="fas fa-times"></i> Clear
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Users Table -->
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="fas fa-table"></i> System Users (<?php echo number_format($totalUsers); ?> total)</h5>
                            <small class="text-muted">Page <?php echo $page; ?> of <?php echo $totalPages; ?></small>
                        </div>
                        <div class="card-body">
                            <?php if (empty($users)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-users fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">No users found</h5>
                                <p class="text-muted">Try adjusting your search criteria.</p>
                            </div>
                            <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Service #</th>
                                            <th>Name</th>
                                            <th>Rank</th>
                                            <th>Unit</th>
                                            <th>Role</th>
                                            <th>Status</th>
                                            <th>Last Login</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($users as $user): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($user['service_number'] ?? 'N/A'); ?></strong>
                                            </td>
                                            <td>
                                                <div>
                                                    <strong><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></strong>
                                                    <br><small class="text-muted">@<?php echo htmlspecialchars($user['username']); ?></small>
                                                </div>
                                            </td>
                                            <td>
                                                <?php if ($user['rank_name']): ?>
                                                <span class="badge bg-secondary"><?php echo htmlspecialchars($user['rank_abbr'] ?? $user['rank_name']); ?></span>
                                                <?php else: ?>
                                                <span class="text-muted">N/A</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars($user['unit_name'] ?? 'Unassigned'); ?>
                                            </td>
                                            <td>
                                                <select class="form-select form-select-sm role-select" 
                                                        data-user-id="<?php echo $user['id']; ?>" 
                                                        data-current-role="<?php echo htmlspecialchars($user['role']); ?>">
                                                    <?php foreach (ARMIS_ROLES as $roleKey => $roleData): ?>
                                                    <option value="<?php echo $roleKey; ?>" 
                                                            <?php echo ($user['role'] === $roleKey) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($roleData['name']); ?>
                                                    </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </td>
                                            <td>
                                                <?php
                                                $status = strtolower($user['accStatus'] ?? 'inactive');
                                                $statusClass = ($status === 'active') ? 'success' : 'danger';
                                                $statusText = ucfirst($status);
                                                ?>
                                                <span class="badge bg-<?php echo $statusClass; ?>"><?php echo $statusText; ?></span>
                                            </td>
                                            <td>
                                                <?php if ($user['last_login']): ?>
                                                <small><?php echo date('M j, Y H:i', strtotime($user['last_login'])); ?></small>
                                                <?php else: ?>
                                                <small class="text-muted">Never</small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <button type="button" class="btn btn-outline-primary btn-sm toggle-status-btn"
                                                            data-user-id="<?php echo $user['id']; ?>"
                                                            data-current-status="<?php echo strtolower($user['accStatus'] ?? 'inactive'); ?>"
                                                            title="Toggle Status">
                                                        <i class="fas fa-power-off"></i>
                                                    </button>
                                                    <a href="/Armis2/admin_branch/edit_staff.php?id=<?php echo $user['id']; ?>" 
                                                       class="btn btn-outline-secondary btn-sm" title="Edit User">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Pagination -->
                            <?php if ($totalPages > 1): ?>
                            <nav aria-label="User pagination" class="mt-3">
                                <ul class="pagination justify-content-center">
                                    <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>">Previous</a>
                                    </li>
                                    <?php endif; ?>
                                    
                                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                    <li class="page-item <?php echo ($i === $page) ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>"><?php echo $i; ?></a>
                                    </li>
                                    <?php endfor; ?>
                                    
                                    <?php if ($page < $totalPages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>">Next</a>
                                    </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                            <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Hidden forms for AJAX actions -->
<form id="roleUpdateForm" method="POST" style="display: none;">
    <input type="hidden" name="action" value="update_role">
    <input type="hidden" name="user_id" id="roleUserId">
    <input type="hidden" name="new_role" id="newRole">
</form>

<form id="statusToggleForm" method="POST" style="display: none;">
    <input type="hidden" name="action" value="toggle_status">
    <input type="hidden" name="user_id" id="statusUserId">
    <input type="hidden" name="current_status" id="currentStatus">
</form>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle role changes
    document.querySelectorAll('.role-select').forEach(function(select) {
        select.addEventListener('change', function() {
            const userId = this.dataset.userId;
            const newRole = this.value;
            const currentRole = this.dataset.currentRole;
            
            if (newRole !== currentRole) {
                if (confirm('Are you sure you want to change this user\'s role?')) {
                    document.getElementById('roleUserId').value = userId;
                    document.getElementById('newRole').value = newRole;
                    document.getElementById('roleUpdateForm').submit();
                } else {
                    this.value = currentRole; // Reset to original value
                }
            }
        });
    });
    
    // Handle status toggle
    document.querySelectorAll('.toggle-status-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const userId = this.dataset.userId;
            const currentStatus = this.dataset.currentStatus;
            const newStatus = currentStatus === 'active' ? 'inactive' : 'active';
            
            if (confirm(`Are you sure you want to ${newStatus === 'active' ? 'activate' : 'deactivate'} this user?`)) {
                document.getElementById('statusUserId').value = userId;
                document.getElementById('currentStatus').value = currentStatus;
                document.getElementById('statusToggleForm').submit();
            }
        });
    });
});
</script>

<?php include dirname(__DIR__) . '/shared/footer.php'; ?>