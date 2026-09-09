<?php
/**
 * ARMIS — Bulk Assign Default Login Credentials
 *
 * v3: all filtering (search text, rank, category) is live/client-side -
 * every keystroke or dropdown change re-filters the already-loaded table
 * instantly, no page reload. The full eligible list is fetched once,
 * ordered by rank seniority using the same canonical order as
 * admin_branch/reports_seniority.php: rankIndex ASC, subWef ASC,
 * tempWef ASC, attestDate ASC, svcNo ASC. Category (Officer/NCO/CE) is
 * computed per row with shared/rank_levels.php's getRankCategory(), the
 * same helper the seniority report uses, so the two stay consistent.
 *
 * Checkbox selection lets you exclude specific accounts from a run beyond
 * just Marriott. The server never trusts the submitted selection blindly -
 * every posted svcNo is re-checked against a fresh eligibility query
 * (Active, no username yet, not Marriott) before anything is written.
 *
 * Deliberately NOT a raw SQL script for the write itself: the password
 * must go through PHP's password_hash() to produce a hash that
 * password_verify() in login.php will actually accept.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/shared/database_connection.php';
require_once dirname(__DIR__) . '/shared/rank_levels.php';
require_once dirname(__DIR__) . '/shared/rbac.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . dirname($_SERVER['PHP_SELF']) . '/../login.php');
    exit();
}

requireModuleAccess('admin');
requireBranchAdmin(); // system-admin only - this creates login credentials for many accounts at once

$pdo = getDbConnection();

const DEFAULT_PASSWORD = 'Armis@2026';
const EXCLUDED_SVCNO = '007414'; // Marriott - stays as-is per explicit instruction

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

$result = null;

/**
 * Fetch the currently eligible list: Active (svcStatus), no username yet,
 * not Marriott. Ordered by rank seniority - same canonical order as
 * admin_branch/reports_seniority.php. Always unfiltered by rank/category -
 * that filtering happens live, client-side, over this full result.
 */
function fetchEligible(PDO $pdo) {
    $sql = "
        SELECT s.svcNo, s.fName, s.lName, s.rankId, s.svcStatus, r.rankIndex
        FROM staff s
        LEFT JOIN `rank` r ON s.rankId = r.rankId
        WHERE s.svcStatus = 'Active'
          AND (s.username IS NULL OR s.username = '')
          AND s.svcNo <> :excluded
        ORDER BY r.rankIndex ASC, s.subWef ASC, s.tempWef ASC, s.attestDate ASC, s.svcNo ASC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['excluded' => EXCLUDED_SVCNO]);
    return $stmt->fetchAll();
}

$eligible = fetchEligible($pdo);

// Distinct ranks actually present in the eligible list, in seniority order,
// for the rank filter dropdown.
$seenRanks = [];
foreach ($eligible as $row) {
    if (!isset($seenRanks[$row['rankId']])) {
        $seenRanks[$row['rankId']] = $row['rankIndex'];
    }
}
asort($seenRanks);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'apply') {
    $csrf = $_POST['csrf_token'] ?? '';
    $selected = $_POST['selected_svcnos'] ?? [];

    if (!hash_equals($_SESSION['csrf_token'], $csrf)) {
        $result = ['ok' => false, 'message' => 'Invalid CSRF token. Please reload and try again.'];
    } elseif (empty($selected)) {
        $result = ['ok' => false, 'message' => 'No accounts were selected.'];
    } else {
        $hashedPassword = password_hash(DEFAULT_PASSWORD, PASSWORD_DEFAULT);
        $updateStmt = $pdo->prepare("
            UPDATE staff
            SET username = :username,
                password = :password,
                accStatus = 'Active',
                isFirstLogin = 1,
                passwordChangedAt = NULL
            WHERE svcNo = :svcNo
              AND (username IS NULL OR username = '')
              AND svcStatus = 'Active'
              AND svcNo <> :excluded
        ");

        // Re-check every submitted svcNo against a fresh eligibility fetch -
        // a checked box is a UI convenience, not something the server trusts.
        $currentlyEligible = array_column(fetchEligible($pdo), null, 'svcNo');
        $toProcess = array_values(array_intersect($selected, array_keys($currentlyEligible)));

        $count = 0;
        $pdo->beginTransaction();
        try {
            foreach ($toProcess as $svcNo) {
                $updateStmt->execute([
                    'username' => $svcNo,
                    'password' => $hashedPassword,
                    'svcNo' => $svcNo,
                    'excluded' => EXCLUDED_SVCNO,
                ]);
                $count += $updateStmt->rowCount();
            }
            $pdo->commit();
            $skipped = count($selected) - $count;
            $msg = "Assigned login credentials to $count staff member(s). Default password: " . DEFAULT_PASSWORD . " — everyone must change it on first login.";
            if ($skipped > 0) {
                $msg .= " ($skipped selected account(s) were skipped - no longer eligible.)";
            }
            $result = ['ok' => true, 'message' => $msg];
            logAccess('admin', 'bulk_credential_assignment', true, "Assigned credentials to $count accounts");

            $eligible = fetchEligible($pdo);
            $seenRanks = [];
            foreach ($eligible as $row) {
                if (!isset($seenRanks[$row['rankId']])) $seenRanks[$row['rankId']] = $row['rankIndex'];
            }
            asort($seenRanks);
        } catch (Exception $e) {
            $pdo->rollBack();
            $result = ['ok' => false, 'message' => 'Error: ' . $e->getMessage()];
            error_log('Bulk credential assignment failed: ' . $e->getMessage());
        }
    }
}

$pageTitle = "Bulk Assign Login Credentials";
$moduleName = "System Admin";
$moduleIcon = "key";
$currentPage = "bulk_credentials";

require_once __DIR__ . '/includes/sidebar_nav.php';

include dirname(__DIR__) . '/shared/header.php';
include dirname(__DIR__) . '/shared/sidebar.php';
?>

<div class="content-wrapper with-sidebar">
  <div class="container-fluid">
    <div class="main-content">
      <div class="row mb-4">
        <div class="col-12">
          <h1 class="admin-section-title"><i class="fas fa-key text-primary"></i> Bulk Assign Login Credentials</h1>
          <p class="text-muted mb-0">
            Sets <code>username = svcNo</code> and a default password for selected <strong>Active</strong> staff
            members who don't already have login credentials. Marriott (007414) is always excluded.
            Everyone assigned a credential this way is forced to change their password on first login.
          </p>
        </div>
      </div>

      <?php if ($result): ?>
        <div class="alert alert-<?= $result['ok'] ? 'success' : 'danger' ?>">
          <?= htmlspecialchars($result['message']) ?>
        </div>
      <?php endif; ?>

      <div class="card mb-4">
        <div class="card-body">
          <h5><i class="fas fa-triangle-exclamation text-warning"></i> Before you run this</h5>
          <ul class="mb-0">
            <li>Default password will be: <code><?= htmlspecialchars(DEFAULT_PASSWORD) ?></code> (same for everyone, hashed individually per account)</li>
            <li><code>isFirstLogin</code> is set to <code>1</code> for every affected account, forcing a password change on next login</li>
            <li>Only <strong>Active</strong> staff with no existing username are listed — Retired/Deceased/Discharged/AWOL are skipped, and any account that already has a username is left untouched</li>
            <li>Only <strong>checked</strong> rows are affected - uncheck anyone you want to leave out of this run</li>
            <li>Safe to re-run: already-assigned accounts won't appear here again</li>
          </ul>
        </div>
      </div>

      <div class="row g-3 mb-3">
        <div class="col-md-3">
          <select id="rankFilter" class="form-select">
            <option value="">All Ranks</option>
            <?php foreach ($seenRanks as $rankId => $idx): ?>
              <option value="<?= htmlspecialchars($rankId) ?>"><?= htmlspecialchars($rankId) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3">
          <select id="categoryFilter" class="form-select">
            <option value="">All Categories</option>
            <option value="Officer">Officers</option>
            <option value="Officer Cadet">Officer Cadets</option>
            <option value="NCO">NCOs</option>
            <option value="Recruit">Recruits</option>
            <option value="Civilian Employee">Civilian Employees</option>
          </select>
        </div>
        <div class="col-md-4">
          <input type="text" id="credentialSearch" class="form-control" placeholder="Live search - svcNo, name, rank...">
        </div>
        <div class="col-md-2 d-grid">
          <button type="button" id="clearFilters" class="btn btn-outline-secondary">Clear Filters</button>
        </div>
      </div>

      <form method="POST" id="bulkCredentialForm" onsubmit="return confirmSubmit();">
        <input type="hidden" name="action" value="apply">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

        <div class="card">
          <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0">
              Eligible Accounts (<span id="visibleCount"><?= count($eligible) ?></span> of <?= count($eligible) ?>), ordered by rank seniority
            </h5>
            <?php if (!empty($eligible)): ?>
              <button type="submit" class="btn btn-primary">
                <i class="fas fa-key"></i> Assign to Selected (<span id="selectedCount">0</span>)
              </button>
            <?php endif; ?>
          </div>
          <div class="card-body">
            <div class="table-responsive" style="max-height:560px;overflow-y:auto;">
              <table class="table table-striped table-sm" id="credentialTable">
                <thead class="table-dark" style="position:sticky;top:0;z-index:1;">
                  <tr>
                    <th><input type="checkbox" id="selectAllRows" aria-label="Select all"></th>
                    <th>Svc No (new username)</th>
                    <th>Rank</th>
                    <th>Name</th>
                    <th>Status</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($eligible as $row):
                    $category = getRankCategory($row['rankIndex']);
                    $fullName = trim(($row['fName'] ?? '') . ' ' . ($row['lName'] ?? ''));
                    $searchBlob = strtolower($row['svcNo'] . ' ' . $fullName . ' ' . ($row['rankId'] ?? ''));
                  ?>
                    <tr data-rank="<?= htmlspecialchars($row['rankId'] ?? '') ?>" data-category="<?= htmlspecialchars($category) ?>" data-search="<?= htmlspecialchars($searchBlob) ?>">
                      <td><input type="checkbox" class="rowCheckbox" name="selected_svcnos[]" value="<?= htmlspecialchars($row['svcNo']) ?>"></td>
                      <td><code><?= htmlspecialchars($row['svcNo']) ?></code></td>
                      <td><?= htmlspecialchars($row['rankId'] ?? '') ?></td>
                      <td><?= htmlspecialchars($fullName) ?></td>
                      <td><span class="badge bg-success"><?= htmlspecialchars($row['svcStatus']) ?></span></td>
                    </tr>
                  <?php endforeach; ?>
                  <?php if (empty($eligible)): ?>
                    <tr id="emptyRow"><td colspan="5" class="text-center text-muted py-4">No eligible accounts remaining — everyone Active already has credentials.</td></tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
const searchBox = document.getElementById('credentialSearch');
const rankFilter = document.getElementById('rankFilter');
const categoryFilter = document.getElementById('categoryFilter');
const rows = Array.from(document.querySelectorAll('#credentialTable tbody tr[data-search]'));

function applyFilters() {
    const query = searchBox.value.trim().toLowerCase();
    const rank = rankFilter.value;
    const category = categoryFilter.value;
    let visible = 0;

    rows.forEach(function (row) {
        const matchesSearch = !query || row.dataset.search.includes(query);
        const matchesRank = !rank || row.dataset.rank === rank;
        const matchesCategory = !category || row.dataset.category === category;
        const show = matchesSearch && matchesRank && matchesCategory;
        row.style.display = show ? '' : 'none';
        if (show) visible++;
        if (!show) {
            const cb = row.querySelector('.rowCheckbox');
            if (cb) cb.checked = false; // hidden rows shouldn't stay silently selected
        }
    });

    const visibleCountEl = document.getElementById('visibleCount');
    if (visibleCountEl) visibleCountEl.textContent = visible;
    updateSelectedCount();
}

searchBox?.addEventListener('input', applyFilters);
rankFilter?.addEventListener('change', applyFilters);
categoryFilter?.addEventListener('change', applyFilters);

document.getElementById('clearFilters')?.addEventListener('click', function () {
    searchBox.value = '';
    rankFilter.value = '';
    categoryFilter.value = '';
    applyFilters();
});

function updateSelectedCount() {
    const n = document.querySelectorAll('.rowCheckbox:checked').length;
    const el = document.getElementById('selectedCount');
    if (el) el.textContent = n;
}

document.getElementById('selectAllRows')?.addEventListener('change', function () {
    rows.forEach(function (row) {
        if (row.style.display !== 'none') {
            const cb = row.querySelector('.rowCheckbox');
            if (cb) cb.checked = this.checked;
        }
    }, this);
    updateSelectedCount();
});

document.querySelectorAll('.rowCheckbox').forEach(function (cb) {
    cb.addEventListener('change', updateSelectedCount);
});

function confirmSubmit() {
    const n = document.querySelectorAll('.rowCheckbox:checked').length;
    if (n === 0) {
        alert('Select at least one account first.');
        return false;
    }
    return confirm('Assign svcNo/Armis@2026 login credentials to ' + n + ' selected account(s)?');
}
</script>

<?php include dirname(__DIR__) . '/shared/footer.php'; ?>