<?php
session_start();

// Include database functions
require_once __DIR__ . '/shared/database_connection.php';

// Debug: Log form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log("Login form submitted with data: " . print_r($_POST, true));
}

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (!empty($username) && !empty($password)) {
        // Try database authentication first
        $user = authenticateUser($username, $password);
        
        if ($user) {
            // Check if user needs to change temporary password
            if ($user['temp_password'] == 1 || $user['force_password_change'] == 1) {
                // Store user info for password change
                $_SESSION['temp_password_change_required'] = true;
                $_SESSION['temp_password_user_id'] = $user['id'];
                $_SESSION['temp_user_info'] = [
                    'username' => $user['username'],
                    'name' => trim($user['first_name'] . ' ' . $user['last_name']),
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
            $_SESSION['rank'] = $user['rank_name'] ?? 'Unknown';
            $_SESSION['name'] = trim($user['first_name'] . ' ' . $user['last_name']);
            $_SESSION['unit'] = $user['unit_name'] ?? 'Unknown';
            $_SESSION['corps'] = $user['corps_name'] ?? $user['corps'] ?? 'Unknown';
            $_SESSION['service_number'] = $user['service_number'];
            $_SESSION['first_name'] = $user['first_name'];
            $_SESSION['last_name'] = $user['last_name'];
            $_SESSION['email'] = $user['email'];
            
            // Redirect based on role
            switch ($user['role']) {
                case 'admin':
                    header('Location: /Armis2/admin/index.php');
                    break;
                case 'admin_branch':
                    header('Location: /Armis2/admin_branch/index.php');
                    break;
                case 'command':
                    header('Location: /Armis2/command/index.php');
                    break;
                case 'training':
                    header('Location: /Armis2/training/index.php');
                    break;
                case 'operations':
                    header('Location: /Armis2/operations/index.php');
                    break;
                case 'finance':
                    header('Location: /Armis2/finance/index.php');
                    break;
                case 'ordinance':
                    header('Location: /Armis2/ordinance/index.php');
                    break;
                default:
                    header('Location: /Armis2/users/index.php');
                    break;
            }
            exit();
        } else {
            // Fallback to hardcoded credentials for demo
            if (($username === 'admin' || $username === 'Admin') && $password === 'armis2025') {
                $_SESSION['user_id'] = 1;
                $_SESSION['username'] = 'admin';
                $_SESSION['role'] = 'admin'; // Changed to 'admin' for System Administrator
                $_SESSION['rank'] = 'Colonel';
                $_SESSION['name'] = 'System Administrator';
                $_SESSION['unit'] = 'HQ Command';
                
                // Redirect to System Admin dashboard for 'admin' role
                header('Location: /Armis2/admin/index.php');
                exit();
            } else {
                $error = 'Invalid username or password';
            }
        }
    } else {
        $error = 'Please enter both username and password';
    }
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
                    <small class="d-block mt-2" style="opacity: 0.8;">Strength • Discipline • Excellence</small>
                </div>
                
                <div class="login-form-container">
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger" role="alert">
                            <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="/Armis2/login.php" class="needs-validation" novalidate>
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
                    
                    <!-- Enhanced Demo Credentials -->
                    <div class="demo-credentials">
                        <h6><i class="fas fa-key"></i> Demo Access Credentials</h6>
                        
                        <div class="credential-group">
                            <strong class="text-danger">System Administrator:</strong><br>
                            <code>admin</code> / <code>armis2025</code>
                            <small class="d-block text-muted">Full system access & control</small>
                        </div>
                        
                        <div class="credential-group">
                            <strong class="text-warning">Command Officers:</strong><br>
                            <code>commander</code> / <code>commander123</code><br>
                            <code>trainer</code> / <code>trainer123</code>
                            <small class="d-block text-muted">Module-specific access</small>
                        </div>
                        
                        <div class="credential-group">
                            <strong class="text-info">Staff Members:</strong><br>
                            <code>staff1</code> / <code>staff123</code><br>
                            <code>staff2</code> / <code>staff456</code>
                            <small class="d-block text-muted">Limited access permissions</small>
                        </div>
                        
                        <div class="text-center mt-3">
                            <small class="text-muted">
                                <i class="fas fa-info-circle"></i> Role-based access control ensures secure system operation
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
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
        });
    </script>
</body>
</html>
                            </label>
                            <input type="password" class="form-control" id="password" name="password" required 
                                   placeholder="Enter your password">
                        </div>
                        
                        <button type="submit" class="btn btn-login">
                            <i class="fas fa-sign-in-alt"></i> Login to ARMIS
                        </button>
                    </form>
                    
                    <hr class="my-4">
                    
                    <div class="text-center">
                        <a href="/Armis2/" class="btn btn-outline-secondary">
                            <i class="fas fa-home"></i> Back to Home
                        </a>
                    </div>
                    
                    <!-- Demo Credentials -->
                    <div class="mt-4 p-3 bg-light rounded">
                        <h6 class="mb-2"><i class="fas fa-info-circle"></i> Demo Credentials:</h6>
                        <div class="row">
                            <div class="col-12">
                                <small class="text-muted">
                                    <strong>Administrator:</strong><br>
                                    • admin / armis2025<br><br>
                                    
                                    <strong>Officers:</strong><br>
                                    • commander / commander123<br>
                                    • trainer / trainer123<br><br>
                                    
                                    <strong>Staff:</strong><br>
                                    • staff1 / staff123<br>
                                    • staff2 / staff456
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>