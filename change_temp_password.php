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
require_once __DIR__ . '/shared/password_policy.php';
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
    } else {
        $policyErrors = armisPasswordValidationErrors($new_password, $confirm_password);
        if ($policyErrors) { $error = implode(' ', $policyErrors); }
    }
    if (!$error) {
        try {
            // FIX: `users` table doesn't exist anywhere in the schema -
            // staff records (including login credentials) live directly
            // on `staff`, keyed by `svcNo` (not `id`). `temp_password`
            // isn't a real column either - the real equivalent is
            // `isFirstLogin`.
            $svcNo = (string)$_SESSION['temp_password_user_id'];
            $currentStmt = $pdo->prepare('SELECT password FROM staff WHERE svcNo = ? LIMIT 1');
            $currentStmt->execute([$svcNo]);
            $current = $currentStmt->fetch(PDO::FETCH_ASSOC);
            if (!$current) throw new RuntimeException('User account could not be found.');
            if (password_verify($new_password, (string)$current['password'])) {
                throw new RuntimeException('Your new password cannot be the same as your temporary password.');
            }
            if (armisPasswordWasUsedBefore($pdo, $svcNo, $new_password)) {
                throw new RuntimeException('Password reuse detected. Please choose a password you have not used recently. ARMIS protects the last 5 passwords.');
            }
            $hashedPassword = password_hash($new_password, PASSWORD_DEFAULT);
            if ($hashedPassword === false) throw new RuntimeException('Unable to securely hash the new password.');
            // Ensure password-history table exists before starting the transaction.
            armisEnsurePasswordHistoryTable($pdo);
            $pdo->beginTransaction();
            try {
                armisArchiveCurrentPassword($pdo, $svcNo, $current['password'], $svcNo, 'first_login_password_change');
                $stmt = $pdo->prepare("UPDATE staff SET password = ?, isFirstLogin = 0, passwordChangedAt = NOW() WHERE svcNo = ?");
                $stmt->execute([$hashedPassword, $svcNo]);
                $pdo->commit();
            } catch (Throwable $inner) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $inner;
            }
            
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
<?php
$pageTitle = 'Change Temporary Password';
include __DIR__ . '/shared/header.php';
?>

<style>
    /* Temporary-password page: same visual language as the authenticated ARMIS UI */
    .temp-password-page {
        min-height: calc(100vh - 76px);
        background: linear-gradient(135deg, #f5f7fa 0%, #e9ecef 100%);
        padding: 2rem 1rem 3rem;
    }
    .temp-password-container {
        max-width: 1080px;
        margin: 0 auto;
    }
    .temp-page-heading {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        margin-bottom: 1.5rem;
        padding-bottom: 1rem;
        border-bottom: 3px solid var(--military-green, #4a5d23);
    }
    .temp-page-heading h1 {
        margin: 0;
        color: #2c3e50;
        font-size: 1.8rem;
        font-weight: 600;
    }
    .temp-page-heading h1 i { color: var(--military-green, #4a5d23); margin-right: .65rem; }
    .temp-breadcrumb { margin: 0; }
    .temp-breadcrumb a { color: var(--military-green, #4a5d23); text-decoration: none; }

    .security-banner {
        background: #fff;
        border: 1px solid #e1e6ea;
        border-left: 4px solid var(--military-tan, #c19b5b);
        border-radius: 10px;
        box-shadow: var(--card-shadow, 0 4px 12px rgba(0,0,0,.08));
        padding: 1rem 1.15rem;
        margin-bottom: 1.25rem;
        display: flex;
        gap: .9rem;
        align-items: flex-start;
    }
    .security-banner .banner-icon {
        width: 40px; height: 40px; flex: 0 0 40px;
        border-radius: 50%; display: flex; align-items: center; justify-content: center;
        background: rgba(193,155,91,.15); color: #8b6914;
    }
    .security-banner h6 { margin: 0 0 .25rem; color: #34495e; font-weight: 700; }
    .security-banner p { margin: 0; color: #6c757d; font-size: .92rem; line-height: 1.5; }

    .password-card {
        background: #fff;
        border: 0;
        border-radius: 12px;
        box-shadow: var(--card-shadow, 0 4px 12px rgba(0,0,0,.08));
        overflow: hidden;
    }
    .password-card-header {
        padding: 1.25rem 1.5rem;
        background: linear-gradient(135deg, #f8f9fa, #e9ecef);
        border-bottom: 1px solid #dee2e6;
        display: flex; align-items: center; gap: 1rem;
    }
    .password-card-icon {
        width: 48px; height: 48px; flex: 0 0 48px;
        border-radius: 50%; display: flex; align-items: center; justify-content: center;
        color: #fff; background: var(--military-green, #4a5d23);
        font-size: 1.15rem;
        box-shadow: 0 3px 8px rgba(74,93,35,.2);
    }
    .password-card-header h2 { font-size: 1.2rem; margin: 0 0 .2rem; color: #2c3e50; font-weight: 600; }
    .password-card-header p { margin: 0; color: #6c757d; font-size: .9rem; }
    .password-card-body { padding: 1.5rem; }

    .form-label { color: #34495e; font-weight: 600; margin-bottom: .5rem; }
    .form-control { border-color: #ced4da; min-height: 44px; }
    .form-control:focus { border-color: var(--military-green, #4a5d23); box-shadow: 0 0 0 .2rem rgba(74,93,35,.12); }
    .password-input .btn { min-width: 46px; border-color: #ced4da; }

    .strength-track { height: 6px; background: #e9ecef; border-radius: 99px; overflow: hidden; }
    .strength-fill { height: 100%; width: 0; transition: width .2s ease, background-color .2s ease; background: var(--military-green, #4a5d23); }
    .strength-label { font-size: .82rem; font-weight: 600; }

    .requirements-box {
        background: #f8f9fa;
        border: 1px solid #e5e7eb;
        border-radius: 10px;
        padding: 1rem 1.1rem;
    }
    .requirements-title { color: #34495e; font-weight: 600; font-size: .92rem; margin-bottom: .7rem; }
    .requirement { color: #6c757d; font-size: .84rem; display: flex; align-items: center; gap: .45rem; }
    .requirement i { width: 15px; text-align: center; }
    .requirement.valid { color: #3f6f22; }

    .match-message { min-height: 20px; font-size: .82rem; }
    .action-row { display: flex; justify-content: flex-end; gap: .65rem; padding-top: 1.1rem; border-top: 1px solid #e9ecef; margin-top: 1.25rem; }
    .btn-armis-primary {
        background: var(--military-green, #4a5d23); border-color: var(--military-green, #4a5d23); color: #fff;
        min-height: 44px; padding: .6rem 1.25rem; font-weight: 600;
    }
    .btn-armis-primary:hover, .btn-armis-primary:focus { background: #3d4d1d; border-color: #3d4d1d; color: #fff; }
    .btn-armis-primary:disabled { opacity: .65; }

    .success-panel { text-align: center; padding: 2rem 1rem; }
    .success-icon { width: 68px; height: 68px; border-radius: 50%; background: rgba(74,93,35,.12); color: var(--military-green,#4a5d23); display:flex; align-items:center; justify-content:center; margin:0 auto 1rem; font-size:1.7rem; }
    .success-panel h3 { color:#2c3e50; font-size:1.25rem; }
    .success-panel p { color:#6c757d; margin-bottom:.35rem; }

    .privacy-note { color: #7a828a; font-size: .78rem; margin-top: 1rem; text-align: center; }
    @media (max-width: 767.98px) {
        .temp-password-page { padding: 1.25rem .75rem 2rem; }
        .temp-page-heading { align-items: flex-start; flex-direction: column; }
        .temp-page-heading h1 { font-size: 1.5rem; }
        .password-card-body { padding: 1.1rem; }
        .action-row { flex-direction: column-reverse; }
        .action-row .btn { width: 100%; }
    }
</style>

<main class="temp-password-page">
    <div class="temp-password-container">
        <div class="temp-page-heading">
            <div>
                <h1><i class="fas fa-key"></i> Change Temporary Password</h1>
            </div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb temp-breadcrumb">
                    <li class="breadcrumb-item"><a href="/Armis2/login.php">Login</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Password Setup</li>
                </ol>
            </nav>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <strong>Unable to update password.</strong> <?= htmlspecialchars($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <section class="password-card">
                <div class="success-panel">
                    <div class="success-icon"><i class="fas fa-check"></i></div>
                    <h3>Password changed successfully</h3>
                    <p>Your temporary password has been replaced with your new secure password.</p>
                    <p>You will be redirected to the ARMIS login page shortly.</p>
                </div>
            </section>
        <?php else: ?>
            <div class="security-banner">
                <div class="banner-icon"><i class="fas fa-shield-alt"></i></div>
                <div>
                    <h6>Secure your ARMIS account</h6>
                    <p>Your temporary password is for first-time access only. Choose a strong password that you have not used recently. ARMIS protects your last 5 passwords and never displays or stores previous passwords in plain text.</p>
                </div>
            </div>

            <section class="password-card">
                <div class="password-card-header">
                    <div class="password-card-icon"><i class="fas fa-lock"></i></div>
                    <div>
                        <h2>Set your new password</h2>
                        <p>You must complete this step before accessing ARMIS.</p>
                    </div>
                </div>

                <div class="password-card-body">
                    <form method="POST" id="passwordForm" novalidate>
                        <div class="row g-4">
                            <div class="col-lg-7">
                                <div class="mb-4">
                                    <label for="new_password" class="form-label">New Password</label>
                                    <div class="input-group password-input">
                                        <input type="password" class="form-control" id="new_password" name="new_password" autocomplete="new-password" required aria-describedby="passwordHelp">
                                        <button class="btn btn-outline-secondary" type="button" id="toggleNewPassword" aria-label="Show password"><i class="fas fa-eye"></i></button>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center mt-2">
                                        <small id="passwordHelp" class="text-muted">Minimum 12 characters</small>
                                        <span id="strengthLabel" class="strength-label text-muted">Not set</span>
                                    </div>
                                    <div class="strength-track mt-1" aria-hidden="true"><div class="strength-fill" id="strengthFill"></div></div>
                                </div>

                                <div class="mb-3">
                                    <label for="confirm_password" class="form-label">Confirm New Password</label>
                                    <div class="input-group password-input">
                                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" autocomplete="new-password" required>
                                        <button class="btn btn-outline-secondary" type="button" id="toggleConfirmPassword" aria-label="Show password"><i class="fas fa-eye"></i></button>
                                    </div>
                                    <div id="matchMessage" class="match-message mt-1"></div>
                                </div>
                            </div>

                            <div class="col-lg-5">
                                <div class="requirements-box h-100">
                                    <div class="requirements-title"><i class="fas fa-clipboard-check me-1"></i> Password requirements</div>
                                    <div class="row g-2">
                                        <div class="col-12 requirement" data-rule="length"><i class="fas fa-circle text-muted"></i><span>At least 12 characters</span></div>
                                        <div class="col-12 requirement" data-rule="lower"><i class="fas fa-circle text-muted"></i><span>One lowercase letter</span></div>
                                        <div class="col-12 requirement" data-rule="upper"><i class="fas fa-circle text-muted"></i><span>One uppercase letter</span></div>
                                        <div class="col-12 requirement" data-rule="number"><i class="fas fa-circle text-muted"></i><span>One number</span></div>
                                        <div class="col-12 requirement" data-rule="special"><i class="fas fa-circle text-muted"></i><span>One special character</span></div>
                                        <div class="col-12 requirement"><i class="fas fa-history text-success"></i><span>Last 5 passwords cannot be reused</span></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="action-row">
                            <a href="/Armis2/login.php" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left me-1"></i> Return to Login
                            </a>
                            <button type="submit" class="btn btn-armis-primary" id="submitBtn" disabled>
                                <i class="fas fa-check me-1"></i> Set New Password
                            </button>
                        </div>
                    </form>
                    <div class="privacy-note"><i class="fas fa-lock me-1"></i> Your password is securely hashed. ARMIS administrators cannot view your password.</div>
                </div>
            </section>
        <?php endif; ?>
    </div>
</main>

<script>
(function () {
    const pw = document.getElementById('new_password');
    const cp = document.getElementById('confirm_password');
    const submit = document.getElementById('submitBtn');
    const strengthFill = document.getElementById('strengthFill');
    const strengthLabel = document.getElementById('strengthLabel');
    const matchMessage = document.getElementById('matchMessage');

    function togglePassword(id, buttonId) {
        const input = document.getElementById(id), button = document.getElementById(buttonId);
        if (!input || !button) return;
        const visible = input.type === 'text';
        input.type = visible ? 'password' : 'text';
        button.innerHTML = visible ? '<i class="fas fa-eye"></i>' : '<i class="fas fa-eye-slash"></i>';
        button.setAttribute('aria-label', visible ? 'Show password' : 'Hide password');
    }

    document.getElementById('toggleNewPassword')?.addEventListener('click', () => togglePassword('new_password', 'toggleNewPassword'));
    document.getElementById('toggleConfirmPassword')?.addEventListener('click', () => togglePassword('confirm_password', 'toggleConfirmPassword'));

    function updateUI() {
        if (!pw) return;
        const v = pw.value;
        const rules = {
            length: v.length >= 12,
            lower: /[a-z]/.test(v),
            upper: /[A-Z]/.test(v),
            number: /\d/.test(v),
            special: /[^A-Za-z0-9]/.test(v)
        };
        Object.keys(rules).forEach(function (key) {
            const row = document.querySelector('[data-rule="' + key + '"]');
            const icon = row?.querySelector('i');
            if (!row || !icon) return;
            row.classList.toggle('valid', rules[key]);
            icon.className = rules[key] ? 'fas fa-check-circle text-success' : 'fas fa-circle text-muted';
        });

        let score = Object.values(rules).filter(Boolean).length;
        if (v.length >= 16 && score >= 4) score = Math.min(5, score + 1);
        const widths = [0, 20, 40, 60, 80, 100];
        const labels = ['Not set', 'Weak', 'Fair', 'Good', 'Strong', 'Very strong'];
        strengthFill.style.width = widths[score] + '%';
        strengthLabel.textContent = labels[score];
        strengthLabel.className = 'strength-label ' + (score >= 4 ? 'text-success' : score >= 3 ? 'text-warning' : 'text-muted');

        if (cp && matchMessage) {
            if (!cp.value) {
                matchMessage.textContent = '';
                matchMessage.className = 'match-message mt-1';
            } else if (v === cp.value) {
                matchMessage.innerHTML = '<i class="fas fa-check-circle me-1"></i>Passwords match.';
                matchMessage.className = 'match-message mt-1 text-success';
            } else {
                matchMessage.innerHTML = '<i class="fas fa-times-circle me-1"></i>Passwords do not match.';
                matchMessage.className = 'match-message mt-1 text-danger';
            }
        }

        const valid = Object.values(rules).every(Boolean) && cp && cp.value === v && v.length > 0;
        if (submit) submit.disabled = !valid;
    }

    pw?.addEventListener('input', updateUI);
    cp?.addEventListener('input', updateUI);
    updateUI();
})();
</script>

<!-- ARMIS core JS for this standalone authentication page -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
