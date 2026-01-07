<?php
// Define module constants
define('ARMIS_ADMIN_BRANCH', true);
define('ARMIS_DEVELOPMENT', false);

require_once __DIR__ . '/includes/auth.php';
require_once dirname(__DIR__) . '/shared/database_connection.php';
requireAuth();

$pageTitle = "Deceased Report as at " . date('d-M-Y');
$moduleName = "Admin Branch";
$moduleIcon = "users-cog";
$currentPage = "deceased";

$sidebarLinks = [
    ['title' => 'Dashboard', 'url' => '/Armis2/admin_branch/index.php', 'icon' => 'tachometer-alt', 'page' => 'dashboard'],
    ['title' => 'Staff Management', 'url' => '/Armis2/admin_branch/edit_staff.php', 'icon' => 'users', 'page' => 'staff'],
    ['title' => 'Create Staff', 'url' => '/Armis2/admin_branch/create_staff.php', 'icon' => 'user-plus', 'page' => 'create'],
    ['title' => 'Promotions', 'url' => '/Armis2/admin_branch/promote_staff.php', 'icon' => 'arrow-up', 'page' => 'promotions'],
    ['title' => 'Appointments', 'url' => '/Armis2/admin_branch/appointments.php', 'icon' => 'user-tie', 'page' => 'appointments'],
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

// Helper for dynamic filter options (for deceased staff)
function getDeceasedOptions($pdo, $selectedRank, $selectedUnit, $selectedCategory) {
    // Only filter for dropdowns, not for the main query
    // Use canonical `rank` table and order by level for sensible ordering
    $rankSql = "SELECT DISTINCT r.rankId AS id, COALESCE(r.rankId, r.rankId) AS name FROM `rank` r JOIN staff s ON s.rankId = r.rankId WHERE s.svcStatus = 'Deceased'";
    $unitSql = "SELECT DISTINCT u.unitId, u.name FROM unit u JOIN staff s ON s.unitId = u.unitId WHERE s.svcStatus = 'Deceased'";
    $catSql  = "SELECT DISTINCT s.category FROM staff s WHERE s.category IS NOT NULL AND s.category <> '' AND s.svcStatus = 'Deceased'";

    $ranks = fetchAll($rankSql . " ORDER BY name ASC");
    $units = fetchAll($unitSql . " ORDER BY u.name ASC");
    $categories = fetchAll($catSql . " ORDER BY s.category ASC");

    return [$ranks, $units, $categories];
}

$filter_rank = $_GET['rankID'] ?? '';
$filter_unit = $_GET['unitID'] ?? '';
$filter_category = $_GET['category'] ?? '';
$search = trim($_GET['search'] ?? '');
$params = [];

list($ranks, $units, $categories) = getDeceasedOptions($pdo, $filter_rank, $filter_unit, $filter_category);

$per_page = intval($_GET['per_page'] ?? 25);
$page = max(1, intval($_GET['page'] ?? 1));
$offset = ($page - 1) * $per_page;

$sortable_columns = [
    'svcNo' => 's.svcNo',
    'rank' => 'r.level',
    'surname' => 's.lName',
    'fName' => 's.fName',
    'unit' => 'u.name',
    'category' => 's.category',
    'DOB' => 's.DOB',
    'attestDate' => 's.attestDate',
    'dod' => 's.dod'
];
$sort_col = $_GET['sort_col'] ?? '';
$sort_dir = strtolower($_GET['sort_dir'] ?? 'asc') === 'desc' ? 'DESC' : 'ASC';


// Always fetch all deceased staff, only filter if user selects a filter
$sql = "SELECT s.*, COALESCE(r.rankId, r.rankId) as rankName, r.rankId as rankAbbr, r.level as rankIndex, COALESCE(u.code, u.name) as unitName, u.code as unitCode
    FROM staff s
    LEFT JOIN `rank` r ON s.rankId = r.rankId
    LEFT JOIN unit u ON s.unitId = u.unitId
    WHERE s.svcStatus = 'Deceased'";
$count_sql = "SELECT COUNT(*) FROM staff s
    LEFT JOIN `rank` r ON s.rankId = r.rankId
        LEFT JOIN unit u ON s.unitId = u.unitId
        WHERE s.svcStatus = 'Deceased'";

$params = [];
$count_params = [];
if ($filter_rank !== '') {
    $sql .= " AND s.rankId = ?";
    $count_sql .= " AND s.rankId = ?";
    $params[] = $filter_rank;
    $count_params[] = $filter_rank;
}
if ($filter_unit !== '') {
    $sql .= " AND s.unitId = ?";
    $count_sql .= " AND s.unitId = ?";
    $params[] = $filter_unit;
    $count_params[] = $filter_unit;
}
if ($filter_category !== '') {
    $sql .= " AND s.category = ?";
    $count_sql .= " AND s.category = ?";
    $params[] = $filter_category;
    $count_params[] = $filter_category;
}
if ($search !== '') {
    $sql .= " AND (s.svcNo LIKE ? OR s.lName LIKE ? OR s.fName LIKE ? OR COALESCE(r.rankId, r.rankId) LIKE ? OR COALESCE(u.code, u.name) LIKE ? OR s.category LIKE ? OR s.DOB LIKE ? OR s.attestDate LIKE ? OR s.dod LIKE ?)";
    $count_sql .= " AND (s.svcNo LIKE ? OR s.lName LIKE ? OR s.fName LIKE ? OR COALESCE(r.rankId, r.rankId) LIKE ? OR COALESCE(u.code, u.name) LIKE ? OR s.category LIKE ? OR s.DOB LIKE ? OR s.attestDate LIKE ? OR s.dod LIKE ?)";
    for ($i = 0; $i < 9; $i++) {
        $params[] = "%$search%";
        $count_params[] = "%$search%";
    }
}

if ($sort_col && array_key_exists($sort_col, $sortable_columns)) {
    $sql .= " ORDER BY " . $sortable_columns[$sort_col] . " $sort_dir";
} else {
    // Default seniority sorting: rank level, then subWef, then tempWef, then attestDate, then service number
    // Personnel without ranks (NULL rankId) are listed last
    $sql .= " ORDER BY 
        CASE WHEN s.rankId IS NULL THEN 1 ELSE 0 END,
        r.level ASC,
        s.subWef ASC,
        s.tempWef ASC,
        s.attestDate ASC,
        s.svcNo ASC";
}

$sql .= " LIMIT $per_page OFFSET $offset";

$staff = fetchAll($sql, $params);
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
            <h1 class="section-title mb-4"><i class="fas fa-user-slash"></i> <?= htmlspecialchars($pageTitle) ?></h1>
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
                    <select name="rankID" id="rankFilter" class="form-select" aria-label="Filter by rank">
                        <option value="">All Ranks</option>
                        <?php foreach ($ranks as $r): ?>
                            <option value="<?= $r->id ?>" <?= ($filter_rank == $r->id) ? 'selected' : '' ?>><?= htmlspecialchars($r->name) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="unitID" id="unitFilter" class="form-select" aria-label="Filter by unit">
                        <option value="">All Units</option>
                        <?php foreach ($units as $u): ?>
                            <option value="<?= $u->id ?>" <?= ($filter_unit == $u->id) ? 'selected' : '' ?>><?= htmlspecialchars($u->name) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="category" id="categoryFilter" class="form-select" aria-label="Filter by category">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= htmlspecialchars($cat->category) ?>" <?= ($filter_category == $cat->category) ? 'selected' : '' ?>><?= htmlspecialchars($cat->category) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <input type="text" id="deceasedSearch" name="search" class="form-control" placeholder="Quick Search..." aria-label="Quick search" value="<?=htmlspecialchars($search)?>">
                </div>
                <div class="col-md-1">
                    <select name="per_page" class="form-select" title="Records per page">
                        <?php foreach ([10, 25, 50, 100] as $pp): ?>
                            <option value="<?= $pp ?>" <?= ($per_page == $pp) ? 'selected' : '' ?>><?= $pp ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 d-grid">
                    <button type="submit" class="btn btn-primary"><i class="fa fa-search"></i> Filter</button>
                </div>
            </form>
            <div class="mb-2">
                <strong>Show/Hide Columns:</strong>
                <?php
                $columns = [
                    'svcNo' => 'Service No',
                    'rank' => 'Rank',
                    'surname' => 'Surname',
                    'fName' => 'First Name(s)',
                    'unit' => 'Unit',
                    'category' => 'Category',
                    'DOB' => 'Date of Birth',
                    'attestDate' => 'Date of Enlistment',
                    'dod' => 'Date of Death'
                ];
                foreach ($columns as $key => $label):
                ?>
                    <label class="me-3">
                        <input type="checkbox" class="toggle-col" data-col="<?= $key ?>" checked> <?= $label ?>
                    </label>
                <?php endforeach; ?>
            </div>
            <div class="table-responsive print-friendly">
                <form id="batchForm" method="post" action="/Armis2/admin_branch/batch_action.php">
                <table class="table table-bordered table-hover align-middle" id="deceasedTable">
                    <thead class="table-light">
                        <tr>
                            <th><input type="checkbox" id="selectAllRows" aria-label="Select all"></th>
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
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($staff) == 0): ?>
                            <tr><td colspan="<?= count($columns)+2 ?>" class="text-center text-muted">No staff found.</td></tr>
                        <?php else: $i=1; foreach ($staff as $s): ?>
                            <tr>
                                <td>
                                    <input type="checkbox" name="selected_ids[]" value="<?= htmlspecialchars($s->id) ?>" class="rowCheckbox">
                                </td>
                                <td class="col-svcNo"><?= htmlspecialchars($s->svcNo ?? '') ?></td>
                                <td class="col-rank"><?= htmlspecialchars($s->rankAbbr ?? $s->rankName ?? '') ?></td>
                                <td class="col-surname"><?= htmlspecialchars($s->lName ?? '') ?></td>
                                <td class="col-fName"><?= htmlspecialchars($s->fName ?? '') ?></td>
                                <td class="col-unit"><?= htmlspecialchars($s->unitCode ?? $s->unitName ?? '') ?></td>
                                <td class="col-category"><?= htmlspecialchars($s->category ?? '') ?></td>
                                <td class="col-DOB"><?= htmlspecialchars($s->DOB ?? '') ?></td>
                                <td class="col-attestDate"><?= htmlspecialchars($s->attestDate ?? '') ?></td>
                                <td class="col-dod"><?= htmlspecialchars($s->dod ?? '') ?></td>
                                <td>
                                    <?php if (!empty($s->id)): ?>
                                        <a href="/Armis2/admin_branch/view_staff.php?id=<?= urlencode($s->id) ?>" class="btn btn-outline-primary btn-sm" target="_blank" aria-label="View staff">View</a>
                                    <?php else: ?>
                                        <span class="text-muted">N/A</span>
                                    <?php endif; ?>
                                    <a href="/Armis2/admin_branch/edit_staff.php?svcNo=<?= urlencode($s->svcNo) ?>" class="btn btn-outline-secondary btn-sm ms-1" aria-label="Edit staff">Edit</a>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
                <div class="d-flex justify-content-start align-items-center gap-2 mb-2">
                    <button type="submit" name="action" value="export" class="btn btn-outline-success btn-sm"><i class="fa fa-file-csv"></i> Export Selected</button>
                    <button type="button" id="exportExcelBtn" class="btn btn-outline-success btn-sm"><i class="fa fa-file-excel"></i> Excel</button>
                    <button type="button" id="exportPDFBtn" class="btn btn-outline-danger btn-sm"><i class="fa fa-file-pdf"></i> PDF</button>
                    <button type="submit" name="action" value="delete" class="btn btn-outline-danger btn-sm" onclick="return confirm('Are you sure you want to delete selected records?');"><i class="fa fa-trash"></i> Delete Selected</button>
                </div>
                </form>
            </div>
            <div class="d-flex justify-content-center my-3">
                <nav aria-label="Deceased pagination">
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

document.getElementById('selectAllRows').addEventListener('change', function() {
    var checked = this.checked;
    document.querySelectorAll('.rowCheckbox').forEach(function(cb) {
        cb.checked = checked;
    });
});

document.getElementById('exportExcelBtn').addEventListener('click', function() {
    let table = document.getElementById('deceasedTable');
    let wb = XLSX.utils.table_to_book(table, {sheet:"Deceased"});
    XLSX.writeFile(wb, 'deceased_report.xlsx');
});

document.getElementById('exportPDFBtn').addEventListener('click', function(){
    let table = document.getElementById('deceasedTable');
    let rows = Array.from(table.rows).map(row => Array.from(row.cells).map(cell => cell.innerText));
    const { jsPDF } = window.jspdf;
    let doc = new jsPDF();
    let startY = 20;
    doc.text("Deceased Report", 14, startY);
    rows.forEach(function(row, idx){
        doc.text(row.join(" | "), 14, startY + 8 + idx*8);
    });
    doc.save("deceased_report.pdf");
});

// Dynamic dropdown filtering
['rankFilter','unitFilter','categoryFilter'].forEach(function(id){
    document.getElementById(id).addEventListener('change', function(){
        document.forms[0].submit();
    });
});

// Dynamic searchbar: client-side instant filter
document.getElementById('deceasedSearch').addEventListener('input', function() {
    const query = this.value.toLowerCase();
    document.querySelectorAll('#deceasedTable tbody tr').forEach(function(row) {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(query) ? '' : 'none';
    });
});
</script>
<?php include dirname(__DIR__) . '/shared/footer.php'; ?>