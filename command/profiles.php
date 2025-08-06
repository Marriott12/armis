<?php

$search = trim(Input::get('search', ''));
$params = [];
$where = '';

if ($search !== '') {
    $where = "WHERE s.service_number LIKE ? OR s.first_name LIKE ? OR s.last_name LIKE ?";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$staffList = $db->query(
    "SELECT s.service_number, s.first_name, s.last_name, s.category,
            IFNULL(r.abbreviation, 'Unknown') as rankAbb, IFNULL(r.rankName, 'Unknown') as rankName,
            IFNULL(u.name, 'Unknown') as unitName
     FROM staff s
     LEFT JOIN ranks r ON s.rankID = r.rankID
     LEFT JOIN units u ON s.unitID = u.unitID
     $where
     ORDER BY s.last_name ASC
     LIMIT 100",
    $params
)->results();

// Fetch profile if requested
$profile = null;
if (Input::get('service_number')) {
    $profileSvcNo = Input::get('service_number');
    $profile = $db->query(
        "SELECT s.*, r.name, r.abbreviation, u.name
         FROM staff s
         LEFT JOIN ranks r ON s.id = r.id
         LEFT JOIN units u ON s.id = u.id
         WHERE s.service_number = ?", [$profileSvcNo]
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

function getInitials($first_name) {
    $parts = preg_split('/\s+/', trim($first_name));
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
        return htmlspecialchars($prefix. ' ' . getInitials($staff->first_name)  . ' ' . $staff->last_name);
    } else {
        return htmlspecialchars($prefix . ' ' . $staff->last_name . ' ' . getInitials($staff->fname));
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
                                        <a href="?svcNo=<?=urlencode($staff->service_number)?>" class="ms-2 text-decoration-none">
                                            <?php
                                            echo (strtolower($staff->category) === 'officer' || strtolower($staff->category) === 'officer cadet')
                                                ? htmlspecialchars(getInitials($staff->first_name) . ' ' . $staff->last_name)
                                                : htmlspecialchars($staff->last_name . ' ' . getInitials($staff->first_name));
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
                        <div class="small"><?=htmlspecialchars($profile->service_number)?> | <?=htmlspecialchars($profile->unitName ?? '')?></div>
                    </div>
                    <div class="card-body">
                        <dl class="row mb-0">
                            <dt class="col-sm-4">Full Name</dt>
                            <dd class="col-sm-8"><?=htmlspecialchars($profile->first_name . ' ' . $profile->last_name)?></dd>
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
                                <a href="?svcNo=${encodeURIComponent(staff.service_number)}" class="ms-2 text-decoration-none">
                                    ${(staff.category.toLowerCase() === 'officer' || staff.category.toLowerCase() === 'officer cadet')
                                        ? (staff.fname.split(' ').map(n => n[0].toUpperCase()).join(' ') + ' ' + staff.lname)
                                        : (staff.last_name + ' ' + staff.first_name.split(' ').map(n => n[0].toUpperCase()).join(' '))}
                                </a>
                                <span class="badge bg-secondary ms-2">${staff.service_number}</span>
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