<?php
// FIX: config.php only defines constants/helper functions - it never
// calls session_start() or opens a database connection. Without
// session_start() here, $_SESSION is never loaded for this request (even
// though login.php started one and redirected here), so every
// isset($_SESSION[...]) check below would fail regardless of which keys
// they test - the user would bounce straight back to login.php no matter
// what. And without a real connection, $pdo->prepare() further down would
// fatal-error on null.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'config.php';
require_once __DIR__ . '/shared/database_connection.php';
$pdo = getDbConnection();

// Check if user is logged in and has temp password.
// FIX: login.php sets `temp_password_change_required` and
// `temp_password_user_id` when redirecting here (see the isFirstLogin
// branch in login.php) - this file was checking `temp_password` and
// `user_id` instead, which are never set at this point in the flow
// (user_id is only set AFTER a successful non-temp-password login).
// That meant this condition was always true and every first-time-login
// user got bounced straight back to login.php in an infinite loop,
// with no way to ever actually set their permanent password.
if (!isset($_SESSION['temp_password_change_required']) || $_SESSION['temp_password_change_required'] !== true || !isset($_SESSION['temp_password_user_id'])) {
    header('Location: login.php');
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($new_password) || empty($confirm_password)) {
        $error = 'Please enter and confirm your new password.';
    } elseif ($new_password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } elseif (strlen($new_password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } else {
        try {
            // FIX: `users` table doesn't exist anywhere in the schema -
            // staff records (including login credentials) live directly
            // on `staff`, keyed by `svcNo` (not `id`). `temp_password`
            // isn't a real column either - the real equivalent is
            // `isFirstLogin`.
            $hashedPassword = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE staff SET password = ?, isFirstLogin = 0, passwordChangedAt = NOW() WHERE svcNo = ?");
            $stmt->execute([$hashedPassword, $_SESSION['temp_password_user_id']]);
            
            // Clear temp password flags from session
            unset($_SESSION['temp_password_change_required']);
            unset($_SESSION['temp_password_user_id']);
            unset($_SESSION['temp_user_info']);
            
            $success = 'Password changed successfully. You can now use the system normally.';
            
            // Redirect after 2 seconds
            header("refresh:2;url=login.php");
        } catch (PDOException $e) {
            error_log('change_temp_password error: ' . $e->getMessage());
            $error = 'Database error occurred while updating your password. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password - ARMIS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card mt-5">
                    <div class="card-header">
                        <h4>Change Temporary Password</h4>
                    </div>
                    <div class="card-body">
                        <p class="text-info">You are using a temporary password. Please set a new password to continue.</p>
                        
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                        <?php else: ?>
                            <form method="POST">
                                <div class="mb-3">
                                    <label for="new_password" class="form-label">New Password</label>
                                    <input type="password" class="form-control" id="new_password" name="new_password" required>
                                    <small class="form-text text-muted">Minimum 6 characters</small>
                                </div>
                                <div class="mb-3">
                                    <label for="confirm_password" class="form-label">Confirm New Password</label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                </div>
                                <button type="submit" class="btn btn-primary">Change Password</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
