<?php
/**
 * ARMIS Password Reset and First Login Handler
 */

require_once __DIR__ . '/shared/session_security.php';
armisStartSecureSession();
require_once __DIR__ . '/shared/csrf.php';
require_once 'shared/database_connection.php';
require_once 'shared/email_mailer.php';
require_once __DIR__ . '/shared/password_policy.php';

// Handle password reset requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    require_csrf();
    
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
            $pdo = getDbConnection();
            // Check if user exists.
            // FIX: `staff` has no `email` column - the real columns are
            // `officialEmail` and `emailPvt`. Matches either, since a
            // person requesting a reset may only remember whichever one
            // they registered with (same fallback pattern used by
            // UserProfileManager elsewhere in this app).
            // FIX: `getMysqliConnection()` did not exist anywhere in this
            // codebase - only `getDbConnection()` (PDO) does, which every
            // other file in the app uses. That undefined-function call
            // was a fatal error hit on every single reset attempt, before
            // the request ever reached the (also broken) SQL below.
            $stmt = $pdo->prepare("SELECT * FROM staff WHERE (officialEmail = ? OR emailPvt = ?) AND accStatus = 'active'");
            $stmt->execute([$email, $email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($user) {
                // Generate reset token
                $resetToken = bin2hex(random_bytes(32));
                $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
                // Create staff_password_resets table if not exists.
                // FIX: svcNo is VARCHAR(10) on `staff` (values like '007414'
                // have meaningful leading zeros) - declaring this column as
                // INT would silently mangle/truncate those values and break
                // every lookup that joins back against the real svcNo.
                $pdo->exec('CREATE TABLE IF NOT EXISTS staff_password_resets (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    svcNo VARCHAR(10) NOT NULL,
                    reset_token VARCHAR(128) NOT NULL,
                    expires_at DATETIME NOT NULL,
                    used TINYINT(1) DEFAULT 0,
                    createdAt DATETIME DEFAULT CURRENT_TIMESTAMP
                )');
                // Insert token.
                // FIX: staff has no `id` column - its primary key is `svcNo`.
                $stmt2 = $pdo->prepare('INSERT INTO staff_password_resets (svcNo, reset_token, expires_at) VALUES (?, ?, ?)');
                $stmt2->execute([$user['svcNo'], $resetToken, $expiry]);
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
    if (empty($token)) $errors[] = 'Invalid reset token';
    foreach (armisPasswordValidationErrors($password, $confirmPassword) as $policyError) $errors[] = $policyError;
    if (empty($errors)) {
        try {
            $pdo = getDbConnection();
            // Find reset token in staff_password_resets
            $stmt = $pdo->prepare('SELECT * FROM staff_password_resets WHERE reset_token = ? AND used = 0 LIMIT 1');
            $stmt->execute([$token]);
            $resetRow = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($resetRow) {
                if (strtotime($resetRow['expires_at']) > time()) {
                    $staffSvcNo = $resetRow['svcNo'];
                    // Prevent reuse of the last five passwords and the current password.
                    $currentStmt = $pdo->prepare('SELECT password FROM staff WHERE svcNo = ? LIMIT 1');
                    $currentStmt->execute([$staffSvcNo]);
                    $current = $currentStmt->fetch(PDO::FETCH_ASSOC);
                    if ($current && password_verify($password, (string)$current['password'])) {
                        $_SESSION['error_message'] = 'You cannot reuse your current password. Please choose a new password.';
                    } elseif (armisPasswordWasUsedBefore($pdo, $staffSvcNo, $password)) {
                        $_SESSION['error_message'] = 'Password reuse detected. Please choose a password you have not used recently. ARMIS protects the last 5 passwords.';
                    } else {
                        // Update password.
                        // FIX: `staff` has no `id`, `temp_password`,
                        // `force_password_change`, or `last_password_change`
                        // columns - the real, equivalent columns are
                        // `svcNo`, `isFirstLogin`, and `passwordChangedAt`.
                        // This UPDATE previously referenced four columns
                        // that don't exist, meaning password reset could
                        // never actually complete even after everything
                        // above it was fixed.
                        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                        if ($hashedPassword === false) throw new RuntimeException('Unable to securely hash the new password.');
                        $now = date('Y-m-d H:i:s');
                        // Ensure password-history table exists before starting the transaction.
                        armisEnsurePasswordHistoryTable($pdo);
                        $pdo->beginTransaction();
                        try {
                            armisArchiveCurrentPassword($pdo, $staffSvcNo, $current['password'] ?? null, null, 'self_service_password_reset');
                            $updateStmt = $pdo->prepare('UPDATE staff SET password = ?, isFirstLogin = 0, passwordChangedAt = ? WHERE svcNo = ?');
                            $updateStmt->execute([$hashedPassword, $now, $staffSvcNo]);
                            $pdo->commit();
                        } catch (Throwable $inner) {
                            if ($pdo->inTransaction()) $pdo->rollBack();
                            throw $inner;
                        }
                        // Mark token as used
                        $markUsedStmt = $pdo->prepare('UPDATE staff_password_resets SET used = 1 WHERE id = ?');
                        $markUsedStmt->execute([$resetRow['id']]);
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
            error_log('Password reset error: ' . $e->getMessage());
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
    
    // FIX: this is the person's svcNo (staff has no `id` column), kept as
    // the variable name the rest of this function already used to avoid
    // touching the session-key contract with login.php.
    $userSvcNo = $_SESSION['temp_password_user_id'];
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
            $pdo = getDbConnection();
            
            // Verify current password. FIX: WHERE id -> WHERE svcNo.
            $stmt = $pdo->prepare("SELECT password FROM staff WHERE svcNo = ?");
            $stmt->execute([$userSvcNo]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user) {
                if (password_verify($currentPassword, $user['password'])) {
                    // Update password.
                    // FIX: same column-name corrections as handlePasswordReset()
                    // above - `id`/`temp_password`/`force_password_change`/
                    // `last_password_change`/`account_activated` don't exist;
                    // the real columns are `svcNo`/`isFirstLogin`/
                    // `passwordChangedAt`. `accStatus` (a real column) is set
                    // to 'active' as the closest real equivalent of
                    // "account_activated = 1".
                    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                    $now = date('Y-m-d H:i:s');
                    
                    $updateStmt = $pdo->prepare("UPDATE staff SET password = ?, isFirstLogin = 0, passwordChangedAt = ?, accStatus = 'active' WHERE svcNo = ?");
                    $updateStmt->execute([$hashedPassword, $now, $userSvcNo]);
                    
                    // Add to password history
                    $pdo->exec('CREATE TABLE IF NOT EXISTS staff_password_history (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        svcNo VARCHAR(10) NOT NULL,
                        password_hash VARCHAR(255) NOT NULL,
                        createdAt DATETIME DEFAULT CURRENT_TIMESTAMP
                    )');
                    $historyStmt = $pdo->prepare("INSERT INTO staff_password_history (svcNo, password_hash) VALUES (?, ?)");
                    $historyStmt->execute([$userSvcNo, $hashedPassword]);
                    
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
<style>
body{background:#f5f7fa}.reset-card{border:0;border-radius:12px;box-shadow:0 4px 12px rgba(0,0,0,.08);overflow:hidden}.reset-card .card-header{background:linear-gradient(135deg,#f8f9fa,#e9ecef);color:#2c3e50;border-bottom:1px solid #dee2e6}.strength-bar{height:7px;border-radius:99px;background:#e9ecef;overflow:hidden}.strength-fill{height:100%;width:0;transition:width .2s}.req{font-size:.84rem}
</style></head>
<body class="bg-light">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card mt-5 reset-card">
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
<?= csrf_field() ?>
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
<?= csrf_field() ?>
                                <input type="hidden" name="action" value="reset_password">
                                <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                                
                                <div class="mb-3">
                                    <label for="password" class="form-label">New Password</label>
                                    <div class="input-group"><input type="password" class="form-control" id="password" name="password" required minlength="12" autocomplete="new-password"><button type="button" class="btn btn-outline-secondary" onclick="toggleRP()"><i class="fas fa-eye" id="rpEye"></i></button></div>
                                    <div class="d-flex justify-content-between mt-2"><small class="text-muted">Minimum 12 characters</small><small id="strengthLabel" class="fw-semibold text-muted">Not set</small></div><div class="strength-bar mt-1"><div class="strength-fill" id="strengthFill"></div></div>
                                    <div class="row g-2 mt-2"><div class="col-sm-6 req" data-req="length" data-label="At least 12 characters">○ At least 12 characters</div><div class="col-sm-6 req" data-req="lower" data-label="Lowercase letter">○ Lowercase letter</div><div class="col-sm-6 req" data-req="upper" data-label="Uppercase letter">○ Uppercase letter</div><div class="col-sm-6 req" data-req="number" data-label="Number">○ Number</div><div class="col-sm-6 req" data-req="special" data-label="Special character">○ Special character</div><div class="col-sm-6 req text-success">✓ Last 5 passwords protected</div></div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="confirm_password" class="form-label">Confirm New Password</label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required autocomplete="new-password">
                                    <div id="match" class="small mt-1"></div>
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
    
    <!-- Core JS (jQuery/Bootstrap) are loaded centrally in shared/footer.php. -->
<script>
const rp=document.getElementById('password'), rc=document.getElementById('confirm_password');
function toggleRP(){rp.type=rp.type==='password'?'text':'password';document.getElementById('rpEye').className=rp.type==='password'?'fas fa-eye':'fas fa-eye-slash';}
function rpUpdate(){if(!rp)return;const v=rp.value,r={length:v.length>=12,lower:/[a-z]/.test(v),upper:/[A-Z]/.test(v),number:/\d/.test(v),special:/[^A-Za-z0-9]/.test(v)};Object.keys(r).forEach(k=>{const e=document.querySelector('[data-req="'+k+'"]');if(e)e.innerHTML=(r[k]?'✓':'○')+' '+e.dataset.label;});let n=Object.values(r).filter(Boolean).length;if(v.length>=16)n=Math.min(5,n+1);document.getElementById('strengthFill').style.width=([0,20,40,60,80,100][n])+'%';document.getElementById('strengthLabel').textContent=['Not set','Weak','Fair','Good','Strong','Very strong'][n];}
rp&&rp.addEventListener('input',rpUpdate);rc&&rc.addEventListener('input',()=>{const e=document.getElementById('match');if(e)e.textContent=rc.value?(rp.value===rc.value?'Passwords match.':'Passwords do not match.'):'';});
</script></body>
</html>
