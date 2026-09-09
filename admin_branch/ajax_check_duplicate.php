<?php
// AJAX endpoint for real-time duplicate NRC and email check
require_once dirname(__DIR__, 2) . '/shared/database_connection.php';
header('Content-Type: application/json');

// FIX: $pdo was never instantiated - getDbConnection()'s $pdo is a
// function-local static variable, not a global. requiring the file only
// makes the function available; it has to actually be called. Every
// request to this endpoint previously fatal-errored on the first
// ->prepare() call ("Call to a member function prepare() on null").
$pdo = getDbConnection();

$nrc = isset($_POST['nrc']) ? trim($_POST['nrc']) : '';
$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$response = ["nrc_exists" => false, "email_exists" => false];

if ($nrc !== '') {
    // Column name matching is case-insensitive in MySQL, so NRC vs nrc
    // isn't itself a bug - kept as-is for a minimal diff.
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM staff WHERE NRC = ?');
    $stmt->execute([$nrc]);
    $response["nrc_exists"] = $stmt->fetchColumn() > 0;
}
if ($email !== '') {
    // FIX: `staff` has no `email` column - the real columns are
    // `officialEmail` and `emailPvt`.
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM staff WHERE officialEmail = ? OR emailPvt = ?');
    $stmt->execute([$email, $email]);
    $response["email_exists"] = $stmt->fetchColumn() > 0;
}
echo json_encode($response);
