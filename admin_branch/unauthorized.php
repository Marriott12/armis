<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include necessary files
require_once dirname(__DIR__) . '/shared/database_connection.php';
require_once dirname(__DIR__) . '/shared/permissions.php';

$pageTitle = "Access Denied - Admin Branch";
$moduleName = "Admin Branch";
$moduleIcon = "exclamation-triangle";

// Get user information if logged in
$isLoggedIn = isset($_SESSION['user_id']);
$userRole = $_SESSION['role'] ?? 'guest';
$userName = $_SESSION['name'] ?? 'Unknown User';

// Get reason from query string
$reason = $_GET['reason'] ?? 'unknown';
$module = str_replace('_access', '', $reason);
$moduleDisplay = ucwords(str_replace('_', ' ', $module));

// Get user's permissions
$userPermissions = [];
if ($isLoggedIn) {
    $userPermissions = getUserPermissions($userRole);
}

include dirname(__DIR__) . '/shared/header.php';
?>

<div class="container-fluid">
    <div class="row justify-content-center align-items-center" style="min-height: 80vh;">
        <div class="col-md-8 col-lg-6">
            <div class="card border-danger">
                <div class="card-header bg-danger text-white">
                    <h5 class="mb-0"><i class="fas fa-shield-alt"></i> Access Denied</h5>
                </div>
                <div class="card-body p-4">
                    <div class="text-center mb-4">
                        <i class="fas fa-lock fa-4x text-danger"></i>
                    </div>
                    
                    <div class="alert alert-danger">
                        <h4 class="alert-heading">Insufficient Permissions</h4>
                        <p>You do not have the required permissions to access the <strong><?php echo htmlspecialchars($moduleDisplay); ?></strong> module.</p>
                    </div>
                    
                    <?php if ($isLoggedIn): ?>
                    <div class="bg-light p-3 rounded mb-4">
                        <h6 class="mb-2">Current Access Details:</h6>
                        <p class="mb-0">
                            <strong>User:</strong> <?php echo htmlspecialchars($userName); ?><br>
                            <strong>Role:</strong> <?php echo htmlspecialchars(ucfirst($userRole)); ?>
                        </p>
                    </div>
                    
                    <div class="bg-light p-3 rounded mb-4">
                        <h6 class="mb-2">Your Current Permissions:</h6>
                        <div class="d-flex flex-wrap gap-2">
                            <?php if (empty($userPermissions)): ?>
                                <span class="badge bg-secondary">No permissions found</span>
                            <?php else: ?>
                                <?php foreach ($userPermissions as $permission): ?>
                                    <span class="badge bg-info"><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $permission))); ?></span>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <a href="/Armis2/admin_branch/" class="btn btn-primary">
                            <i class="fas fa-arrow-left"></i> Back to Admin Branch
                        </a>
                        <a href="/Armis2/" class="btn btn-secondary">
                            <i class="fas fa-home"></i> Home
                        </a>
                    </div>
                    <?php else: ?>
                    <div class="alert alert-info">
                        <p class="mb-0">Please log in to access ARMIS resources.</p>
                    </div>
                    
                    <a href="/Armis2/login.php" class="btn btn-primary btn-block">
                        <i class="fas fa-sign-in-alt"></i> Login
                    </a>
                    <?php endif; ?>
                </div>
                <div class="card-footer text-muted">
                    <small>If you believe you should have access to this module, please contact your system administrator.</small>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include dirname(__DIR__) . '/shared/footer.php'; ?>
