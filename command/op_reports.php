<?php
require_once dirname(__DIR__) . '/shared/module_auth.php';
bootModule(['module' => 'command', 'page' => 'op_reports', 'pageTitle' => 'Operational Reports - Command - ARMIS', 'moduleName' => 'Command', 'moduleIcon' => 'chess-king']);

$pdo = getDbConnection();
$scope = getSnapshotScope();
$search = trim($_GET['search'] ?? '');
$params = []; $where = [];
if ($scope['scope'] === 'branch') { $where[] = 's.branch_id = :branch_id'; $params['branch_id'] = $scope['branch_id']; }
if ($search !== '') { $where[] = '(u.unitId LIKE :u OR s.province LIKE :p)'; $params['u'] = "%$search%"; $params['p'] = "%$search%"; }
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$stmt = $pdo->prepare("SELECT COALESCE(u.unitId,'Unassigned') AS unitName, COUNT(*) AS total FROM staff s LEFT JOIN unit u ON s.unitId = u.unitId $whereSql GROUP BY u.unitId ORDER BY total DESC LIMIT 100");
$stmt->execute($params);
$staffByUnit = $stmt->fetchAll(PDO::FETCH_OBJ);

$stmt2 = $pdo->prepare("SELECT COALESCE(s.province,'Unknown') AS province, COUNT(*) AS total FROM staff s LEFT JOIN unit u ON s.unitId = u.unitId $whereSql GROUP BY s.province ORDER BY total DESC LIMIT 100");
$stmt2->execute($params);
$staffByProvince = $stmt2->fetchAll(PDO::FETCH_OBJ);

include dirname(__DIR__) . '/shared/header.php';
include dirname(__DIR__) . '/shared/sidebar.php';
?>
<div class="content-wrapper with-sidebar"><div class="container-fluid"><div class="main-content">
  <h2 class="mb-4"><i class="fa fa-bar-chart"></i> Operational Reports</h2>
  <div class="row">
    <div class="col-md-4 mb-4">
      <form class="mb-3" method="get" action=""><div class="input-group">
        <input type="text" name="search" class="form-control" placeholder="Search by unit or province..." value="<?= htmlspecialchars($search) ?>">
        <button class="btn btn-success" type="submit"><i class="fa fa-search"></i></button>
      </div></form>
      <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-success text-white"><strong>Staff per Unit</strong></div>
        <div class="card-body p-0" style="max-height:350px;overflow-y:auto;">
          <?php if ($staffByUnit): ?>
            <table class="table table-sm table-bordered mb-0"><thead class="table-light"><tr><th>Unit</th><th>Total</th></tr></thead>
              <tbody><?php foreach ($staffByUnit as $row): ?><tr><td><?= htmlspecialchars($row->unitName) ?></td><td><?= htmlspecialchars($row->total) ?></td></tr><?php endforeach; ?></tbody>
            </table>
          <?php else: ?><div class="p-3 text-muted">No units found.</div><?php endif; ?>
        </div>
      </div>
    </div>
    <div class="col-md-8 mb-4">
      <div class="card border-0 shadow-sm">
        <div class="card-header bg-info text-white"><strong>Staff per Province</strong></div>
        <div class="card-body p-0" style="max-height:350px;overflow-y:auto;">
          <?php if ($staffByProvince): ?>
            <table class="table table-sm table-bordered mb-0"><thead class="table-light"><tr><th>Province</th><th>Total</th></tr></thead>
              <tbody><?php foreach ($staffByProvince as $row): ?><tr><td><?= htmlspecialchars($row->province) ?></td><td><?= htmlspecialchars($row->total) ?></td></tr><?php endforeach; ?></tbody>
            </table>
          <?php else: ?><div class="p-3 text-muted">No provinces found.</div><?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div></div></div>
<?php include dirname(__DIR__) . '/shared/footer.php'; ?>
