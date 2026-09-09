<?php
session_start();

// Include database functions
require_once __DIR__ . '/shared/database_connection.php';
require_once __DIR__ . '/shared/csrf.php';

/**
 * Validate return URL to prevent open redirect vulnerabilities
 */
function isValidReturnUrl($url) {
    // Must start with /Armis2/
    if (strpos($url, '/Armis2/') !== 0) {
        return false;
    }
    
    // Must not contain protocol or domain
    if (preg_match('#^https?://#i', $url)) {
        return false;
    }
    
    // Must not contain special characters that could be used for XSS
    if (preg_match('/[<>"\'()]/', $url)) {
        return false;
    }
    
    return true;
}

// Debug: Log that a login attempt happened — never log $_POST here,
// it contains the plaintext password.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log("Login form submitted for username: " . ($_POST['username'] ?? '(none)'));
}

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    // Blocked-IP check — real infrastructure backing admin/security.php's
    // "Block IP" action (database/migrations/2026_08_28_add_security_center_tables.sql).
    $clientIp = $_SERVER['REMOTE_ADDR'] ?? '';
    if ($clientIp !== '') {
        try {
            $pdo = getDbConnection();
            $blockStmt = $pdo->prepare('SELECT reason FROM blocked_ips WHERE ip_address = ?');
            $blockStmt->execute([$clientIp]);
            $blockReason = $blockStmt->fetchColumn();
            if ($blockReason !== false) {
                $error = 'Access denied from this network.';
                error_log("Login blocked - IP $clientIp is on the block list ($blockReason)");
                goto login_blocked;
            }
        } catch (PDOException $e) {
            // blocked_ips table not migrated yet — fail open.
            error_log('blocked_ips check failed (has the migration been run?): ' . $e->getMessage());
        }
    }

    // Emergency lockdown — real infrastructure backing admin/security.php's
    // "Emergency Lockdown" action. When active, only admin/superadmin
    // roles can still log in.
    try {
        $pdo = getDbConnection();
        $lockdownStmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'EMERGENCY_LOCKDOWN'");
        $lockdownStmt->execute();
        if ($lockdownStmt->fetchColumn() === '1') {
            $checkStmt = $pdo->prepare('SELECT role FROM staff WHERE username = ? OR svcNo = ?');
            $checkStmt->execute([$_POST['username'] ?? '', $_POST['username'] ?? '']);
            $roleCheck = strtolower($checkStmt->fetchColumn() ?: '');
            if (!str_contains($roleCheck, 'admin')) {
                $error = 'The system is currently in emergency lockdown. Only administrators can log in.';
                error_log("Login blocked - emergency lockdown active, non-admin role '$roleCheck'");
                goto login_blocked;
            }
        }
    } catch (PDOException $e) {
        error_log('Lockdown check failed (has the migration been run?): ' . $e->getMessage());
    }

    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (!empty($username) && !empty($password)) {
        // Try database authentication first
        $user = authenticateUser($username, $password);
        
        if ($user) {
            // Check if user needs to change temporary password (first-time login)
            if ($user['isFirstLogin'] == 1) {
                // Store user info for password change
                $_SESSION['temp_password_change_required'] = true;
                $_SESSION['temp_password_user_id'] = $user['id'];
                $_SESSION['temp_user_info'] = [
                    'username' => $user['username'],
                    'name' => trim($user['fName'] . ' ' . $user['lName']),
                    'rank' => $user['rank_name'] ?? 'Unknown'
                ];
                
                // Redirect to password change page
                header('Location: /Armis2/change_temp_password.php');
                exit();
            }
            
            // Update last login
            updateLastLogin($user['id']);
            
            // Set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['userID'] = $user['id']; // For compatibility
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            // CHANGELOG (branch-scoping upgrade): shared/rbac.php's
            // canAlterRecord()/getSnapshotScope()/getUserModules() all read
            // $_SESSION['branch_id'] to resolve a branch-scoped user's
            // reach. Without this line every write is silently rejected and
            // every branch dashboard looks empty, regardless of what
            // staff.branch_id actually contains.
            $_SESSION['branch_id'] = $user['branch_id'] ?? null;
            $_SESSION['rank'] = $user['rank_name'] ?? 'Unknown';
            $_SESSION['name'] = trim($user['fName'] . ' ' . $user['lName']);
            $_SESSION['unit'] = $user['unit_name'] ?? 'Unknown';
            $_SESSION['corps'] = $user['corps'] ?? 'Unknown';
            $_SESSION['svcNo'] = $user['svcNo'];
            $_SESSION['fName'] = $user['fName'];
            $_SESSION['lName'] = $user['lName'];
            $_SESSION['email'] = $user['officialEmail'];
            
            // Include RBAC functions for centralized role management
            require_once __DIR__ . '/shared/rbac.php';
            
            // Check for return URL parameter from POST or GET
            $returnUrl = $_POST['return_url'] ?? $_GET['return_url'] ?? null;
            
            // Validate return URL to prevent open redirects
            if ($returnUrl && !isValidReturnUrl($returnUrl)) {
                $returnUrl = null; // Reset if invalid
            }
            
            // Check for saved state from session timeout
            if (isset($_GET['reason']) && $_GET['reason'] === 'timeout') {
                // User was redirected here due to timeout
                // State should be in sessionStorage (handled by JavaScript)
                $_SESSION['restore_state'] = true;
            }
            
            // For admin role, always go directly to admin dashboard
            if ($user['role'] === 'admin') {
                // If there's a return URL and it's valid, use it
                if ($returnUrl && isValidReturnUrl($returnUrl)) {
                    $dashboardUrl = $returnUrl;
                } else {
                    $dashboardUrl = '/Armis2/admin/index.php';
                }
            } else {
                // Get role-specific dashboard URL using centralized function
                if ($returnUrl && isValidReturnUrl($returnUrl)) {
                    $dashboardUrl = $returnUrl;
                } else {
                    $dashboardUrl = getRoleDashboardUrl($user['role']);
                }
            }
            
            // Track login redirect in session
            $_SESSION['last_login_time'] = time();
            $_SESSION['login_redirect'] = $dashboardUrl;
            $_SESSION['login_complete'] = true; // Flag to indicate successful login
            
            // Force session write
            session_write_close();
            
            // Debug log: successful authentication and redirect target
            error_log(sprintf("User '%s' (id=%s) authenticated successfully; redirecting to %s", $user['username'], $user['id'], $dashboardUrl));

            // Redirect to appropriate dashboard
            header('Location: ' . $dashboardUrl);
            exit();
        } else {
            // No user found or password mismatch - log attempt and show error
            error_log(sprintf("Failed login attempt for username='%s' from IP=%s", $username, $_SERVER['REMOTE_ADDR'] ?? 'unknown'));
            $error = 'Invalid username or password';
        }
        
    } else {
        $error = 'Please enter both username and password';
    }

    login_blocked:
}

$pageTitle = "Login";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | ARMIS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="/Armis2/shared/armis-styles.css" rel="stylesheet">
</head>
<body class="login-body">
    <div class="security-badge">
        <i class="fas fa-shield-alt"></i> Secure Login
    </div>
    
    <div class="container">
        <div class="login-container">
            <div class="card login-card">
                <div class="login-header">
                    <div class="login-logo">
                        <img src="/Armis2/logo.png" alt="ARMIS Logo" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                        <i class="fas fa-shield-alt" style="display:none;"></i>
                    </div>
                    <h1 class="system-title">ARMIS</h1>
                    <p class="mb-0 system-subtitle">Army Resource Management Information System</p>
                </div>
                
                <div class="login-form-container">
                    <?php if (isset($_SESSION['timeout_message'])): ?>
                        <div class="alert alert-warning alert-dismissible fade show" role="alert">
                            <i class="fas fa-clock"></i> <?php echo htmlspecialchars($_SESSION['timeout_message']); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        <?php unset($_SESSION['timeout_message']); ?>
                    <?php endif; ?>
                    
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger" role="alert">
                            <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="/Armis2/login.php<?php echo isset($_GET['return_url']) ? '?return_url=' . urlencode($_GET['return_url']) : ''; ?>" class="needs-validation" novalidate>
                        <?= csrf_field() ?>
                        <?php if (isset($_GET['return_url'])): ?>
                            <input type="hidden" name="return_url" value="<?php echo htmlspecialchars($_GET['return_url']); ?>">
                        <?php endif; ?>
                        <div class="mb-3">
                            <label for="username" class="form-label">
                                <i class="fas fa-user"></i> Username
                            </label>
                            <input type="text" class="form-control" id="username" name="username" required 
                                   placeholder="Enter your military username" 
                                   value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                                   autocomplete="username">
                            <div class="invalid-feedback">
                                Please provide a valid username.
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label for="password" class="form-label">
                                <i class="fas fa-lock"></i> Password
                            </label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="password" name="password" required 
                                       placeholder="Enter your secure password" autocomplete="current-password">
                                <button class="btn btn-outline-secondary" type="button" id="togglePassword" style="border-radius: 0 12px 12px 0;">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div class="invalid-feedback">
                                Please provide a valid password.
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-login">
                            <i class="fas fa-sign-in-alt"></i> Access ARMIS System
                        </button>
                    </form>
                    
                    <hr class="my-4">
                    
                    <div class="text-center">
                        <a href="/Armis2/access_demo.php" class="btn btn-outline-secondary me-2">
                            <i class="fas fa-info-circle"></i> System Info
                        </a>
                        <a href="/Armis2/" class="btn btn-outline-secondary">
                            <i class="fas fa-home"></i> Home
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Core JS (Bootstrap) is loaded in shared/footer.php when included; no inline bootstrap bundle here. -->
    <script>
        // Password visibility toggle
        document.getElementById('togglePassword').addEventListener('click', function() {
            const password = document.getElementById('password');
            const icon = this.querySelector('i');
            
            if (password.type === 'password') {
                password.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                password.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });

        // Form validation and submission
        (function() {
            'use strict';
            window.addEventListener('load', function() {
                var forms = document.getElementsByClassName('needs-validation');
                var validation = Array.prototype.filter.call(forms, function(form) {
                    form.addEventListener('submit', function(event) {
                        if (form.checkValidity() === false) {
                            event.preventDefault();
                            event.stopPropagation();
                        } else {
                            // Form is valid, show loading state
                            const btn = form.querySelector('.btn-login');
                            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Authenticating...';
                            btn.disabled = true;
                        }
                        form.classList.add('was-validated');
                    }, false);
                });
            }, false);
        })();

        // Auto-focus username field
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('username').focus();
            
            // Check if we need to add return URL to login form
            const urlParams = new URLSearchParams(window.location.search);
            const reason = urlParams.get('reason');
            
            if (reason === 'timeout') {
                // Get saved state from sessionStorage
                const savedState = sessionStorage.getItem('armis_saved_state');
                
                if (savedState) {
                    try {
                        const state = JSON.parse(savedState);
                        console.log('Found saved state:', state);
                        
                        // Add return URL to login form
                        const form = document.querySelector('form[action="/Armis2/login.php"]');
                        if (form && state.pathname) {
                            const returnUrlInput = document.createElement('input');
                            returnUrlInput.type = 'hidden';
                            returnUrlInput.name = 'return_url';
                            returnUrlInput.value = state.pathname + (state.search || '');
                            form.appendChild(returnUrlInput);
                            
                            // Show notification about session timeout
                            const alertDiv = document.createElement('div');
                            alertDiv.className = 'alert alert-info alert-dismissible fade show';
                            alertDiv.innerHTML = `
                                <i class="fas fa-info-circle"></i> 
                                Your session expired due to inactivity. Please login again to continue where you left off.
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            `;
                            form.parentElement.insertBefore(alertDiv, form);
                        }
                    } catch (e) {
                        console.error('Error parsing saved state:', e);
                    }
                }
            }
        });
    </script>
</body>
</html>