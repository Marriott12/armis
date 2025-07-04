<?php
require_once '../init.php';
require_once $abs_us_root . $us_url_root . 'users/includes/template/prep.php';
include 'sidebar.php';

if (!securePage($_SERVER['PHP_SELF'])) { die(); }

// --- Breadcrumb State Management ---
function get_breadcrumbs() {
    $crumbs = [];
    if (!empty($_GET['breadcrumbs'])) {
        $raw = explode('|', $_GET['breadcrumbs']);
        foreach ($raw as $item) {
            $parts = explode(';', $item, 2);
            if (count($parts) === 2) {
                $crumbs[] = [
                    'label' => urldecode($parts[0]),
                    'url'   => urldecode($parts[1])
                ];
            }
        }
    }
    return $crumbs;
}
function create_breadcrumbs($current_label) {
    $crumbs = get_breadcrumbs();
    $url = $_SERVER['PHP_SELF'] . '?' . http_build_query(array_merge($_GET, ['breadcrumbs'=>null,'page'=>null]));
    $url = preg_replace('/(&|\?)breadcrumbs=[^&]*/', '', $url);
    $url = preg_replace('/(&|\?)page=[^&]*/', '', $url);
    $url = preg_replace('/[&?]+$/', '', $url);
    $crumbs[] = [
        'label' => $current_label,
        'url'   => $url
    ];
    $parts = [];
    foreach ($crumbs as $c) {
        $parts[] = urlencode($c['label']) . ";" . urlencode($c['url']);
    }
    return implode('|', $parts);
}
$breadcrumbs = get_breadcrumbs();

// --- Filters and Params ---
$units      = $db->query("SELECT unitID, unitName FROM units ORDER BY unitName ASC")->results();
$ranks      = $db->query("SELECT rankID, rankName, rankIndex FROM ranks ORDER BY rankIndex ASC")->results();
$categories = ['Officer', 'NCO', 'Civilian'];
$courses    = $db->query("SELECT courseID, courseName FROM courses ORDER BY courseName ASC")->results();

$filter_unit     = $_GET['unitID']    ?? '';
$filter_rank     = $_GET['rankID']    ?? '';
$filter_category = $_GET['category']  ?? '';
$filter_course   = $_GET['courseID']  ?? '';
$search          = trim($_GET['search'] ?? '');
$page            = max(1, intval($_GET['page'] ?? 1));
$perPage         = 50;

// --- SQL Construction ---
$params = [];
$joins = "";
$where = "WHERE 1=1";

if ($filter_course !== '') {
    $joins .= " INNER JOIN staff_courses sc ON s.svcNo = sc.svcNo AND sc.courseID = ? ";
    $params[] = $filter_course;
    $joins .= " INNER JOIN courses c ON sc.courseID = c.courseID ";
}
if ($filter_unit !== '') {
    $where .= " AND s.unitID = ?";
    $params[] = $filter_unit;
}
if ($filter_rank !== '') {
    $where .= " AND s.rankID = ?";
    $params[] = $filter_rank;
}
if ($filter_category !== '') {
    $where .= " AND s.category = ?";
    $params[] = $filter_category;
}
if ($search !== '') {
    $where .= " AND (s.svcNo LIKE ? OR s.lname LIKE ? OR s.fname LIKE ? OR u.unitName LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// --- RBAC Example: ---
if (!in_array($user->data()->permissions, [1,2])) { // 1=admin, 2=analytics
    die("Access denied.");
}

// --- Analytics ---
$unitCounts = $db->query(
    "SELECT u.unitID, u.unitName, COUNT(DISTINCT s.svcNo) AS staffCount
     FROM staff s
     LEFT JOIN units u ON s.unitID = u.unitID
     $joins
     $where
     GROUP BY u.unitID, u.unitName
     ORDER BY staffCount DESC"
, $params)->results();

$courseCounts = [];
if ($filter_course === '') {
    $courseCounts = $db->query(
        "SELECT c.courseID, c.courseName, COUNT(DISTINCT s.svcNo) AS staffCount
         FROM staff s
         INNER JOIN staff_courses sc ON s.svcNo = sc.svcNo
         INNER JOIN courses c ON sc.courseID = c.courseID
         $where
         GROUP BY c.courseID, c.courseName
         ORDER BY staffCount DESC"
    , $params)->results();
}

$rankCounts = $db->query(
    "SELECT r.rankID, r.rankName, COUNT(DISTINCT s.svcNo) AS staffCount
     FROM staff s
     LEFT JOIN ranks r ON s.rankID = r.rankID
     $joins
     $where
     GROUP BY r.rankID, r.rankName
     ORDER BY r.rankName ASC"
, $params)->results();

$courseStats = null;
if ($filter_course !== '') {
    $courseStats = $db->query(
        "SELECT 
            COUNT(DISTINCT s.svcNo) AS totalStaff,
            SUM(CASE WHEN sc.dateCompleted IS NOT NULL AND sc.dateCompleted <> '' THEN 1 ELSE 0 END) AS completed,
            SUM(CASE WHEN sc.dateCompleted IS NULL OR sc.dateCompleted = '' THEN 1 ELSE 0 END) AS notCompleted
         FROM staff s
         INNER JOIN staff_courses sc ON s.svcNo = sc.svcNo AND sc.courseID = ?
         $where"
    , array_merge([$filter_course], $params))->first();
}

// --- Time Series for Deeper Analytics ---
$courseMonthlyStats = $db->query(
    "SELECT c.courseName, 
            DATE_FORMAT(sc.dateCompleted, '%Y-%m') as month,
            COUNT(DISTINCT sc.svcNo) as completions
     FROM staff_courses sc
     INNER JOIN courses c ON sc.courseID = c.courseID
     WHERE sc.dateCompleted IS NOT NULL AND sc.dateCompleted <> ''
     GROUP BY c.courseName, month
     ORDER BY month ASC"
)->results();

// --- Pagination ---
$totalCountArr = $db->query(
    "SELECT COUNT(DISTINCT s.svcNo) AS cnt
        FROM staff s
        LEFT JOIN ranks r ON s.rankID = r.rankID
        LEFT JOIN units u ON s.unitID = u.unitID
        $joins
        $where
    ", $params)->first();
$totalCount = $totalCountArr ? intval($totalCountArr->cnt) : 0;
$totalPages = max(1, ceil($totalCount / $perPage));
if ($page > $totalPages) $page = $totalPages;
$offset = ($page-1)*$perPage;

// --- Main Query ---
$sql = "SELECT 
            s.*, 
            r.rankName, 
            r.rankIndex, 
            u.unitName
            " . ($filter_course !== '' ? ", c.courseName, sc.dateCompleted" : "") . "
        FROM staff s
        LEFT JOIN ranks r ON s.rankID = r.rankID
        LEFT JOIN units u ON s.unitID = u.unitID
        $joins
        $where
        GROUP BY s.svcNo
        ORDER BY u.unitName ASC, r.rankIndex ASC, s.lname ASC, s.fname ASC
        LIMIT $perPage OFFSET $offset";

$staff = $db->query($sql, $params)->results();

function getStaffCourses($db, $svcNo) {
    $courses = $db->query(
        "SELECT c.courseName, sc.dateCompleted 
         FROM staff_courses sc 
         JOIN courses c ON sc.courseID = c.courseID 
         WHERE sc.svcNo = ? 
         ORDER BY c.courseName ASC", 
        [$svcNo]
    )->results();
    $out = [];
    foreach ($courses as $course) {
        $out[] = htmlspecialchars($course->courseName) .
            ($course->dateCompleted ? " <small class='text-muted'>(" . htmlspecialchars($course->dateCompleted) . ")</small>" : "");
    }
    return implode("<br>", $out);
}

// --- Prepare Time Series for JS ---
$monthlyLabels = [];
$monthlySeries = [];
foreach ($courseMonthlyStats as $row) {
    $monthlyLabels[$row->month] = true;
    $monthlySeries[$row->courseName][$row->month] = intval($row->completions);
}
$monthlyLabels = array_keys($monthlyLabels);
sort($monthlyLabels);
$seriesData = [];
foreach ($monthlySeries as $course => $months) {
    $data = [];
    foreach ($monthlyLabels as $m) $data[] = $months[$m] ?? 0;
    $seriesData[] = ['label'=>$course, 'data'=>$data];
}
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.css" rel="stylesheet" />
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/animate.css@4.1.1/animate.min.css"/>
<style>
#scrollToTopBtn {
    position: fixed;
    bottom: 32px;
    right: 32px;
    z-index: 9999;
    display: none;
    border-radius: 50%;
    width: 48px;
    height: 48px;
    background: #007bff;
    color: #fff;
    border: none;
    box-shadow: 0 2px 8px rgba(0,0,0,.2);
    font-size: 22px;
    transition: background .2s;
}
#scrollToTopBtn:hover { background: #0056b3; }
.breadcrumbs-drilldown {
    margin-bottom: 1rem;
    background: #f8f9fa;
    border-radius: 0.25rem;
    padding: 0.5rem 1rem;
}
.breadcrumbs-drilldown a { text-decoration: none; color: #0d6efd; }
.breadcrumbs-drilldown a:hover { text-decoration: underline; }
</style>
<!-- Live Notification Popup Container -->
<div id="liveNotificationContainer" style="position:fixed;bottom:30px;right:30px;z-index:20000;"></div>
<div class="container my-5">
    <div class="mb-3">
        <a href="../admin_branch.php" class="btn btn-outline-secondary"><i class="fa fa-arrow-left"></i> Back to Admin Branch</a>
    </div>
    <?php
    if (count($breadcrumbs) > 0): ?>
        <div class="breadcrumbs-drilldown">
            <i class="fa fa-map-marker-alt"></i>
            <?php foreach ($breadcrumbs as $i => $crumb): ?>
                <?php if ($i > 0): ?> <span class="mx-1">&rsaquo;</span> <?php endif; ?>
                <a href="<?=htmlspecialchars($crumb['url'])?>"><?=htmlspecialchars($crumb['label'])?></a>
            <?php endforeach; ?>
            <span class="mx-1">&rsaquo;</span>
            <span class="fw-bold">Current</span>
        </div>
    <?php endif; ?>
    <div class="card shadow-sm">
        <div class="card-header bg-info text-white d-flex flex-wrap align-items-center justify-content-between">
            <h4 class="mb-0"><i class="fa fa-graduation-cap"></i> List By Courses Done</h4>
            <form method="post" action="" class="d-inline">
                <button type="button" class="btn btn-outline-success btn-sm" id="exportCSV"><i class="fa fa-file-csv"></i> Export CSV</button>
                <button type="button" class="btn btn-outline-secondary btn-sm ms-2" id="exportCharts"><i class="fa fa-download"></i> Export Charts</button>
                <button type="button" class="btn btn-outline-primary btn-sm ms-2" id="saveDashboard"><i class="fa fa-save"></i> Save Dashboard</button>
            </form>
        </div>
        <div class="card-body">
            <form class="row g-3 mb-4" method="get" action="" id="filterForm" autocomplete="off">
                <!-- preserve breadcrumbs -->
                <?php if (isset($_GET['breadcrumbs'])): ?>
                    <input type="hidden" name="breadcrumbs" value="<?=htmlspecialchars($_GET['breadcrumbs'])?>">
                <?php endif; ?>
                <div class="col-md-2">
                    <input type="text" name="search" class="form-control" placeholder="Search (Svc No, Name, Unit)" value="<?=htmlspecialchars($search)?>">
                </div>
                <div class="col-md-2">
                    <select name="unitID" class="form-select dynamic-filter" id="unitFilter" multiple>
                        <?php foreach ($units as $u): ?>
                            <option value="<?=$u->unitID?>" <?=in_array($u->unitID, (array)$filter_unit)?'selected':''?>><?=htmlspecialchars($u->unitName)?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="rankID" class="form-select dynamic-filter" id="rankFilter" multiple>
                        <?php foreach ($ranks as $r): ?>
                            <option value="<?=$r->rankID?>" <?=in_array($r->rankID, (array)$filter_rank)?'selected':''?>><?=htmlspecialchars($r->rankName)?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="category" class="form-select dynamic-filter" id="categoryFilter" multiple>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?=$cat?>" <?=in_array($cat, (array)$filter_category)?'selected':''?>><?=$cat?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="courseID" class="form-select dynamic-filter" id="courseFilter" multiple>
                        <?php foreach ($courses as $c): ?>
                            <option value="<?=$c->courseID?>" <?=in_array($c->courseID, (array)$filter_course)?'selected':''?>><?=htmlspecialchars($c->courseName)?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 d-grid">
                    <button type="submit" class="btn btn-info"><i class="fa fa-search"></i> Filter</button>
                </div>
            </form>
            <!-- Advanced Analytics: Time Series -->
            <div class="card mb-4">
                <div class="card-header bg-light">
                    <strong><i class="fa fa-chart-line"></i> Course Completions Over Time</strong>
                </div>
                <div class="card-body">
                    <canvas id="timeseriesChart" height="120"></canvas>
                </div>
            </div>
            <!-- Analytics Charts with Drill-down -->
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <canvas id="unitChart" style="cursor:pointer"></canvas>
                </div>
                <div class="col-md-4">
                    <canvas id="rankChart" style="cursor:pointer"></canvas>
                </div>
                <div class="col-md-4">
                    <canvas id="courseChart" style="cursor:pointer"></canvas>
                </div>
            </div>
            <!-- Analytics List -->
            <div class="mb-3">
                <div class="row g-2">
                    <div class="col-md-4">
                        <div class="bg-light rounded p-2 border h-100">
                            <b><i class="fa fa-users"></i> Staff by Unit:</b>
                            <ul class="mb-0 small" id="unitList">
                                <?php foreach (array_slice($unitCounts,0,5) as $uc): ?>
                                    <li><?=htmlspecialchars($uc->unitName ?? 'Unknown')?>: <b><?=intval($uc->staffCount)?></b></li>
                                <?php endforeach; ?>
                                <?php if(count($unitCounts)>5): ?><li class="text-muted">...more</li><?php endif; ?>
                            </ul>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="bg-light rounded p-2 border h-100">
                            <b><i class="fa fa-user-graduate"></i> Staff by Rank:</b>
                            <ul class="mb-0 small" id="rankList">
                                <?php foreach (array_slice($rankCounts,0,7) as $rc): ?>
                                    <li><?=htmlspecialchars($rc->rankName ?? 'Unknown')?>: <b><?=intval($rc->staffCount)?></b></li>
                                <?php endforeach; ?>
                                <?php if(count($rankCounts)>7): ?><li class="text-muted">...more</li><?php endif; ?>
                            </ul>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="bg-light rounded p-2 border h-100">
                            <?php if ($filter_course !== ''): ?>
                                <b><i class="fa fa-chart-bar"></i> Course Completion:</b>
                                <ul class="mb-0 small">
                                    <li><span class="text-success">Completed:</span> <b><?=intval($courseStats->completed ?? 0)?></b></li>
                                    <li><span class="text-danger">Not Completed:</span> <b><?=intval($courseStats->notCompleted ?? 0)?></b></li>
                                    <li><span class="text-secondary">Total:</span> <b><?=intval($courseStats->totalStaff ?? 0)?></b></li>
                                </ul>
                            <?php elseif ($courseCounts && count($courseCounts)>0): ?>
                                <b><i class="fa fa-list"></i> Top Courses:</b>
                                <ul class="mb-0 small" id="courseList">
                                    <?php foreach(array_slice($courseCounts,0,5) as $cc): ?>
                                        <li><?=htmlspecialchars($cc->courseName)?>: <b><?=intval($cc->staffCount)?></b></li>
                                    <?php endforeach; ?>
                                    <?php if(count($courseCounts)>5): ?><li class="text-muted">...more</li><?php endif; ?>
                                </ul>
                            <?php else: ?>
                                <span class="text-muted">No course analytics available.</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Results Table -->
            <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle" id="courseTable">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Unit</th>
                            <th>Rank</th>
                            <th>Service No</th>
                            <th>Surname</th>
                            <th>First Name(s)</th>
                            <th>Category</th>
                            <th>Date of Birth</th>
                            <th>Date of Enlistment</th>
                            <th>Status</th>
                            <th>Courses Done</th>
                            <?php if ($filter_course !== ''): ?>
                                <th>Course</th>
                                <th>Date Completed</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($staff) == 0): ?>
                            <tr><td colspan="<?=($filter_course !== '') ? '13' : '11'?>" class="text-center text-muted">No staff found.</td></tr>
                        <?php else: $i=1; foreach ($staff as $s): ?>
                            <tr>
                                <td><?=$offset + $i++?></td>
                                <td><?=htmlspecialchars($s->unitName ?? '')?></td>
                                <td><?=htmlspecialchars($s->rankName ?? '')?></td>
                                <td><?=htmlspecialchars($s->svcNo ?? '')?></td>
                                <td><?=htmlspecialchars($s->lname ?? '')?></td>
                                <td><?=htmlspecialchars($s->fname ?? '')?></td>
                                <td><?=htmlspecialchars($s->category ?? '')?></td>
                                <td><?=htmlspecialchars($s->DOB ?? '')?></td>
                                <td><?=htmlspecialchars($s->attestDate ?? '')?></td>
                                <td><?=htmlspecialchars($s->svcStatus ?? '')?></td>
                                <td><?=getStaffCourses($db, $s->svcNo)?></td>
                                <?php if ($filter_course !== ''): ?>
                                    <td><?=htmlspecialchars($s->courseName ?? '')?></td>
                                    <td><?=htmlspecialchars($s->dateCompleted ?? '')?></td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
            <!-- Pagination -->
            <nav>
                <ul class="pagination justify-content-center">
                    <?php for ($p=1; $p<=$totalPages; $p++): ?>
                        <li class="page-item<?=$page==$p?' active':''?>">
                            <a class="page-link" href="<?=htmlspecialchars($_SERVER['PHP_SELF'])?>?<?=http_build_query(array_merge($_GET,['page'=>$p]))?>"><?=$p?></a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
            <div class="text-muted small mt-3">
                <i class="fa fa-info-circle"></i>
                Showing <?=($totalCount==0)?0:(($offset+1))?> - <?=min($offset+$perPage, $totalCount)?> of <?=$totalCount?> record(s).
                <?php if ($filter_course !== ''): ?>
                    Showing only staff who have completed the selected course.
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<button id="scrollToTopBtn" title="Scroll to Top"><i class="fa fa-arrow-up"></i></button>

<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
// --- AJAX filter chaining ---
$('.dynamic-filter').select2({width:'100%'});
$('.dynamic-filter').on('change', function () {
    let data = {
        unitID: $('#unitFilter').val(),
        rankID: $('#rankFilter').val(),
        category: $('#categoryFilter').val(),
        courseID: $('#courseFilter').val()
    };
    $.getJSON('reports_courses_filters.php', data, function (res) {
        if (res.units) {
            let unitSel = $('#unitFilter');
            let currUnit = unitSel.val() || [];
            unitSel.empty();
            $.each(res.units, function (_, u) {
                unitSel.append('<option value="'+u.unitID+'">'+u.unitName+'</option>');
            });
            unitSel.val(currUnit).trigger('change.select2');
        }
        if (res.ranks) {
            let rankSel = $('#rankFilter');
            let currRank = rankSel.val() || [];
            rankSel.empty();
            $.each(res.ranks, function (_, r) {
                rankSel.append('<option value="'+r.rankID+'">'+r.rankName+'</option>');
            });
            rankSel.val(currRank).trigger('change.select2');
        }
        if (res.categories) {
            let catSel = $('#categoryFilter');
            let currCat = catSel.val() || [];
            catSel.empty();
            $.each(res.categories, function (_, c) {
                catSel.append('<option value="'+c+'">'+c+'</option>');
            });
            catSel.val(currCat).trigger('change.select2');
        }
        if (res.courses) {
            let courseSel = $('#courseFilter');
            let currCourse = courseSel.val() || [];
            courseSel.empty();
            $.each(res.courses, function (_, c) {
                courseSel.append('<option value="'+c.courseID+'">'+c.courseName+'</option>');
            });
            courseSel.val(currCourse).trigger('change.select2');
        }
    });
});

// --- Breadcrumbs/Drilldown State ---
function getCurrentBreadcrumbs(label) {
    let crumbs = [];
    if ($('input[name="breadcrumbs"]').length) {
        let existing = $('input[name="breadcrumbs"]').val();
        if (existing) crumbs = existing.split('|');
    }
    let url = window.location.pathname + '?' + $('#filterForm').serialize().replace(/&?page=\d+/g, '');
    url = url.replace(/breadcrumbs=[^&]*&?/,'');
    crumbs.push(encodeURIComponent(label)+';'+encodeURIComponent(url));
    return crumbs.join('|');
}

// --- Drill-down visualization: chart click sets filter, submits form (with breadcrumb) ---
function drillDownFilter(type, value, label) {
    if (type === 'unit') { $('#unitFilter').val([value]).trigger('change'); }
    if (type === 'rank') { $('#rankFilter').val([value]).trigger('change'); }
    if (type === 'course') { $('#courseFilter').val([value]).trigger('change'); }
    setTimeout(function(){
        let form = $('#filterForm');
        form.find('input[name="breadcrumbs"]').remove();
        let newCrumbs = getCurrentBreadcrumbs(label);
        $('<input>').attr({
            type: 'hidden',
            name: 'breadcrumbs',
            value: newCrumbs
        }).appendTo(form);
        form.submit();
    }, 100);
}

// --- Chart.js Analytics with Drill-down ---
const unitChartCtx = document.getElementById('unitChart').getContext('2d');
const rankChartCtx = document.getElementById('rankChart').getContext('2d');
const courseChartCtx = document.getElementById('courseChart').getContext('2d');
const timeseriesChartCtx = document.getElementById('timeseriesChart').getContext('2d');

let unitLabels = <?=json_encode(array_column($unitCounts, 'unitName'))?>;
let unitIDs = <?=json_encode(array_column($unitCounts, 'unitID'))?>;
let unitData = <?=json_encode(array_map('intval', array_column($unitCounts, 'staffCount')));?>;

let rankLabels = <?=json_encode(array_column($rankCounts, 'rankName'))?>;
let rankIDs = <?=json_encode(array_column($rankCounts, 'rankID'))?>;
let rankData = <?=json_encode(array_map('intval', array_column($rankCounts, 'staffCount')));?>;

let courseLabels = <?=json_encode(array_column($courseCounts, 'courseName'))?>;
let courseIDs = <?=json_encode(array_column($courseCounts, 'courseID'))?>;
let courseData = <?=json_encode(array_map('intval', array_column($courseCounts, 'staffCount')));?>;

// --- Drill-down Bar Charts ---
const unitChart = new Chart(unitChartCtx, {
    type: 'bar',
    data: {
        labels: unitLabels,
        datasets: [{
            label: 'Staff by Unit',
            data: unitData,
            backgroundColor: 'rgba(54, 162, 235, 0.5)'
        }]
    },
    options: {
        plugins: {legend: {display: false}},
        onClick: function(evt, elements) {
            if (elements.length) {
                let idx = elements[0].index;
                drillDownFilter('unit', unitIDs[idx], unitLabels[idx]);
            }
        },
        scales: {x: {ticks: {autoSkip: false}}}
    }
});
const rankChart = new Chart(rankChartCtx, {
    type: 'bar',
    data: {
        labels: rankLabels,
        datasets: [{
            label: 'Staff by Rank',
            data: rankData,
            backgroundColor: 'rgba(255, 206, 86, 0.5)'
        }]
    },
    options: {
        plugins: {legend: {display: false}},
        onClick: function(evt, elements) {
            if (elements.length) {
                let idx = elements[0].index;
                drillDownFilter('rank', rankIDs[idx], rankLabels[idx]);
            }
        },
        scales: {x: {ticks: {autoSkip: false}}}
    }
});
const courseChart = new Chart(courseChartCtx, {
    type: 'bar',
    data: {
        labels: courseLabels,
        datasets: [{
            label: 'Staff by Course',
            data: courseData,
            backgroundColor: 'rgba(75, 192, 192, 0.5)'
        }]
    },
    options: {
        plugins: {legend: {display: false}},
        onClick: function(evt, elements) {
            if (elements.length) {
                let idx = elements[0].index;
                drillDownFilter('course', courseIDs[idx], courseLabels[idx]);
            }
        },
        scales: {x: {ticks: {autoSkip: false}}}
    }
});

// --- Time Series Chart: Course Completions Over Time ---
let monthlyLabels = <?=json_encode($monthlyLabels)?>;
let seriesData = <?=json_encode($seriesData)?>;
const timeseriesChart = new Chart(timeseriesChartCtx, {
    type: 'line',
    data: {
        labels: monthlyLabels,
        datasets: seriesData.map((s, i) => ({
            label: s.label,
            data: s.data,
            borderColor: 'hsl('+(i*37)%360+',60%,50%)',
            fill: false,
            tension: 0.3
        }))
    },
    options: {
        responsive: true,
        plugins: {
            legend: {display: true, position: "bottom"}
        },
        scales: {x: {title: {display:true, text:'Month'}}, y: {title:{display:true, text:'Completions'}}}
    }
});

// --- CSV Export ---
document.getElementById('exportCSV').addEventListener('click', function () {
    let rows = [];
    document.querySelectorAll('#courseTable thead tr, #courseTable tbody tr').forEach(row => {
        let csvRow = [];
        row.querySelectorAll('th,td').forEach(cell => {
            let text = cell.innerText.replace(/\s+/g, ' ').trim();
            csvRow.push('"' + text.replace(/"/g, '""') + '"');
        });
        rows.push(csvRow.join(','));
    });
    let csvContent = rows.join("\r\n");
    let blob = new Blob([csvContent], {type: 'text/csv'});
    let url = URL.createObjectURL(blob);
    let a = document.createElement('a');
    a.href = url;
    a.download = "courses_report.csv";
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
});

// --- Chart Export ---
document.getElementById('exportCharts').addEventListener('click', function() {
    let charts = [unitChart, rankChart, courseChart, timeseriesChart];
    charts.forEach((chart, i) => {
        let url = chart.toBase64Image();
        let a = document.createElement('a');
        a.href = url;
        a.download = 'chart_' + i + '.png';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
    });
});

// --- Save Dashboard (to localStorage) ---
document.getElementById('saveDashboard').addEventListener('click', function() {
    let state = {
        filters: $('#filterForm').serialize(),
        breadcrumbs: $('input[name="breadcrumbs"]').val() || ''
    };
    localStorage.setItem('dashboardState', JSON.stringify(state));
    alert('Dashboard configuration saved!');
});
$(document).ready(function() {
    // Load saved dashboard if exists
    let state = localStorage.getItem('dashboardState');
    if (state) {
        state = JSON.parse(state);
        let params = new URLSearchParams(state.filters);
        for (const [key, value] of params) {
            let $el = $('[name="'+key+'"]');
            if ($el.is('select')) $el.val(value.split(',')).trigger('change');
            else $el.val(value);
        }
        if (state.breadcrumbs) {
            $('input[name="breadcrumbs"]').val(state.breadcrumbs);
        }
    }
});

// --- Scroll-to-top ---
let scrollBtn = document.getElementById("scrollToTopBtn");
window.onscroll = function() {
    if (document.body.scrollTop > 200 || document.documentElement.scrollTop > 200) {
        scrollBtn.style.display = "block";
    } else {
        scrollBtn.style.display = "none";
    }
};
scrollBtn.onclick = function() {
    window.scrollTo({top: 0, behavior: 'smooth'});
};

// --- Focus search input on load ---
document.querySelector('input[name="search"]').focus();
// --- Breadcrumbs and Drilldown State Management ---
// --- Live Popup Notifications Implementation ---

/**
 * Show a live notification popup.
 * @param {string} message - The message to display
 * @param {string} type - success/info/warning/danger
 * @param {number} autoClose - milliseconds before auto close (0 to disable)
 */
function showLiveNotification(message, type = 'info', autoClose = 5000, notifId = null) {
    const id = 'notif-' + Math.random().toString(36).substr(2, 8);
    let $notif = $(`
        <div id="${id}" class="alert alert-${type} shadow fade show animate__animated animate__fadeInUp"
             style="min-width:300px;max-width:400px;margin-bottom:16px;cursor:pointer;">
            <button type="button" class="btn-close float-end" aria-label="Close"></button>
            <i class="fa fa-bell"></i>
            <span class="ms-2">${message}</span>
        </div>
    `);
    $('#liveNotificationContainer').append($notif);

    function markRead() {
        if (notifId) {
            $.post('mark_notification_read.php', {id: notifId});
        }
    }

    $notif.on('click', function() { $notif.alert('close'); markRead(); });
    $notif.find('.btn-close').on('click', function(e){ e.stopPropagation(); $notif.alert('close'); markRead(); });

    if (autoClose > 0) setTimeout(() => { $notif.alert('close'); markRead(); }, autoClose);
}

let lastNotifId = 0;
function pollNotifications() {
    $.ajax({
        url: 'live_notifications.php',
        method: 'GET',
        dataType: 'json',
        data: {since: lastNotifId},
        success: function(resp) {
            if (resp && Array.isArray(resp.notifications)) {
                resp.notifications.forEach(function(notif) {
                    if (notif.id > lastNotifId) lastNotifId = notif.id;
                    showLiveNotification(notif.message, notif.type || 'info', notif.timeout || 5000, notif.id);
                });
            }
        },
        complete: function() {
            setTimeout(pollNotifications, 15000);
        }
    });
}
$(function(){ pollNotifications(); });

// --- Demo: Local notification trigger ---
// Uncomment to test popup
//showLiveNotification("This is a test notification!","success",4000);

// --- Other JS: drilldown, CSV export, chart export, dashboard save/load, scroll-to-top, etc. ---

</script>

<?php require_once $abs_us_root . $us_url_root . 'users/includes/html_footer.php'; ?>