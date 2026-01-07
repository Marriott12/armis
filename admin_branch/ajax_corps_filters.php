<?php
require_once __DIR__ . '/includes/auth.php';
require_once dirname(__DIR__) . '/shared/database_connection.php';
requireAuth();

$pdo = getDbConnection();
$type = $_GET['type'] ?? '';
$corps = $_GET['corps'] ?? '';
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
    case 'corps':
        $sql = "SELECT DISTINCT s.corps FROM staff s WHERE s.corps IS NOT NULL AND s.corps <> '' AND s.svcStatus = 'Active'";
        $params = [];
        if ($unit) { $sql .= " AND s.unitId = ?"; $params[] = $unit; }
        if ($rank) { $sql .= " AND s.rankId = ?"; $params[] = $rank; }
        if ($category) { $sql .= " AND s.category = ?"; $params[] = $category; }
        $sql .= " ORDER BY s.corps ASC";
        $options = fetchOptions($sql, $params, 'corps', 'corps');
        break;
    case 'rank':
        // Use rankId and abbreviation for compatibility with different schema versions
        $sql = "SELECT DISTINCT r.rankId as id, COALESCE(r.rankId, r.rankId) as name FROM rank r JOIN staff s ON s.rankId = r.rankId WHERE s.svcStatus = 'Active'";
        $params = [];
        if ($unit) { $sql .= " AND s.unitId = ?"; $params[] = $unit; }
        if ($corps) { $sql .= " AND s.corps = ?"; $params[] = $corps; }
        if ($category) { $sql .= " AND s.category = ?"; $params[] = $category; }
    $sql .= " ORDER BY name ASC";
    $options = fetchOptions($sql, $params, 'id', 'name');
        break;
    case 'unit':
        $sql = "SELECT DISTINCT u.unitId as id, u.code as name FROM unit u JOIN staff s ON s.unitId = u.unitId WHERE s.svcStatus = 'Active'";
        $params = [];
        if ($rank) { $sql .= " AND s.rankId = ?"; $params[] = $rank; }
        if ($corps) { $sql .= " AND s.corps = ?"; $params[] = $corps; }
        if ($category) { $sql .= " AND s.category = ?"; $params[] = $category; }
        $sql .= " ORDER BY u.name ASC";
        $options = fetchOptions($sql, $params, 'id', 'name');
        break;
    case 'category':
        $sql = "SELECT DISTINCT s.category FROM staff s WHERE s.category IS NOT NULL AND s.category <> '' AND s.svcStatus = 'Active'";
        $params = [];
        if ($unit) { $sql .= " AND s.unitId = ?"; $params[] = $unit; }
        if ($rank) { $sql .= " AND s.rankId = ?"; $params[] = $rank; }
        if ($corps) { $sql .= " AND s.corps = ?"; $params[] = $corps; }
        $sql .= " ORDER BY s.category ASC";
        $options = fetchOptions($sql, $params, 'category', 'category');
        break;
    default:
        $options = [];
}
echo json_encode($options);
