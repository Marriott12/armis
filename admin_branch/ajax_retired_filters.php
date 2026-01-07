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
        if ($unit) $sql .= " AND unitId = " . intval($unit);
        if ($rank) $sql .= " AND rankId = " . intval($rank);
        if ($category) $sql .= " AND category = " . $pdo->quote($category);
        $sql .= " ORDER BY appt ASC";
        $options = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        break;
    case 'unit':
        $sql = "SELECT DISTINCT u.unitId as value, u.code as label FROM unit u JOIN staff s ON s.unitId = u.unitId WHERE s.svcStatus = 'Retired'";
        if ($appt) $sql .= " AND s.appt = " . $pdo->quote($appt);
        if ($rank) $sql .= " AND s.rankId = " . intval($rank);
        if ($category) $sql .= " AND s.category = " . $pdo->quote($category);
        $sql .= " ORDER BY u.name ASC";
        $options = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        break;
    case 'rank':
    $sql = "SELECT DISTINCT r.rankId as value, COALESCE(r.rankId, r.rankId) as label FROM rank r JOIN staff s ON s.rankId = r.rankId WHERE s.svcStatus = 'Retired'";
        if ($appt) $sql .= " AND s.appt = " . $pdo->quote($appt);
        if ($unit) $sql .= " AND s.unitId = " . intval($unit);
        if ($category) $sql .= " AND s.category = " . $pdo->quote($category);
        $sql .= " ORDER BY r.rankId ASC";
        $options = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        break;
    case 'category':
        $sql = "SELECT DISTINCT s.category as value, s.category as label FROM staff s WHERE s.svcStatus = 'Retired' AND s.category IS NOT NULL AND s.category <> ''";
        if ($appt) $sql .= " AND s.appt = " . $pdo->quote($appt);
        if ($unit) $sql .= " AND s.unitId = " . intval($unit);
        if ($rank) $sql .= " AND s.rankId = " . intval($rank);
        $sql .= " ORDER BY s.category ASC";
        $options = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        break;
}
header('Content-Type: application/json');
echo json_encode($options);
