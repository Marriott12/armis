<?php
/** ARMIS role-specific branch dashboard for DG (read-only oversight). */
require_once __DIR__ . '/shared/module_auth.php';
require_once __DIR__ . '/shared/permissions.php';
$role = strtolower((string)($_SESSION['role'] ?? ''));
if ($role !== 'dg') { redirectToRoleDashboard($role); }
$branchId = getUserBranch();
$branch = $branchId ? getBranchById($branchId) : null;
if (!$branch) { http_response_code(403); exit('A branch posting is required for this dashboard.'); }
$module = strtolower((string)$branch['code']);
if (!hasModuleAccess($module)) { http_response_code(403); exit('Your role is not assigned to this branch module.'); }
$moduleName = $branch['name']; $moduleIcon = $branch['icon'] ?: 'sitemap'; $currentPage='dashboard'; $pageTitle=$moduleName.' Oversight Dashboard - ARMIS';
$sidebarLinks = getModuleSidebarLinks($module);
$pdo=getDbConnection(); $scope=getSnapshotScope(); $where=''; $params=[];
if (($scope['scope']??'none')==='branch') { $where=' WHERE s.branch_id=:branch_id'; $params['branch_id']=$branchId; }
elseif (($scope['scope']??'none')!=='all') { http_response_code(403); exit('No personnel analytics scope is assigned to this account.'); }
function armisOversightScalar(PDO $pdo,string $sql,array $params=[]):int { $st=$pdo->prepare($sql);$st->execute($params);return (int)$st->fetchColumn(); }
$total=armisOversightScalar($pdo,"SELECT COUNT(*) FROM staff s".$where,$params);
$active=armisOversightScalar($pdo,"SELECT COUNT(*) FROM staff s".($where?$where." AND s.svcStatus='Active'":" WHERE s.svcStatus='Active'"),$params);
$units=armisOversightScalar($pdo,"SELECT COUNT(DISTINCT s.unitId) FROM staff s".($where?$where." AND s.unitId IS NOT NULL":" WHERE s.unitId IS NOT NULL"),$params);
$recent=armisOversightScalar($pdo,"SELECT COUNT(*) FROM staff_appointment sa INNER JOIN staff s ON s.svcNo=sa.svcNo WHERE sa.apptWef >= (CURDATE() - INTERVAL 30 DAY)".($where?" AND s.branch_id=:branch_id":''),$params);
include __DIR__.'/shared/header.php'; include __DIR__.'/shared/sidebar.php';
?>
<div class="content-wrapper with-sidebar"><div class="container-fluid"><div class="main-content">
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4"><div><div class="text-muted small text-uppercase fw-semibold">Branch Oversight</div><h1 class="section-title mb-1"><i class="fas fa-chart-line me-2"></i>Director General Dashboard</h1><p class="text-muted mb-0">Read-only branch analytics for <strong><?=htmlspecialchars($moduleName)?></strong>.</p></div><span class="badge bg-secondary-subtle text-secondary border px-3 py-2"><i class="fas fa-eye me-1"></i>Read Only</span></div>
<div class="row g-3"><div class="col-sm-6 col-xl-3"><div class="card h-100"><div class="card-body"><div class="text-muted small">Personnel</div><div class="display-6 fw-bold"><?=number_format($total)?></div></div></div></div><div class="col-sm-6 col-xl-3"><div class="card h-100"><div class="card-body"><div class="text-muted small">Active</div><div class="display-6 fw-bold"><?=number_format($active)?></div><div class="small text-muted"><?=$total?round(($active/$total)*100):0?>% active rate</div></div></div></div><div class="col-sm-6 col-xl-3"><div class="card h-100"><div class="card-body"><div class="text-muted small">Units</div><div class="display-6 fw-bold"><?=number_format($units)?></div></div></div></div><div class="col-sm-6 col-xl-3"><div class="card h-100"><div class="card-body"><div class="text-muted small">Postings · 30 Days</div><div class="display-6 fw-bold"><?=number_format($recent)?></div></div></div></div></div>
<div class="card mt-4"><div class="card-body"><h5>Oversight View</h5><p class="text-muted mb-0">You have read-only visibility of your posted branch. Management actions are intentionally not displayed or exposed to this role.</p></div></div>
</div></div></div>
<?php include __DIR__.'/shared/footer.php'; ?>
