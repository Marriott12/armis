<?php
// AJAX endpoint for dynamic retired staff filter dropdowns
require_once __DIR__ . '/includes/auth.php';
require_once dirname(__DIR__) . '/shared/database_connection.php';
requireAuth();

$type = $_GET['type'] ?? '';
$appt = $_GET['appointment'] ?? '';
$unit = $_GET['unitID'] ?? '';
$rank = $_GET['rankID'] ?? '';
$category = $_GET['category'] ?? '';

$pdo = getDbConnection();
$options = [];

switch ($type) {
    case 'appointment':
        $sql = "SELECT DISTINCT appt as value, appt as label FROM staff WHERE svcStatus = 'Retired' AND appt IS NOT NULL AND appt <> ''";
        if ($unit) $sql .= " AND unit_id = " . intval($unit);
        if ($rank) $sql .= " AND rank_id = " . intval($rank);
        if ($category) $sql .= " AND category = " . $pdo->quote($category);
        $sql .= " ORDER BY appt ASC";
        $options = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        break;
    case 'unit':
        $sql = "SELECT DISTINCT u.id as value, u.name as label FROM units u JOIN staff s ON s.unit_id = u.id WHERE s.svcStatus = 'Retired'";
        if ($appt) $sql .= " AND s.appt = " . $pdo->quote($appt);
        if ($rank) $sql .= " AND s.rank_id = " . intval($rank);
        if ($category) $sql .= " AND s.category = " . $pdo->quote($category);
        $sql .= " ORDER BY u.name ASC";
        $options = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        break;
    case 'rank':
        $sql = "SELECT DISTINCT r.id as value, r.name as label FROM ranks r JOIN staff s ON s.rank_id = r.id WHERE s.svcStatus = 'Retired'";
        if ($appt) $sql .= " AND s.appt = " . $pdo->quote($appt);
        if ($unit) $sql .= " AND s.unit_id = " . intval($unit);
        if ($category) $sql .= " AND s.category = " . $pdo->quote($category);
        $sql .= " ORDER BY r.name ASC";
        $options = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        break;
    case 'category':
        $sql = "SELECT DISTINCT s.category as value, s.category as label FROM staff s WHERE s.svcStatus = 'Retired' AND s.category IS NOT NULL AND s.category <> ''";
        if ($appt) $sql .= " AND s.appt = " . $pdo->quote($appt);
        if ($unit) $sql .= " AND s.unit_id = " . intval($unit);
        if ($rank) $sql .= " AND s.rank_id = " . intval($rank);
        $sql .= " ORDER BY s.category ASC";
        $options = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        break;
}
header('Content-Type: application/json');
echo json_encode($options);
