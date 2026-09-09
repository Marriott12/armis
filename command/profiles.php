<?php
require_once dirname(__DIR__) . '/shared/module_auth.php';
require_once dirname(__DIR__) . '/shared/rank_levels.php';
bootModule(['module' => 'command', 'page' => 'profiles', 'pageTitle' => 'Staff Profiles - Command - ARMIS', 'moduleName' => 'Command', 'moduleIcon' => 'chess-king']);

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
$stmt = $pdo->prepare("
    SELECT s.svcNo, s.fName, s.lName, " . getRankCategoryCaseSQL('r') . " AS category,
           r.rankId AS rankAbbr, COALESCE(u.unitId, 'Unassigned') AS unitName
    FROM staff s LEFT JOIN `rank` r ON s.rankId = r.rankId LEFT JOIN unit u ON s.unitId = u.unitId
    $whereSql ORDER BY s.lName ASC LIMIT 100
");
$stmt->execute($params);
$staffList = $stmt->fetchAll(PDO::FETCH_OBJ);

$profile = null;
if (!empty($_GET['svcNo'])) {
    $pp = ['svcNo' => $_GET['svcNo']]; $sc = '';
    if ($scope['scope'] === 'branch') { $sc = ' AND s.branch_id = :branch_id'; $pp['branch_id'] = $scope['branch_id']; }
    $pstmt = $pdo->prepare("
        SELECT s.*, r.rankId AS rankName, r.rankId AS abbreviation, COALESCE(u.unitId, 'Unassigned') AS unitName,
               " . getRankCategoryCaseSQL('r') . " AS category
        FROM staff s LEFT JOIN `rank` r ON s.rankId = r.rankId LEFT JOIN unit u ON s.unitId = u.unitId
        WHERE s.svcNo = :svcNo $sc
    ");
    $pstmt->execute($pp);
    $profile = $pstmt->fetch(PDO::FETCH_OBJ);
}
function calculateAge($dob) { return $dob ? (new DateTime())->diff(new DateTime($dob))->y : 'N/A'; }
function getInitials($name) {
    $out = '';
    foreach (preg_split('/\s+/', trim((string)$name)) as $p) if ($p !== '') $out .= strtoupper($p[0]) . ' ';
    return trim($out);
}
function formatHeading($staff) {
    $cat = strtolower($staff->category ?? '');
    $prefix = trim($staff->rankAbbr ?? $staff->rankName ?? '');
    return ($cat === 'officer' || $cat === 'officer cadet')
        ? htmlspecialchars(trim($prefix . ' ' . getInitials($staff->fName) . ' ' . $staff->lName))
        : htmlspecialchars(trim($prefix . ' ' . $staff->lName . ' ' . getInitials($staff->fName)));
}

include dirname(__DIR__) . '/shared/header.php';
include dirname(__DIR__) . '/shared/sidebar.php';
?>
<div class="content-wrapper with-sidebar"><div class="container-fluid"><div class="main-content">
  <h2 class="mb-4"><i class="fa fa-users"></i> Staff Profiles</h2>
  <div class="row">
    <aside class="col-md-4 mb-4">
      <form class="mb-3" method="get" action=""><div class="input-group">
        <input type="text" id="staff-search" name="search" class="form-control" placeholder="Search by service number or name..." value="<?= htmlspecialchars($search) ?>">
        <button class="btn btn-success" type="submit"><i class="fa fa-search"></i> Search</button>
      </div></form>
      <div class="card border-0 shadow-sm">
        <div class="card-header bg-success text-white"><strong>Staff List</strong> <span class="badge bg-light text-success float-end"><?= count($staffList) ?></span></div>
        <div class="card-body p-0" style="max-height:600px;overflow-y:auto;">
          <ul class="list-group list-group-flush" id="staff-list">
            <?php if ($staffList): foreach ($staffList as $staff):
              $isOfficer = in_array(strtolower($staff->category ?? ''), ['officer', 'officer cadet']);
              $displayName = $isOfficer ? getInitials($staff->fName) . ' ' . $staff->lName : $staff->lName . ' ' . getInitials($staff->fName);
            ?>
              <li class="list-group-item d-flex justify-content-between align-items-center">
                <div>
                  <span class="fw-bold"><?= htmlspecialchars($staff->rankAbbr ?? '') ?></span>
                  <a href="?svcNo=<?= urlencode($staff->svcNo) ?>" class="ms-2 text-decoration-none"><?= htmlspecialchars($displayName) ?></a>
                  <span class="badge bg-secondary ms-2"><?= htmlspecialchars($staff->svcNo) ?></span>
                </div>
                <span class="text-muted small"><?= htmlspecialchars($staff->unitName) ?></span>
              </li>
            <?php endforeach; else: ?><div class="p-3 text-muted">No staff found.</div><?php endif; ?>
          </ul>
        </div>
      </div>
    </aside>
    <main class="col-md-8">
      <?php if ($profile): ?>
        <div class="card border-0 shadow-sm">
          <div class="card-header bg-primary text-white">
            <h4 class="mb-0"><?= formatHeading($profile) ?></h4>
            <div class="small"><?= htmlspecialchars($profile->svcNo) ?> | <?= htmlspecialchars($profile->unitName ?? '') ?></div>
          </div>
          <div class="card-body">
            <dl class="row mb-0">
              <dt class="col-sm-4">Full Name</dt><dd class="col-sm-8"><?= htmlspecialchars($profile->fName . ' ' . $profile->lName) ?></dd>
              <dt class="col-sm-4">Gender</dt><dd class="col-sm-8"><?= htmlspecialchars($profile->gender ?? '') ?></dd>
              <dt class="col-sm-4">Date of Birth / Age</dt>
              <dd class="col-sm-8"><?= htmlspecialchars($profile->DOB ?? 'N/A') ?><?php if (!empty($profile->DOB)): ?> (Age: <?= calculateAge($profile->DOB) ?>)<?php endif; ?></dd>
              <dt class="col-sm-4">Blood Group</dt><dd class="col-sm-8"><?= htmlspecialchars($profile->bloodGp ?? '') ?></dd>
              <dt class="col-sm-4">Phone</dt><dd class="col-sm-8"><?= htmlspecialchars($profile->telNo ?? '') ?></dd>
              <dt class="col-sm-4">Email</dt><dd class="col-sm-8"><?= htmlspecialchars($profile->officialEmail ?: ($profile->emailPvt ?? '')) ?></dd>
              <dt class="col-sm-4">Province</dt><dd class="col-sm-8"><?= htmlspecialchars($profile->province ?? '') ?></dd>
              <dt class="col-sm-4">Category</dt><dd class="col-sm-8"><?= htmlspecialchars($profile->category ?? '') ?></dd>
              <dt class="col-sm-4">Rank</dt><dd class="col-sm-8"><?= htmlspecialchars($profile->rankName ?? '') ?></dd>
              <dt class="col-sm-4">Unit</dt><dd class="col-sm-8"><?= htmlspecialchars($profile->unitName ?? '') ?></dd>
              <dt class="col-sm-4">Intake</dt><dd class="col-sm-8"><?= htmlspecialchars($profile->intake ?? '') ?></dd>
              <dt class="col-sm-4">Date of Enlistment</dt><dd class="col-sm-8"><?= htmlspecialchars($profile->attestDate ?? '') ?></dd>
            </dl>
          </div>
        </div>
      <?php else: ?><div class="alert alert-info">Select a staff member from the list to view their full profile.</div><?php endif; ?>
    </main>
  </div>
</div></div></div>
<script>
document.addEventListener('DOMContentLoaded', function () {
  const searchInput = document.getElementById('staff-search');
  const staffList = document.getElementById('staff-list');
  let debounce;
  searchInput.addEventListener('input', function () {
    clearTimeout(debounce);
    debounce = setTimeout(() => {
      fetch('ajax_staff_search.php?search=' + encodeURIComponent(searchInput.value.trim()))
        .then(r => r.json()).then(data => {
          staffList.innerHTML = '';
          if (!data.length) { staffList.innerHTML = '<div class="p-3 text-muted">No staff found.</div>'; return; }
          data.forEach(staff => {
            const isOfficer = ['officer', 'officer cadet'].includes((staff.category || '').toLowerCase());
            const initials = (n) => (n || '').split(/\s+/).filter(Boolean).map(x => x[0].toUpperCase()).join(' ');
            const displayName = isOfficer ? `${initials(staff.fName)} ${staff.lName}` : `${staff.lName} ${initials(staff.fName)}`;
            const li = document.createElement('li');
            li.className = 'list-group-item d-flex justify-content-between align-items-center';
            li.innerHTML = `<div><span class="fw-bold"></span><a href="?svcNo=${encodeURIComponent(staff.svcNo)}" class="ms-2 text-decoration-none"></a><span class="badge bg-secondary ms-2"></span></div><span class="text-muted small"></span>`;
            li.querySelector('.fw-bold').textContent = staff.rankAbbr || '';
            li.querySelector('a').textContent = displayName;
            li.querySelector('.badge').textContent = staff.svcNo;
            li.querySelector('.text-muted').textContent = staff.unitName;
            staffList.appendChild(li);
          });
        });
    }, 250);
  });
});
</script>
<?php include dirname(__DIR__) . '/shared/footer.php'; ?>
