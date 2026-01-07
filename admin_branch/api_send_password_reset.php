<?php
// File: admin_branch/api_send_password_reset.php
// Endpoint to trigger password reset email for a user

require_once __DIR__ . '/../shared/email_mailer.php';
require_once __DIR__ . '/../config/database.php'; // Adjust if you have a DB connection file

header('Content-Type: application/json');

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

// Connect to DB
$conn = new mysqli('localhost', 'root', '', 'armis1');
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

// Find user by email or username
if ($email) {
    $stmt = $conn->prepare('SELECT * FROM staff WHERE email = ? LIMIT 1');
    $stmt->bind_param('s', $email);
} else {
    $stmt = $conn->prepare('SELECT * FROM staff WHERE username = ? LIMIT 1');
    $stmt->bind_param('s', $username);
}
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'User not found']);
    exit;
}
$staffData = $result->fetch_assoc();

// Generate reset token
$resetToken = ARMISMailer::generateActivationToken();
$expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

// Store token in DB (create table staff_password_resets if not exists)
$conn->query('CREATE TABLE IF NOT EXISTS staff_password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    svcNo INT NOT NULL,
    reset_token VARCHAR(128) NOT NULL,
    expires_at DATETIME NOT NULL,
    used TINYINT(1) DEFAULT 0,
    createdAt DATETIME DEFAULT CURRENT_TIMESTAMP
)');

$stmt2 = $conn->prepare('INSERT INTO staff_password_resets (svcNo, reset_token, expires_at) VALUES (?, ?, ?)');
$stmt2->bind_param('iss', $staffData['id'], $resetToken, $expires);
$stmt2->execute();

// Send email
$mailer = new ARMISMailer();
$emailResult = $mailer->sendPasswordResetEmail($staffData, $resetToken);

if ($emailResult['success']) {
    echo json_encode(['success' => true, 'message' => 'Password reset email sent']);
} else {
    echo json_encode(['success' => false, 'message' => $emailResult['message']]);
}
