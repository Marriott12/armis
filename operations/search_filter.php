<?php
// Search and filter utility for operations module
require_once dirname(__DIR__) . '/shared/database_connection.php';

function searchMissions($query, $status = null) {
    $db = getDbConnection();
    $sql = 'SELECT * FROM operations_missions WHERE mission_name LIKE ?';
    $params = ["%$query%"];
    if ($status) {
        $sql .= ' AND status = ?';
        $params[] = $status;
    }
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function filterDeployments($status = null, $location = null) {
    $db = getDbConnection();
    $sql = 'SELECT * FROM operations_deployments WHERE 1';
    $params = [];
    if ($status) {
        $sql .= ' AND status = ?';
        $params[] = $status;
    }
    if ($location) {
        $sql .= ' AND location_id = ?';
        $params[] = $location;
    }
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
