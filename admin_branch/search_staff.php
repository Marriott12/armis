<?php
// DEBUG: Log incoming parameters and SQL for troubleshooting
$debugLog = __DIR__ . '/search_staff_debug.log';
$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$rankId = isset($_GET['rank_id']) ? trim($_GET['rank_id']) : '';

require_once __DIR__ . '/includes/auth.php';
require_once dirname(__DIR__) . '/shared/database_connection.php';
$pdo = getDbConnection();

header('Content-Type: application/json');

// Exclude certain ranks
$excludedRanks = ['Officer Cadet', 'Recruit', 'Mister', 'Miss'];
$excludedRankIds = [];
$ranks = $pdo->query("SELECT id, name, level FROM ranks")->fetchAll(PDO::FETCH_ASSOC);
foreach ($ranks as $r) {
    if (in_array($r['name'], $excludedRanks)) {
        $excludedRankIds[] = $r['id'];
    }
}

$where = [];
$params = [];

if ($rankId !== '') {
    $where[] = "s.rank_id = ?";
    $params[] = $rankId;
    if (count($excludedRankIds)) {
        $where[] = "s.rank_id NOT IN (" . implode(',', $excludedRankIds) . ")";
    }
}
if ($q !== '') {
    $where[] = "(s.service_number LIKE ? OR s.first_name LIKE ? OR s.last_name LIKE ?)";
    $params[] = "%$q%";
    $params[] = "%$q%";
    $params[] = "%$q%";
}
if (empty($where)) {
    echo json_encode([]);
    exit;
}

$sql = "SELECT s.id, s.service_number, s.rank_id, s.first_name, s.last_name, s.unit_id, u.name as unit_name
        FROM staff s
        LEFT JOIN units u ON s.unit_id = u.id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY s.last_name, s.first_name
        LIMIT 20";

$logMsg = date('Y-m-d H:i:s') . " | q=$q | rank_id=$rankId | SQL: $sql | Params: " . json_encode($params) . "\n";
file_put_contents($debugLog, $logMsg, FILE_APPEND);

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$results = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $results[] = [
        'id' => $row['service_number'],
        'text' => $row['service_number'] . ' - ' . $row['last_name'] . ', ' . $row['first_name'] . ' (' . $row['unit_name'] . ')',
        'service_number' => $row['service_number'],
        'rank_id' => $row['rank_id'],
        'first_name' => $row['first_name'],
        'last_name' => $row['last_name'],
        'unit_id' => $row['unit_id'],
        'unit_name' => $row['unit_name'],
    ];
}
echo json_encode($results);
// End of file.