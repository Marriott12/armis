<?php
require_once __DIR__ . '/includes/auth.php';
require_once dirname(__DIR__) . '/shared/database_connection.php';
requireAuth();

$pdo = getDbConnection();
$type = $_GET['type'] ?? '';
$trade = $_GET['trade'] ?? '';
$rank = $_GET['rankID'] ?? '';
$unit = $_GET['unitID'] ?? '';
$category = $_GET['category'] ?? '';

header('Content-Type: application/json');

function fetchOptions($sql, $params, $key, $label) {
    global $pdo;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $options = [];
    foreach ($results as $row) {
        $options[] = [
            'value' => $row[$key],
            'label' => $row[$label]
        ];
    }
    return $options;
}

switch ($type) {
    case 'trade':
        $sql = "SELECT DISTINCT trade FROM staff WHERE svcStatus = 'Active'";
        $params = [];
        if ($unit) { $sql .= " AND unitId = ?"; $params[] = $unit; }
        if ($rank) { $sql .= " AND rankId = ?"; $params[] = $rank; }
        if ($category) { $sql .= " AND category = ?"; $params[] = $category; }
        $sql .= " ORDER BY trade ASC";
        $options = fetchOptions($sql, $params, 'trade', 'trade');
        break;
    case 'rank':
        $sql = "SELECT DISTINCT r.rankId as id, COALESCE(r.rankId, r.rankId) as name FROM rank r JOIN staff s ON s.rankId = r.rankId WHERE s.svcStatus = 'Active'";
        $params = [];
        if ($unit) { $sql .= " AND s.unitId = ?"; $params[] = $unit; }
        if ($trade) { $sql .= " AND s.trade = ?"; $params[] = $trade; }
        if ($category) { $sql .= " AND s.category = ?"; $params[] = $category; }
    $sql .= " ORDER BY name ASC";
    $options = fetchOptions($sql, $params, 'id', 'name');
        break;
    case 'unit':
        $sql = "SELECT DISTINCT u.unitId as id, u.code as name FROM unit u JOIN staff s ON s.unitId = u.unitId WHERE s.svcStatus = 'Active'";
        $params = [];
        if ($rank) { $sql .= " AND s.rankId = ?"; $params[] = $rank; }
        if ($trade) { $sql .= " AND s.trade = ?"; $params[] = $trade; }
        if ($category) { $sql .= " AND s.category = ?"; $params[] = $category; }
        $sql .= " ORDER BY u.name ASC";
        $options = fetchOptions($sql, $params, 'id', 'name');
        break;
    case 'category':
        $sql = "SELECT DISTINCT s.category FROM staff s WHERE s.category IS NOT NULL AND s.category <> '' AND s.svcStatus = 'Active'";
        $params = [];
        if ($unit) { $sql .= " AND s.unitId = ?"; $params[] = $unit; }
        if ($rank) { $sql .= " AND s.rankId = ?"; $params[] = $rank; }
        if ($trade) { $sql .= " AND s.trade = ?"; $params[] = $trade; }
        $sql .= " ORDER BY s.category ASC";
        $options = fetchOptions($sql, $params, 'category', 'category');
        break;
    default:
        $options = [];
}
echo json_encode($options);
