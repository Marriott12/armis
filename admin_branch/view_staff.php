<?php
define('ARMIS_ADMIN_BRANCH', true);
define('ARMIS_DEVELOPMENT', true);

require_once dirname(__DIR__) . '/shared/database_connection.php';

$pdo = getDbConnection();

$pageTitle = "Staff Profile - Admin Branch";
$moduleName = "Admin Branch";
$moduleIcon = "users-cog";
$currentPage = "profile";

$sidebarLinks = [
    ['title' => 'Dashboard', 'url' => '/Armis2/admin_branch/index.php', 'icon' => 'tachometer-alt', 'page' => 'dashboard'],
    ['title' => 'Staff Management', 'url' => '/Armis2/admin_branch/edit_staff.php', 'icon' => 'users', 'page' => 'staff'],
    ['title' => 'Create Staff', 'url' => '/Armis2/admin_branch/create_staff.php', 'icon' => 'user-plus', 'page' => 'create'],
    ['title' => 'Promotions', 'url' => '/Armis2/admin_branch/promote_staff.php', 'icon' => 'arrow-up', 'page' => 'promotions'],
    ['title' => 'Medals', 'url' => '/Armis2/admin_branch/assign_medal.php', 'icon' => 'medal', 'page' => 'medals'],
    [
        'title' => 'Reports',
        'icon' => 'chart-bar',
        'page' => 'reports',
        'children' => [
            ['title' => 'Seniority', 'url' => '/Armis2/admin_branch/reports_seniority.php'],
            ['title' => 'Unit List', 'url' => '/Armis2/admin_branch/reports_units.php'],
            ['title' => 'Appointments', 'url' => '/Armis2/admin_branch/reports_appointment.php'],
            ['title' => 'Contracts', 'url' => '/Armis2/admin_branch/reports_contract.php'],
            ['title' => 'Courses', 'url' => '/Armis2/admin_branch/reports_courses.php'],
            ['title' => 'Deceased', 'url' => '/Armis2/admin_branch/reports_deceased.php'],
            ['title' => 'Gender', 'url' => '/Armis2/admin_branch/reports_gender.php'],
            ['title' => 'Marital', 'url' => '/Armis2/admin_branch/reports_marital.php'],
            ['title' => 'Rank', 'url' => '/Armis2/admin_branch/reports_rank.php'],
            ['title' => 'Retired', 'url' => '/Armis2/admin_branch/reports_retired.php'],
            ['title' => 'Trade', 'url' => '/Armis2/admin_branch/reports_trade.php'],
            ['title' => 'Corps', 'url' => '/Armis2/admin_branch/reports_corps.php'],
            ['title' => 'Units', 'url' => '/Armis2/admin_branch/reports_units.php'],
        ]
    ],
];



$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    die('<div class="alert alert-danger">Invalid staff ID.</div>');
}

$stmt = $pdo->prepare("SELECT s.*, r.name AS rankName, u.name AS unitName FROM staff s LEFT JOIN ranks r ON s.rank_id = r.id LEFT JOIN units u ON s.unit_id = u.id WHERE s.id = ? LIMIT 1");
$stmt->execute([$id]);
$staff = $stmt->fetch(PDO::FETCH_OBJ);
if (!$staff) {
    die('<div class="alert alert-danger">Staff member not found.</div>');
}

// Fetch promotions
$promotions = [];
$promStmt = $pdo->prepare("SELECT p.*, r.name AS newRankName FROM staff_promotions p LEFT JOIN ranks r ON p.new_rank = r.id WHERE p.staff_id = ? ORDER BY p.date_from DESC");
$promStmt->execute([$id]);
$promotions = $promStmt->fetchAll(PDO::FETCH_OBJ);

// Fetch medals
$medals = [];
$medalStmt = $pdo->prepare("SELECT m.*, mm.name AS medalName FROM staff_medals m LEFT JOIN medals mm ON m.medal_id = mm.id WHERE m.staff_id = ? ORDER BY m.award_date DESC");
$medalStmt->execute([$id]);
$medals = $medalStmt->fetchAll(PDO::FETCH_OBJ);

// Fetch courses
$courses = [];
$courseStmt = $pdo->prepare("SELECT c.*, cc.name AS courseName FROM staff_courses c LEFT JOIN courses cc ON c.course_id = cc.id WHERE c.staff_id = ? ORDER BY c.end_date DESC");
$courseStmt->execute([$id]);
$courses = $courseStmt->fetchAll(PDO::FETCH_OBJ);

include dirname(__DIR__) . '/shared/header.php';
include dirname(__DIR__) . '/shared/sidebar.php'; 
?>

<div class="content-wrapper with-sidebar">
    <div class="container-fluid p-4">
        <h1 class="mb-4"><i class="fa fa-user"></i> Staff Profile</h1>
        
        <?php if (isset($_GET['msg'])): ?>
            <div class="alert alert-success"><?=htmlspecialchars($_GET['msg'])?></div>
        <?php endif; ?>
        
        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-danger"><?=htmlspecialchars($_GET['error'])?></div>
        <?php endif; ?>
    <div class="card mb-4 shadow-sm">
        <div class="card-header bg-primary text-white">
            <h3 class="mb-0"><i class="fa fa-user"></i> <?=htmlspecialchars($staff->last_name . ', ' . $staff->first_name)?> (<?=htmlspecialchars($staff->service_number)?>)
                <?php if (defined('ARMIS_ADMIN_BRANCH') && ARMIS_ADMIN_BRANCH): ?>
                    <a href="edit_staff.php?id=<?=urlencode($staff->id)?>" class="btn btn-sm btn-warning float-end ms-2" title="Edit Profile"><i class="fa fa-edit"></i> Edit</a>
                <?php endif; ?>
            </h3>
        </div>
        <div class="card-body row">
            <div class="col-md-3 text-center">
                <?php if (!empty($staff->profile_photo)): ?>
                    <img src="<?=htmlspecialchars($staff->profile_photo)?>" class="img-fluid rounded mb-2" alt="Profile Photo">
                <?php else: ?>
                    <img src="/Armis2/assets/army-logo.svg" class="img-fluid rounded mb-2" alt="No Photo">
                <?php endif; ?>
                <span class="badge bg-<?=strcasecmp($staff->svcStatus,'Active')===0?'success':(strcasecmp($staff->svcStatus,'Retired')===0?'secondary':(strcasecmp($staff->svcStatus,'Deceased')===0?'danger':'light text-dark'))?>">
                    <?=htmlspecialchars($staff->svcStatus ?? 'N/A')?>
                </span>
            </div>
            <div class="col-md-9">
                <table class="table table-bordered table-sm">
                    <tbody>
                        <tr><th>Rank</th><td><?=htmlspecialchars($staff->rankName ?? '')?></td></tr>
                        <tr><th>Unit</th><td><?=htmlspecialchars($staff->unitName ?? '')?></td></tr>
                        <tr><th>Trade</th><td><?=htmlspecialchars($staff->trade ?? '')?></td></tr>
                        <tr><th>Category</th><td><?=htmlspecialchars($staff->category ?? '')?></td></tr>
                        <tr><th>Date of Birth</th><td><?=!empty($staff->DOB)?date('d M Y',strtotime($staff->DOB)):'N/A'?></td></tr>
                        <tr><th>Date of Enlistment</th><td><?=!empty($staff->attestDate)?date('d M Y',strtotime($staff->attestDate)):'N/A'?></td></tr>
                        <tr><th>Gender</th><td><?=htmlspecialchars($staff->gender ?? '')?></td></tr>
                        <tr><th>Marital Status</th><td><?=htmlspecialchars($staff->marital_status ?? '')?></td></tr>
                        <tr><th>Contact</th><td><?=htmlspecialchars($staff->contact ?? '')?></td></tr>
                        <tr><th>Address</th><td><?=htmlspecialchars($staff->address ?? '')?></td></tr>
                        <tr><th>Next of Kin</th><td><?=htmlspecialchars($staff->next_of_kin ?? '')?></td></tr>
                        <tr><th>Emergency Contact</th><td><?=htmlspecialchars($staff->emergency_contact ?? '')?></td></tr>
                        <tr><th>Remarks</th><td><?=htmlspecialchars($staff->remarks ?? '')?></td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-md-12">
            <div class="card mb-4">
                <div class="card-header bg-secondary text-white">Service Timeline</div>
                <div class="card-body p-2">
                    <ul class="timeline list-unstyled">
                        <li><strong>Enlisted:</strong> <?=!empty($staff->attestDate)?date('d M Y',strtotime($staff->attestDate)):'N/A'?></li>
                        <?php foreach ($promotions as $p): ?>
                            <li><strong>Promoted to <?=htmlspecialchars($p->newRankName ?? '')?>:</strong> <?=!empty($p->date_from)?date('d M Y',strtotime($p->date_from)):'N/A'?></li>
                        <?php endforeach; ?>
                        <?php foreach ($medals as $m): ?>
                            <li><strong>Medal Awarded (<?=htmlspecialchars($m->medalName ?? '')?>):</strong> <?=!empty($m->award_date)?date('d M Y',strtotime($m->award_date)):'N/A'?></li>
                        <?php endforeach; ?>
                        <?php foreach ($courses as $c): ?>
                            <li><strong>Course Completed (<?=htmlspecialchars($c->courseName ?? '')?>):</strong> <?=!empty($c->end_date)?date('d M Y',strtotime($c->end_date)):'N/A'?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-header bg-info text-white">Promotions</div>
                <div class="card-body p-2">
                    <?php if (count($promotions) == 0): ?>
                        <div class="text-muted">No promotions found.</div>
                    <?php else: ?>
                        <ul class="list-group list-group-flush">
                            <?php foreach ($promotions as $p): ?>
                                <li class="list-group-item">
                                    <strong><?=htmlspecialchars($p->newRankName ?? '')?></strong> on <?=!empty($p->date_from)?date('d M Y',strtotime($p->date_from)):'N/A'?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-header bg-warning text-white">Medals</div>
                <div class="card-body p-2">
                    <button onclick="exportTableToCSV('medals-table','medals.csv')" class="btn btn-outline-secondary btn-sm mb-2"><i class="fa fa-download"></i> Export Medals</button>
                    <?php if (count($medals) == 0): ?>
                        <div class="text-muted">No medals found.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                        <table class="table table-bordered table-sm mb-0">
                            <thead class="table-warning">
                                <tr>
                                    <th>Medal</th>
                                    <th>Award Date</th>
                                    <th>Citation</th>
                                    <th>Gazette Ref</th>
                                    <th>Bar #</th>
                                    <th>Created By</th>
                                    <th>Created At</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($medals as $m): ?>
                                <tr>
                                    <td title="<?=htmlspecialchars($m->citation ?? '')?>">
                                        <?=htmlspecialchars($m->medalName ?? '')?>
                                        <?php if (!empty($m->status)): ?>
                                            <span class="badge bg-<?=strcasecmp($m->status,'Active')===0?'success':'secondary'?> ms-1"><?=htmlspecialchars($m->status)?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?=!empty($m->award_date)?date('d M Y',strtotime($m->award_date)):'N/A'?></td>
                                    <td><?=htmlspecialchars($m->citation ?? '')?></td>
                                    <td><?=htmlspecialchars($m->gazette_reference ?? '')?></td>
                                    <td><?=htmlspecialchars($m->bar_number ?? '')?></td>
                                    <td><?=htmlspecialchars($m->created_by ?? '')?></td>
                                    <td><?=!empty($m->created_at)?date('d M Y, H:i',strtotime($m->created_at)):'N/A'?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-header bg-success text-white">Courses</div>
                <div class="card-body p-2">
                    <button onclick="exportTableToCSV('courses-table','courses.csv')" class="btn btn-outline-secondary btn-sm mb-2"><i class="fa fa-download"></i> Export Courses</button>
                    <?php if (count($courses) == 0): ?>
                        <div class="text-muted">No courses found.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                        <table class="table table-bordered table-sm mb-0">
                            <thead class="table-success">
                                <tr>
                                    <th>Course Name</th>
                                    <th>Code</th>
                                    <th>Category</th>
                                    <th>Institution</th>
                                    <th>Start Date</th>
                                    <th>End Date</th>
                                    <th>Grade</th>
                                    <th>Position</th>
                                    <th>Total Participants</th>
                                    <th>Certificate #</th>
                                    <th>Status</th>
                                    <th>Created At</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($courses as $c): ?>
                                <tr>
                                    <td title="<?=htmlspecialchars($c->category ?? '')?>">
                                        <?=htmlspecialchars($c->courseName ?? '')?>
                                        <?php if (!empty($c->status)): ?>
                                            <span class="badge bg-<?=strcasecmp($c->status,'Completed')===0?'success':'secondary'?> ms-1"><?=htmlspecialchars($c->status)?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?=htmlspecialchars($c->code ?? '')?></td>
                                    <td><?=htmlspecialchars($c->category ?? '')?></td>
                                    <?php if (!empty($c->institution_id)): ?>
                                        <td><a href="institution_profile.php?id=<?=urlencode($c->institution_id)?>" title="View institution profile"><?=htmlspecialchars($c->institution_id)?></a></td>
                                    <?php else: ?>
                                        <td>N/A</td>
                                    <?php endif; ?>
                                    <td><?=!empty($c->start_date)?date('d M Y',strtotime($c->start_date)):'N/A'?></td>
                                    <td><?=!empty($c->end_date)?date('d M Y',strtotime($c->end_date)):'N/A'?></td>
                                    <td><?=htmlspecialchars($c->grade ?? '')?></td>
                                    <td><?=htmlspecialchars($c->position_in_class ?? '')?></td>
                                    <td><?=htmlspecialchars($c->total_participants ?? '')?></td>
                                    <td><?=htmlspecialchars($c->certificate_number ?? '')?></td>
                                    <td><?=htmlspecialchars($c->status ?? '')?></td>
                                    <td><?=!empty($c->created_at)?date('d M Y, H:i',strtotime($c->created_at)):'N/A'?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <div class="text-end">
    <button onclick="exportTableToCSV('profile-table','profile.csv')" class="btn btn-outline-secondary btn-sm"><i class="fa fa-download"></i> Export Profile</button>
    <div class="mt-3 text-muted small">
        Last updated: <?=!empty($staff->updated_at)?date('d M Y, H:i',strtotime($staff->updated_at)):'N/A'?>
    </div>
    <style>
        @media print {
            .btn, .sidebar, .timeline, .card-header { display: none !important; }
            .card, .card-body, .table { box-shadow: none !important; }
        }
        .timeline li { margin-bottom: 0.5em; }
    </style>
    <script>
    function exportTableToCSV(tableId, filename) {
        var csv = [];
        var rows = document.querySelectorAll('#'+tableId+' tr');
        for (var i = 0; i < rows.length; i++) {
            var row = [], cols = rows[i].querySelectorAll('td, th');
            for (var j = 0; j < cols.length; j++)
                row.push('"' + cols[j].innerText.replace(/"/g, '""') + '"');
            csv.push(row.join(','));
        }
        var csvFile = new Blob([csv.join('\n')], {type: 'text/csv'});
        var downloadLink = document.createElement('a');
        downloadLink.download = filename;
        downloadLink.href = window.URL.createObjectURL(csvFile);
        downloadLink.style.display = 'none';
        document.body.appendChild(downloadLink);
        downloadLink.click();
        document.body.removeChild(downloadLink);
    }
    </script>
        <button onclick="window.print()" class="btn btn-outline-secondary btn-sm"><i class="fa fa-print"></i> Print Profile</button>
        <a href="reports_trade.php" class="btn btn-outline-primary btn-sm"><i class="fa fa-arrow-left"></i> Back to Trade Report</a>
    </div>
</div>
<?php include dirname(__DIR__) . '/shared/footer.php'; ?>
