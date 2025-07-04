<?php
require_once '../init.php';
require_once $abs_us_root . $us_url_root . 'users/includes/template/prep.php';

if (!securePage($_SERVER['PHP_SELF'])) { die(); }

// Fetch genders, units, ranks, and categories for filter dropdowns
$genders = $db->query("SELECT DISTINCT gender FROM staff WHERE gender IS NOT NULL AND gender != '' ORDER BY gender ASC")->results();
$units = $db->query("SELECT unitID, unitName FROM units ORDER BY unitName ASC")->results();
$ranks = $db->query("SELECT rankID, rankName, rankIndex FROM ranks ORDER BY rankIndex ASC")->results();
$categories = ['Officer', 'NCO', 'Civilian'];

// Handle filters
$filter_gender = $_GET['gender'] ?? '';
$filter_unit = $_GET['unitID'] ?? '';
$filter_rank = $_GET['rankID'] ?? '';
$filter_category = $_GET['category'] ?? '';
$search = trim($_GET['search'] ?? '');

$params = [];

$sql = "SELECT 
            s.*, 
            r.rankName, 
            r.rankIndex, 
            u.unitName,
            (
                SELECT MIN(p.datePromoted)
                FROM staff_promotions p
                WHERE p.svcNo = s.svcNo AND p.rankID = s.rankID
            ) AS datePromoted
        FROM staff s
        LEFT JOIN ranks r ON s.rankID = r.rankID
        LEFT JOIN units u ON s.unitID = u.unitID
        WHERE 1=1";

if ($filter_gender !== '') {
    $sql .= " AND s.gender = ?";
    $params[] = $filter_gender;
}
if ($filter_unit !== '') {
    $sql .= " AND s.unitID = ?";
    $params[] = $filter_unit;
}
if ($filter_rank !== '') {
    $sql .= " AND s.rankID = ?";
    $params[] = $filter_rank;
}
if ($filter_category !== '') {
    $sql .= " AND s.category = ?";
    $params[] = $filter_category;
}
if ($search !== '') {
    $sql .= " AND (s.gender LIKE ? OR s.lname LIKE ? OR s.fname LIKE ? OR s.svcNo LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// Order: Officers/NCOs by rankIndex/datePromoted, Civilians always at the bottom by attestDate
$sql .= " ORDER BY 
    CASE WHEN s.category = 'Civilian' THEN 1 ELSE 0 END ASC,
    s.gender ASC,
    r.rankIndex ASC,
    COALESCE(datePromoted, s.attestDate) ASC,
    s.lname ASC,
    s.fname ASC";

$staff = $db->query($sql, $params)->results();

// Fetch gender info if selected
$genderInfo = null;
if ($filter_gender !== '') {
    $genderInfo = [
        'total' => $db->query("SELECT COUNT(*) as total FROM staff WHERE gender = ?", [$filter_gender])->first()->total,
        'units' => $db->query("SELECT DISTINCT u.unitName FROM staff s LEFT JOIN units u ON s.unitID = u.unitID WHERE s.gender = ? AND u.unitName IS NOT NULL", [$filter_gender])->results(),
        'ranks' => $db->query("SELECT DISTINCT r.rankName FROM staff s LEFT JOIN ranks r ON s.rankID = r.rankID WHERE s.gender = ? AND r.rankName IS NOT NULL", [$filter_gender])->results(),
        'categories' => $db->query("SELECT DISTINCT category FROM staff WHERE gender = ?", [$filter_gender])->results(),
    ];
}
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" />

<div class="container my-5">
    <div class="mb-3">
        <a href="../admin_branch.php" class="btn btn-outline-secondary"><i class="fa fa-arrow-left"></i> Back to Admin Branch</a>
    </div>
    <div class="card shadow-sm">
        <div class="card-header bg-info text-white d-flex align-items-center justify-content-between">
            <h4 class="mb-0"><i class="fa fa-venus-mars"></i> List by Gender</h4>
            <span class="badge bg-light text-info fs-6"><?= count($staff) ?> Staff Listed</span>
        </div>
        <div class="card-body">
            <form class="row g-3 mb-4" method="get" action="">
                <div class="col-md-3">
                    <input type="text" name="search" class="form-control" placeholder="Search by Gender, Name, Service No" value="<?=htmlspecialchars($search)?>">
                </div>
                <div class="col-md-2">
                    <select name="gender" class="form-select">
                        <option value="">All Genders</option>
                        <?php foreach ($genders as $g): ?>
                            <option value="<?=htmlspecialchars($g->gender)?>" <?=($filter_gender==$g->gender)?'selected':''?>><?=htmlspecialchars($g->gender)?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="unitID" class="form-select">
                        <option value="">All Units</option>
                        <?php foreach ($units as $u): ?>
                            <option value="<?=$u->unitID?>" <?=($filter_unit==$u->unitID)?'selected':''?>><?=htmlspecialchars($u->unitName)?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="rankID" class="form-select">
                        <option value="">All Ranks</option>
                        <?php foreach ($ranks as $r): ?>
                            <option value="<?=$r->rankID?>" <?=($filter_rank==$r->rankID)?'selected':''?>><?=htmlspecialchars($r->rankName)?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-1">
                    <select name="category" class="form-select">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?=$cat?>" <?=($filter_category==$cat)?'selected':''?>><?=$cat?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 d-grid">
                    <button type="submit" class="btn btn-info text-white"><i class="fa fa-search"></i> Filter</button>
                </div>
            </form>

            <?php if ($filter_gender && $genderInfo): ?>
                <div class="alert alert-info mb-4">
                    <strong>Gender:</strong> <?=htmlspecialchars($filter_gender)?><br>
                    <strong>Total Staff:</strong> <?=htmlspecialchars($genderInfo['total'])?><br>
                    <strong>Units:</strong>
                    <?php
                        $unitNames = array_map(function($u){ return $u->unitName; }, $genderInfo['units']);
                        echo $unitNames ? htmlspecialchars(implode(', ', $unitNames)) : 'None';
                    ?><br>
                    <strong>Ranks:</strong>
                    <?php
                        $rankNames = array_map(function($r){ return $r->rankName; }, $genderInfo['ranks']);
                        echo $rankNames ? htmlspecialchars(implode(', ', $rankNames)) : 'None';
                    ?><br>
                    <strong>Categories:</strong>
                    <?php
                        $catNames = array_map(function($c){ return $c->category; }, $genderInfo['categories']);
                        echo $catNames ? htmlspecialchars(implode(', ', $catNames)) : 'None';
                    ?>
                </div>
            <?php endif; ?>

            <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle table-striped" id="genderTable">
                    <thead class="table-info">
                        <tr>
                            <th>#</th>
                            <th>Gender</th>
                            <th>Unit</th>
                            <th>Rank</th>
                            <th>Service No</th>
                            <th>Surname</th>
                            <th>First Name(s)</th>
                            <th>Category</th>
                            <th>Date of Birth</th>
                            <th>Date of Enlistment</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($staff) == 0): ?>
                            <tr><td colspan="11" class="text-center text-muted">No staff found.</td></tr>
                        <?php else: $i=1; foreach ($staff as $s): ?>
                            <tr>
                                <td><?=$i++?></td>
                                <td><?=htmlspecialchars($s->gender)?></td>
                                <td><?=htmlspecialchars($s->unitName)?></td>
                                <td><?=htmlspecialchars($s->rankName)?></td>
                                <td><?=htmlspecialchars($s->svcNo)?></td>
                                <td><?=htmlspecialchars($s->lname)?></td>
                                <td><?=htmlspecialchars($s->fname)?></td>
                                <td><?=htmlspecialchars($s->category)?></td>
                                <td><?=htmlspecialchars($s->DOB)?></td>
                                <td><?=htmlspecialchars($s->attestDate)?></td>
                                <td><?=htmlspecialchars($s->svcStatus)?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="text-end mt-2">
                <button onclick="window.print()" class="btn btn-outline-secondary btn-sm"><i class="fa fa-print"></i> Print Report</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.querySelector('input[name="search"]').focus();
</script>

<?php require_once $abs_us_root . $us_url_root . 'users/includes/html_footer.php'; ?>