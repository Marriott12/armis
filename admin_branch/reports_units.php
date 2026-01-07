<?php
define('ARMIS_ADMIN_BRANCH', true);
require_once __DIR__ . '/includes/auth.php';
require_once dirname(__DIR__) . '/shared/database_connection.php';
require_once dirname(__DIR__) . '/shared/rank_levels.php';
requireAuth();

$pageTitle = "Personnel Report by Unit as at " . date('d-M-Y');
$currentPage = "reports";
$moduleName = "Admin Branch";
$moduleIcon = "users-cog";

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

$pdo = getDbConnection();

// Helper function to format names in title case (capitalize each word)
function formatSentenceCase($name) {
    if (empty($name)) return '';
    return ucwords(strtolower(trim($name)));
}

function getUnitOptions($pdo) {
    $unitSql = "SELECT unitId as id, code as name FROM unit ORDER BY code ASC";
    $catSql  = "SELECT DISTINCT " . getRankCategoryCaseSQL('r') . " as id, " . getRankCategoryCaseSQL('r') . " as name FROM `rank` r JOIN staff s ON s.rankId = r.rankId WHERE r.level IS NOT NULL ORDER BY r.level ASC";
    $rankSql = "SELECT DISTINCT r.rankId as id, r.rankId as name FROM `rank` r JOIN staff s ON s.rankId = r.rankId ORDER BY r.rankId ASC";
    
    $stmt = $pdo->query($unitSql);
    $units = $stmt->fetchAll(PDO::FETCH_OBJ);
    
    $stmt = $pdo->query($catSql);
    $categories = $stmt->fetchAll(PDO::FETCH_OBJ);
    
    $stmt = $pdo->query($rankSql);
    $ranks = $stmt->fetchAll(PDO::FETCH_OBJ);
    
    return [$units, $categories, $ranks];
}
$filter_unit = $_GET['unitID'] ?? '';
$filter_category = $_GET['category'] ?? '';
$filter_rank = $_GET['rankID'] ?? '';
$search = trim($_GET['search'] ?? '');

list($units, $categories, $ranks) = getUnitOptions($pdo);

// Sorting functionality
$sortable_columns = [
    'unit' => 'u.code',
    'rank' => 'r.level',
    'svcNo' => 's.svcNo',
    'surname' => 's.lName',
    'fName' => 's.fName',
    'category' => 'r.level',
    'DOB' => 's.DOB',
    'attestDate' => 's.attestDate'
];
$sort_col = $_GET['sort_col'] ?? '';
$sort_dir = strtolower($_GET['sort_dir'] ?? 'asc') === 'desc' ? 'DESC' : 'ASC';

$params = [];
$sql = "SELECT s.*, COALESCE(r.rankId, s.rankId) as rankName, r.rankId as rankAbbr, r.level as rankLevel, " . getRankCategoryCaseSQL('r') . " as category, u.code as unitCode FROM staff s
    LEFT JOIN `rank` r ON s.rankId = r.rankId
        LEFT JOIN unit u ON s.unitId = u.unitId
        WHERE 1=1";
if ($filter_unit !== '')      { $sql .= " AND s.unitId = ?"; $params[] = $filter_unit; }
if ($filter_category !== '')  { 
    $categorySQL = getRankCategorySQL($filter_category, 'r');
    $sql .= " AND (" . $categorySQL . ")"; 
}
if ($filter_rank !== '')      { $sql .= " AND s.rankId = ?"; $params[] = $filter_rank; }
if ($search !== '') {
    $sql .= " AND (u.code LIKE ? OR s.svcNo LIKE ? OR s.lName LIKE ? OR s.fName LIKE ? OR COALESCE(r.rankId, r.rankId) LIKE ?)";
    for ($i = 0; $i < 5; $i++) $params[] = "%$search%";
}

// Handle sorting
$order_clause = '';
if ($sort_col && isset($sortable_columns[$sort_col])) {
    $order_clause = " ORDER BY " . $sortable_columns[$sort_col] . " $sort_dir";
} else {
    // Default seniority sorting: rank level, then subWef, then tempWef, then attestDate, then service number
    // Personnel without ranks (NULL rankId) are listed last
    $order_clause = " ORDER BY 
        CASE WHEN s.rankId IS NULL THEN 1 ELSE 0 END,
        r.level ASC,
        s.subWef ASC,
        s.tempWef ASC,
        s.attestDate ASC,
        s.svcNo ASC";
}
$sql .= $order_clause;
$per_page = intval($_GET['per_page'] ?? 25);
$page = max(1, intval($_GET['page'] ?? 1)); $offset = ($page - 1) * $per_page;
$sql .= " LIMIT $per_page OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$staff = $stmt->fetchAll(PDO::FETCH_OBJ);

include dirname(__DIR__) . '/shared/header.php';
include dirname(__DIR__) . '/shared/sidebar.php';
?>
<div class="content-wrapper with-sidebar">
    <div class="container-fluid">
        <div class="main-content">
            <h1 class="section-title mb-4"><i class="fas fa-building"></i> <?= htmlspecialchars($pageTitle) ?></h1>
            <div class="alert alert-info d-flex align-items-center mb-3" role="alert">
                <i class="fas fa-question-circle me-2"></i>
                <span>
                    Filter by unit, rank, category. Use the search box for instant filtering. Export, print, show/hide columns. Double-click row for details.
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
                    <select name="unitID" id="unitFilter" class="form-select">
                        <option value="">Unit</option>
                        <?php foreach ($units as $u): ?>
                            <option value="<?= $u->id ?>" <?= ($filter_unit == $u->id) ? 'selected':''?>><?= htmlspecialchars($u->name) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="rankID" id="rankFilter" class="form-select">
                        <option value="">Rank</option>
                        <?php foreach ($ranks as $r): ?>
                            <option value="<?= $r->id ?>" <?= ($filter_rank == $r->id) ? 'selected':''?>><?= htmlspecialchars($r->name) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="category" id="categoryFilter" class="form-select">
                        <option value="">Category</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= htmlspecialchars($cat->category) ?>" <?= ($filter_category == $cat->category) ? 'selected' : '' ?>><?= htmlspecialchars($cat->category) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <input type="text" id="unitsSearch" name="search" class="form-control" placeholder="Quick Search..." value="<?=htmlspecialchars($search)?>">
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
                    'unit'=>'Unit','rank'=>'Rank','svcNo'=>'Service No','surname'=>'Surname','fName'=>'First Name(s)',
                    'category'=>'Category','DOB'=>'Date of Birth','attestDate'=>'Date of Enlistment'
                ];
                foreach ($columns as $key=>$label): ?>
                <label class="me-3"><input type="checkbox" class="toggle-col" data-col="<?= $key ?>" checked> <?= $label ?></label>
                <?php endforeach; ?>
            </div>
            <div class="table-responsive print-friendly">
                <table class="table table-bordered table-hover align-middle" id="unitsTable">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <?php foreach ($columns as $key => $label): ?>
                                <th class="col-<?= $key ?> <?= isset($sortable_columns[$key]) ? 'sortable' : '' ?>" 
                                    <?= isset($sortable_columns[$key]) ? 'style="cursor: pointer;" onclick="sortTable(\'' . $key . '\')"' : '' ?>>
                                    <?= $label ?>
                                    <?php if (isset($sortable_columns[$key]) && $sort_col === $key): ?>
                                        <i class="fas fa-sort-<?= $sort_dir === 'ASC' ? 'up' : 'down' ?> ms-1"></i>
                                    <?php elseif (isset($sortable_columns[$key])): ?>
                                        <i class="fas fa-sort ms-1 text-muted"></i>
                                    <?php endif; ?>
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
                                <td class="col-unit"><?= htmlspecialchars($s->unitCode ?? $s->unitName ?? '') ?></td>
                                <td class="col-rank"><?= htmlspecialchars($s->rankAbbr ?? $s->rankName ?? '') ?></td>
                                <td class="col-svcNo"><?= htmlspecialchars($s->svcNo ?? '') ?></td>
                                <td class="col-surname"><?= htmlspecialchars(formatSentenceCase($s->lName ?? '')) ?></td>
                                <td class="col-fName"><?= htmlspecialchars(formatSentenceCase($s->fName ?? '')) ?></td>
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
                <nav aria-label="Units pagination">
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
// Sort function
function sortTable(column) {
    const currentParams = new URLSearchParams(window.location.search);
    let newDir = 'asc';
    
    if (currentParams.get('sort_col') === column && currentParams.get('sort_dir') === 'asc') {
        newDir = 'desc';
    }
    
    currentParams.set('sort_col', column);
    currentParams.set('sort_dir', newDir);
    
    window.location.search = currentParams.toString();
}

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
    let table = document.getElementById('unitsTable');
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
    link.download = 'units_report.csv';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
});
document.getElementById('exportExcelBtn').addEventListener('click', function() {
    let table = document.getElementById('unitsTable');
    let wb = XLSX.utils.table_to_book(table, {sheet:"Units"});
    XLSX.writeFile(wb, 'units_report.xlsx');
});
document.getElementById('exportPDFBtn').addEventListener('click', function(){
    let table = document.getElementById('unitsTable');
    let rows = Array.from(table.rows).map(row => Array.from(row.cells).map(cell => cell.innerText));
    const { jsPDF } = window.jspdf;
    let doc = new jsPDF();
    let startY = 20;
    doc.text("Units Report", 14, startY);
    rows.forEach(function(row, idx){
        doc.text(row.join(" | "), 14, startY + 8 + idx*8);
    });
    doc.save("units_report.pdf");
});
document.querySelector('.print-btn').addEventListener('click', function(){
    window.print();
});
['unitFilter','rankFilter','categoryFilter'].forEach(function(id){
    document.getElementById(id).addEventListener('change', function(){
        document.forms[0].submit();
    });
});
document.getElementById('unitsSearch').addEventListener('input', function() {
    const query = this.value.toLowerCase();
    document.querySelectorAll('#unitsTable tbody tr').forEach(function(row) {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(query) ? '' : 'none';
    });
});
</script>
<?php include dirname(__DIR__) . '/shared/footer.php'; ?>