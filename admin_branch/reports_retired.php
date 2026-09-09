<?php
define('ARMIS_ADMIN_BRANCH', true);
require_once __DIR__ . '/includes/auth.php';
require_once dirname(__DIR__) . '/shared/database_connection.php';
require_once __DIR__ . '/includes/db_helpers.php';
requireAuth();

$pageTitle = "Retired Staff Report as at " . date('d-M-Y');
$currentPage = "reports";
$moduleName = "Admin Branch";
$moduleIcon = "users-cog";

$sidebarLinks = []; // set by shared nav include below
require_once __DIR__ . '/includes/sidebar_nav.php';

$pdo = getDbConnection();

function getRetiredOptions($pdo, $unit, $rank, $cat) {
    $apptSql = "SELECT DISTINCT apptId FROM staff WHERE apptId IS NOT NULL AND apptId <> '' AND svcStatus = 'Retired'";
    $unitSql = "SELECT DISTINCT u.unitId, u.unitId AS name FROM unit u JOIN staff s ON s.unitId = u.unitId WHERE s.svcStatus = 'Retired'";
    $rankSql = "SELECT DISTINCT r.rankId as id, COALESCE(r.rankId, r.rankId) as name FROM `rank` r JOIN staff s ON s.rankId = r.rankId WHERE s.svcStatus = 'Retired'";
    $catSql  = "SELECT DISTINCT s.category FROM staff s WHERE s.category IS NOT NULL AND s.category <> '' AND s.svcStatus = 'Retired'";
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

list($appts, $units, $ranks, $categories) = getRetiredOptions($pdo, $filter_unit, $filter_rank, $filter_category);

$params = [];
$sql = "SELECT s.*, r.rankId as rankName, u.unitId as unitName FROM staff s
    LEFT JOIN `rank` r ON s.rankId = r.rankId
        LEFT JOIN unit u ON s.unitId = u.unitId
        WHERE s.svcStatus = 'Retired'";
if ($filter_appt !== '')      { $sql .= " AND s.apptId = ?"; $params[] = $filter_appt; }
if ($filter_unit !== '')      { $sql .= " AND s.unitId = ?"; $params[] = $filter_unit; }
if ($filter_rank !== '')      { $sql .= " AND s.rankId = ?"; $params[] = $filter_rank; }
if ($filter_category !== '')  { $sql .= " AND s.category = ?"; $params[] = $filter_category; }
if ($search !== '') {
    $sql .= " AND (s.apptId LIKE ? OR s.svcNo LIKE ? OR s.lName LIKE ? OR s.fName LIKE ? OR r.rankId LIKE ? OR u.unitId LIKE ? OR s.category LIKE ?)";
    for ($i = 0; $i < 7; $i++) $params[] = "%$search%";
}
// Default seniority sorting: rank level, then subWef, then tempWef, then attestDate, then service number
// Personnel without ranks (NULL rankId) are listed last
$sql .= " ORDER BY 
    CASE WHEN s.rankId IS NULL THEN 1 ELSE 0 END,
    r.rankIndex ASC,
    s.subWef ASC,
    s.tempWef ASC,
    s.attestDate ASC,
    s.svcNo ASC";
$per_page = intval($_GET['per_page'] ?? 25);
$page = max(1, intval($_GET['page'] ?? 1)); $offset = ($page - 1) * $per_page;
$sql .= " LIMIT $per_page OFFSET $offset";

// Fetch paginated staff
$staff = fetchAll($sql, $params);

// Get total count for pagination
$count_sql = "SELECT COUNT(*) FROM staff s
    LEFT JOIN `rank` r ON s.rankId = r.rankId
    LEFT JOIN unit u ON s.unitId = u.unitId
    WHERE s.svcStatus = 'Retired'";
if ($filter_appt !== '')      { $count_sql .= " AND s.apptId = '" . addslashes($filter_appt) . "'"; }
if ($filter_unit !== '')      { $count_sql .= " AND s.unitId = '" . addslashes($filter_unit) . "'"; }
if ($filter_rank !== '')      { $count_sql .= " AND s.rankId = '" . addslashes($filter_rank) . "'"; }
if ($filter_category !== '')  { $count_sql .= " AND s.category = '" . addslashes($filter_category) . "'"; }
if ($search !== '') {
    $search_esc = addslashes($search);
    $count_sql .= " AND (s.apptId LIKE '%$search_esc%' OR s.svcNo LIKE '%$search_esc%' OR s.lName LIKE '%$search_esc%' OR s.fName LIKE '%$search_esc%' OR r.rankId LIKE '%$search_esc%' OR u.unitId LIKE '%$search_esc%' OR s.category LIKE '%$search_esc%')";
}
$total_staff = $pdo->query($count_sql)->fetchColumn();
$total_pages = ceil($total_staff / $per_page);

include dirname(__DIR__) . '/shared/header.php';
include dirname(__DIR__) . '/shared/sidebar.php';
?>
<div class="content-wrapper with-sidebar">
    <div class="container-fluid">
        <div class="main-content">
            <h1 class="section-title mb-4"><i class="fas fa-user-check"></i> <?= htmlspecialchars($pageTitle) ?></h1>
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
                    <select name="appointment" id="apptFilter" class="form-select" aria-label="Filter by appointment">
                        <option value="">Appointment</option>
                        <?php foreach ($appts as $a): ?>
                            <option value="<?= htmlspecialchars($a) ?>" <?= ($filter_appt == $a) ? 'selected':''?>><?= htmlspecialchars($a) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="rankID" id="rankFilter" class="form-select" aria-label="Filter by rank">
                        <option value="">Rank</option>
                        <?php foreach ($ranks as $r): ?>
                            <option value="<?= $r->id ?>" <?= ($filter_rank == $r->id) ? 'selected':''?>><?= htmlspecialchars($r->name) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="unitID" id="unitFilter" class="form-select" aria-label="Filter by unit">
                        <option value="">Unit</option>
                        <?php foreach ($units as $u): ?>
                            <option value="<?= $u->id ?>" <?= ($filter_unit == $u->id) ? 'selected':''?>><?= htmlspecialchars($u->name) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="category" id="categoryFilter" class="form-select" aria-label="Filter by category">
                        <option value="">Category</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= htmlspecialchars($cat->category) ?>" <?= ($filter_category == $cat->category) ? 'selected' : '' ?>><?= htmlspecialchars($cat->category) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <input type="text" id="retiredSearch" name="search" class="form-control" placeholder="Quick Search..." value="<?=htmlspecialchars($search)?>">
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
                    'unit'=>'Unit','category'=>'Category','DOB'=>'Date of Birth','attestDate'=>'Date of Enlistment','retire_date'=>'Retirement Date'
                ];
                foreach ($columns as $key=>$label): ?>
                <label class="me-3"><input type="checkbox" class="toggle-col" data-col="<?= $key ?>" checked> <?= $label ?></label>
                <?php endforeach; ?>
            </div>
            <div class="table-responsive print-friendly">
                <table class="table table-bordered table-hover align-middle" id="retiredTable">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <?php foreach ($columns as $key => $label): ?>
                                <th class="col-<?= $key ?>"><?= $label ?></th>
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
                                <td class="col-rank"><?= htmlspecialchars($s->rankName ?? '') ?></td>
                                <td class="col-svcNo"><?= htmlspecialchars($s->svcNo ?? '') ?></td>
                                <td class="col-surname"><?= htmlspecialchars($s->lName ?? '') ?></td>
                                <td class="col-fName"><?= htmlspecialchars($s->fName ?? '') ?></td>
                                <td class="col-unit"><?= htmlspecialchars($s->unitName ?? '') ?></td>
                                <td class="col-category"><?= htmlspecialchars($s->category ?? '') ?></td>
                                <td class="col-DOB"><?= htmlspecialchars($s->DOB ?? '') ?></td>
                                <td class="col-attestDate"><?= htmlspecialchars($s->attestDate ?? '') ?></td>
                                <td class="col-retire_date"><?= htmlspecialchars($s->retire_date ?? '') ?></td>
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
                <nav aria-label="Retired Staff pagination">
                    <ul class="pagination pagination-sm">
                        <li class="page-item<?= ($page <= 1) ? ' disabled' : '' ?>">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page-1])) ?>" aria-label="Previous">&laquo;</a>
                        </li>
                        <?php
                        $max_links = 7;
                        $start = max(1, $page - intval($max_links/2));
                        $end = min($total_pages, $start + $max_links - 1);
                        if ($end - $start + 1 < $max_links) $start = max(1, $end - $max_links + 1);
                        for ($p = $start; $p <= $end; $p++):
                        ?>
                            <li class="page-item<?= ($p == $page) ? ' active' : '' ?>">
                                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $p])) ?>"><?= $p ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item<?= ($page >= $total_pages) ? ' disabled' : '' ?>">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page+1])) ?>" aria-label="Next">&raquo;</a>
                        </li>
                    </ul>
                </nav>
                <div class="ms-3 align-self-center text-muted small">
                    Page <?= $page ?> of <?= $total_pages ?> | Total: <?= $total_staff ?> records
                </div>
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
    let table = document.getElementById('retiredTable');
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
    link.download = 'retired_report.csv';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
});
document.getElementById('exportExcelBtn').addEventListener('click', function() {
    let table = document.getElementById('retiredTable');
    let wb = XLSX.utils.table_to_book(table, {sheet:"Retired"});
    XLSX.writeFile(wb, 'retired_report.xlsx');
});
document.getElementById('exportPDFBtn').addEventListener('click', function(){
    let table = document.getElementById('retiredTable');
    let rows = Array.from(table.rows).map(row => Array.from(row.cells).map(cell => cell.innerText));
    const { jsPDF } = window.jspdf;
    let doc = new jsPDF({orientation: 'landscape'});
    let startY = 20;
    doc.text("Retired Staff Report", 14, startY);
    let colCount = rows[0].length;
    let colWidth = (doc.internal.pageSize.width - 28) / colCount;
    rows.forEach(function(row, idx){
        row.forEach(function(cell, cidx){
            doc.text(cell, 14 + cidx*colWidth, startY + 8 + idx*8, {maxWidth: colWidth-2});
        });
    });
    doc.save("retired_report.pdf");
});
document.querySelector('.print-btn').addEventListener('click', function(){
    window.print();
});
function updateRetiredFilterOptions(changed) {
    const appt = document.getElementById('apptFilter').value;
    const rank = document.getElementById('rankFilter').value;
    const unit = document.getElementById('unitFilter').value;
    const category = document.getElementById('categoryFilter').value;
    const endpoints = [
        {id: 'apptFilter', type: 'appointment'},
        {id: 'rankFilter', type: 'rank'},
        {id: 'unitFilter', type: 'unit'},
        {id: 'categoryFilter', type: 'category'}
    ];
    endpoints.forEach(ep => {
        if (ep.id === changed) return;
        fetch(`ajax_retired_filters.php?type=${ep.type}&appointment=${encodeURIComponent(appt)}&rankID=${encodeURIComponent(rank)}&unitID=${encodeURIComponent(unit)}&category=${encodeURIComponent(category)}`)
            .then(r => {
                if (!r.ok) throw new Error('Network error');
                return r.json();
            })
            .then(options => {
                const sel = document.getElementById(ep.id);
                const prev = sel.value;
                let label = sel.getAttribute('aria-label') || sel.name;
                label = label.replace('Filter by ','').replace(/ID$/,'')
                sel.innerHTML = `<option value="">All ${label.charAt(0).toUpperCase()+label.slice(1)}</option>`;
                options.forEach(opt => {
                    let val = opt.value;
                    let text = opt.label;
                    sel.innerHTML += `<option value="${val}"${val==prev?' selected':''}>${text}</option>`;
                });
            })
            .catch(err => {
                alert('Failed to update filter options: ' + err.message);
            });
    });
}
['apptFilter','rankFilter','unitFilter','categoryFilter'].forEach(function(id){
    document.getElementById(id).addEventListener('change', function(){
        updateRetiredFilterOptions(id);
    });
});
document.getElementById('retiredSearch').addEventListener('input', function() {
    const query = this.value.toLowerCase();
    document.querySelectorAll('#retiredTable tbody tr').forEach(function(row) {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(query) ? '' : 'none';
    });
});
</script>
<?php include dirname(__DIR__) . '/shared/footer.php'; ?>