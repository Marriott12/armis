<?php
// AJAX endpoint for dynamic rank report filter dropdowns
require_once __DIR__ . '/includes/auth.php';
require_once dirname(__DIR__) . '/shared/database_connection.php';
require_once dirname(__DIR__) . '/shared/rank_levels.php';
requireAuth();

$type = $_GET['type'] ?? '';
$rank = $_GET['rankID'] ?? '';
$unit = $_GET['unitID'] ?? '';
$category = $_GET['category'] ?? '';

$pdo = getDbConnection();
$options = [];

switch ($type) {
    case 'rank':
    $sql = "SELECT DISTINCT r.rankId as value, r.rankId as label FROM `rank` r JOIN staff s ON s.rankId = r.rankId WHERE s.svcStatus = 'Active'";
        if ($unit) $sql .= " AND s.unitId = " . $pdo->quote($unit);
        if ($category) {
            $categorySQL = getRankCategorySQL($category, 'r');
            $sql .= " AND (" . $categorySQL . ")";
        }
    $sql .= " ORDER BY r.rankIndex ASC";
        $options = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        break;
    case 'unit':
        $sql = "SELECT DISTINCT u.unitId as value, COALESCE(u.unitId, u.unitLoc, u.mainUnit '') as label FROM unit u JOIN staff s ON s.unitId = u.unitId LEFT JOIN `rank` r ON s.rankId = r.rankId WHERE s.svcStatus = 'Active'";
        if ($rank) $sql .= " AND s.rankId = " . $pdo->quote($rank);
        if ($category) {
            $categorySQL = getRankCategorySQL($category, 'r');
            $sql .= " AND (" . $categorySQL . ")";
        }
        $sql .= " ORDER BY COALESCE(u.code, u.unitId, u.name, u.unitLoc, '') ASC";
        $options = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        break;
    case 'category':
        // Return hardcoded categories based on rank level system
        $sql = "SELECT DISTINCT " . getRankCategoryCaseSQL('r') . " as value, " . getRankCategoryCaseSQL('r') . " as label FROM staff s JOIN `rank` r ON s.rankId = r.rankId WHERE s.svcStatus = 'Active' AND r.rankIndex IS NOT NULL";
        if ($rank) $sql .= " AND s.rankId = " . $pdo->quote($rank);
        if ($unit) $sql .= " AND s.unitId = " . $pdo->quote($unit);
        $sql .= " ORDER BY r.rankIndex ASC";
        $options = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        break;
}
header('Content-Type: application/json');
echo json_encode($options);
