<?php
// AJAX endpoint for dynamic rank report filter dropdowns
require_once __DIR__ . '/includes/auth.php';
require_once dirname(__DIR__) . '/shared/database_connection.php';
requireAuth();

$type = $_GET['type'] ?? '';
$rank = $_GET['rankID'] ?? '';
$unit = $_GET['unitID'] ?? '';
$category = $_GET['category'] ?? '';

$pdo = getDbConnection();
$options = [];

switch ($type) {
    case 'rank':
        $sql = "SELECT DISTINCT r.id as value, r.name as label FROM ranks r JOIN staff s ON s.rank_id = r.id WHERE s.svcStatus = 'Active'";
        if ($unit) $sql .= " AND s.unit_id = " . intval($unit);
        if ($category) $sql .= " AND s.category = " . $pdo->quote($category);
        $sql .= " ORDER BY r.name ASC";
        $options = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        break;
    case 'unit':
        $sql = "SELECT DISTINCT u.id as value, u.name as label FROM units u JOIN staff s ON s.unit_id = u.id WHERE s.svcStatus = 'Active'";
        if ($rank) $sql .= " AND s.rank_id = " . intval($rank);
        if ($category) $sql .= " AND s.category = " . $pdo->quote($category);
        $sql .= " ORDER BY u.name ASC";
        $options = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        break;
    case 'category':
        $sql = "SELECT DISTINCT s.category as value, s.category as label FROM staff s WHERE s.svcStatus = 'Active' AND s.category IS NOT NULL AND s.category <> ''";
        if ($rank) $sql .= " AND s.rank_id = " . intval($rank);
        if ($unit) $sql .= " AND s.unit_id = " . intval($unit);
        $sql .= " ORDER BY s.category ASC";
        $options = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        break;
}
header('Content-Type: application/json');
echo json_encode($options);
