<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include RBAC system
require_once __DIR__ . '/shared/rbac.php';

$pageTitle = "Access Denied";
$moduleName = "Security";
$moduleIcon = "exclamation-triangle";

// Get user information if logged in
$isLoggedIn = isset($_SESSION['user_id']);
$userRole = $_SESSION['role'] ?? 'guest';
$userName = $_SESSION['name'] ?? 'Unknown User';

// Log unauthorized access attempt
if ($isLoggedIn) {
    logAccess('unauthorized', 'access_denied', false);
}

include __DIR__ . '/shared/header.php';
?>

<div class="container-fluid">
    <div class="row justify-content-center align-items-center" style="min-height: 80vh;">
        <div class="col-md-8 col-lg-6">
            <div class="card border-danger">
                <div class="card-body text-center p-5">
                    <div class="mb-4">
                        <i class="fas fa-shield-alt fa-5x text-danger"></i>
                    </div>
                    
                    <h1 class="card-title text-danger mb-3">Access Denied</h1>
                    
                    <div class="alert alert-danger" role="alert">
                        <h4 class="alert-heading">Insufficient Permissions</h4>
                        <p class="mb-0">You do not have the required permissions to access this resource.</p>
                    </div>
                    
                    <?php if ($isLoggedIn): ?>
                        <div class="bg-light p-3 rounded mb-4">
                            <h6 class="mb-2">Current Access Level:</h6>
                            <p class="mb-2">
                                <strong>User:</strong> <?php echo htmlspecialchars($userName); ?><br>
                                <strong>Role:</strong> <?php echo htmlspecialchars(ucfirst($userRole)); ?>
                            </p>
                            
                            <?php 
                            $roleInfo = getRoleInfo($userRole);
                            $userModules = getUserModules($userRole);
                            ?>
                            
                            <h6 class="mb-2">Your Accessible Modules:</h6>
                            <div class="d-flex flex-wrap gap-2">
                                <?php foreach ($userModules as $module): ?>
                                    <span class="badge bg-success"><?php echo ucfirst(str_replace('_', ' ', $module)); ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <div class="d-grid gap-2 d-md-block">
                            <a href="<?php echo getRoleDashboardUrl($userRole); ?>" class="btn btn-primary">
                                <i class="fas fa-tachometer-alt"></i> Go to My Dashboard
                            </a>
                            <a href="/Armis2/" class="btn btn-secondary">
                                <i class="fas fa-home"></i> Home
                            </a>
                        </div>
                        
                    <?php else: ?>
                        <div class="alert alert-info">
                            <p class="mb-0">Please log in to access ARMIS resources.</p>
                        </div>
                        
                        <a href="/Armis2/login.php" class="btn btn-primary">
                            <i class="fas fa-sign-in-alt"></i> Login
                        </a>
                    <?php endif; ?>
                    
                    <hr class="my-4">
                    
                    <div class="text-muted">
                        <small>
                            <i class="fas fa-info-circle"></i>
                            If you believe you should have access to this resource, please contact your system administrator.
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/shared/footer.php'; ?>

<style>
.card {
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

.badge {
    font-size: 0.75rem;
}

.alert {
    border-radius: 8px;
}

@keyframes shake {
    0%, 100% { transform: translateX(0); }
    25% { transform: translateX(-5px); }
    75% { transform: translateX(5px); }
}

.card:hover {
    animation: shake 0.5s ease-in-out;
}
</style>
