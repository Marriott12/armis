<?php
/**
 * ARMIS System Administration — Bulk Branch Posting
 *
 * Allows the System Administrator to select multiple personnel and either:
 *  - assign unposted personnel to a branch; or
 *  - move personnel from one branch to another.
 *
 * Branch membership is represented only by staff.branch_id. Roles are not
 * changed by this operation. Every write is validated server-side and the
 * complete operation is transactional.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/shared/database_connection.php';
require_once dirname(__DIR__) . '/shared/rbac.php';
require_once dirname(__DIR__) . '/shared/csrf.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /Armis2/login.php?return_url=' . urlencode($_SERVER['REQUEST_URI'] ?? '/Armis2/admin/bulk_branch_assignment.php'));
    exit();
}

requireModuleAccess('admin');
requireBranchAdmin();

$pdo = getDbConnection();
$pageTitle = 'Bulk Branch Posting';
$moduleName = 'System Admin';
$moduleIcon = 'user-friends';
$currentPage = 'bulk_branch_assignment';
require_once __DIR__ . '/includes/sidebar_nav.php';

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

$branches = getAllBranches(true);
$message = '';
$messageType = '';
$selectedSource = isset($_GET['source_branch']) && $_GET['source_branch'] !== '' ? (int)$_GET['source_branch'] : null;
$search = trim((string)($_GET['q'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'bulk_assign_branch') {
    try {
        require_csrf();

        $targetBranchId = (int)($_POST['target_branch_id'] ?? 0);
        $sourceBranchRaw = $_POST['source_branch_id'] ?? '';
        // Empty = any source; 0 = specifically unassigned; positive ID = source branch.
        $sourceBranchId = $sourceBranchRaw === '' ? null : (int)$sourceBranchRaw;
        $sourceIsUnassigned = $sourceBranchId === 0;
        if ($sourceIsUnassigned) $sourceBranchId = null;
        $svcNos = $_POST['svcNo'] ?? [];
        if (!is_array($svcNos)) $svcNos = [$svcNos];

        $svcNos = array_values(array_unique(array_filter(array_map(static fn($v) => trim((string)$v), $svcNos), static fn($v) => $v !== '')));
        $targetBranch = $branches[$targetBranchId] ?? null;
        if (!$targetBranch) throw new RuntimeException('Please select a valid destination branch.');
        if (!$svcNos) throw new RuntimeException('Select at least one personnel record.');
        if (count($svcNos) > 2000) throw new RuntimeException('A maximum of 2,000 personnel may be moved in one operation.');
        if ($sourceBranchId !== null && !isset($branches[$sourceBranchId])) throw new RuntimeException('Invalid source branch.');

        // Load every selected record and validate its current branch before any write.
        $placeholders = implode(',', array_fill(0, count($svcNos), '?'));
        $stmt = $pdo->prepare("SELECT svcNo, fName, mName, lName, branch_id FROM staff WHERE svcNo IN ($placeholders) FOR UPDATE");
        $pdo->beginTransaction();
        $stmt->execute($svcNos);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($rows) !== count($svcNos)) {
            $found = array_column($rows, 'svcNo');
            $missing = array_values(array_diff($svcNos, $found));
            throw new RuntimeException('One or more selected personnel records no longer exist: ' . implode(', ', array_slice($missing, 0, 10)) . (count($missing) > 10 ? ' …' : ''));
        }

        $invalid = [];
        foreach ($rows as $row) {
            $currentBranch = $row['branch_id'] === null ? null : (int)$row['branch_id'];
            if ($sourceIsUnassigned && $currentBranch !== null) {
                $invalid[] = $row['svcNo'];
            } elseif ($sourceBranchId !== null && $currentBranch !== $sourceBranchId) {
                $invalid[] = $row['svcNo'];
            }
            if ($currentBranch === $targetBranchId) {
                $invalid[] = $row['svcNo'];
            }
        }
        $invalid = array_values(array_unique($invalid));
        if ($invalid) {
            throw new RuntimeException('Selection changed or includes personnel already in the destination/source mismatch. Refresh and select the records again. Affected service numbers: ' . implode(', ', array_slice($invalid, 0, 15)) . (count($invalid) > 15 ? ' …' : ''));
        }

        $update = $pdo->prepare('UPDATE staff SET branch_id = :target_branch_id WHERE svcNo = :svcNo');
        foreach ($rows as $row) {
            $update->execute(['target_branch_id' => $targetBranchId, 'svcNo' => $row['svcNo']]);
        }

        $fromLabel = $sourceIsUnassigned ? 'Unassigned' : ($sourceBranchId === null ? 'Selected/current branches' : ($branches[$sourceBranchId]['name'] ?? ('Branch #' . $sourceBranchId)));
        $pdo->commit();

        logAccess('admin', 'bulk_branch_posting', true, sprintf(
            'Moved/assigned %d personnel from %s to %s. Service numbers: %s',
            count($rows), $fromLabel, $targetBranch['name'], implode(',', $svcNos)
        ));

        $message = sprintf('%d personnel successfully posted to %s.', count($rows), $targetBranch['name']);
        $messageType = 'success';
        $selectedSource = $sourceIsUnassigned ? null : $sourceBranchId;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $message = $e->getMessage();
        $messageType = 'danger';
        error_log('ARMIS bulk branch posting failed: ' . $e->getMessage());
    }
}

// Active personnel are the normal posting pool. Search and source filtering
// are server-side so the screen remains usable as the personnel table grows.
$where = ["s.svcStatus = 'Active'"];
$params = [];
if ($selectedSource !== null) {
    $where[] = 's.branch_id = :source_branch_id';
    $params['source_branch_id'] = $selectedSource;
} elseif (isset($_GET['source_branch']) && $_GET['source_branch'] === '0') {
    $where[] = 's.branch_id IS NULL';
}
if ($search !== '') {
    $where[] = '(s.svcNo LIKE :q OR s.fName LIKE :q OR s.mName LIKE :q OR s.lName LIKE :q OR s.username LIKE :q)';
    $params['q'] = '%' . $search . '%';
}
$sql = "SELECT s.svcNo, s.fName, s.mName, s.lName, s.rankId, s.username, s.branch_id, b.name AS branch_name
        FROM staff s LEFT JOIN branches b ON b.id = s.branch_id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY s.svcNo LIMIT 2500";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$personnel = $stmt->fetchAll(PDO::FETCH_ASSOC);

$sourceOptions = $branches;
?>
<?php include dirname(__DIR__) . '/shared/header.php'; ?>
<?php include dirname(__DIR__) . '/shared/sidebar.php'; ?>
<div class="content-wrapper with-sidebar">
  <div class="container-fluid">
    <div class="main-content">
      <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
          <h1 class="admin-section-title"><i class="fas fa-user-friends text-primary"></i> Bulk Branch Posting</h1>
          <p class="text-muted mb-0">Select multiple personnel and post them to a destination branch, including moving personnel from one branch to another.</p>
        </div>
      </div>

      <?php if ($message): ?>
        <div class="alert alert-<?= htmlspecialchars($messageType) ?> alert-dismissible fade show" role="alert">
          <i class="fas fa-<?= $messageType === 'success' ? 'check-circle' : 'exclamation-triangle' ?> me-2"></i><?= htmlspecialchars($message) ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>

      <div class="card mb-4">
        <div class="card-header"><h5 class="mb-0"><i class="fas fa-filter me-2"></i>Personnel Selection</h5></div>
        <div class="card-body">
          <form method="get" class="row g-3 align-items-end">
            <div class="col-md-4">
              <label class="form-label">Personnel currently in</label>
              <select class="form-select" name="source_branch">
                <option value="">All active personnel</option>
                <option value="0" <?= (isset($_GET['source_branch']) && $_GET['source_branch'] === '0') ? 'selected' : '' ?>>Unassigned / No Branch</option>
                <?php foreach ($sourceOptions as $id => $b): ?>
                  <option value="<?= (int)$id ?>" <?= $selectedSource === (int)$id ? 'selected' : '' ?>><?= htmlspecialchars($b['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-5">
              <label class="form-label">Search personnel</label>
              <input type="search" class="form-control" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Service number, name or username">
            </div>
            <div class="col-md-3 d-flex gap-2">
              <button class="btn btn-outline-primary flex-fill"><i class="fas fa-search me-1"></i> Find</button>
              <a class="btn btn-outline-secondary" href="bulk_branch_assignment.php">Reset</a>
            </div>
          </form>
        </div>
      </div>

      <form method="post" id="bulkPostingForm" onsubmit="return confirmBulkPosting();">
        <input type="hidden" name="action" value="bulk_assign_branch">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
        <input type="hidden" name="source_branch_id" value="<?= isset($_GET['source_branch']) && $_GET['source_branch'] === '0' ? '0' : ($selectedSource !== null ? (int)$selectedSource : '') ?>">

        <div class="card">
          <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div><h5 class="mb-0"><i class="fas fa-users me-2"></i>Personnel</h5><small class="text-muted"><?= number_format(count($personnel)) ?> records shown</small></div>
            <label class="mb-0"><input type="checkbox" id="selectAll"> Select all shown</label>
          </div>
          <div class="card-body border-bottom">
            <div class="row g-3 align-items-end">
              <div class="col-md-6">
                <label class="form-label fw-semibold">Post selected personnel to</label>
                <select class="form-select" name="target_branch_id" id="targetBranch" required>
                  <option value="">Select destination branch…</option>
                  <?php foreach ($branches as $id => $b): ?>
                    <option value="<?= (int)$id ?>"><?= htmlspecialchars($b['name']) ?><?= !empty($b['is_org_wide']) ? ' (Army-wide)' : '' ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-6">
                <div class="alert alert-info mb-0 py-2">
                  <i class="fas fa-info-circle me-1"></i>
                  This changes <strong>branch posting only</strong>. It does not change the person's role, account, rank, unit or other personnel data.
                </div>
              </div>
            </div>
          </div>
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
              <thead class="table-dark"><tr><th style="width:45px"></th><th>Svc No</th><th>Rank</th><th>Name</th><th>Username</th><th>Current Branch</th></tr></thead>
              <tbody>
              <?php foreach ($personnel as $p): ?>
                <tr>
                  <td><input class="person-check" type="checkbox" name="svcNo[]" value="<?= htmlspecialchars($p['svcNo']) ?>"></td>
                  <td><strong><?= htmlspecialchars($p['svcNo']) ?></strong></td>
                  <td><?= htmlspecialchars($p['rankId'] ?? '') ?></td>
                  <td><?= htmlspecialchars(trim(($p['lName'] ?? '') . ' ' . ($p['fName'] ?? '') . ' ' . ($p['mName'] ?? ''))) ?></td>
                  <td><?= htmlspecialchars($p['username'] ?? '—') ?></td>
                  <td><?= htmlspecialchars($p['branch_name'] ?? 'Unassigned') ?></td>
                </tr>
              <?php endforeach; ?>
              <?php if (!$personnel): ?><tr><td colspan="6" class="text-center text-muted py-4">No active personnel matched the current filters.</td></tr><?php endif; ?>
              </tbody>
            </table>
          </div>
          <div class="card-footer d-flex flex-wrap justify-content-between align-items-center gap-2">
            <span class="text-muted"><strong id="selectedCount">0</strong> personnel selected</span>
            <button class="btn btn-primary" type="submit" <?= !$personnel ? 'disabled' : '' ?>><i class="fas fa-exchange-alt me-1"></i> Post Selected Personnel</button>
          </div>
        </div>
      </form>

      <div class="alert alert-warning mt-4 mb-0">
        <i class="fas fa-shield-alt me-2"></i><strong>System Administrator action.</strong>
        Bulk posting is restricted to the System Administrator and is recorded in the ARMIS access log. The operation is transactional: if validation fails, no selected record is changed.
      </div>
    </div>
  </div>
</div>
<script>
(function () {
  const checks = () => Array.from(document.querySelectorAll('.person-check'));
  const selectAll = document.getElementById('selectAll');
  const count = document.getElementById('selectedCount');
  function refresh() {
    const all = checks();
    const selected = all.filter(c => c.checked).length;
    count.textContent = selected;
    selectAll.checked = all.length > 0 && selected === all.length;
    selectAll.indeterminate = selected > 0 && selected < all.length;
  }
  selectAll?.addEventListener('change', function () { checks().forEach(c => c.checked = this.checked); refresh(); });
  document.addEventListener('change', function (e) { if (e.target.classList.contains('person-check')) refresh(); });
  refresh();
  window.confirmBulkPosting = function () {
    const selected = checks().filter(c => c.checked).length;
    const target = document.getElementById('targetBranch').selectedOptions[0]?.text || '';
    if (!selected) { alert('Select at least one personnel record.'); return false; }
    if (!document.getElementById('targetBranch').value) { alert('Select a destination branch.'); return false; }
    return confirm('Post ' + selected + ' selected personnel to ' + target + '?\n\nTheir branch_id will be changed. Their roles and other personnel information will not be changed.');
  };
})();
</script>
<?php include dirname(__DIR__) . '/shared/footer.php'; ?>
