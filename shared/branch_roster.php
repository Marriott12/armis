<?php
/**
 * NEW FILE (branch-scoping upgrade). Shared branch roster component.
 *
 * Command, Operations, Training, Finance and Ordinance don't (yet) have
 * their own staff-editing UI the way admin_branch does - Finance and
 * Ordinance in particular are still stub modules. Rather than build five
 * near-identical dashboards, each module's roster.php just sets a couple of
 * variables and includes this file.
 *
 * Expected variables set by the caller before including this file:
 *   $__branchCode  - the branch's `code` in the `branches` table (required)
 *   $__moduleName  - display name for the page header, e.g. "Training"
 *   $__moduleIcon  - Font Awesome icon name for the page header
 *   $__sidebarLinks (optional) - if the calling module already builds its
 *                    own $sidebarLinks array, set it before including this
 *                    file and it will be used instead of the minimal default
 *                    below.
 */

require_once dirname(__DIR__) . '/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__) . '/shared/session_guard.php';
require_once dirname(__DIR__) . '/shared/database_connection.php';
require_once dirname(__DIR__) . '/shared/module_navigation.php';
require_once dirname(__DIR__) . '/shared/pagination.php';
require_once dirname(__DIR__) . '/shared/rank_levels.php';
require_once dirname(__DIR__) . '/shared/rbac.php';

enforceSessionTimeout();

if (empty($__branchCode)) {
    die('branch_roster.php included without $__branchCode set.');
}

if (!isset($_SESSION['user_id'])) {
    header('Location: /Armis2/login.php');
    exit();
}

requireModuleAccess($__branchCode);

$__branch = getBranchByCode($__branchCode);
if (!$__branch) {
    header('Location: /Armis2/unauthorized.php?from=' . urlencode($__branchCode));
    exit();
}

$__scope = getSnapshotScope();
// Safety net: if a role with no relationship to this branch somehow reaches
// here (shouldn't happen - requireModuleAccess() already gated it), bounce.
if ($__scope['scope'] === 'none') {
    header('Location: /Armis2/unauthorized.php?from=' . urlencode($__branchCode));
    exit();
}

$__roleInfo = getRoleInfo();
$__canWrite = ($__roleInfo['access'] ?? '') === 'write';
$__statusFilter = $_GET['status'] ?? 'Active';
$__validStatuses = ['Active', 'Retired', 'AWOL', 'Discharged', 'On Contract', 'Deceased'];
if (!in_array($__statusFilter, $__validStatuses, true)) $__statusFilter = '';
$__reportType = $_GET['report_type'] ?? 'all';
if (!in_array($__reportType, ['all', 'officer', 'nco', 'ce'], true)) $__reportType = 'all';

$pdo = getDbConnection();

// Whole-Army (org-wide branch, e.g. Admin Branch) vs single-branch roster
$__rosterConditions = [];
$__rosterParams = $__scope['scope'] === 'branch' ? ['branch_id' => $__scope['branch_id']] : [];
$__rosterConditions = $__scope['scope'] === 'branch' ? ['s.branch_id = :branch_id'] : [];
if ($__statusFilter !== '') { $__rosterConditions[] = 's.svcStatus = :status'; $__rosterParams['status'] = $__statusFilter; }
if ($__reportType === 'officer') $__rosterConditions[] = 'r.rankIndex BETWEEN ' . RANK_OFFICER_MIN . ' AND ' . RANK_OFFICER_CADET;
if ($__reportType === 'nco') $__rosterConditions[] = 'r.rankIndex BETWEEN ' . RANK_NCO_MIN . ' AND ' . RANK_RECRUIT;
if ($__reportType === 'ce') $__rosterConditions[] = 'r.rankIndex = ' . RANK_CIVILIAN;
$__rosterWhere = $__rosterConditions ? 'WHERE ' . implode(' AND ', $__rosterConditions) : '';
$__summaryStmt = $pdo->prepare("SELECT COUNT(*) AS total, COALESCE(SUM(s.svcStatus = 'Active'), 0) AS active FROM staff s LEFT JOIN `rank` r ON r.rankId = s.rankId $__rosterWhere");
$__summaryStmt->execute($__rosterParams);
$__rosterSummary = $__summaryStmt->fetch();
$__pagination = getPagination((int) $__rosterSummary['total']);
$__stmt = $pdo->prepare("
    SELECT s.svcNo, s.prefix, s.rankId, s.fName, s.mName, s.lName, s.svcStatus, s.subWef, s.tempWef, s.attestDate, s.branch_id,
        r.rankIndex, " . getRankCategoryCaseSQL('r') . " AS category, COALESCE(u.unitId, 'Unassigned') AS unit_name, b.name AS branch_name
  FROM staff s
    LEFT JOIN `rank` r ON r.rankId = s.rankId
      LEFT JOIN unit u ON u.unitId = s.unitId
  LEFT JOIN branches b ON b.id = s.branch_id
  $__rosterWhere
    ORDER BY r.rankIndex ASC, s.subWef ASC, s.tempWef ASC, s.attestDate ASC, s.svcNo ASC
  LIMIT {$__pagination['perPage']} OFFSET {$__pagination['offset']}
");
$__stmt->execute($__rosterParams);
$__roster = $__stmt->fetchAll();

$pageTitle = $__moduleName . ' — Roster';
$moduleName = $__moduleName;
$moduleIcon = $__moduleIcon ?? $__branch['icon'];
$currentPage = 'roster';
$moduleStylesheet = $__branchCode === 'command' ? '/Armis2/command/module.css' : null;

if (empty($sidebarLinks)) {
  $sidebarLinks = getModuleSidebarLinks($__branchCode);
  if (empty($sidebarLinks)) {
    $sidebarLinks = [
        ['title' => 'Dashboard', 'url' => $__branch['url_path'] ?? ('/Armis2/' . $__branchCode . '/index.php'), 'icon' => 'tachometer-alt', 'page' => 'dashboard'],
        ['title' => 'Roster', 'url' => '/Armis2/' . $__branchCode . '/roster.php', 'icon' => 'users', 'page' => 'roster'],
    ];
  }
}

if (!isset($__skipLayout) || !$__skipLayout) {
    include dirname(__DIR__) . '/shared/header.php';
    include dirname(__DIR__) . '/shared/sidebar.php';
}
?>

<div class="content-wrapper with-sidebar">
  <div class="container-fluid">
    <div class="main-content">

      <div id="rosterNotice" class="alert d-none" role="status" aria-live="polite"></div>

      <?php if (!$__canWrite): ?>
      <div class="alert alert-info">
        <i class="fas fa-eye"></i>
        <?= $__scope['scope'] === 'all' ? 'Whole-Army snapshot — read-only.' : 'Snapshot of ' . htmlspecialchars($__branch['name']) . ' only — read-only.' ?>
      </div>
      <?php endif; ?>

      <div class="row g-4 mb-4">
        <div class="col-md-4">
          <div class="card bg-primary text-white h-100">
            <div class="card-body">
              <h6 class="card-title text-white-75">Total Staff<?= $__scope['scope'] === 'all' ? ' (Army-wide)' : ' — ' . htmlspecialchars($__branch['name']) ?></h6>
              <h2 class="display-6 text-white"><?= number_format($__rosterSummary['total']) ?></h2>
            </div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="card bg-success text-white h-100">
            <div class="card-body">
              <h6 class="card-title text-white-75">Active</h6>
              <h2 class="display-6 text-white"><?= number_format($__rosterSummary['active']) ?></h2>
            </div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="card h-100" style="background:<?= $__canWrite ? '#198754' : '#6c757d' ?>;color:#fff;">
            <div class="card-body">
              <h6 class="card-title text-white-75">Your Access</h6>
              <h4 class="text-white"><?= $__canWrite ? 'Can alter records' : 'View only' ?></h4>
            </div>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2"><h5 class="card-title mb-0"><i class="fas fa-users"></i> Roster</h5><span class="text-muted small"><?= number_format($__pagination['total']) ?> personnel</span></div>
        <div class="card-body">
          <ul class="nav nav-tabs mb-3" aria-label="Seniority roster category">
            <?php foreach (['all' => 'All personnel', 'officer' => 'Officers', 'nco' => 'NCOs', 'ce' => 'Civilian Employees'] as $type => $label): ?><li class="nav-item"><a class="nav-link<?= $__reportType === $type ? ' active' : '' ?>" href="?<?= htmlspecialchars(http_build_query(array_merge($_GET, ['report_type' => $type, 'page' => 1])), ENT_QUOTES) ?>"><?= $label ?></a></li><?php endforeach; ?>
          </ul>
          <form class="d-flex flex-wrap gap-2 mb-3" method="get"><input type="hidden" name="report_type" value="<?= htmlspecialchars($__reportType) ?>"><label class="visually-hidden" for="roster-status">Service status</label><select id="roster-status" class="form-select form-select-sm w-auto" name="status"><option value="">All statuses</option><?php foreach ($__validStatuses as $status): ?><option value="<?= htmlspecialchars($status) ?>"<?= $__statusFilter === $status ? ' selected' : '' ?>><?= htmlspecialchars($status) ?></option><?php endforeach; ?></select><button class="btn btn-sm btn-outline-primary" type="submit">Filter</button><a class="btn btn-sm btn-outline-secondary" href="?<?= htmlspecialchars(http_build_query(['report_type' => $__reportType]), ENT_QUOTES) ?>">Active only</a></form>
          <div class="table-responsive">
            <table class="table table-striped table-hover" data-command-table>
              <thead class="table-dark">
                <tr>
                  <th>Svc No</th><th>Rank</th><th>Category</th><th>Name</th><th>Substantive from</th><th>Unit</th>
                  <?php if ($__scope['scope'] === 'all'): ?><th>Branch</th><?php endif; ?>
                  <th>Status</th>
                  <?php if ($__canWrite): ?><th>Action</th><?php endif; ?>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($__roster as $r): ?>
                <tr>
                  <td><?= htmlspecialchars($r['svcNo']) ?></td>
                  <td><?= htmlspecialchars($r['rankId'] ?? '') ?></td>
                  <td><?= htmlspecialchars(getRosterGroup($r['category'] ?? '')) ?></td>
                  <td><?= htmlspecialchars(trim(($r['fName'] ?? '') . (!empty($r['mName']) ? ' ' . $r['mName'] : '') . ' ' . ($r['lName'] ?? ''))) ?></td>
                  <td><?= htmlspecialchars($r['subWef'] ?? $r['tempWef'] ?? $r['attestDate'] ?? '') ?></td>
                  <td><?= htmlspecialchars($r['unit_name']) ?></td>
                  <?php if ($__scope['scope'] === 'all'): ?><td><?= htmlspecialchars($r['branch_name'] ?? 'Unassigned') ?></td><?php endif; ?>
                  <td><span class="badge bg-<?= $r['svcStatus'] === 'Active' ? 'success' : 'secondary' ?>"><?= htmlspecialchars($r['svcStatus'] ?? '') ?></span></td>
                  <?php if ($__canWrite): ?>
                    <td>
                      <button class="btn btn-outline-primary btn-sm" onclick='openRosterEdit(<?= json_encode($r) ?>)' data-bs-toggle="modal" data-bs-target="#rosterEditModal">
                        <i class="fas fa-edit"></i> Edit
                      </button>
                    </td>
                  <?php endif; ?>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($__roster)): ?>
                  <tr><td colspan="<?= 7 + ($__scope['scope'] === 'all' ? 1 : 0) + ($__canWrite ? 1 : 0) ?>" class="text-center text-muted py-4">No staff currently assigned to this branch.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
        <?php if ($__pagination['total'] > 0): ?><div class="card-footer d-flex flex-wrap justify-content-between align-items-center gap-2"><small class="text-muted">Showing <?= $__pagination['offset'] + 1 ?>-<?= min($__pagination['offset'] + $__pagination['perPage'], $__pagination['total']) ?> of <?= number_format($__pagination['total']) ?></small><?= renderPagination($__pagination) ?></div><?php endif; ?>
      </div>

    </div>
  </div>
</div>

<?php if ($__canWrite): ?>
<div class="modal fade" id="rosterEditModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Update Service Status</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form id="rosterEditForm">
        <div class="modal-body">
          <input type="hidden" name="svcNo" id="r_svcNo">
          <input type="hidden" name="module" value="<?= htmlspecialchars($__branchCode) ?>">
          <div class="mb-3">
            <label class="form-label">Service Number</label>
            <input type="text" class="form-control" id="r_svcNo_display" readonly>
          </div>
          <div class="mb-3">
            <label class="form-label">Service Status</label>
            <select class="form-select" name="svcStatus" id="r_svcStatus">
              <option value="Active">Active</option>
              <option value="Retired">Retired</option>
              <option value="AWOL">AWOL</option>
              <option value="Discharged">Discharged</option>
              <option value="On Contract">On Contract</option>
              <option value="Deceased">Deceased</option>
            </select>
          </div>
          <div class="form-check"><input class="form-check-input" type="checkbox" value="1" id="r_confirm" required><label class="form-check-label" for="r_confirm">I confirm this service-status change.</label></div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>
<script>
function openRosterEdit(r) {
  document.getElementById('r_svcNo').value = r.svcNo;
  document.getElementById('r_svcNo_display').value = r.svcNo;
  const statusSelect = document.getElementById('r_svcStatus');
  statusSelect.value = r.svcStatus || 'Active';
  statusSelect.dataset.previous = statusSelect.value;
  document.getElementById('r_confirm').checked = false;
}
document.getElementById('rosterEditForm').addEventListener('submit', async function (e) {
  e.preventDefault();
  const fd = new FormData(this);
  const submitButton = this.querySelector('[type="submit"]');
  const previousStatus = document.getElementById('r_svcStatus').dataset.previous || document.getElementById('r_svcStatus').value;
  const nextStatus = document.getElementById('r_svcStatus').value;
  const notice = document.getElementById('rosterNotice');
  const showNotice = (message, type) => {
    notice.className = 'alert alert-' + type;
    notice.textContent = message;
  };
  submitButton.disabled = true;
  try {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const res = await fetch('/Armis2/shared/branch_roster_save.php', {
      method: 'POST',
      body: fd,
      headers: { 'Accept': 'application/json', 'X-CSRF-Token': csrfToken }
    });
    const data = await res.json();
    if (!res.ok || !data.ok) {
      showNotice(data.message || data.error || 'The status could not be updated.', 'danger');
      return;
    }
    showNotice(data.message || 'Service status updated.', 'success');
    if (previousStatus !== nextStatus) {
      const undoButton = document.createElement('button');
      undoButton.type = 'button'; undoButton.className = 'btn btn-sm btn-outline-success ms-2'; undoButton.textContent = 'Undo';
      undoButton.addEventListener('click', async () => {
        const undoData = new FormData(); undoData.set('svcNo', fd.get('svcNo')); undoData.set('module', fd.get('module')); undoData.set('svcStatus', previousStatus);
        const undoResponse = await fetch('/Armis2/shared/branch_roster_save.php', { method: 'POST', body: undoData, headers: { 'Accept': 'application/json', 'X-CSRF-Token': csrfToken } });
        const undoResult = await undoResponse.json();
        showNotice(undoResponse.ok && undoResult.ok ? 'Service status restored.' : (undoResult.message || 'The change could not be undone.'), undoResponse.ok && undoResult.ok ? 'success' : 'danger');
        if (undoResponse.ok && undoResult.ok) setTimeout(() => location.reload(), 600);
      });
      notice.appendChild(undoButton);
    }
  } catch (error) {
    showNotice('The status could not be updated. Check your connection and try again.', 'danger');
  } finally {
    submitButton.disabled = false;
  }
});
</script>
<?php endif; ?>

<?php if (!isset($__skipLayout) || !$__skipLayout): ?>
<?php include dirname(__DIR__) . '/shared/footer.php'; ?>
<?php endif; ?>
