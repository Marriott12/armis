<?php
define('ARMIS_ADMIN_BRANCH', true);
require_once __DIR__ . '/includes/auth.php';
require_once dirname(__DIR__) . '/shared/database_connection.php';
require_once __DIR__ . '/includes/db_helpers.php';
requireAuth();

$pageTitle = "Contract Staff Report as at " . date('d-M-Y');
$currentPage = "reports";
$moduleName = "Admin Branch";
$moduleIcon = "users-cog";

$sidebarLinks = []; // set by shared nav include below
require_once __DIR__ . '/includes/sidebar_nav.php';

$pdo = getDbConnection();

// Helper function to format names in title case (capitalize each word)
function formatSentenceCase($name) {
    if (empty($name)) return '';
    return ucwords(strtolower(trim($name)));
}

function getContractOptions($pdo, $unit, $rank, $cat) {
    $apptSql = "SELECT DISTINCT apptId FROM staff WHERE apptId IS NOT NULL AND apptId <> '' AND svcStatus = 'Contract'";
    $unitSql = "SELECT DISTINCT u.unitId, u.unitId AS name FROM unit u JOIN staff s ON s.unitId = u.unitId WHERE s.svcStatus = 'Contract'";
    $rankSql = "SELECT DISTINCT r.rankId as id, COALESCE(r.rankId, r.rankId) as name FROM `rank` r JOIN staff s ON s.rankId = r.rankId WHERE s.svcStatus = 'Contract'";
    $catSql  = "SELECT DISTINCT s.category FROM staff s WHERE s.category IS NOT NULL AND s.category <> '' AND s.svcStatus = 'Contract'";
    return [
        $pdo->query($apptSql)->fetchAll(PDO::FETCH_COLUMN),
        fetchAll($unitSql . " ORDER BY u.unitId ASC"),
        fetchAll($rankSql . " ORDER BY r.rankId ASC"),
        fetchAll($catSql . " ORDER BY s.category ASC")
    ];
}
$filter_appt = $_GET['appointment'] ?? '';
$filter_unit = $_GET['unitID'] ?? '';
$filter_rank = $_GET['rankID'] ?? '';
$filter_category = $_GET['category'] ?? '';
$search = trim($_GET['search'] ?? '');

list($appts, $units, $ranks, $categories) = getContractOptions($pdo, $filter_unit, $filter_rank, $filter_category);

// Sorting functionality
$sortable_columns = [
    'appt' => 's.apptId',
    'rank' => 'r.rankIndex',
    'svcNo' => 's.svcNo',
    'surname' => 's.lName',
    'fName' => 's.fName',
    'unit' => 'u.unitId',
    'category' => 's.category',
    'DOB' => 's.DOB',
    'attestDate' => 's.attestDate'
];
$sort_col = $_GET['sort_col'] ?? '';
$sort_dir = strtolower($_GET['sort_dir'] ?? 'asc') === 'desc' ? 'DESC' : 'ASC';

$params = [];
$sql = "SELECT s.*, r.rankId as rankName, r.rankId as rankAbbr, u.unitId as unitName, u.unitId as unitCode FROM staff s
    LEFT JOIN `rank` r ON s.rankId = r.rankId
        LEFT JOIN unit u ON s.unitId = u.unitId
        WHERE s.svcStatus = 'Contract'";
if ($filter_appt !== '')      { $sql .= " AND s.apptId = ?"; $params[] = $filter_appt; }
if ($filter_unit !== '')      { $sql .= " AND s.unitId = ?"; $params[] = $filter_unit; }
if ($filter_rank !== '')      { $sql .= " AND s.rankId = ?"; $params[] = $filter_rank; }
if ($filter_category !== '')  { $sql .= " AND s.category = ?"; $params[] = $filter_category; }
if ($search !== '') {
    $sql .= " AND (s.apptId LIKE ? OR s.svcNo LIKE ? OR s.lName LIKE ? OR s.fName LIKE ? OR r.rankId LIKE ? OR u.unitId LIKE ? OR s.category LIKE ?)";
    for ($i = 0; $i < 7; $i++) $params[] = "%$search%";
}

// Add sorting
if ($sort_col && isset($sortable_columns[$sort_col])) {
    $sql .= " ORDER BY " . $sortable_columns[$sort_col] . " " . $sort_dir;
} else {
    // Default seniority sorting: rank level, then subWef, then tempWef, then attestDate, then service number
    // Personnel without ranks (NULL rankId) are listed last
    $sql .= " ORDER BY 
        CASE WHEN s.rankId IS NULL THEN 1 ELSE 0 END,
        r.rankIndex ASC,
        s.subWef ASC,
        s.tempWef ASC,
        s.attestDate ASC,
        s.svcNo ASC";
}

$per_page = intval($_GET['per_page'] ?? 25);
$page = max(1, intval($_GET['page'] ?? 1)); $offset = ($page - 1) * $per_page;
$sql .= " LIMIT $per_page OFFSET $offset";
$staff = fetchAll($sql, $params);

include dirname(__DIR__) . '/shared/header.php';
include dirname(__DIR__) . '/shared/sidebar.php';
?>
<div class="content-wrapper with-sidebar">
    <div class="container-fluid">
        <div class="main-content">
            <h1 class="section-title mb-4"><i class="fas fa-file-contract"></i> <?= htmlspecialchars($pageTitle) ?></h1>
            <div class="alert alert-info d-flex align-items-center mb-3" role="alert">
                <i class="fas fa-question-circle me-2"></i>
                <span>
                    Filter by appointment, rank, unit, category. Use the search box for instant filtering. Export, print, show/hide columns. Double-click row for details.
                </span>
                <button type="button" class="btn btn-sm btn-outline-info ms-auto" data-bs-toggle="modal" data-bs-target="#helpModal" title="Show Help"><i class="fa fa-info-circle"></i> Help</button>
            </div>
            <div class="modal fade" id="helpModal" tabindex="-1" aria-labelledby="helpModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="helpModalLabel">Report Help</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <ul>
                                <li>Use filters and search for instant results.</li>
                                <li>Export: CSV, Excel, PDF. Print for a print-friendly table.</li>
                                <li>Show/hide columns using the checkboxes.</li>
                                <li>Double-click row for history/audit details.</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
            <form class="row g-3 mb-4" method="get" action="">
                <div class="col-md-2">
                    <select name="appointment" id="apptFilter" class="form-select">
                        <option value="">All Appointments</option>
                        <?php foreach ($appts as $a): ?>
                            <option value="<?= htmlspecialchars($a) ?>" <?= ($filter_appt == $a) ? 'selected':''?>><?= htmlspecialchars($a) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="rankID" id="rankFilter" class="form-select">
                        <option value="">All Ranks</option>
                        <?php foreach ($ranks as $r): ?>
                            <option value="<?= $r->id ?>" <?= ($filter_rank == $r->id) ? 'selected':''?>><?= htmlspecialchars($r->name) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="unitID" id="unitFilter" class="form-select">
                        <option value="">All Units</option>
                        <?php foreach ($units as $u): ?>
                            <option value="<?= $u->id ?>" <?= ($filter_unit == $u->id) ? 'selected':''?>><?= htmlspecialchars($u->name) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="category" id="categoryFilter" class="form-select">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= htmlspecialchars($cat->category) ?>" <?= ($filter_category == $cat->category) ? 'selected' : '' ?>><?= htmlspecialchars($cat->category) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <input type="text" id="contractSearch" name="search" class="form-control" placeholder="Quick Search..." value="<?=htmlspecialchars($search)?>">
                </div>
                <div class="col-md-1">
                    <select name="per_page" class="form-select">
                        <?php foreach ([10,25,50,100] as $pp): ?>
                        <option value="<?= $pp ?>" <?= ($per_page == $pp) ? 'selected' : '' ?>><?= $pp ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>
            <div class="mb-2">
                <strong>Show/Hide Columns:</strong>
                <?php $columns = [
                    'appt'=>'Appointment','rank'=>'Rank','svcNo'=>'Service No','surname'=>'Surname','fName'=>'First Name(s)',
                    'unit'=>'Unit','category'=>'Category','DOB'=>'Date of Birth','attestDate'=>'Date of Enlistment'
                ];
                foreach ($columns as $key=>$label): ?>
                <label class="me-3"><input type="checkbox" class="toggle-col" data-col="<?= $key ?>" checked> <?= $label ?></label>
                <?php endforeach; ?>
            </div>
            <div class="table-responsive print-friendly">
                <table class="table table-bordered table-hover align-middle" id="contractTable">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <?php foreach ($columns as $key => $label): ?>
                                <th class="col-<?= $key ?>">
                                    <a href="?<?= http_build_query(array_merge($_GET, ['sort_col' => $key, 'sort_dir' => ($sort_col==$key && $sort_dir=='ASC')?'desc':'asc', 'page'=>1])) ?>"
                                       class="text-decoration-none text-dark"
                                       aria-label="Sort by <?= $label ?>">
                                        <?= $label ?>
                                        <?php if ($sort_col == $key): ?>
                                            <i class="fa fa-sort-<?= strtolower($sort_dir)=='asc' ? 'up' : 'down' ?>"></i>
                                        <?php else: ?>
                                            <i class="fa fa-sort text-muted"></i>
                                        <?php endif; ?>
                                    </a>
                                </th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$staff): ?>
                            <tr><td colspan="<?= count($columns)+1 ?>" class="text-center text-muted">No staff found.</td></tr>
                        <?php else: $i=1; foreach($staff as $s): ?>
                            <tr ondblclick="alert('Audit/History details coming soon.')">
                                <td><?= $i++ ?></td>
                                <td class="col-appt"><?= htmlspecialchars($s->appt ?? '') ?></td>
                                <td class="col-rank"><?= htmlspecialchars($s->rankAbbr ?? $s->rankName ?? '') ?></td>
                                <td class="col-svcNo"><?= htmlspecialchars($s->svcNo ?? '') ?></td>
                                <td class="col-surname"><?= htmlspecialchars(formatSentenceCase($s->lName ?? '')) ?></td>
                                <td class="col-fName"><?= htmlspecialchars(formatSentenceCase($s->fName ?? '')) ?></td>
                                <td class="col-unit"><?= htmlspecialchars($s->unitCode ?? $s->unitName ?? '') ?></td>
                                <td class="col-category"><?= htmlspecialchars($s->category ?? '') ?></td>
                                <td class="col-DOB"><?= htmlspecialchars($s->DOB ?? '') ?></td>
                                <td class="col-attestDate"><?= htmlspecialchars($s->attestDate ?? '') ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
                <div class="text-end mt-2">
                    <button onclick="window.print()" class="btn btn-outline-secondary btn-sm print-btn"><i class="fa fa-print"></i> Print Report</button>
                    <button id="exportCSVBtn" class="btn btn-outline-success btn-sm ms-2"><i class="fa fa-file-csv"></i> Export CSV</button>
                    <button id="exportExcelBtn" class="btn btn-outline-success btn-sm"><i class="fa fa-file-excel"></i> Excel</button>
                    <button id="exportPDFBtn" class="btn btn-outline-danger btn-sm"><i class="fa fa-file-pdf"></i> PDF</button>
                </div>
            </div>
            <div class="d-flex justify-content-center my-3">
                <nav aria-label="Contract Staff pagination">
                    <ul class="pagination pagination-sm">
                        <?php
                        $max_links=7;
                        $start=max(1,$page-intval($max_links/2));
                        $end=$start+$max_links-1;
                        for ($p=$start;$p<=$end;$p++): ?>
                        <li class="page-item<?= ($p==$page)?' active':''?>">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET,['page'=>$p])) ?>"><?= $p ?></a>
                        </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            </div>
        </div>
    </div>
</div>
<style>
@media print {
    body * { visibility: hidden !important; }
    .print-friendly, .print-friendly * { visibility: visible !important; }
    .print-friendly { position: absolute !important; left: 0; top: 0; width: 100vw; }
}
</style>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.20.0/xlsx.full.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script>
document.querySelectorAll('.toggle-col').forEach(function(box) {
    let saved = localStorage.getItem('col-' + box.dataset.col);
    if (saved !== null) box.checked = saved === 'true';
    box.addEventListener('change', function() {
        var col = this.getAttribute('data-col');
        var show = this.checked;
        localStorage.setItem('col-' + col, show);
        document.querySelectorAll('.col-' + col).forEach(function(cell) {
            cell.style.display = show ? '' : 'none';
        });
    });
    document.querySelectorAll('.col-' + box.dataset.col).forEach(function(cell) {
        cell.style.display = box.checked ? '' : 'none';
    });
});
document.getElementById('exportCSVBtn').addEventListener('click', function() {
    let table = document.getElementById('contractTable');
    let rows = Array.from(table.rows);
    let visibleCols = [];
    rows[0].querySelectorAll('th').forEach(function(th, idx) {
        if (th.offsetParent !== null) visibleCols.push(idx);
    });
    let csv = rows.map(row => {
        let cells = Array.from(row.children);
        return visibleCols.map(i => {
            let text = cells[i] ? cells[i].innerText.replace(/"/g, '""') : '';
            return '"' + text + '"';
        }).join(',');
    }).join('\n');
    let blob = new Blob([csv], {type:'text/csv'});
    let link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = 'contract_staff_report.csv';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
});
document.getElementById('exportExcelBtn').addEventListener('click', function() {
    let table = document.getElementById('contractTable');
    let wb = XLSX.utils.table_to_book(table, {sheet:"ContractStaff"});
    XLSX.writeFile(wb, 'contract_staff_report.xlsx');
});
document.getElementById('exportPDFBtn').addEventListener('click', function(){
    let table = document.getElementById('contractTable');
    let rows = Array.from(table.rows).map(row => Array.from(row.cells).map(cell => cell.innerText));
    const { jsPDF } = window.jspdf;
    let doc = new jsPDF();
    let startY = 20;
    doc.text("Contract Staff Report", 14, startY);
    rows.forEach(function(row, idx){
        doc.text(row.join(" | "), 14, startY + 8 + idx*8);
    });
    doc.save("contract_staff_report.pdf");
});
document.querySelector('.print-btn').addEventListener('click', function(){
    window.print();
});
['apptFilter','unitFilter','rankFilter','categoryFilter'].forEach(function(id){
    document.getElementById(id).addEventListener('change', function(){
        document.forms[0].submit();
    });
});
document.getElementById('contractSearch').addEventListener('input', function() {
    const query = this.value.toLowerCase();
    document.querySelectorAll('#contractTable tbody tr').forEach(function(row) {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(query) ? '' : 'none';
    });
});
</script>
<?php include dirname(__DIR__) . '/shared/footer.php'; ?>