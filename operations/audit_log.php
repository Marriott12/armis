<?php
// Audit logging for operations module
require_once dirname(__DIR__) . '/shared/database_connection.php';
$db = getDbConnection();

function logOperationAction($userId, $action, $details = '') {
    $stmt = $db->prepare('INSERT INTO operations_activity_log (user_id, action, details) VALUES (?, ?, ?)');
    $stmt->execute([$userId, $action, $details]);
}

function getAuditTrail($limit = 100) {
    global $db;
    $stmt = $db->prepare('SELECT l.*, s.username FROM operations_activity_log l LEFT JOIN staff s ON l.user_id = s.id ORDER BY l.created_at DESC LIMIT ?');
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
