<?php
// Mission history tracking for operations module
require_once dirname(__DIR__) . '/shared/database_connection.php';
$db = getDbConnection();

function addMissionHistory($missionId, $changedBy, $changeType, $changeDetails) {
    $stmt = $db->prepare('INSERT INTO operations_mission_history (mission_id, changed_by, change_type, change_details) VALUES (?, ?, ?, ?)');
    $stmt->execute([$missionId, $changedBy, $changeType, $changeDetails]);
}

function getMissionHistory($missionId) {
    $stmt = $db->prepare('SELECT h.*, s.username FROM operations_mission_history h LEFT JOIN staff s ON h.changed_by = s.svcNo WHERE h.mission_id = ? ORDER BY h.changed_at DESC');
    $stmt->execute([$missionId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
