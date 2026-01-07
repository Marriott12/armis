<?php

$search = trim(Input::get('search', ''));
$params = [];
$where = '';

if ($search !== '') {
    $where = "WHERE s.svcNo LIKE ? OR s.fName LIKE ? OR s.lName LIKE ?";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$staffList = $db->query(
    "SELECT s.svcNo, s.fName, s.lName, s.category,
            IFNULL(r.rankId, 'Unknown') as rankAbb, IFNULL(r.rankId, 'Unknown') as rankName,
            IFNULL(u.name, 'Unknown') as unitName
     FROM staff s
     LEFT JOIN rank r ON s.rankId = r.rankId
     LEFT JOIN unit u ON s.unitId = u.unitId
     $where
     ORDER BY s.lName ASC
     LIMIT 100",
    $params
)->results();

// Fetch profile if requested
$profile = null;
if (Input::get('svcNo')) {
    $profileSvcNo = Input::get('svcNo');
    $profile = $db->query(
        "SELECT s.*, r.rankId as rankName, r.rankId as abbreviation, u.code
         FROM staff s
         LEFT JOIN rank r ON s.rankId = r.rankId
         LEFT JOIN unit u ON s.unitId = u.unitId
         WHERE s.svcNo = ?", [$profileSvcNo]
    )->first();
}

// Helper functions
function calculateAge($dob) {
    if (!$dob) return 'N/A';
    $dobDate = new DateTime($dob);
    $now = new DateTime();
    $age = $now->diff($dobDate)->y;
    return $age;
}

function getInitials($fName) {
    $parts = preg_split('/\s+/', trim($fName));
    $initials = '';
    foreach ($parts as $part) {
        if ($part !== '') {
            $initials .= strtoupper($part[0]) . ' ';
        }
    }
    return $initials;
}

function formatHeading($staff) {
    $category = strtolower($staff->category);
    $prefix = trim(($staff->rankAbb ? $staff->rankAbb : '') .' ');
    if ($category === 'officer' || $category === 'officer cadet') {
        return htmlspecialchars($prefix. ' ' . getInitials($staff->fName)  . ' ' . $staff->lName);
    } else {
        return htmlspecialchars($prefix . ' ' . $staff->lName . ' ' . getInitials($staff->fname));
    }
}
include dirname(__DIR__) . '/shared/header.php';
include dirname(__DIR__) . '/shared/sidebar.php';
?>

<!-- Main Content -->
<div class="content-wrapper with-sidebar">
    <div class="container-fluid">
    <h2 class="mb-4" style="color:#355E3B;"><i class="fa fa-users"></i> Staff Profiles</h2>
    <div class="row">
        <!-- Staff List & Search -->
        <aside class="col-md-4 mb-4">
            <form class="mb-3" method="get" action="">
                <div class="input-group">
                    <input type="text" id="staff-search" name="search" class="form-control" placeholder="Search by Service Number or Name..." value="<?=htmlspecialchars($search)?>">
                    <button class="btn btn-success" type="submit"><i class="fa fa-search"></i> Search</button>
                </div>
            </form>
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-success text-white">
                    <strong>Staff List</strong>
                </div>
                <div class="card-body p-0" style="max-height: 600px; overflow-y: auto;">
                    <ul class="list-group list-group-flush" id="staff-list">
                        <?php if(count($staffList)): ?>
                            <?php foreach($staffList as $staff): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <span class="fw-bold"><?=htmlspecialchars($staff->abbreviation)?></span>
                                        <a href="?svcNo=<?=urlencode($staff->svcNo)?>" class="ms-2 text-decoration-none">
                                            <?php
                                            echo (strtolower($staff->category) === 'officer' || strtolower($staff->category) === 'officer cadet')
                                                ? htmlspecialchars(getInitials($staff->fName) . ' ' . $staff->lName)
                                                : htmlspecialchars($staff->lName . ' ' . getInitials($staff->fName));
                                            ?>
                                        </a>
                                        <span class="badge bg-secondary ms-2"><?=htmlspecialchars($staff->svcNo)?></span>
                                    </div>
                                    <span class="text-muted small"><?=htmlspecialchars($staff->unitName)?></span>
                                </li>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="p-3 text-muted">No staff found.</div>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </aside>
        <!-- Profile Details -->
        <main class="col-md-8">
            <?php if($profile): ?>
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0"><?=formatHeading($profile)?></h4>
                        <div class="small"><?=htmlspecialchars($profile->svcNo)?> | <?=htmlspecialchars($profile->unitName ?? '')?></div>
                    </div>
                    <div class="card-body">
                        <dl class="row mb-0">
                            <dt class="col-sm-4">Full Name</dt>
                            <dd class="col-sm-8"><?=htmlspecialchars($profile->fName . ' ' . $profile->lName)?></dd>
                            <dt class="col-sm-4">Gender</dt>
                            <dd class="col-sm-8"><?=htmlspecialchars($profile->gender ?? '')?></dd>
                            <dt class="col-sm-4">Date of Birth / Age</dt>
                            <dd class="col-sm-8">
                                <?=htmlspecialchars($profile->DOB ?? 'N/A');?>
                                <?php if (!empty($profile->DOB)) : ?>
                                    (Age: <?= calculateAge($profile->DOB); ?>)
                                <?php endif; ?>
                            </dd>
                            <dt class="col-sm-4">Blood Group</dt>
                            <dd class="col-sm-8"><?=htmlspecialchars($profile->bloodGp ?? '')?></dd>
                            <dt class="col-sm-4">Phone</dt>
                            <dd class="col-sm-8"><?=htmlspecialchars($profile->tel ?? '')?></dd>
                            <dt class="col-sm-4">Email</dt>
                            <dd class="col-sm-8"><?=htmlspecialchars($profile->email ?? '')?></dd>
                            <dt class="col-sm-4">Province</dt>
                            <dd class="col-sm-8"><?=htmlspecialchars($profile->province ?? '')?></dd>
                            <dt class="col-sm-4">Category</dt>
                            <dd class="col-sm-8"><?=htmlspecialchars($profile->category ?? '')?></dd>
                            <dt class="col-sm-4">Rank</dt>
                            <dd class="col-sm-8"><?=htmlspecialchars($profile->rankName . ' ')?></dd>
                            <dt class="col-sm-4">Unit</dt>
                            <dd class="col-sm-8"><?=htmlspecialchars($profile->unitName ?? '')?></dd>
                            <dt class="col-sm-4">Intake</dt>
                            <dd class="col-sm-8"><?=htmlspecialchars($profile->intake ?? '')?></dd>
                            <dt class="col-sm-4">Date of Enlistment</dt>
                            <dd class="col-sm-8"><?=htmlspecialchars($profile->attestDate ?? '')?></dd>
                        </dl>
                    </div>
                </div>
            <?php else: ?>
                <div class="alert alert-info">Select a staff member to view their full profile.</div>
            <?php endif; ?>
        </main>
    </div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('staff-search');
    const staffList = document.getElementById('staff-list');

    searchInput.addEventListener('input', function() {
        const query = searchInput.value.trim();
        fetch('ajax_staff_search.php?search=' + encodeURIComponent(query))
            .then(response => response.json())
            .then(data => {
                staffList.innerHTML = '';
                if (data.length === 0) {
                    staffList.innerHTML = '<div class="p-3 text-muted">No staff found.</div>';
                } else {
                    data.forEach(staff => {
                        const li = document.createElement('li');
                        li.className = 'list-group-item d-flex justify-content-between align-items-center';
                        li.innerHTML = `
                            <div>
                                <span class="fw-bold">${staff.abbreviation}</span>
                                <a href="?svcNo=${encodeURIComponent(staff.svcNo)}" class="ms-2 text-decoration-none">
                                    ${(staff.category.toLowerCase() === 'officer' || staff.category.toLowerCase() === 'officer cadet')
                                        ? (staff.fname.split(' ').map(n => n[0].toUpperCase()).join(' ') + ' ' + staff.lname)
                                        : (staff.lName + ' ' + staff.fName.split(' ').map(n => n[0].toUpperCase()).join(' '))}
                                </a>
                                <span class="badge bg-secondary ms-2">${staff.svcNo}</span>
                            </div>
                            <span class="text-muted small">${staff.unitName}</span>
                        `;
                        staffList.appendChild(li);
                    });
                }
            });
    });
});
</script>
<?php include dirname(__DIR__) . '/shared/footer.php'; ?>