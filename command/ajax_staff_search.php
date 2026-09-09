<?php
/** Live staff search for profiles.php. Rewritten - previously required a
 * nonexistent '../init.php' (UserSpice) and called DB::getInstance(). */
require_once dirname(__DIR__) . '/shared/module_auth.php';
require_once dirname(__DIR__) . '/shared/rank_levels.php';
bootModuleApi('command');
header('Content-Type: application/json');

$pdo = getDbConnection();
$scope = getSnapshotScope();
$search = trim($_GET['search'] ?? '');
$params = []; $where = [];
if ($scope['scope'] === 'branch') { $where[] = 's.branch_id = :branch_id'; $params['branch_id'] = $scope['branch_id']; }
elseif ($scope['scope'] === 'none') { echo json_encode([]); exit(); }
if ($search !== '') {
    $where[] = '(s.svcNo LIKE :s1 OR s.fName LIKE :s2 OR s.lName LIKE :s3)';
    $params['s1'] = "%$search%"; $params['s2'] = "%$search%"; $params['s3'] = "%$search%";
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
$stmt = $pdo->prepare("
    SELECT s.svcNo, s.fName, s.lName, s.rankId, " . getRankCategoryCaseSQL('r') . " AS category,
           r.rankId AS rankAbbr, COALESCE(u.unitId, 'Unassigned') AS unitName
    FROM staff s LEFT JOIN `rank` r ON s.rankId = r.rankId LEFT JOIN unit u ON s.unitId = u.unitId
    $whereSql ORDER BY s.lName ASC LIMIT 100
");
$stmt->execute($params);
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
