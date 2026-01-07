<?php
/**
 * AJAX endpoint: return active staff for a given rankId as JSON
 */
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Allow CLI testing by skipping authentication when running from CLI
if (php_sapi_name() !== 'cli') {
    session_start();
}

require_once __DIR__ . '/includes/auth.php';
require_once dirname(__DIR__) . '/shared/database_connection.php';

if (php_sapi_name() !== 'cli') {
    requireAuth();
}

header('Content-Type: application/json');

try {
    $pdo = getDbConnection();
    $rankId = $_GET['rankId'] ?? ($_POST['rankId'] ?? '');
    if (empty($rankId)) {
        echo json_encode([]);
        exit(0);
    }

    $sql = "SELECT s.svcNo, s.fName, s.lName, s.rankId, COALESCE(r.rankId, r.rankId) as rank_name, r.rankId as rank_abbr, u.code as unit_name, s.corps, s.svcStatus as status, s.appt FROM staff s LEFT JOIN `rank` r ON s.rankId = r.rankId LEFT JOIN `unit` u ON s.unitId = u.unitId WHERE s.rankId = ? AND s.svcStatus = 'Active' ORDER BY COALESCE(s.subWef, s.tempWef, s.attestDate, '1900-01-01') ASC, s.svcNo ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$rankId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($rows);
} catch (Exception $e) {
    error_log('ajax_get_staff_by_rank error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Server error']);
}
