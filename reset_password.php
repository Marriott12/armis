<?php
require_once 'staff_auth_standalone.php';

// Require user to be logged in
requireLogin();

$user = getCurrentUser();

// Handle password reset form submission
if ($_POST && isset($_POST['new_password']) && isset($_POST['confirm_password'])) {
    if (isset($_POST['csrf_token']) && CSRFToken::validate($_POST['csrf_token'])) {
        $newPassword = $_POST['new_password'];
        $confirmPassword = $_POST['confirm_password'];
        
        // Validate passwords
        if (empty($newPassword) || empty($confirmPassword)) {
            setMessage('Please fill in all password fields.', 'error');
        } elseif ($newPassword !== $confirmPassword) {
            setMessage('Passwords do not match.', 'error');
        } elseif (strlen($newPassword) < 8) {
            setMessage('Password must be at least 8 characters long.', 'error');
        } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/', $newPassword)) {
            setMessage('Password must contain at least one uppercase letter, one lowercase letter, one number, and one special character.', 'error');
        } else {
            try {
                $db = getDatabase();
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                
                // Update password and clear must_reset_password flag
                $stmt = $db->prepare("UPDATE staff SET password = ?, must_reset_password = 0 WHERE svcNo = ?");
                $stmt->execute([$hashedPassword, $user['svcNo']]);
                
                // Update session
                $_SESSION['user']['must_reset_password'] = false;
                
                logActivity($user['svcNo'], 'password_reset', 'Password reset completed successfully');
                setMessage('Password reset successfully. You can now access your dashboard.', 'success');
                
                // Redirect to dashboard
                redirectToDashboard();
                
            } catch (Exception $e) {
                error_log("Password reset error: " . $e->getMessage());
                setMessage('An error occurred while resetting your password. Please try again.', 'error');
            }
        }
    } else {
        setMessage('Security token validation failed. Please try again.', 'error');
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ARMIS - Reset Password</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&family=Open+Sans:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #355E3B;
            --yellow: #f1c40f;
            --primary-dark: #2d4d32;
            --primary-light: #4a7c59;
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
        
        .reset-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1), 0 5px 15px rgba(0,0,0,0.07);
            overflow: hidden;
            max-width: 500px;
            width: 100%;
        }
        
        .reset-header {
            background: var(--primary);
            color: white;
            padding: 2rem;
            text-align: center;
            position: relative;
        }
        
        .reset-header h3 {
            margin: 0;
            font-weight: 600;
            font-size: 1.5rem;
        }
        
        .reset-header p {
            margin: 0.5rem 0 0 0;
            opacity: 0.9;
            font-size: 0.9rem;
        }
        
        .reset-form {
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
        
        .password-requirements {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1rem;
            margin: 1rem 0;
            font-size: 0.9rem;
        }
        
        .requirement {
            display: flex;
            align-items: center;
            margin-bottom: 0.25rem;
        }
        
        .requirement i {
            margin-right: 0.5rem;
            width: 16px;
        }
        
        .requirement.valid {
            color: #28a745;
        }
        
        .requirement.invalid {
            color: #dc3545;
        }
        
        .btn-reset {
            background: var(--primary);
            border: none;
            color: white;
            padding: 12px 30px;
            width: 100%;
            border-radius: 8px;
            font-weight: 500;
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        
        .btn-reset:hover:not(:disabled) {
            background: var(--primary-dark);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(53, 94, 59, 0.3);
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
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="reset-container">
                    <div class="reset-header">
                        <h3><i class="fas fa-key"></i> Reset Password</h3>
                        <p>Welcome <?php echo htmlspecialchars($user['fname'] . ' ' . $user['lname']); ?></p>
                        <p>You must reset your password before continuing</p>
                    </div>
                    <div class="reset-form">
                        <?php displayMessages(); ?>
                        
                        <form method="POST" id="resetForm" novalidate>
                            <?php echo CSRFToken::getField(); ?>
                            
                            <div class="mb-3">
                                <label for="new_password" class="form-label">
                                    New Password <span class="text-danger">*</span>
                                </label>
                                <input 
                                    type="password" 
                                    class="form-control" 
                                    id="new_password" 
                                    name="new_password" 
                                    required 
                                    autocomplete="new-password"
                                >
                            </div>
                            
                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">
                                    Confirm New Password <span class="text-danger">*</span>
                                </label>
                                <input 
                                    type="password" 
                                    class="form-control" 
                                    id="confirm_password" 
                                    name="confirm_password" 
                                    required 
                                    autocomplete="new-password"
                                >
                            </div>
                            
                            <div class="password-requirements">
                                <h6>Password Requirements:</h6>
                                <div class="requirement" id="req-length">
                                    <i class="fas fa-times"></i>
                                    At least 8 characters long
                                </div>
                                <div class="requirement" id="req-lowercase">
                                    <i class="fas fa-times"></i>
                                    Contains lowercase letter (a-z)
                                </div>
                                <div class="requirement" id="req-uppercase">
                                    <i class="fas fa-times"></i>
                                    Contains uppercase letter (A-Z)
                                </div>
                                <div class="requirement" id="req-number">
                                    <i class="fas fa-times"></i>
                                    Contains number (0-9)
                                </div>
                                <div class="requirement" id="req-special">
                                    <i class="fas fa-times"></i>
                                    Contains special character (@$!%*?&)
                                </div>
                                <div class="requirement" id="req-match">
                                    <i class="fas fa-times"></i>
                                    Passwords match
                                </div>
                            </div>
                            
                            <div class="d-grid mb-3">
                                <button 
                                    type="submit" 
                                    class="btn btn-reset" 
                                    id="submitBtn"
                                >
                                    <i class="fas fa-check"></i> Reset Password
                                </button>
                            </div>
                        </form>
                        
                        <div class="text-center">
                            <a href="logout.php" class="text-muted">
                                <i class="fas fa-sign-out-alt"></i> Logout
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const newPassword = document.getElementById('new_password');
            const confirmPassword = document.getElementById('confirm_password');
            const submitBtn = document.getElementById('submitBtn');
            
            function updateRequirement(id, valid) {
                const element = document.getElementById(id);
                const icon = element.querySelector('i');
                
                if (valid) {
                    element.classList.remove('invalid');
                    element.classList.add('valid');
                    icon.classList.remove('fa-times');
                    icon.classList.add('fa-check');
                } else {
                    element.classList.remove('valid');
                    element.classList.add('invalid');
                    icon.classList.remove('fa-check');
                    icon.classList.add('fa-times');
                }
            }
            
            function validatePassword() {
                const password = newPassword.value;
                const confirm = confirmPassword.value;
                
                // Check requirements
                updateRequirement('req-length', password.length >= 8);
                updateRequirement('req-lowercase', /[a-z]/.test(password));
                updateRequirement('req-uppercase', /[A-Z]/.test(password));
                updateRequirement('req-number', /\d/.test(password));
                updateRequirement('req-special', /[@$!%*?&]/.test(password));
                updateRequirement('req-match', password === confirm && password.length > 0);
                
                // Enable/disable submit button
                const allValid = password.length >= 8 &&
                    /[a-z]/.test(password) &&
                    /[A-Z]/.test(password) &&
                    /\d/.test(password) &&
                    /[@$!%*?&]/.test(password) &&
                    password === confirm;
                
                submitBtn.disabled = !allValid;
            }
            
            newPassword.addEventListener('input', validatePassword);
            confirmPassword.addEventListener('input', validatePassword);
            
            // Initial validation
            validatePassword();
        });
    </script>
</body>
</html>