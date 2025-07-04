<?php
require_once '../init.php';
require_once $abs_us_root . $us_url_root . 'users/includes/template/prep.php';

if (!securePage($_SERVER['PHP_SELF'])) { die(); }

// Fetch marital statuses, ranks, units, and categories for filter dropdowns
$maritals = $db->query("SELECT DISTINCT maritalStatus FROM staff WHERE maritalStatus IS NOT NULL AND maritalStatus != '' ORDER BY maritalStatus ASC")->results();
$ranks = $db->query("SELECT rankID, rankName, rankIndex FROM ranks ORDER BY rankIndex ASC")->results();
$units = $db->query("SELECT unitID, unitName FROM units ORDER BY unitName ASC")->results();
$categories = ['Officer', 'NCO', 'Civilian'];

// Handle filters
$filter_marital = $_GET['maritalStatus'] ?? '';
$filter_rank = $_GET['rankID'] ?? '';
$filter_unit = $_GET['unitID'] ?? '';
$filter_category = $_GET['category'] ?? '';
$search = trim($_GET['search'] ?? '');

$params = [];

$sql = "SELECT 
            s.*, 
            r.rankName, 
            r.rankIndex, 
            u.unitName
        FROM staff s
        LEFT JOIN ranks r ON s.rankID = r.rankID
        LEFT JOIN units u ON s.unitID = u.unitID
        WHERE 1=1";

if ($filter_marital !== '') {
    $sql .= " AND s.maritalStatus = ?";
    $params[] = $filter_marital;
}
if ($filter_rank !== '') {
    $sql .= " AND s.rankID = ?";
    $params[] = $filter_rank;
}
if ($filter_unit !== '') {
    $sql .= " AND s.unitID = ?";
    $params[] = $filter_unit;
}
if ($filter_category !== '') {
    $sql .= " AND s.category = ?";
    $params[] = $filter_category;
}
if ($search !== '') {
    $sql .= " AND (s.maritalStatus LIKE ? OR s.svcNo LIKE ? OR s.lname LIKE ? OR s.fname LIKE ? OR r.rankName LIKE ? OR u.unitName LIKE ? OR s.category LIKE ? OR s.DOB LIKE ? OR s.attestDate LIKE ?)";
    for ($i = 0; $i < 9; $i++) {
        $params[] = "%$search%";
    }
}

// Order by marital status, rank, and name
$sql .= " ORDER BY 
    s.maritalStatus ASC,
    r.rankIndex ASC,
    s.lname ASC,
    s.fname ASC";

$staff = $db->query($sql, $params)->results();
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" />

<div class="container my-5">
    <div class="mb-3">
        <a href="../admin_branch.php" class="btn btn-outline-secondary"><i class="fa fa-arrow-left"></i> Back to Admin Branch</a>
    </div>
    <div class="card shadow">
        <div class="card-header bg-warning text-dark d-flex align-items-center justify-content-between">
            <h4 class="mb-0"><i class="fa fa-ring"></i> List by Marital Status</h4>
            <span class="badge bg-light text-warning fs-6"><?= count($staff) ?> Staff Listed</span>
        </div>
        <div class="card-body">
            <form class="row g-3 mb-4" method="get" action="">
                <div class="col-md-3">
                    <input type="text" id="maritalSearch" name="search" class="form-control" placeholder="Search by any field..." value="<?=htmlspecialchars($search)?>">
                </div>
                <div class="col-md-2">
                    <select name="maritalStatus" class="form-select">
                        <option value="">All Marital Statuses</option>
                        <?php foreach ($maritals as $m): ?>
                            <option value="<?=htmlspecialchars($m->maritalStatus)?>" <?=($filter_marital==$m->maritalStatus)?'selected':''?>><?=htmlspecialchars($m->maritalStatus)?></option>
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
                <div class="col-md-2">
                    <select name="unitID" class="form-select">
                        <option value="">All Units</option>
                        <?php foreach ($units as $u): ?>
                            <option value="<?=$u->unitID?>" <?=($filter_unit==$u->unitID)?'selected':''?>><?=htmlspecialchars($u->unitName)?></option>
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
                    <button type="submit" class="btn btn-warning text-dark"><i class="fa fa-search"></i> Filter</button>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle table-striped" id="maritalTable">
                    <thead class="table-warning">
                        <tr>
                            <th>#</th>
                            <th>Marital Status</th>
                            <th>Service No</th>
                            <th>Rank</th>
                            <th>Surname</th>
                            <th>First Name(s)</th>
                            <th>Unit</th>
                            <th>Category</th>
                            <th>Date of Birth</th>
                            <th>Date of Enlistment</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($staff) == 0): ?>
                            <tr><td colspan="10" class="text-center text-muted">No staff found.</td></tr>
                        <?php else: $i=1; foreach ($staff as $s): ?>
                            <tr>
                                <td><?=$i++?></td>
                                <td>
                                    <span class="badge bg-light text-dark"><?=htmlspecialchars($s->maritalStatus)?></span>
                                </td>
                                <td><?=htmlspecialchars($s->svcNo)?></td>
                                <td><?=htmlspecialchars($s->rankName)?></td>
                                <td><?=htmlspecialchars($s->lname)?></td>
                                <td><?=htmlspecialchars($s->fname)?></td>
                                <td><?=htmlspecialchars($s->unitName)?></td>
                                <td>
                                    <span class="badge bg-secondary"><?=htmlspecialchars($s->category)?></span>
                                </td>
                                <td><?=htmlspecialchars($s->DOB)?></td>
                                <td><?=htmlspecialchars($s->attestDate)?></td>
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
// Dynamic search/filter for the table (client-side)
document.getElementById('maritalSearch').addEventListener('keyup', function() {
    const query = this.value.toLowerCase();
    document.querySelectorAll('#maritalTable tbody tr').forEach(function(row) {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(query) ? '' : 'none';
    });
});
</script>

<?php require_once $abs_us_root . $us_url_root . 'users/includes/html_footer.php'; ?>