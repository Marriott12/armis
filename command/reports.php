<?php
require_once dirname(__DIR__) . '/shared/module_auth.php';
require_once dirname(__DIR__) . '/shared/rank_levels.php';
bootModule(['module' => 'command', 'page' => 'reports', 'pageTitle' => 'Command Reports - ARMIS', 'moduleName' => 'Command', 'moduleIcon' => 'chess-king']);

$pdo = getDbConnection();
$scope = getSnapshotScope();
$search = trim($_GET['search'] ?? '');
$params = []; $where = [];
if ($scope['scope'] === 'branch') { $where[] = 's.branch_id = :branch_id'; $params['branch_id'] = $scope['branch_id']; }
if ($search !== '') {
    $where[] = '(s.svcNo LIKE :s1 OR s.fName LIKE :s2 OR s.lName LIKE :s3)';
    $params['s1'] = "%$search%"; $params['s2'] = "%$search%"; $params['s3'] = "%$search%";
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

function statList(PDO $pdo, $sql, $params) { $s = $pdo->prepare($sql); $s->execute($params); return $s->fetchAll(PDO::FETCH_OBJ); }
function statValue(PDO $pdo, $sql, $params) { $s = $pdo->prepare($sql); $s->execute($params); return (int)$s->fetchColumn(); }

$totalStaff = statValue($pdo, "SELECT COUNT(*) FROM staff s LEFT JOIN `rank` r ON s.rankId = r.rankId $whereSql", $params);
$byCategory = statList($pdo, "SELECT " . getRankCategoryCaseSQL('r') . " AS category, COUNT(*) AS total FROM staff s LEFT JOIN `rank` r ON s.rankId = r.rankId $whereSql GROUP BY category", $params);
$byGender = statList($pdo, "SELECT s.gender, COUNT(*) AS total FROM staff s LEFT JOIN `rank` r ON s.rankId = r.rankId $whereSql GROUP BY s.gender", $params);
$byUnit = statList($pdo, "SELECT COALESCE(u.unitId,'Unassigned') AS unitName, COUNT(*) AS total FROM staff s LEFT JOIN `rank` r ON s.rankId = r.rankId LEFT JOIN unit u ON s.unitId = u.unitId $whereSql GROUP BY u.unitId ORDER BY total DESC", $params);
$byRank = statList($pdo, "SELECT COALESCE(r.rankId,'Unknown') AS rankName, COUNT(*) AS total FROM staff s LEFT JOIN `rank` r ON s.rankId = r.rankId $whereSql GROUP BY r.rankId ORDER BY total DESC", $params);
$byProvince = statList($pdo, "SELECT COALESCE(s.province,'Unknown') AS province, COUNT(*) AS total FROM staff s LEFT JOIN `rank` r ON s.rankId = r.rankId $whereSql GROUP BY s.province ORDER BY total DESC", $params);
$byBlood = statList($pdo, "SELECT COALESCE(s.bloodGp,'Unknown') AS bloodGp, COUNT(*) AS total FROM staff s LEFT JOIN `rank` r ON s.rankId = r.rankId $whereSql GROUP BY s.bloodGp", $params);
$byAge = statList($pdo, "SELECT CASE WHEN TIMESTAMPDIFF(YEAR,s.DOB,CURDATE())<25 THEN '<25' WHEN TIMESTAMPDIFF(YEAR,s.DOB,CURDATE()) BETWEEN 25 AND 34 THEN '25-34' WHEN TIMESTAMPDIFF(YEAR,s.DOB,CURDATE()) BETWEEN 35 AND 44 THEN '35-44' WHEN TIMESTAMPDIFF(YEAR,s.DOB,CURDATE()) BETWEEN 45 AND 54 THEN '45-54' ELSE '55+' END AS age_group, COUNT(*) AS total FROM staff s LEFT JOIN `rank` r ON s.rankId = r.rankId $whereSql GROUP BY age_group ORDER BY age_group", $params);

include dirname(__DIR__) . '/shared/header.php';
include dirname(__DIR__) . '/shared/sidebar.php';
?>
<style>.stat-card{border-radius:.5rem;box-shadow:0 2px 8px rgba(0,0,0,.05);margin-bottom:1.5rem}.stat-title{font-size:1.1rem;color:#355E3B;font-weight:600}.stat-value{font-size:2rem;font-weight:bold;color:#198754}</style>
<div class="content-wrapper with-sidebar"><div class="container-fluid"><div class="main-content">
  <h2 class="mb-4"><i class="fa fa-chart-bar"></i> Command Statistical Report</h2>
  <form class="mb-4" method="get" action=""><div class="input-group">
    <input type="text" name="search" class="form-control" placeholder="Search by name or service number" value="<?= htmlspecialchars($search) ?>">
    <button class="btn btn-success" type="submit"><i class="fa fa-search"></i> Search</button>
  </div></form>
  <div class="row mb-4">
    <div class="col-md-3 col-6"><div class="stat-card p-3 text-center bg-light"><div class="stat-title">Total Staff</div><div class="stat-value"><?= number_format($totalStaff) ?></div></div></div>
    <?php foreach ($byCategory as $cat): ?>
    <div class="col-md-3 col-6"><div class="stat-card p-3 text-center"><div class="stat-title"><?= htmlspecialchars($cat->category ?: 'Unknown') ?>s</div><div class="stat-value"><?= number_format($cat->total) ?></div></div></div>
    <?php endforeach; ?>
  </div>
  <div class="row mb-4">
    <?php $g = ['Male'=>0,'Female'=>0,'Other'=>0]; foreach ($byGender as $row) { $k = ucfirst(strtolower($row->gender ?? '')); if (!isset($g[$k])) $k = 'Other'; $g[$k] += (int)$row->total; }
    foreach ($g as $label => $total): ?>
    <div class="col-md-3 col-6"><div class="stat-card p-3 text-center"><div class="stat-title"><?= $label ?></div><div class="stat-value"><?= number_format($total) ?></div></div></div>
    <?php endforeach; ?>
  </div>
  <?php
  $sections = [
    ['icon'=>'building','title'=>'Staff by Unit','rows'=>$byUnit,'label'=>'unitName'],
    ['icon'=>'medal','title'=>'Staff by Rank','rows'=>$byRank,'label'=>'rankName'],
    ['icon'=>'map-marker-alt','title'=>'Staff by Province','rows'=>$byProvince,'label'=>'province'],
    ['icon'=>'tint','title'=>'Staff by Blood Group','rows'=>$byBlood,'label'=>'bloodGp'],
    ['icon'=>'birthday-cake','title'=>'Staff by Age Group','rows'=>$byAge,'label'=>'age_group'],
  ];
  foreach ($sections as $s): ?>
  <div class="row mb-4"><div class="col-12"><div class="stat-card p-3">
    <div class="stat-title mb-2"><i class="fa fa-<?= $s['icon'] ?>"></i> <?= htmlspecialchars($s['title']) ?></div>
    <div class="table-responsive"><table class="table table-sm table-bordered align-middle mb-0">
      <thead class="table-success"><tr><th><?= htmlspecialchars($s['title']) ?></th><th>Count</th></tr></thead>
      <tbody>
        <?php foreach ($s['rows'] as $row): ?><tr><td><?= htmlspecialchars($row->{$s['label']} ?: 'Unknown') ?></td><td><?= number_format($row->total) ?></td></tr><?php endforeach; ?>
        <?php if (empty($s['rows'])): ?><tr><td colspan="2" class="text-center text-muted">No data.</td></tr><?php endif; ?>
      </tbody>
    </table></div>
  </div></div></div>
  <?php endforeach; ?>
</div></div></div>
<?php include dirname(__DIR__) . '/shared/footer.php'; ?>
