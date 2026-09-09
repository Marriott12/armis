<?php
/**
 * Command Dashboard. Previously: hardcoded $sidebarLinks out of sync with
 * courses.php, pointing "Operational Reports" at a nonexistent
 * /command/operations.php, and a "Command Overview" section with entirely
 * fabricated numbers that never touched the database. Both fixed below.
 */
require_once dirname(__DIR__) . '/shared/module_auth.php';
require_once dirname(__DIR__) . '/shared/rank_levels.php';

bootModule(['module' => 'command', 'page' => 'dashboard', 'pageTitle' => 'Command Dashboard - ARMIS', 'moduleName' => 'Command', 'moduleIcon' => 'chess-king']);

$pdo = getDbConnection();
$scope = getSnapshotScope();
$branchWhere = ''; $branchParams = [];
if ($scope['scope'] === 'branch') { $branchWhere = ' AND s.branch_id = :branch_id'; $branchParams['branch_id'] = $scope['branch_id']; }

function scalar(PDO $pdo, $sql, $params) { $s = $pdo->prepare($sql); $s->execute($params); return (int)$s->fetchColumn(); }

$totalStaff  = scalar($pdo, "SELECT COUNT(*) FROM staff s WHERE 1=1 $branchWhere", $branchParams);
$activeStaff = scalar($pdo, "SELECT COUNT(*) FROM staff s WHERE s.svcStatus='Active' $branchWhere", $branchParams);
$readyRate   = $totalStaff > 0 ? round(($activeStaff / $totalStaff) * 100) : 0;
$unitCount   = $scope['scope'] === 'branch'
    ? scalar($pdo, "SELECT COUNT(DISTINCT s.unitId) FROM staff s WHERE s.unitId IS NOT NULL $branchWhere", $branchParams)
    : scalar($pdo, "SELECT COUNT(*) FROM unit", []);
$recordedOperations = scalar($pdo, "SELECT COUNT(*) FROM operation", []);
$recentPostings = scalar($pdo, "SELECT COUNT(*) FROM staff_appointment sa INNER JOIN staff s ON sa.svcNo=s.svcNo WHERE sa.apptWef >= (CURDATE() - INTERVAL 30 DAY) $branchWhere", $branchParams);

$categoryStmt = $pdo->prepare("SELECT " . getRankCategoryCaseSQL('r') . " AS category, COUNT(*) AS total FROM staff s LEFT JOIN `rank` r ON s.rankId=r.rankId WHERE 1=1 $branchWhere GROUP BY category");
$categoryStmt->execute($branchParams);
$categoryCounts = ['Officer' => 0, 'NCO' => 0, 'CE' => 0];
foreach ($categoryStmt->fetchAll(PDO::FETCH_OBJ) as $row) {
    if (in_array($row->category, ['Officer', 'Officer Cadet'])) $categoryCounts['Officer'] += (int)$row->total;
    elseif (in_array($row->category, ['NCO', 'Recruit'])) $categoryCounts['NCO'] += (int)$row->total;
    elseif ($row->category === 'CE') $categoryCounts['CE'] += (int)$row->total;
}

include dirname(__DIR__) . '/shared/header.php';
include dirname(__DIR__) . '/shared/sidebar.php';
?>
<div class="content-wrapper with-sidebar"><div class="container-fluid"><div class="main-content">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="section-title"><i class="fas fa-chess-king"></i> Command Dashboard</h1>
    <span class="badge status-badge"><?= htmlspecialchars($scope['scope'] === 'all' ? 'Whole-Army View' : ($scope['branch_name'] ?? 'Command')) ?></span>
  </div>
  <div class="row g-4">
    <div class="col-md-6 col-lg-2"><div class="card module-card"><div class="card-body text-center">
      <div class="mb-3"><i class="fas fa-users fa-2x text-primary"></i></div><h5 class="card-title">Branch Roster</h5>
      <p class="card-text">Seniority-ordered roster by category</p><a href="roster.php" class="btn btn-armis">View Roster</a>
    </div></div></div>
    <div class="col-md-6 col-lg-2"><div class="card module-card"><div class="card-body text-center">
      <div class="mb-3"><i class="fas fa-file-alt fa-2x text-primary"></i></div><h5 class="card-title">Operational Reports</h5>
      <p class="card-text">Staff distribution by unit &amp; province</p><a href="op_reports.php" class="btn btn-armis">View Reports</a>
    </div></div></div>
    <div class="col-md-6 col-lg-2"><div class="card module-card"><div class="card-body text-center">
      <div class="mb-3"><i class="fas fa-id-card fa-2x text-success"></i></div><h5 class="card-title">Staff Profiles</h5>
      <p class="card-text">Detailed personnel profiles &amp; search</p><a href="profiles.php" class="btn btn-armis">View Profiles</a>
    </div></div></div>
    <div class="col-md-6 col-lg-2"><div class="card module-card"><div class="card-body text-center">
      <div class="mb-3"><i class="fas fa-chart-line fa-2x text-warning"></i></div><h5 class="card-title">Command Reports</h5>
      <p class="card-text">Category, gender, rank &amp; province breakdowns</p><a href="reports.php" class="btn btn-armis">Generate Reports</a>
    </div></div></div>
    <div class="col-md-6 col-lg-2"><div class="card module-card"><div class="card-body text-center">
      <div class="mb-3"><i class="fas fa-graduation-cap fa-2x text-info"></i></div><h5 class="card-title">Course Records</h5>
      <p class="card-text">Qualifications &amp; training history</p><a href="courses.php" class="btn btn-armis">View Courses</a>
    </div></div></div>
  </div>
  <div class="row mt-5"><div class="col-12"><h3 class="section-title mb-4">Command Overview</h3></div>
    <div class="col-md-6 col-lg-3"><div class="card bg-primary text-white h-100"><div class="card-body d-flex justify-content-between">
      <div><h6 class="text-white-75 mb-1">Total Personnel</h6><h2 class="mb-0"><?= number_format($totalStaff) ?></h2></div><i class="fas fa-users fa-2x"></i>
    </div></div></div>
    <div class="col-md-6 col-lg-3"><div class="card bg-success text-white h-100"><div class="card-body d-flex justify-content-between">
      <div><h6 class="text-white-75 mb-1">Active Personnel Rate</h6><h2 class="mb-0"><?= $readyRate ?>%</h2></div><i class="fas fa-user-check fa-2x"></i>
    </div></div></div>
    <div class="col-md-6 col-lg-3"><div class="card bg-info text-white h-100"><div class="card-body d-flex justify-content-between">
      <div><h6 class="text-white-75 mb-1"><?= $scope['scope'] === 'all' ? 'Total Units' : 'Units in Branch' ?></h6><h2 class="mb-0"><?= number_format($unitCount) ?></h2></div><i class="fas fa-sitemap fa-2x"></i>
    </div></div></div>
    <div class="col-md-6 col-lg-3"><div class="card bg-warning text-white h-100"><div class="card-body d-flex justify-content-between">
      <div><h6 class="text-white-75 mb-1">Postings (Last 30 Days)</h6><h2 class="mb-0"><?= number_format($recentPostings) ?></h2></div><i class="fas fa-file-signature fa-2x"></i>
    </div></div></div>
  </div>
  <div class="row mt-3">
    <div class="col-md-4"><div class="card h-100"><div class="card-body text-center"><h6 class="text-muted">Officers</h6><h3><?= number_format($categoryCounts['Officer']) ?></h3></div></div></div>
    <div class="col-md-4"><div class="card h-100"><div class="card-body text-center"><h6 class="text-muted">NCOs</h6><h3><?= number_format($categoryCounts['NCO']) ?></h3></div></div></div>
    <div class="col-md-4"><div class="card h-100"><div class="card-body text-center"><h6 class="text-muted">Civilian Employees</h6><h3><?= number_format($categoryCounts['CE']) ?></h3></div></div></div>
  </div>
  <p class="text-muted small mt-3">
    <i class="fas fa-database"></i> Recorded operations in the system: <?= number_format($recordedOperations) ?>.
    Figures on this page are computed live from the database and reflect <?= $scope['scope'] === 'all' ? 'the whole Army' : 'your branch only' ?>.
  </p>
</div></div></div>
<?php include dirname(__DIR__) . '/shared/footer.php'; ?>
