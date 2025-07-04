<?php
require_once '../init.php';
require_once $abs_us_root . $us_url_root . 'users/includes/template/prep.php';

if (!securePage($_SERVER['PHP_SELF'])) { die(); }

// Fetch trades, units, ranks, and categories for filter dropdowns
$trades = $db->query("SELECT DISTINCT trade FROM staff WHERE trade IS NOT NULL AND trade != '' ORDER BY trade ASC")->results();
$units = $db->query("SELECT unitID, unitName FROM units ORDER BY unitName ASC")->results();
$ranks = $db->query("SELECT rankID, rankName, rankIndex FROM ranks ORDER BY rankIndex ASC")->results();
$categories = ['Officer', 'NCO', 'Civilian'];

// Handle filters
$filter_trade = $_GET['trade'] ?? '';
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

if ($filter_trade !== '') {
    $sql .= " AND s.trade = ?";
    $params[] = $filter_trade;
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
    $sql .= " AND (s.trade LIKE ? OR s.lname LIKE ? OR s.fname LIKE ? OR s.svcNo LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// Order: Officers/NCOs by rankIndex/datePromoted, Civilians always at the bottom by attestDate
$sql .= " ORDER BY 
    CASE WHEN s.category = 'Civilian' THEN 1 ELSE 0 END ASC,
    s.trade ASC,
    r.rankIndex ASC,
    COALESCE(datePromoted, s.attestDate) ASC,
    s.lname ASC,
    s.fname ASC";

$staff = $db->query($sql, $params)->results();

// Fetch trade info if selected
$tradeInfo = null;
if ($filter_trade !== '') {
    $tradeInfo = [
        'total' => $db->query("SELECT COUNT(*) as total FROM staff WHERE trade = ?", [$filter_trade])->first()->total,
        'units' => $db->query("SELECT DISTINCT u.unitName FROM staff s LEFT JOIN units u ON s.unitID = u.unitID WHERE s.trade = ? AND u.unitName IS NOT NULL", [$filter_trade])->results(),
        'ranks' => $db->query("SELECT DISTINCT r.rankName FROM staff s LEFT JOIN ranks r ON s.rankID = r.rankID WHERE s.trade = ? AND r.rankName IS NOT NULL", [$filter_trade])->results(),
        'categories' => $db->query("SELECT DISTINCT category FROM staff WHERE trade = ?", [$filter_trade])->results(),
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
        <div class="card-header bg-success text-white d-flex align-items-center justify-content-between">
            <h4 class="mb-0"><i class="fa fa-tools"></i> List by Trade</h4>
            <span class="badge bg-light text-success fs-6"><?= count($staff) ?> Staff Listed</span>
        </div>
        <div class="card-body">
            <form class="row g-3 mb-4" method="get" action="">
                <div class="col-md-3">
                    <input type="text" name="search" class="form-control" placeholder="Search by Trade, Name, Service No" value="<?=htmlspecialchars($search)?>">
                </div>
                <div class="col-md-2">
                    <select name="trade" class="form-select">
                        <option value="">All Trades</option>
                        <?php foreach ($trades as $t): ?>
                            <option value="<?=htmlspecialchars($t->trade)?>" <?=($filter_trade==$t->trade)?'selected':''?>><?=htmlspecialchars($t->trade)?></option>
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
                    <button type="submit" class="btn btn-success"><i class="fa fa-search"></i> Filter</button>
                </div>
            </form>

            <?php if ($filter_trade && $tradeInfo): ?>
                <div class="alert alert-info mb-4">
                    <strong>Trade:</strong> <?=htmlspecialchars($filter_trade)?><br>
                    <strong>Total Staff:</strong> <?=htmlspecialchars($tradeInfo['total'])?><br>
                    <strong>Units:</strong>
                    <?php
                        $unitNames = array_map(function($u){ return $u->unitName; }, $tradeInfo['units']);
                        echo $unitNames ? htmlspecialchars(implode(', ', $unitNames)) : 'None';
                    ?><br>
                    <strong>Ranks:</strong>
                    <?php
                        $rankNames = array_map(function($r){ return $r->rankName; }, $tradeInfo['ranks']);
                        echo $rankNames ? htmlspecialchars(implode(', ', $rankNames)) : 'None';
                    ?><br>
                    <strong>Categories:</strong>
                    <?php
                        $catNames = array_map(function($c){ return $c->category; }, $tradeInfo['categories']);
                        echo $catNames ? htmlspecialchars(implode(', ', $catNames)) : 'None';
                    ?>
                </div>
            <?php endif; ?>

            <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle table-striped" id="tradeTable">
                    <thead class="table-success">
                        <tr>
                            <th>#</th>
                            <th>Trade</th>
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
                                <td><?=htmlspecialchars($s->trade)?></td>
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