<?php
// File: admin_branch/api_send_password_reset.php
// Endpoint to trigger password reset email for a user

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/../shared/email_mailer.php';
require_once __DIR__ . '/../shared/database_connection.php';

header('Content-Type: application/json');

// FIX: this admin-triggered reset endpoint had no authentication check at
// all - any anonymous request could trigger a reset email for an arbitrary
// user. Now requires an authenticated admin, matching how every other
// admin_branch endpoint protects itself (see create_staff.php etc.).
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get email or username from POST
$data = json_decode(file_get_contents('php://input'), true);
$email = $data['email'] ?? null;
$username = $data['username'] ?? null;

if (!$email && !$username) {
    echo json_encode(['success' => false, 'message' => 'Email or username required']);
    exit;
}

try {
    // FIX: previously connected directly with `new mysqli('localhost',
    // 'root', '', 'armis1')` - hardcoded root credentials with an empty
    // password, bypassing the shared, centrally-configured connection
    // layer every other file in this app uses. Now uses getDbConnection()
    // like the rest of the codebase, so credentials live in one place.
    $pdo = getDbConnection();

    // Find user by email or username.
    // FIX: `staff` has no `email` column - the real columns are
    // `officialEmail` and `emailPvt`.
    if ($email) {
        $stmt = $pdo->prepare('SELECT * FROM staff WHERE (officialEmail = ? OR emailPvt = ?) LIMIT 1');
        $stmt->execute([$email, $email]);
    } else {
        $stmt = $pdo->prepare('SELECT * FROM staff WHERE username = ? LIMIT 1');
        $stmt->execute([$username]);
    }
    $staffData = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$staffData) {
        // FIX: previously returned a distinct "User not found" message,
        // which lets a caller enumerate valid emails/usernames by
        // observing the response. This admin tool still needs SOME
        // feedback for a genuine typo, but a generic message avoids
        // confirming existence outright.
        echo json_encode(['success' => false, 'message' => 'No matching account found, or the account cannot receive reset emails right now.']);
        exit;
    }

    // Generate reset token
    $resetToken = ARMISMailer::generateActivationToken();
    $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

    // Store token in DB (create table staff_password_resets if not exists).
    // FIX: svcNo is VARCHAR(10) on `staff` (values like '007414' have
    // meaningful leading zeros) - INT would silently mangle them.
    $pdo->exec('CREATE TABLE IF NOT EXISTS staff_password_resets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        svcNo VARCHAR(10) NOT NULL,
        reset_token VARCHAR(128) NOT NULL,
        expires_at DATETIME NOT NULL,
        used TINYINT(1) DEFAULT 0,
        createdAt DATETIME DEFAULT CURRENT_TIMESTAMP
    )');

    // FIX: staff has no `id` column - its primary key is `svcNo`.
    $stmt2 = $pdo->prepare('INSERT INTO staff_password_resets (svcNo, reset_token, expires_at) VALUES (?, ?, ?)');
    $stmt2->execute([$staffData['svcNo'], $resetToken, $expires]);

    // Send email
    $mailer = new ARMISMailer();
    $emailResult = $mailer->sendPasswordResetEmail($staffData, $resetToken);

    if ($emailResult['success']) {
        echo json_encode(['success' => true, 'message' => 'Password reset email sent']);
    } else {
        echo json_encode(['success' => false, 'message' => $emailResult['message']]);
    }
} catch (Exception $e) {
    error_log('api_send_password_reset error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An error occurred processing this request.']);
}
