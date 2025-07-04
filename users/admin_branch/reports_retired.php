<?php
require_once '../init.php';
require_once $abs_us_root . $us_url_root . 'users/includes/template/prep.php';

if (!securePage($_SERVER['PHP_SELF'])) { die(); }

// Fetch appointments, ranks, units, and categories for filter dropdowns
$appointments = $db->query("SELECT DISTINCT appointment FROM staff WHERE appointment IS NOT NULL AND appointment != '' ORDER BY appointment ASC")->results();
$ranks = $db->query("SELECT rankID, rankName, rankIndex FROM ranks ORDER BY rankIndex ASC")->results();
$units = $db->query("SELECT unitID, unitName FROM units ORDER BY unitName ASC")->results();
$categories = ['Officer', 'NCO', 'Civilian'];

// Handle filters
$filter_appointment = $_GET['appointment'] ?? '';
$filter_rank = $_GET['rankID'] ?? '';
$filter_unit = $_GET['unitID'] ?? '';
$filter_category = $_GET['category'] ?? '';
$search = trim($_GET['search'] ?? '');

$params = [];

// Only retired staff
$sql = "SELECT 
            s.*, 
            r.rankName, 
            r.rankIndex, 
            u.unitName,
            (
                SELECT MIN(p.datePromoted)
                FROM staff_promotions p
                WHERE p.svcNo = s.svcNo AND p.rankID = s.rankID
            ) AS datePromoted,
            (
                SELECT GROUP_CONCAT(CONCAT(a.advancementType, ' (', a.advancementDate, ')') ORDER BY a.advancementDate ASC SEPARATOR ', ')
                FROM staff_advancements a
                WHERE a.svcNo = s.svcNo
            ) AS advancements
        FROM staff s
        LEFT JOIN ranks r ON s.rankID = r.rankID
        LEFT JOIN units u ON s.unitID = u.unitID
        WHERE s.svcStatus = 'Retired'";

if ($filter_appointment !== '') {
    $sql .= " AND s.appointment = ?";
    $params[] = $filter_appointment;
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
    $sql .= " AND (s.appointment LIKE ? OR s.svcNo LIKE ? OR s.lname LIKE ? OR s.fname LIKE ? OR r.rankName LIKE ? OR u.unitName LIKE ? OR s.category LIKE ? OR s.svcStatus LIKE ? OR s.DOB LIKE ? OR s.attestDate LIKE ?)";
    for ($i = 0; $i < 10; $i++) {
        $params[] = "%$search%";
    }
}

// Order: Officers/NCOs by rankIndex/datePromoted, Civilians always at the bottom by attestDate
$sql .= " ORDER BY 
    CASE WHEN s.category = 'Civilian' THEN 1 ELSE 0 END ASC,
    r.rankIndex ASC,
    CASE WHEN s.category = 'Civilian' THEN s.attestDate ELSE datePromoted END ASC,
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
    <div class="card shadow-sm">
        <div class="card-header bg-secondary text-white d-flex align-items-center justify-content-between">
            <h4 class="mb-0"><i class="fa fa-user-times"></i> Retired Staff</h4>
            <span class="badge bg-light text-secondary fs-6"><?= count($staff) ?> Retired Staff Listed</span>
        </div>
        <div class="card-body">
            <form class="row g-3 mb-4" method="get" action="">
                <div class="col-md-3">
                    <input type="text" id="retiredSearch" name="search" class="form-control" placeholder="Type to filter..." value="<?=htmlspecialchars($search)?>">
                </div>
                <div class="col-md-2">
                    <select name="appointment" class="form-select">
                        <option value="">All Appointments</option>
                        <?php foreach ($appointments as $a): ?>
                            <option value="<?=htmlspecialchars($a->appointment)?>" <?=($filter_appointment==$a->appointment)?'selected':''?>><?=htmlspecialchars($a->appointment)?></option>
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
                    <button type="submit" class="btn btn-secondary text-white"><i class="fa fa-search"></i> Filter</button>
                </div>
            </form>
            <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle" id="retiredTable">
                    <thead class="table-secondary">
                        <tr>
                            <th>#</th>
                            <th>Appointment</th>
                            <th>Rank</th>
                            <th>Service No</th>
                            <th>Surname</th>
                            <th>First Name(s)</th>
                            <th>Unit</th>
                            <th>Category</th>
                            <th>Date of Birth</th>
                            <th>Date of Enlistment</th>
                            <th>Status</th>
                            <th>Advancements</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($staff) == 0): ?>
                            <tr><td colspan="12" class="text-center text-muted">No retired staff found.</td></tr>
                        <?php else: $i=1; foreach ($staff as $s): ?>
                            <tr>
                                <td><?=$i++?></td>
                                <td><?=htmlspecialchars($s->appointment)?></td>
                                <td><?=htmlspecialchars($s->rankName)?></td>
                                <td><?=htmlspecialchars($s->svcNo)?></td>
                                <td><?=htmlspecialchars($s->lname)?></td>
                                <td><?=htmlspecialchars($s->fname)?></td>
                                <td><?=htmlspecialchars($s->unitName)?></td>
                                <td><?=htmlspecialchars($s->category)?></td>
                                <td><?=htmlspecialchars($s->DOB)?></td>
                                <td><?=htmlspecialchars($s->attestDate)?></td>
                                <td><?=htmlspecialchars($s->svcStatus)?></td>
                                <td><?=htmlspecialchars($s->advancements)?></td>
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
document.getElementById('retiredSearch').addEventListener('keyup', function() {
    const query = this.value.toLowerCase();
    document.querySelectorAll('#retiredTable tbody tr').forEach(function(row) {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(query) ? '' : 'none';
    });
});
</script>

<?php require_once $abs_us_root . $us_url_root . 'users/includes/html_footer.php'; ?>