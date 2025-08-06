<?php
// AJAX endpoint for real-time duplicate NRC and email check
require_once dirname(__DIR__, 2) . '/shared/database_connection.php';
header('Content-Type: application/json');

$nrc = isset($_POST['nrc']) ? trim($_POST['nrc']) : '';
$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$response = ["nrc_exists" => false, "email_exists" => false];

if ($nrc !== '') {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM staff WHERE nrc = ?');
    $stmt->execute([$nrc]);
    $response["nrc_exists"] = $stmt->fetchColumn() > 0;
}
if ($email !== '') {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM staff WHERE email = ?');
    $stmt->execute([$email]);
    $response["email_exists"] = $stmt->fetchColumn() > 0;
}
echo json_encode($response);
