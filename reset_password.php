<?php
/**
 * ARMIS Password Reset and First Login Handler
 */

session_start();
require_once 'shared/database_connection.php';
require_once 'shared/email_mailer.php';

// Handle password reset requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    if ($_POST['action'] === 'request_reset') {
        handlePasswordResetRequest();
    } elseif ($_POST['action'] === 'reset_password') {
        handlePasswordReset();
    } elseif ($_POST['action'] === 'change_temp_password') {
        handleTempPasswordChange();
    }
}

function handlePasswordResetRequest() {
    $email = trim($_POST['email']);
    $errors = [];
    
    if (empty($email)) {
        $errors[] = 'Email address is required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address';
    }
    
    if (empty($errors)) {
        try {
            $conn = getMysqliConnection();
            
            // Check if user exists
            $stmt = $conn->prepare("SELECT id, fname, lname, email FROM staff WHERE email = ? AND accStatus = 'active'");
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $user = $result->fetch_assoc();
                
                // Generate reset token
                $resetToken = bin2hex(random_bytes(32));
                $expiry = date('Y-m-d H:i:s', strtotime('+24 hours'));
                
                // Save reset token
                $updateStmt = $conn->prepare("UPDATE staff SET password_reset_token = ?, password_reset_expires = ? WHERE id = ?");
                $updateStmt->bind_param('ssi', $resetToken, $expiry, $user['id']);
                $updateStmt->execute();
                
                // Send reset email
                $mailer = new ARMISMailer();
                $emailResult = $mailer->sendPasswordResetEmail($user, $resetToken);
                
                if ($emailResult['success']) {
                    $_SESSION['success_message'] = 'Password reset instructions have been sent to your email address.';
                } else {
                    $_SESSION['error_message'] = 'Failed to send reset email. Please try again or contact support.';
                }
            } else {
                // Don't reveal if email exists or not for security
                $_SESSION['success_message'] = 'If an account with that email exists, password reset instructions have been sent.';
            }
            
        } catch (Exception $e) {
            error_log("Password reset error: " . $e->getMessage());
            $_SESSION['error_message'] = 'An error occurred. Please try again.';
        }
    } else {
        $_SESSION['error_message'] = implode('<br>', $errors);
    }
    
    header('Location: reset_password.php');
    exit;
}

function handlePasswordReset() {
    $token = trim($_POST['token']);
    $password = trim($_POST['password']);
    $confirmPassword = trim($_POST['confirm_password']);
    $errors = [];
    
    // Validation
    if (empty($token)) {
        $errors[] = 'Invalid reset token';
    }
    
    if (empty($password)) {
        $errors[] = 'Password is required';
    } elseif (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters long';
    } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/', $password)) {
        $errors[] = 'Password must contain at least one uppercase letter, one lowercase letter, one number, and one special character';
    }
    
    if ($password !== $confirmPassword) {
        $errors[] = 'Passwords do not match';
    }
    
    if (empty($errors)) {
        try {
            $conn = getMysqliConnection();
            
            // Verify token and check expiry
            $stmt = $conn->prepare("SELECT id, password_reset_expires FROM staff WHERE password_reset_token = ?");
            $stmt->bind_param('s', $token);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $user = $result->fetch_assoc();
                
                if (strtotime($user['password_reset_expires']) > time()) {
                    // Check password history (prevent reuse of last 3 passwords)
                    $historyStmt = $conn->prepare("SELECT password_hash FROM staff_password_history WHERE staff_id = ? ORDER BY created_at DESC LIMIT 3");
                    $historyStmt->bind_param('i', $user['id']);
                    $historyStmt->execute();
                    $historyResult = $historyStmt->get_result();
                    
                    $passwordReused = false;
                    while ($row = $historyResult->fetch_assoc()) {
                        if (password_verify($password, $row['password_hash'])) {
                            $passwordReused = true;
                            break;
                        }
                    }
                    
                    if ($passwordReused) {
                        $_SESSION['error_message'] = 'You cannot reuse one of your last 3 passwords.';
                    } else {
                        // Update password
                        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                        $now = date('Y-m-d H:i:s');
                        
                        $updateStmt = $conn->prepare("UPDATE staff SET password = ?, password_reset_token = NULL, password_reset_expires = NULL, temp_password = 0, force_password_change = 0, last_password_change = ? WHERE id = ?");
                        $updateStmt->bind_param('ssi', $hashedPassword, $now, $user['id']);
                        $updateStmt->execute();
                        
                        // Add to password history
                        $historyInsertStmt = $conn->prepare("INSERT INTO staff_password_history (staff_id, password_hash) VALUES (?, ?)");
                        $historyInsertStmt->bind_param('is', $user['id'], $hashedPassword);
                        $historyInsertStmt->execute();
                        
                        $_SESSION['success_message'] = 'Your password has been successfully updated. You can now log in with your new password.';
                        header('Location: login.php');
                        exit;
                    }
                } else {
                    $_SESSION['error_message'] = 'Password reset token has expired. Please request a new one.';
                }
            } else {
                $_SESSION['error_message'] = 'Invalid reset token.';
            }
            
        } catch (Exception $e) {
            error_log("Password reset error: " . $e->getMessage());
            $_SESSION['error_message'] = 'An error occurred. Please try again.';
        }
    } else {
        $_SESSION['error_message'] = implode('<br>', $errors);
    }
    
    header('Location: reset_password.php?token=' . urlencode($token));
    exit;
}

function handleTempPasswordChange() {
    if (!isset($_SESSION['temp_password_change_required'])) {
        header('Location: login.php');
        exit;
    }
    
    $userId = $_SESSION['temp_password_user_id'];
    $currentPassword = trim($_POST['current_password']);
    $newPassword = trim($_POST['new_password']);
    $confirmPassword = trim($_POST['confirm_password']);
    $errors = [];
    
    // Validation
    if (empty($currentPassword)) {
        $errors[] = 'Current password is required';
    }
    
    if (empty($newPassword)) {
        $errors[] = 'New password is required';
    } elseif (strlen($newPassword) < 8) {
        $errors[] = 'Password must be at least 8 characters long';
    } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/', $newPassword)) {
        $errors[] = 'Password must contain at least one uppercase letter, one lowercase letter, one number, and one special character';
    }
    
    if ($newPassword !== $confirmPassword) {
        $errors[] = 'Passwords do not match';
    }
    
    if (empty($errors)) {
        try {
            $conn = getMysqliConnection();
            
            // Verify current password
            $stmt = $conn->prepare("SELECT password FROM staff WHERE id = ?");
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $user = $result->fetch_assoc();
                
                if (password_verify($currentPassword, $user['password'])) {
                    // Update password
                    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                    $now = date('Y-m-d H:i:s');
                    
                    $updateStmt = $conn->prepare("UPDATE staff SET password = ?, temp_password = 0, force_password_change = 0, last_password_change = ?, account_activated = 1 WHERE id = ?");
                    $updateStmt->bind_param('ssi', $hashedPassword, $now, $userId);
                    $updateStmt->execute();
                    
                    // Add to password history
                    $historyStmt = $conn->prepare("INSERT INTO staff_password_history (staff_id, password_hash) VALUES (?, ?)");
                    $historyStmt->bind_param('is', $userId, $hashedPassword);
                    $historyStmt->execute();
                    
                    // Clear temp password session
                    unset($_SESSION['temp_password_change_required']);
                    unset($_SESSION['temp_password_user_id']);
                    
                    $_SESSION['success_message'] = 'Your password has been successfully changed. Welcome to ARMIS!';
                    header('Location: index.php'); // Redirect to dashboard
                    exit;
                    
                } else {
                    $_SESSION['error_message'] = 'Current password is incorrect.';
                }
            } else {
                $_SESSION['error_message'] = 'User not found.';
            }
            
        } catch (Exception $e) {
            error_log("Password change error: " . $e->getMessage());
            $_SESSION['error_message'] = 'An error occurred. Please try again.';
        }
    } else {
        $_SESSION['error_message'] = implode('<br>', $errors);
    }
    
    header('Location: change_temp_password.php');
    exit;
}

// Display reset password form
$token = $_GET['token'] ?? '';
$pageTitle = 'Reset Password - ARMIS';
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card mt-5">
                    <div class="card-header">
                        <h4><i class="fas fa-key"></i> Reset Password</h4>
                    </div>
                    <div class="card-body">
                        
                        <?php if (isset($_SESSION['success_message'])): ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle"></i> <?= $_SESSION['success_message'] ?>
                            </div>
                            <?php unset($_SESSION['success_message']); ?>
                        <?php endif; ?>
                        
                        <?php if (isset($_SESSION['error_message'])): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-triangle"></i> <?= $_SESSION['error_message'] ?>
                            </div>
                            <?php unset($_SESSION['error_message']); ?>
                        <?php endif; ?>
                        
                        <?php if (empty($token)): ?>
                            <!-- Request Reset Form -->
                            <form method="post">
                                <input type="hidden" name="action" value="request_reset">
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email Address</label>
                                    <input type="email" class="form-control" id="email" name="email" required>
                                </div>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-paper-plane"></i> Send Reset Link
                                </button>
                                <a href="login.php" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left"></i> Back to Login
                                </a>
                            </form>
                        <?php else: ?>
                            <!-- Reset Password Form -->
                            <form method="post">
                                <input type="hidden" name="action" value="reset_password">
                                <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                                
                                <div class="mb-3">
                                    <label for="password" class="form-label">New Password</label>
                                    <input type="password" class="form-control" id="password" name="password" required minlength="8">
                                    <div class="form-text">Password must be at least 8 characters and contain uppercase, lowercase, number, and special character.</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="confirm_password" class="form-label">Confirm New Password</label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-check"></i> Update Password
                                </button>
                                <a href="login.php" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left"></i> Back to Login
                                </a>
                            </form>
                        <?php endif; ?>
                        
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
