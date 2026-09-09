<?php
require_once dirname(__DIR__) . '/shared/module_auth.php';
bootModule(['module' => 'command', 'page' => 'courses', 'pageTitle' => 'Course Records - Command - ARMIS', 'moduleName' => 'Command', 'moduleIcon' => 'chess-king']);

$pdo = getDbConnection();
$scope = getSnapshotScope();
$search = trim($_GET['search'] ?? '');
$params = []; $where = [];
if ($scope['scope'] === 'branch') { $where[] = 's.branch_id = :branch_id'; $params['branch_id'] = $scope['branch_id']; }
if ($search !== '') {
    $where[] = '(s.svcNo LIKE :s1 OR s.fName LIKE :s2 OR s.lName LIKE :s3 OR sc.qualification LIKE :s4 OR sc.cseId LIKE :s5)';
    $params['s1'] = "%$search%"; $params['s2'] = "%$search%"; $params['s3'] = "%$search%"; $params['s4'] = "%$search%"; $params['s5'] = "%$search%";
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$stmt = $pdo->prepare("
    SELECT sc.id, sc.svcNo, s.fName, s.lName, sc.instId, i.instLoc, sc.cseId, c.cseType, c.cseLevel,
           sc.qualification, sc.cseStart, sc.cseEnd, sc.grade, sc.result, sc.isHighest
    FROM staff_course sc
    INNER JOIN staff s ON sc.svcNo = s.svcNo
    LEFT JOIN institution i ON sc.instId = i.instId
    LEFT JOIN course c ON sc.cseId = c.cseId
    $whereSql ORDER BY sc.cseEnd DESC, sc.cseStart DESC LIMIT 200
");
$stmt->execute($params);
$records = $stmt->fetchAll(PDO::FETCH_OBJ);

include dirname(__DIR__) . '/shared/header.php';
include dirname(__DIR__) . '/shared/sidebar.php';
?>
<div class="content-wrapper with-sidebar"><div class="container-fluid"><div class="main-content">
  <h2 class="mb-4"><i class="fa fa-graduation-cap"></i> Course Records</h2>
  <form class="mb-3" method="get" action=""><div class="input-group" style="max-width:420px;">
    <input type="text" name="search" class="form-control" placeholder="Search name, service no, qualification..." value="<?= htmlspecialchars($search) ?>">
    <button class="btn btn-success" type="submit"><i class="fa fa-search"></i></button>
  </div></form>
  <div class="card border-0 shadow-sm">
    <div class="card-header bg-success text-white d-flex justify-content-between">
      <strong>Course &amp; Qualification Records</strong>
      <span class="badge bg-light text-success"><?= count($records) ?> record<?= count($records) === 1 ? '' : 's' ?></span>
    </div>
    <div class="card-body p-0"><div class="table-responsive">
      <table class="table table-striped table-hover mb-0">
        <thead class="table-dark"><tr><th>Svc No</th><th>Name</th><th>Qualification</th><th>Course</th><th>Institution</th><th>Start</th><th>End</th><th>Grade</th><th>Result</th><th>Highest</th></tr></thead>
        <tbody>
          <?php foreach ($records as $r): ?>
          <tr>
            <td><?= htmlspecialchars($r->svcNo) ?></td>
            <td><?= htmlspecialchars($r->fName . ' ' . $r->lName) ?></td>
            <td><?= htmlspecialchars($r->qualification ?? '') ?></td>
            <td><?= htmlspecialchars($r->cseId ?? '—') ?><?php if ($r->cseType): ?><br><small class="text-muted"><?= htmlspecialchars($r->cseType) ?> · <?= htmlspecialchars($r->cseLevel ?? '') ?></small><?php endif; ?></td>
            <td><?= htmlspecialchars($r->instId ?? '—') ?><?= $r->instLoc ? ' (' . htmlspecialchars($r->instLoc) . ')' : '' ?></td>
            <td><?= htmlspecialchars($r->cseStart ?? '') ?></td>
            <td><?= htmlspecialchars($r->cseEnd ?? '') ?></td>
            <td><?= htmlspecialchars($r->grade ?? '') ?></td>
            <td><?= htmlspecialchars($r->result ?? '') ?></td>
            <td><?= $r->isHighest ? '<span class="badge bg-success">Yes</span>' : '' ?></td>
          </tr>
          <?php endforeach; ?>
          <?php if (!$records): ?><tr><td colspan="10" class="text-center text-muted py-4">No course records found<?= $search ? ' for that search' : '' ?>.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div></div>
  </div>
</div></div></div>
<?php include dirname(__DIR__) . '/shared/footer.php'; ?>
