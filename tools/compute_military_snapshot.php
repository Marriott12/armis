<?php
require_once __DIR__ . '/../shared/database_connection.php';
require_once __DIR__ . '/../shared/rank_levels.php';
$pdo = getDbConnection();
$rankTable = '`rank`';
$sql = "SELECT r.level, s.gender, COUNT(*) as cnt FROM staff s INNER JOIN `rank` r ON s.rankId = r.rankId WHERE s.svcNo IS NOT NULL AND s.svcStatus != 'Discharged' AND r.level IS NOT NULL GROUP BY r.level, s.gender";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
$result = [];
foreach ($rows as $r) {
    $level = (int)$r['level'];
    $cat = getRankCategory($level);
    $g = strtolower($r['gender'] ?? 'unknown');
    if (!isset($result[$cat])) {
        $result[$cat] = [];
    }
    if (!isset($result[$cat][$g])) {
        $result[$cat][$g] = 0;
    }
    $result[$cat][$g] += (int)$r['cnt'];
}
echo json_encode($result, JSON_PRETTY_PRINT) . "\n";
