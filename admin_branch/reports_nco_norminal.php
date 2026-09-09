<?php
// Define module constants
define('ARMIS_ADMIN_BRANCH', true);
define('ARMIS_DEVELOPMENT', false);

// Include admin branch authentication and database
require_once __DIR__ . '/includes/auth.php';
require_once dirname(__DIR__) . '/shared/database_connection.php';
require_once dirname(__DIR__) . '/shared/rank_levels.php';
require_once __DIR__ . '/includes/db_helpers.php';

// Require authentication
requireAuth();

$pageTitle = "NCO Nominal Roll as at " . date('d-M-Y');
$moduleName = "Admin Branch";
$moduleIcon = "users-cog";
$currentPage = "nco_nominal";

// Determine report type (officer, nco, ce)
$reportType = $_GET['report_type'] ?? 'nco';

// Sidebar navigation

$sidebarLinks = []; // set by shared nav include below
require_once __DIR__ . '/includes/sidebar_nav.php';

// Get database connection
$pdo = getDbConnection();

// Helper function to format names in title case (capitalize each word)
function formatSentenceCase($name) {
    if (empty($name)) return '';
    return ucwords(strtolower(trim($name)));
}

// Dynamic filter options (only for active NCO staff)
function getDynamicOptions($pdo, $selectedRank, $selectedUnit, $selectedCategory) {
    $rankSql = "SELECT DISTINCT r.rankId as id, r.rankId as name FROM `rank` r JOIN staff s ON s.rankId = r.rankId WHERE s.svcStatus = 'Active' AND r.rankIndex >= 15 AND r.rankIndex <= 26";
    $unitSql = "SELECT DISTINCT u.unitId as id, u.unitId as name FROM unit u JOIN staff s ON s.unitId = u.unitId JOIN `rank` r ON s.rankId = r.rankId WHERE s.svcStatus = 'Active' AND r.rankIndex >= 15 AND r.rankIndex <= 26";
    $catSql  = "SELECT DISTINCT " . getRankCategoryCaseSQL('r') . " as id, " . getRankCategoryCaseSQL('r') . " as name FROM staff s JOIN `rank` r ON s.rankId = r.rankId WHERE r.rankIndex IS NOT NULL AND s.svcStatus = 'Active'";

    $rankParams = [];
    $unitParams = [];
    $catParams  = [];

    if ($selectedUnit) {
        $rankSql .= " AND s.unitId = ?";
        $rankParams[] = $selectedUnit;
    }
    if ($selectedCategory) {
        $categorySQL = getRankCategorySQL($selectedCategory, 'r');
        $rankSql .= " AND (" . $categorySQL . ")";
    }
    if ($selectedRank) {
        $unitSql .= " AND s.rankId = ?";
        $unitParams[] = $selectedRank;
    }
    if ($selectedCategory) {
        $categorySQL = getRankCategorySQL($selectedCategory, 'r');
        $unitSql .= " AND (" . $categorySQL . ")";
    }
    if ($selectedUnit) {
        $catSql .= " AND s.unitId = ?";
        $catParams[] = $selectedUnit;
    }
    if ($selectedRank) {
        $catSql .= " AND s.rankId = ?";
        $catParams[] = $selectedRank;
    }

    $ranks = fetchAll($rankSql . " ORDER BY r.rankIndex ASC", $rankParams);
    $units = fetchAll($unitSql . " ORDER BY u.unitId ASC", $unitParams);
    $categories = fetchAll($catSql . " ORDER BY r.rankIndex ASC", $catParams);

    return [$ranks, $units, $categories];
}

$filter_rank = $_GET['rankID'] ?? '';
$filter_unit = $_GET['unitID'] ?? '';
$filter_category = $_GET['category'] ?? '';
$search = trim($_GET['search'] ?? '');
$categoryOptions = ['Officer' => 'Officer', 'Officer Cadet' => 'Officer Cadet', 'NCO' => 'NCO', 'Recruit' => 'Recruit', 'CE' => 'CE'];
$params = [];

list($ranks, $units, $categories) = getDynamicOptions($pdo, $filter_rank, $filter_unit, $filter_category);

$per_page = intval($_GET['per_page'] ?? 25);
$page = max(1, intval($_GET['page'] ?? 1));
$offset = ($page - 1) * $per_page;

$sortable_columns = [
    'svcNo' => 's.svcNo',
    'rank' => 'r.rankIndex',
    'surname' => 's.lName',
    'fName' => 's.fName',
    'unit' => 'u.unitId',
    'category' => 'r.rankIndex',
    'DOB' => 's.DOB',
    'attestDate' => 's.attestDate',
    'subWef' => 's.subWef',
    'tempWef' => 's.tempWef',
    'svcStatus' => 's.svcStatus'
];
$sort_col = $_GET['sort_col'] ?? '';
$sort_dir = strtolower($_GET['sort_dir'] ?? 'asc') === 'desc' ? 'DESC' : 'ASC';


$sql = "SELECT s.*, r.rankId as rankName, r.rankId as rankAbbr, r.rankIndex as rankIndex, u.unitId as unitName, u.unitId as unitCode
        FROM staff s
    LEFT JOIN `rank` r ON s.rankId = r.rankId
        LEFT JOIN unit u ON s.unitId = u.unitId
        WHERE s.svcStatus = 'Active' AND r.rankIndex >= 15 AND r.rankIndex <= 26";
$count_sql = "SELECT COUNT(*) FROM staff s
        LEFT JOIN `rank` r ON s.rankId = r.rankId
        LEFT JOIN unit u ON s.unitId = u.unitId
        WHERE s.svcStatus = 'Active' AND r.rankIndex >= 15 AND r.rankIndex <= 26";

// Filter by report type
// Already filtered to NCO in the base query

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
    $categorySQL = getRankCategorySQL($filter_category, 'r');
    $sql .= " AND (" . $categorySQL . ")";
    $count_sql .= " AND (" . $categorySQL . ")";
}
if ($search !== '') {
    $sql .= " AND (s.svcNo LIKE ? OR s.lName LIKE ? OR s.fName LIKE ? OR r.rankId LIKE ? OR u.unitId LIKE ? OR s.svcStatus LIKE ? OR s.DOB LIKE ? OR s.attestDate LIKE ?)";
    $count_sql .= " AND (s.svcNo LIKE ? OR s.lName LIKE ? OR s.fName LIKE ? OR r.rankId LIKE ? OR u.unitId LIKE ? OR s.svcStatus LIKE ? OR s.DOB LIKE ? OR s.attestDate LIKE ?)";
    for ($i = 0; $i < 8; $i++) {
        $params[] = "%$search%";
        $count_params[] = "%$search%";
    }
}

if ($sort_col && array_key_exists($sort_col, $sortable_columns)) {
    $sql .= " ORDER BY " . $sortable_columns[$sort_col] . " $sort_dir";
} else {
    $sql .= " ORDER BY s.svcNo ASC";
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
            <h1 class="section-title mb-4">
                <i class="fas fa-list"></i> <?= htmlspecialchars($pageTitle) ?>
            </h1>
            <div class="alert alert-info d-flex align-items-center mb-3" role="alert">
                <i class="fas fa-question-circle me-2"></i>
                <span>
                    Filter by appointment, rank, unit, category. Use the search box for instant filtering. Export, print, show/hide columns. Double-click row for details.
                </span>
                <button type="button" class="btn btn-sm btn-outline-info ms-auto" data-bs-toggle="modal" data-bs-target="#helpModal" title="Show Help"><i class="fa fa-info-circle"></i> Help</button>
            </div>

            <!-- FIX: this category tab selector was previously nested
                 inside the Help modal (between .modal-header and
                 .modal-body), invisible unless a user opened Help.
                 Moved to the visible page body. -->
                            <ul class="nav nav-tabs mb-3" id="seniorityTabs" role="tablist">
                                <?php foreach ([
                                    'officer' => 'Officers',
                                    'nco' => 'NCOs',
                                    'ce' => 'CEs'
                                ] as $key => $label): ?>
                                    <li class="nav-item" role="presentation">
                                        <a class="nav-link<?= (($reportType ?? ($_GET['report_type'] ?? 'officer'))==$key)?' active':'' ?>" href="/Armis2/admin_branch/reports_<?= $key == 'officer' ? 'officer' : ($key == 'nco' ? 'nco' : 'ce') ?>_norminal.php?report_type=<?= $key ?>&page=1" role="tab">
                                            <?= $label ?> Report
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
            <div class="modal fade" id="helpModal" tabindex="-1" aria-labelledby="helpModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="helpModalLabel">NCO Nominal Roll Help</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <ul>
                                <li><b>Filtering:</b> Use dropdowns to filter, and type in the search box for instant filtering.</li>
                                <li><b>Export:</b> Export to CSV, Excel, or PDF with the export buttons.</li>
                                <li><b>Print:</b> Click Print for a print-friendly version of the table.</li>
                                <li><b>Customize Columns:</b> Show/hide columns using checkboxes above the table.</li>
                                <li><b>Details:</b> Double-click a row to see audit/history details.</li>
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
                <div class="col-md-2"><select name="category" id="categoryFilter" class="form-select" aria-label="Filter by category"><option value="">All Categories</option><?php foreach ($categoryOptions as $value => $label): ?><option value="<?= htmlspecialchars($value) ?>" <?= $filter_category === $value ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option><?php endforeach; ?></select></div>
                <div class="col-md-3">
                    <input type="text" id="senioritySearch" name="search" class="form-control" placeholder="Quick Search..." aria-label="Quick search" value="<?=htmlspecialchars($search)?>">
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
                    'DOB' => 'Date of Birth',
                    'attestDate' => 'Date of Enlistment',
                    'svcStatus' => 'Status'
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
                <table class="table table-bordered table-hover align-middle" id="seniorityTable">
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
                            <tr><td colspan="<?= count($columns)+2 ?>" class="text-center text-muted">No NCOs found.</td></tr>
                        <?php else: $i=1; foreach ($staff as $s): ?>
                            <tr>
                                <td>
                                    <input type="checkbox" name="selected_ids[]" value="<?= htmlspecialchars($s->id) ?>" class="rowCheckbox">
                                </td>
                                <td class="col-svcNo"><?= htmlspecialchars($s->svcNo ?? '') ?></td>
                                <td class="col-svcNo"><?= htmlspecialchars($s->rankAbbr ?? $s->rankName ?? '') ?></td>
                                <td class="col-surname"><?= htmlspecialchars(formatSentenceCase($s->lName ?? '')) ?></td>
                                <td class="col-fName"><?= htmlspecialchars(formatSentenceCase($s->fName ?? '')) ?></td>
                                <td class="col-unit"><?= htmlspecialchars($s->unitCode ?? $s->unitName ?? '') ?></td>
                                <td class="col-DOB"><?= htmlspecialchars($s->DOB ?? '') ?></td>
                                <td class="col-attestDate"><?= htmlspecialchars($s->attestDate ?? '') ?></td>
                                <td class="col-svcStatus"><?= htmlspecialchars($s->svcStatus ?? '') ?></td>
                                <td>
                                    <?php if (!empty($s->id)): ?>
                                        <a href="/Armis2/admin_branch/view_staff.php?id=<?= urlencode($s->id) ?>" class="btn btn-outline-primary btn-sm" target="_blank" aria-label="View staff">View</a>
                                    <?php else: ?>
                                        <span class="text-muted">N/A</span>
                                    <?php endif; ?>
                                    <a href="/Armis2/admin_branch/edit_staff.php?svcNo=<?= urlencode($s->svcNo) ?>" class="btn btn-outline-secondary btn-sm ms-1" aria-label="Edit staff">Edit</a>
                                    <!--<a href="/Armis2/reset_password.php?svcNo=<?= urlencode($s->svcNo) ?>" class="btn btn-outline-warning btn-sm ms-1" aria-label="Reset password">Reset Password</a>-->
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
                <nav aria-label="Seniority pagination">
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
    body * {
        visibility: hidden !important;
    }
    .print-friendly, .print-friendly * {
        visibility: visible !important;
        print-color-adjust: exact;
    }
    .print-friendly {
        position: absolute !important;
        left: 0; top: 0; width: 100vw;
    }
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

document.getElementById('exportCSVBtn').addEventListener('click', function() {
    let table = document.getElementById('seniorityTable');
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
    link.download = 'nco_nominal_report.csv';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
});

document.getElementById('exportExcelBtn').addEventListener('click', function() {
    let table = document.getElementById('seniorityTable');
    let wb = XLSX.utils.table_to_book(table, {sheet:"NCO Nominal"});
    XLSX.writeFile(wb, 'nco_nominal_report.xlsx');
});

document.getElementById('exportPDFBtn').addEventListener('click', function(){
    let table = document.getElementById('seniorityTable');
    let rows = Array.from(table.rows).map(row => Array.from(row.cells).map(cell => cell.innerText));
    const { jsPDF } = window.jspdf;
    let doc = new jsPDF();
    let startY = 20;
    doc.text("NCO Nominal Report", 14, startY);
    rows.forEach(function(row, idx){
        doc.text(row.join(" | "), 14, startY + 8 + idx*8);
    });
    doc.save("nco_nominal_report.pdf");
});

document.querySelector('.print-btn').addEventListener('click', function(){
    window.print();
});

// Dynamic dropdown filtering via AJAX (simulate for demo, ideally do via endpoint)
['rankFilter','unitFilter'].forEach(function(id){
    document.getElementById(id).addEventListener('change', function(){
        document.forms[0].submit();
    });
});

// Dynamic searchbar: client-side instant filter
document.getElementById('senioritySearch').addEventListener('input', function() {
    const query = this.value.toLowerCase();
    document.querySelectorAll('#seniorityTable tbody tr').forEach(function(row) {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(query) ? '' : 'none';
    });
});
</script>
<?php include dirname(__DIR__) . '/shared/footer.php'; ?>