<?php
require_once 'auth.php';

// Handle login form submission
if ($_POST && isset($_POST['username']) && isset($_POST['password'])) {
    if (isset($_POST['csrf_token']) && CSRFToken::validate($_POST['csrf_token'])) {
        if (loginUser($_POST['username'], $_POST['password'])) {
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
            setMessage('Invalid username or password', 'error');
        }
    } else {
        setMessage('Invalid security token', 'error');
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
    <link rel="stylesheet" href="users/css/armis_custom.css">
    <style>
        :root {
            --primary: #355E3B;
            --yellow: #f1c40f;
        }
        body {
            background: linear-gradient(135deg, var(--primary) 0%, #4a7c59 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        .login-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            overflow: hidden;
        }
        .login-header {
            background: var(--primary);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        .login-form {
            padding: 2rem;
        }
        .btn-login {
            background: var(--primary);
            border: none;
            color: white;
            padding: 12px 30px;
            width: 100%;
            border-radius: 5px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        .btn-login:hover {
            background: #2d4d32;
            color: white;
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
        }
        .feature-item i {
            color: var(--primary);
            margin-right: 0.5rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-4">
                <div class="login-container">
                    <div class="login-header">
                        <h3 class="mb-0">ARMIS Login</h3>
                        <p class="mb-0 mt-2">Army Resource Management Information System</p>
                    </div>
                    <div class="login-form">
                        <?php displayMessages(); ?>
                        <form method="POST">
                            <?php echo CSRFToken::getField(); ?>
                            <div class="mb-3">
                                <label for="username" class="form-label">Username</label>
                                <input type="text" class="form-control" id="username" name="username" required>
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label">Password</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-login">Login</button>
                            </div>
                        </form>
                        <hr>
                        <p class="text-center text-muted small">Default credentials: admin / password</p>
                    </div>
                    <div class="login-features">
                        <h6 class="mb-3">System Features</h6>
                        <div class="feature-item">
                            <i class="fas fa-shield-alt"></i>
                            <small>Secure Authentication</small>
                        </div>
                        <div class="feature-item">
                            <i class="fas fa-users"></i>
                            <small>Staff Management</small>
                        </div>
                        <div class="feature-item">
                            <i class="fas fa-chart-bar"></i>
                            <small>Comprehensive Reports</small>
                        </div>
                        <div class="feature-item">
                            <i class="fas fa-cogs"></i>
                            <small>Admin Tools</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>