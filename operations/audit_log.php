<?php
// Audit logging for operations module
require_once dirname(__DIR__) . '/shared/database_connection.php';
$db = getDbConnection();

function logOperationAction($userId, $action, $details = '') {
    global $db;
    $stmt = $db->prepare('INSERT INTO operations_activity_log (user_id, action_type, description) VALUES (?, ?, ?)');
    $stmt->execute([$userId, $action, $details]);
}

function getAuditTrail($limit = 100) {
    global $db;
    $stmt = $db->prepare('SELECT l.*, s.username FROM operations_activity_log l LEFT JOIN staff s ON l.user_id = s.svcNo ORDER BY l.createdAt DESC LIMIT ?');
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
