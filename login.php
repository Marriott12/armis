<?php
require_once 'staff_auth.php';

// Start session with secure settings
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_samesite', 'Strict');

// Handle login form submission
if ($_POST && isset($_POST['username']) && isset($_POST['password'])) {
    if (isset($_POST['csrf_token']) && CSRFToken::validate($_POST['csrf_token'])) {
        if (loginUser($_POST['username'], $_POST['password'])) {
            // Regenerate session ID for security
            session_regenerate_id(true);
            
            // Handle "Remember me" functionality
            if (isset($_POST['remember_me']) && $_POST['remember_me'] === '1') {
                // Set remember me cookie (30 days)
                $remember_token = bin2hex(random_bytes(32));
                setcookie('remember_token', $remember_token, time() + (30 * 24 * 60 * 60), '/', '', true, true);
                $_SESSION['remember_token'] = $remember_token;
            }
            
            // Smart redirection based on user role and redirect parameter
            $redirect_url = isset($_GET['redirect']) ? $_GET['redirect'] : null;
            
            if ($redirect_url && filter_var($redirect_url, FILTER_VALIDATE_URL) === false) {
                // Validate redirect URL is internal
                if (strpos($redirect_url, '/') === 0) {
                    redirect($redirect_url);
                }
            }
            
            // Default redirection based on user permissions
            redirectToDashboard();
        } else {
            setMessage('Login failed. Please check your credentials and try again.', 'error');
        }
    } else {
        setMessage('Security token validation failed. Please try again.', 'error');
    }
}

// If already logged in, redirect to appropriate dashboard
if (isLoggedIn()) {
    redirectToDashboard();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ARMIS - Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&family=Open+Sans:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="users/css/armis_custom.css">
    <style>
        :root {
            --primary: #355E3B;
            --yellow: #f1c40f;
            --primary-dark: #2d4d32;
            --primary-light: #4a7c59;
        }
        
        * {
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Roboto', 'Open Sans', Arial, sans-serif;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            padding: 20px;
            margin: 0;
        }
        
        .login-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1), 0 5px 15px rgba(0,0,0,0.07);
            overflow: hidden;
            max-width: 400px;
            width: 100%;
        }
        
        .login-header {
            background: var(--primary);
            color: white;
            padding: 2rem;
            text-align: center;
            position: relative;
        }
        
        .logo {
            max-width: 150px;
            height: auto;
            margin-bottom: 1rem;
            border-radius: 8px;
        }
        
        .login-header h3 {
            margin: 0;
            font-weight: 600;
            font-size: 1.5rem;
        }
        
        .login-header p {
            margin: 0.5rem 0 0 0;
            opacity: 0.9;
            font-size: 0.9rem;
        }
        
        .login-form {
            padding: 2rem;
        }
        
        .form-label {
            font-weight: 500;
            color: #333;
            margin-bottom: 0.5rem;
        }
        
        .form-control {
            border: 2px solid #e9ecef;
            border-radius: 8px;
            padding: 0.75rem 1rem;
            font-size: 0.95rem;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.2rem rgba(53, 94, 59, 0.25);
        }
        
        .password-toggle {
            position: relative;
        }
        
        .password-toggle-btn {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #6c757d;
            cursor: pointer;
            padding: 0;
            font-size: 1rem;
            z-index: 10;
        }
        
        .password-toggle-btn:hover {
            color: var(--primary);
        }
        
        .form-check {
            margin: 1rem 0;
        }
        
        .form-check-input:checked {
            background-color: var(--primary);
            border-color: var(--primary);
        }
        
        .form-check-input:focus {
            box-shadow: 0 0 0 0.2rem rgba(53, 94, 59, 0.25);
        }
        
        .btn-login {
            background: var(--primary);
            border: none;
            color: white;
            padding: 12px 30px;
            width: 100%;
            border-radius: 8px;
            font-weight: 500;
            font-size: 1rem;
            transition: all 0.3s ease;
            position: relative;
        }
        
        .btn-login:hover:not(:disabled) {
            background: var(--primary-dark);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(53, 94, 59, 0.3);
        }
        
        .btn-login:disabled {
            background: #6c757d;
            cursor: not-allowed;
            transform: none;
        }
        
        .btn-login .spinner-border {
            width: 1rem;
            height: 1rem;
            margin-right: 0.5rem;
        }
        
        .forgot-password {
            text-align: center;
            margin-top: 1rem;
        }
        
        .forgot-password a {
            color: var(--primary);
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
        }
        
        .forgot-password a:hover {
            color: var(--primary-dark);
            text-decoration: underline;
        }
        
        .login-features {
            background: #f8f9fa;
            padding: 1.5rem;
            border-top: 1px solid #dee2e6;
        }
        
        .feature-item {
            display: flex;
            align-items: center;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }
        
        .feature-item i {
            color: var(--primary);
            margin-right: 0.5rem;
            width: 16px;
        }
        
        .alert {
            border: none;
            border-radius: 8px;
            font-size: 0.9rem;
        }
        
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .alert-success {
            background-color: #d1e7dd;
            color: #0f5132;
        }
        
        /* Accessibility improvements */
        .sr-only {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
            border: 0;
        }
        
        /* High contrast mode support */
        @media (prefers-contrast: high) {
            .form-control {
                border-width: 3px;
            }
            
            .btn-login {
                border: 2px solid var(--primary-dark);
            }
        }
        
        /* Dark mode support */
        @media (prefers-color-scheme: dark) {
            .login-container {
                background: #1a1a1a;
                color: white;
            }
            
            .form-control {
                background: #2a2a2a;
                border-color: #404040;
                color: white;
            }
            
            .form-control:focus {
                background: #2a2a2a;
                border-color: var(--primary);
                color: white;
            }
            
            .form-label {
                color: #e9ecef;
            }
        }
        
        /* Mobile responsiveness */
        @media (max-width: 576px) {
            .login-container {
                margin: 10px;
            }
            
            .login-header {
                padding: 1.5rem;
            }
            
            .login-form {
                padding: 1.5rem;
            }
            
            .logo {
                max-width: 120px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-4">
                <div class="login-container">
                    <div class="login-header">
                        <img src="logo.png" alt="ARMIS Logo" class="logo">
                        <h3>ARMIS Login</h3>
                        <p>Army Resource Management Information System</p>
                    </div>
                    <div class="login-form">
                        <?php displayMessages(); ?>
                        
                        <form method="POST" id="loginForm" novalidate>
                            <?php echo CSRFToken::getField(); ?>
                            
                            <div class="mb-3">
                                <label for="username" class="form-label">
                                    Username <span class="text-danger" aria-label="required">*</span>
                                </label>
                                <input 
                                    type="text" 
                                    class="form-control" 
                                    id="username" 
                                    name="username" 
                                    required 
                                    autocomplete="username"
                                    aria-describedby="username-help"
                                    value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>"
                                >
                                <div id="username-help" class="sr-only">Enter your username</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="password" class="form-label">
                                    Password <span class="text-danger" aria-label="required">*</span>
                                </label>
                                <div class="password-toggle">
                                    <input 
                                        type="password" 
                                        class="form-control" 
                                        id="password" 
                                        name="password" 
                                        required 
                                        autocomplete="current-password"
                                        aria-describedby="password-help"
                                    >
                                    <button 
                                        type="button" 
                                        class="password-toggle-btn" 
                                        id="togglePassword"
                                        aria-label="Toggle password visibility"
                                        tabindex="0"
                                    >
                                        <i class="fas fa-eye" id="togglePasswordIcon"></i>
                                    </button>
                                </div>
                                <div id="password-help" class="sr-only">Enter your password</div>
                            </div>
                            
                            <div class="form-check mb-3">
                                <input 
                                    class="form-check-input" 
                                    type="checkbox" 
                                    value="1" 
                                    id="remember_me" 
                                    name="remember_me"
                                    <?php echo isset($_POST['remember_me']) ? 'checked' : ''; ?>
                                >
                                <label class="form-check-label" for="remember_me">
                                    Remember me for 30 days
                                </label>
                            </div>
                            
                            <div class="d-grid mb-3">
                                <button 
                                    type="submit" 
                                    class="btn btn-login" 
                                    id="submitBtn"
                                    aria-describedby="submit-help"
                                >
                                    <span class="btn-text">
                                        <i class="fas fa-sign-in-alt"></i> Login
                                    </span>
                                    <span class="btn-loading d-none">
                                        <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                                        Signing in...
                                    </span>
                                </button>
                                <div id="submit-help" class="sr-only">Click to submit login form</div>
                            </div>
                            
                            <div class="forgot-password">
                                <a href="users/forgot_password.php" tabindex="0">
                                    <i class="fas fa-key"></i> Forgot your password?
                                </a>
                            </div>
                        </form>
                    </div>
                    
                    <div class="login-features">
                        <h6 class="mb-3">System Features</h6>
                        <div class="feature-item">
                            <i class="fas fa-shield-alt" aria-hidden="true"></i>
                            <small>Secure Authentication</small>
                        </div>
                        <div class="feature-item">
                            <i class="fas fa-users" aria-hidden="true"></i>
                            <small>Staff Management</small>
                        </div>
                        <div class="feature-item">
                            <i class="fas fa-chart-bar" aria-hidden="true"></i>
                            <small>Comprehensive Reports</small>
                        </div>
                        <div class="feature-item">
                            <i class="fas fa-cogs" aria-hidden="true"></i>
                            <small>Admin Tools</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Password toggle functionality
            const togglePassword = document.getElementById('togglePassword');
            const passwordField = document.getElementById('password');
            const toggleIcon = document.getElementById('togglePasswordIcon');
            
            togglePassword.addEventListener('click', function() {
                const type = passwordField.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordField.setAttribute('type', type);
                
                if (type === 'password') {
                    toggleIcon.classList.remove('fa-eye-slash');
                    toggleIcon.classList.add('fa-eye');
                    togglePassword.setAttribute('aria-label', 'Show password');
                } else {
                    toggleIcon.classList.remove('fa-eye');
                    toggleIcon.classList.add('fa-eye-slash');
                    togglePassword.setAttribute('aria-label', 'Hide password');
                }
            });
            
            // Form submission handling
            const loginForm = document.getElementById('loginForm');
            const submitBtn = document.getElementById('submitBtn');
            const btnText = submitBtn.querySelector('.btn-text');
            const btnLoading = submitBtn.querySelector('.btn-loading');
            
            loginForm.addEventListener('submit', function(e) {
                // Disable button to prevent double submissions
                submitBtn.disabled = true;
                btnText.classList.add('d-none');
                btnLoading.classList.remove('d-none');
                
                // Basic client-side validation
                const username = document.getElementById('username').value.trim();
                const password = document.getElementById('password').value;
                
                if (!username || !password) {
                    e.preventDefault();
                    alert('Please enter both username and password.');
                    
                    // Re-enable button
                    submitBtn.disabled = false;
                    btnText.classList.remove('d-none');
                    btnLoading.classList.add('d-none');
                    return;
                }
                
                // Re-enable button after 5 seconds as fallback
                setTimeout(function() {
                    submitBtn.disabled = false;
                    btnText.classList.remove('d-none');
                    btnLoading.classList.add('d-none');
                }, 5000);
            });
            
            // Focus management
            document.getElementById('username').focus();
            
            // Keyboard navigation improvements
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' && e.target.tagName === 'BUTTON') {
                    e.target.click();
                }
            });
            
            // Session timeout warning (25 minutes)
            setTimeout(function() {
                if (confirm('Your session will expire in 5 minutes. Click OK to stay logged in.')) {
                    // Make a request to keep session alive
                    fetch(window.location.href, {
                        method: 'HEAD',
                        credentials: 'same-origin'
                    });
                }
            }, 25 * 60 * 1000); // 25 minutes
        });
    </script>
</body>
</html>