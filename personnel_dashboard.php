<?php
/**
 * ARMIS role-specific branch dashboard for write-capable branch personnel:
 * DAG, DDG, CC and SOI/SOII/SOIII.
 * The dashboard is scoped to the user's actual staff.branch_id.
 */
require_once __DIR__ . '/shared/module_auth.php';
require_once __DIR__ . '/shared/permissions.php';

$role = strtolower((string)($_SESSION['role'] ?? ''));
$allowed = ['dag','ddg','cc','soi','soii','soiii'];
if (!in_array($role, $allowed, true)) {
    redirectToRoleDashboard($role);
}

$branchId = getUserBranch();
$branch = $branchId ? getBranchById($branchId) : null;
if (!$branch) {
    http_response_code(403);
    exit('A branch posting is required for this dashboard.');
}
$module = strtolower((string)$branch['code']);
if (!hasModuleAccess($module)) {
    http_response_code(403);
    exit('Your role is not assigned to this branch module.');
}

$moduleName = $branch['name'];
$moduleIcon = $branch['icon'] ?: 'sitemap';
$currentPage = 'dashboard';
$pageTitle = $moduleName . ' Personnel Dashboard - ARMIS';
$sidebarLinks = getModuleSidebarLinks($module);

$pdo = getDbConnection();
$scope = getSnapshotScope();
$where = '';
$params = [];
if (($scope['scope'] ?? 'none') === 'branch') {
    $where = ' WHERE s.branch_id = :branch_id';
    $params['branch_id'] = $branchId;
} elseif (($scope['scope'] ?? 'none') === 'all') {
    $where = '';
} else {
    http_response_code(403);
    exit('No personnel analytics scope is assigned to this account.');
}

function armisDashboardScalar(PDO $pdo, string $sql, array $params = []): int {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (int)$stmt->fetchColumn();
}

$total = armisDashboardScalar($pdo, "SELECT COUNT(*) FROM staff s" . $where, $params);
$active = armisDashboardScalar($pdo, "SELECT COUNT(*) FROM staff s" . ($where ? $where . " AND s.svcStatus='Active'" : " WHERE s.svcStatus='Active'"), $params);
$inactive = max(0, $total - $active);
$units = armisDashboardScalar($pdo, "SELECT COUNT(DISTINCT s.unitId) FROM staff s" . ($where ? $where . " AND s.unitId IS NOT NULL" : " WHERE s.unitId IS NOT NULL"), $params);
$recentPostings = armisDashboardScalar($pdo, "SELECT COUNT(*) FROM staff_appointment sa INNER JOIN staff s ON s.svcNo=sa.svcNo WHERE sa.apptWef >= (CURDATE() - INTERVAL 30 DAY)" . ($where ? " AND s.branch_id=:branch_id" : ''), $params);

$rankSql = "SELECT " . getRankCategoryCaseSQL('r') . " AS category, COUNT(*) AS total FROM staff s LEFT JOIN `rank` r ON r.rankId=s.rankId" . $where . " GROUP BY category";
$stmt = $pdo->prepare($rankSql); $stmt->execute($params);
$categories = ['Officer'=>0,'NCO'=>0,'CE'=>0];
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $cat = (string)$row['category']; $n=(int)$row['total'];
    if (in_array($cat, ['Officer','Officer Cadet'], true)) $categories['Officer'] += $n;
    elseif (in_array($cat, ['NCO','Recruit'], true)) $categories['NCO'] += $n;
    elseif ($cat === 'CE') $categories['CE'] += $n;
}

$roleNames = ['dag'=>'Deputy Adjutant General','ddg'=>'Deputy Director General','cc'=>'Chief Clerk','soi'=>'Staff Officer I','soii'=>'Staff Officer II','soiii'=>'Staff Officer III'];
$roleName = $roleNames[$role] ?? ucfirst($role);

include __DIR__ . '/shared/header.php';
include __DIR__ . '/shared/sidebar.php';
?>
<div class="content-wrapper with-sidebar"><div class="container-fluid"><div class="main-content">
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
      <div class="text-muted small text-uppercase fw-semibold">Branch Personnel Management</div>
      <h1 class="section-title mb-1"><i class="fas fa-user-tie me-2"></i><?= htmlspecialchars($roleName) ?> Dashboard</h1>
      <p class="text-muted mb-0">Branch-based personnel analytics for <strong><?= htmlspecialchars($moduleName) ?></strong>.</p>
    </div>
    <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2"><i class="fas fa-lock me-1"></i>Branch Scoped</span>
  </div>

  <div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3"><div class="card h-100 shadow-sm"><div class="card-body"><div class="text-muted small">Branch Personnel</div><div class="display-6 fw-bold"><?= number_format($total) ?></div><div class="small text-muted">Current branch roster</div></div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="card h-100 shadow-sm"><div class="card-body"><div class="text-muted small">Active Personnel</div><div class="display-6 fw-bold"><?= number_format($active) ?></div><div class="small text-muted"><?= $total ? round(($active/$total)*100) : 0 ?>% of branch roster</div></div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="card h-100 shadow-sm"><div class="card-body"><div class="text-muted small">Units Represented</div><div class="display-6 fw-bold"><?= number_format($units) ?></div><div class="small text-muted">Distinct units in branch</div></div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="card h-100 shadow-sm"><div class="card-body"><div class="text-muted small">Postings · 30 Days</div><div class="display-6 fw-bold"><?= number_format($recentPostings) ?></div><div class="small text-muted">Recorded appointment movements</div></div></div></div>
  </div>

  <div class="row g-3 mb-4">
    <div class="col-md-4"><div class="card h-100"><div class="card-body"><div class="text-muted small">Officers</div><h3 class="mb-0"><?= number_format($categories['Officer']) ?></h3></div></div></div>
    <div class="col-md-4"><div class="card h-100"><div class="card-body"><div class="text-muted small">NCOs / Recruits</div><h3 class="mb-0"><?= number_format($categories['NCO']) ?></h3></div></div></div>
    <div class="col-md-4"><div class="card h-100"><div class="card-body"><div class="text-muted small">Civilian Employees</div><h3 class="mb-0"><?= number_format($categories['CE']) ?></h3></div></div></div>
  </div>

  <div class="card shadow-sm"><div class="card-body">
    <div class="d-flex justify-content-between align-items-center mb-3"><div><h5 class="mb-1">Branch Management Workspace</h5><p class="text-muted mb-0">Only functions available to your role are shown in the sidebar.</p></div><i class="fas fa-shield-alt fa-2x text-muted"></i></div>
    <div class="row g-3">
      <?php if (hasPermission(PERM_VIEW_STAFF)): ?><div class="col-md-4"><a class="btn btn-outline-primary w-100 py-3" href="/Armis2/<?= htmlspecialchars($module) ?>/roster.php"><i class="fas fa-users me-2"></i>Branch Roster</a></div><?php endif; ?>
      <?php if (hasPermission(PERM_EDIT_STAFF)): ?><div class="col-md-4"><a class="btn btn-outline-primary w-100 py-3" href="/Armis2/users/"><i class="fas fa-user-edit me-2"></i>Personnel Records</a></div><?php endif; ?>
      <?php if (hasPermission(PERM_VIEW_REPORTS)): ?><div class="col-md-4"><a class="btn btn-outline-secondary w-100 py-3" href="/Armis2/<?= htmlspecialchars($module) ?>/reports.php"><i class="fas fa-chart-bar me-2"></i>Branch Reports</a></div><?php endif; ?>
    </div>
  </div></div>
  <p class="text-muted small mt-3"><i class="fas fa-database me-1"></i>Analytics are calculated from personnel whose actual <code>staff.branch_id</code> matches this branch. Unassigned personnel are excluded.</p>
</div></div></div>
<?php include __DIR__ . '/shared/footer.php'; ?>
