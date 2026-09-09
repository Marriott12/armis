<?php
define('ARMIS_ADMIN_BRANCH', true);
require_once __DIR__ . '/includes/auth.php';
require_once dirname(__DIR__) . '/shared/database_connection.php';
require_once dirname(__DIR__) . '/shared/rank_levels.php';
require_once __DIR__ . '/includes/report_helpers.php';
requireAuth();

$pageTitle = "Rank Report as at " . date('d-M-Y');
$currentPage = "reports";
$moduleName = "Admin Branch";
$moduleIcon = "users-cog";

$sidebarLinks = []; // set by shared nav include below
require_once __DIR__ . '/includes/sidebar_nav.php';

$pdo = getDbConnection();

function getRankOptions($pdo, $unit, $cat) {
    // Use rankId which exists across installs
    $rankSql = "SELECT DISTINCT r.rankId as id, r.rankId as name FROM `rank` r JOIN staff s ON s.rankId = r.rankId WHERE s.svcStatus = 'Active' ORDER BY r.rankIndex ASC";
    $unitSql = "SELECT DISTINCT u.unitId as id, COALESCE(u.unitId, u.unitLoc, u.mainUnit) as name FROM unit u JOIN staff s ON s.unitId = u.unitId WHERE s.svcStatus = 'Active' ORDER BY COALESCE(u.unitId, u.unitLoc, u.mainUnit, u.unitId) ASC";
    $catSql  = "SELECT DISTINCT " . getRankCategoryCaseSQL('r') . " as id, " . getRankCategoryCaseSQL('r') . " as name FROM `rank` r JOIN staff s ON s.rankId = r.rankId WHERE r.rankIndex IS NOT NULL AND s.svcStatus = 'Active' ORDER BY r.rankIndex ASC";

    $stmt = $pdo->prepare($rankSql);
    $stmt->execute();
    $ranks = $stmt->fetchAll(PDO::FETCH_OBJ);
    
    $stmt = $pdo->prepare($unitSql);
    $stmt->execute();
    $units = $stmt->fetchAll(PDO::FETCH_OBJ);
    
    $stmt = $pdo->prepare($catSql);
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_OBJ);
    
    return [$ranks, $units, $categories];
}
$filter_rank = $_GET['rankID'] ?? '';
$filter_unit = $_GET['unitID'] ?? '';
$filter_category = $_GET['category'] ?? '';
$search = trim($_GET['search'] ?? '');

list($ranks, $units, $categories) = getRankOptions($pdo, $filter_unit, $filter_category);

$params = [];
    $sql = "SELECT s.*, r.rankId as rankName, r.rankId as rankAbbr, COALESCE(u.unitId, u.unitLoc, u.mainUnit) as unitName, COALESCE(u.unitId, u.unitLoc, u.mainUnit) as unitCode, " . getRankCategoryCaseSQL('r') . " as category FROM staff s
    LEFT JOIN `rank` r ON s.rankId = r.rankId
    LEFT JOIN unit u ON s.unitId = u.unitId
    WHERE s.svcStatus = 'Active'";
if ($filter_rank !== '')      { $sql .= " AND s.rankId = ?"; $params[] = $filter_rank; }
if ($filter_unit !== '')      { $sql .= " AND s.unitId = ?"; $params[] = $filter_unit; }
if ($filter_category !== '')  { 
    $categorySQL = getRankCategorySQL($filter_category, 'r');
    $sql .= " AND (" . $categorySQL . ")"; 
}
if ($search !== '') {
    $sql .= " AND (r.rankId LIKE ? OR s.svcNo LIKE ? OR s.lName LIKE ? OR s.fName LIKE ? OR COALESCE(u.unitId, u.unitLoc, u.mainUnit) LIKE ?)";
    for ($i = 0; $i < 5; $i++) $params[] = "%$search%";
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
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$staff = $stmt->fetchAll(PDO::FETCH_OBJ);

// Get total count for pagination
$count_params = [];
$count_sql = "SELECT COUNT(*) FROM staff s
    LEFT JOIN `rank` r ON s.rankId = r.rankId
    LEFT JOIN unit u ON s.unitId = u.unitId
    WHERE s.svcStatus = 'Active'";
if ($filter_rank !== '')      { $count_sql .= " AND s.rankId = ?"; $count_params[] = $filter_rank; }
if ($filter_unit !== '')      { $count_sql .= " AND s.unitId = ?"; $count_params[] = $filter_unit; }
if ($filter_category !== '')  { 
    $categorySQL = getRankCategorySQL($filter_category, 'r');
    $count_sql .= " AND (" . $categorySQL . ")"; 
}
if ($search !== '') {
    $count_sql .= " AND (r.rankId LIKE ? OR s.svcNo LIKE ? OR s.lName LIKE ? OR s.fName LIKE ? OR COALESCE(u.code, u.unitLoc, u.mainUnit, u.unitId) LIKE ?)";
    for ($i = 0; $i < 5; $i++) $count_params[] = "%$search%";
}
$stmt = $pdo->prepare($count_sql);
$stmt->execute($count_params);
$total_staff = $stmt->fetchColumn();
$total_pages = ceil($total_staff / $per_page);

include dirname(__DIR__) . '/shared/header.php';
include dirname(__DIR__) . '/shared/sidebar.php';
?>
<div class="content-wrapper with-sidebar">
    <div class="container-fluid">
        <div class="main-content">
            <h1 class="section-title mb-4"><i class="fas fa-user-tie"></i> <?= htmlspecialchars($pageTitle) ?></h1>
            <div class="alert alert-info d-flex align-items-center mb-3" role="alert">
                <i class="fas fa-question-circle me-2"></i>
                <span>
                    Filter by rank, unit, category. Use the search box for instant filtering. Export, print, show/hide columns. Double-click row for details.
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
                            <option value="<?= htmlspecialchars($cat->name) ?>" <?= ($filter_category == $cat->name) ? 'selected' : '' ?>><?= htmlspecialchars($cat->name) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <input type="text" id="rankSearch" name="search" class="form-control" placeholder="Quick Search..." value="<?=htmlspecialchars($search)?>">
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
                    'svcNo'=>'Service No','rank'=>'Rank','surname'=>'Surname','fName'=>'First Name(s)','unit'=>'Unit',
                    'category'=>'Category','DOB'=>'Date of Birth','attestDate'=>'Date of Enlistment'
                ];
                foreach ($columns as $key=>$label): ?>
                <label class="me-3"><input type="checkbox" class="toggle-col" data-col="<?= $key ?>" checked> <?= $label ?></label>
                <?php endforeach; ?>
            </div>
            <div class="table-responsive print-friendly">
                <table class="table table-bordered table-hover align-middle" id="rankTable">
                    <thead class="table-light">
                        <tr>
                            <?php foreach ($columns as $key => $label): ?>
                                <th class="col-<?= $key ?>"><?= $label ?></th>
                            <?php endforeach; ?>
                            <th class="no-print">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$staff): ?>
                            <tr><td colspan="<?= count($columns)+1 ?>" class="text-center text-muted">No staff found.</td></tr>
                        <?php else: foreach($staff as $s): ?>
                            <tr>
                                <td class="col-svcNo"><?= htmlspecialchars($s->svcNo ?? '') ?></td>
                                <td class="col-rank"><?= htmlspecialchars($s->rankAbbr ?? $s->rankName ?? '') ?></td>
                                <td class="col-surname"><?= htmlspecialchars(formatSentenceCase($s->lName ?? '')) ?></td>
                                <td class="col-fName"><?= htmlspecialchars(formatSentenceCase($s->fName ?? '')) ?></td>
                                <td class="col-unit"><?= htmlspecialchars($s->unitCode ?? $s->unitName ?? '') ?></td>
                                <td class="col-category"><?= htmlspecialchars($s->category ?? '') ?></td>
                                <td class="col-DOB"><?= htmlspecialchars($s->DOB ?? '') ?></td>
                                <td class="col-attestDate"><?= htmlspecialchars($s->attestDate ?? '') ?></td>
                                <td class="no-print">
                                    <a href="edit_staff.php?svcNo=<?= urlencode($s->svcNo) ?>" class="btn btn-sm btn-primary" title="View/Edit">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
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
                <nav aria-label="Rank pagination">
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
    let table = document.getElementById('rankTable');
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
    link.download = 'rank_report.csv';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
});
document.getElementById('exportExcelBtn').addEventListener('click', function() {
    let table = document.getElementById('rankTable');
    let wb = XLSX.utils.table_to_book(table, {sheet:"Rank"});
    XLSX.writeFile(wb, 'rank_report.xlsx');
});
document.getElementById('exportPDFBtn').addEventListener('click', function(){
    let table = document.getElementById('rankTable');
    let rows = Array.from(table.rows).map(row => Array.from(row.cells).map(cell => cell.innerText));
    const { jsPDF } = window.jspdf;
    let doc = new jsPDF({orientation: 'landscape'});
    let startY = 20;
    doc.text("Rank Report", 14, startY);
    let colCount = rows[0].length;
    let colWidth = (doc.internal.pageSize.width - 28) / colCount;
    rows.forEach(function(row, idx){
        row.forEach(function(cell, cidx){
            doc.text(cell, 14 + cidx*colWidth, startY + 8 + idx*8, {maxWidth: colWidth-2});
        });
    });
    doc.save("rank_report.pdf");
});
document.querySelector('.print-btn').addEventListener('click', function(){
    window.print();
});
function updateRankFilterOptions(changed) {
    const rank = document.getElementById('rankFilter').value;
    const unit = document.getElementById('unitFilter').value;
    const category = document.getElementById('categoryFilter').value;
    const endpoints = [
        {id: 'rankFilter', type: 'rank'},
        {id: 'unitFilter', type: 'unit'},
        {id: 'categoryFilter', type: 'category'}
    ];
    endpoints.forEach(ep => {
        if (ep.id === changed) return;
        fetch(`ajax_rank_filters.php?type=${ep.type}&rankID=${encodeURIComponent(rank)}&unitID=${encodeURIComponent(unit)}&category=${encodeURIComponent(category)}`)
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
['rankFilter','unitFilter','categoryFilter'].forEach(function(id){
    document.getElementById(id).addEventListener('change', function(){
        updateRankFilterOptions(id);
    });
});
document.getElementById('rankSearch').addEventListener('input', function() {
    const query = this.value.toLowerCase();
    document.querySelectorAll('#rankTable tbody tr').forEach(function(row) {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(query) ? '' : 'none';
    });
});
</script>
<?php include dirname(__DIR__) . '/shared/footer.php'; ?>