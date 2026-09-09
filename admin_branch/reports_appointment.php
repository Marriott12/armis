<?php
// Define module constants
define('ARMIS_ADMIN_BRANCH', true);

// Include admin branch authentication and database
require_once __DIR__ . '/includes/auth.php';
require_once dirname(__DIR__) . '/shared/database_connection.php';
require_once __DIR__ . '/includes/db_helpers.php';

// Require authentication
requireAuth();

$pageTitle = "Personnel Report by Appointment as at " . date('d-M-Y');
$moduleName = "Admin Branch";
$moduleIcon = "users-cog";
$currentPage = "reports";

$sidebarLinks = []; // set by shared nav include below
require_once __DIR__ . '/includes/sidebar_nav.php';

$pdo = getDbConnection();

// Helper function to format names in title case (capitalize each word)
function formatSentenceCase($name) {
    if (empty($name)) return '';
    return ucwords(strtolower(trim($name)));
}

function getDynamicOptions($pdo, $selectedRank, $selectedUnit, $selectedCategory, $selectedAppt) {
    // Use canonical `rank` table
    $rankSql = "SELECT DISTINCT r.rankId AS id, COALESCE(r.rankId, r.rankId) AS name FROM `rank` r JOIN staff s ON s.rankId = r.rankId WHERE s.svcStatus = 'Active'";
    $unitSql = "SELECT DISTINCT u.unitId as id, u.unitId AS name FROM unit u JOIN staff s ON s.unitId = u.unitId WHERE s.svcStatus = 'Active'";
    $catSql = "SELECT DISTINCT s.category FROM staff s WHERE s.category IS NOT NULL AND s.category <> '' AND s.svcStatus = 'Active'";
    $apptSql = "SELECT DISTINCT s.apptId as appt FROM staff s WHERE s.apptId IS NOT NULL AND s.apptId <> '' AND s.svcStatus = 'Active'";
    
    $rankParams = [];
    $unitParams = [];
    $catParams = [];
    $apptParams = [];

    if ($selectedUnit) {
        $rankSql .= " AND s.unitId = ?";
        $rankParams[] = $selectedUnit;
    }
    if ($selectedCategory) {
        $rankSql .= " AND s.category = ?";
        $rankParams[] = $selectedCategory;
    }
    if ($selectedAppt) {
        $rankSql .= " AND s.apptId = ?";
        $rankParams[] = $selectedAppt;
    }
    if ($selectedRank) {
        $unitSql .= " AND s.rankId = ?";
        $unitParams[] = $selectedRank;
    }
    if ($selectedCategory) {
        $unitSql .= " AND s.category = ?";
        $unitParams[] = $selectedCategory;
    }
    if ($selectedAppt) {
        $unitSql .= " AND s.apptId = ?";
        $unitParams[] = $selectedAppt;
    }
    if ($selectedUnit) {
        $catSql .= " AND s.unitId = ?";
        $catParams[] = $selectedUnit;
    }
    if ($selectedRank) {
        $catSql .= " AND s.rankId = ?";
        $catParams[] = $selectedRank;
    }
    if ($selectedAppt) {
        $catSql .= " AND s.apptId = ?";
        $catParams[] = $selectedAppt;
    }
    if ($selectedUnit) {
        $apptSql .= " AND s.unitId = ?";
        $apptParams[] = $selectedUnit;
    }
    if ($selectedRank) {
        $apptSql .= " AND s.rankId = ?";
        $apptParams[] = $selectedRank;
    }
    if ($selectedCategory) {
        $apptSql .= " AND s.category = ?";
        $apptParams[] = $selectedCategory;
    }

    $ranks = fetchAll($rankSql . " ORDER BY r.rankIndex ASC, name ASC", $rankParams);
    $units = fetchAll($unitSql . " ORDER BY u.unitId ASC", $unitParams);
    $categories = fetchAll($catSql . " ORDER BY s.category ASC", $catParams);
    $appointments = fetchAll($apptSql . " ORDER BY s.apptId ASC", $apptParams);

    return [$ranks, $units, $categories, $appointments];
}

$filter_rank = $_GET['rankID'] ?? '';
$filter_unit = $_GET['unitID'] ?? '';
$filter_category = $_GET['category'] ?? '';
$filter_appt = $_GET['appointment'] ?? '';
$search = trim($_GET['search'] ?? '');
$params = [];

list($ranks, $units, $categories, $appointments) = getDynamicOptions($pdo, $filter_rank, $filter_unit, $filter_category, $filter_appt);

$per_page = intval($_GET['per_page'] ?? 25);
$page = max(1, intval($_GET['page'] ?? 1));
$offset = ($page - 1) * $per_page;

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

// Normalize rank display to use canonical rank fields
$sql = "SELECT s.*, COALESCE(r.rankId, r.rankId) as rankName, r.rankId as rankAbbr, r.rankIndex as rankIndex, COALESCE(u.unitId, u.unitId) as unitName, u.unitId as unitCode
    FROM staff s
    LEFT JOIN `rank` r ON s.rankId = r.rankId
    LEFT JOIN unit u ON s.unitId = u.unitId
    WHERE s.svcStatus = 'Active'";

$count_sql = "SELECT COUNT(*) FROM staff s
    LEFT JOIN `rank` r ON s.rankId = r.rankId
    LEFT JOIN unit u ON s.unitId = u.unitId
    WHERE s.svcStatus = 'Active'";
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
if ($filter_appt !== '') {
    $sql .= " AND s.apptId = ?";
    $count_sql .= " AND s.apptId = ?";
    $params[] = $filter_appt;
    $count_params[] = $filter_appt;
}
if ($search !== '') {
    $sql .= " AND (s.apptId LIKE ? OR s.svcNo LIKE ? OR s.lName LIKE ? OR s.fName LIKE ? OR COALESCE(r.rankId, r.rankId) LIKE ? OR COALESCE(u.unitId, u.unitId) LIKE ? OR s.category LIKE ?)";
    $count_sql .= " AND (s.apptId LIKE ? OR s.svcNo LIKE ? OR s.lName LIKE ? OR s.fName LIKE ? OR COALESCE(r.rankId, r.rankId) LIKE ? OR COALESCE(u.unitId, u.unitId) LIKE ? OR s.category LIKE ?)";
    for ($i = 0; $i < 7; $i++) {
        $params[] = "%$search%";
        $count_params[] = "%$search%";
    }
}

// Handle sorting

if ($sort_col && isset($sortable_columns[$sort_col])) {
    $sql .= " ORDER BY " . $sortable_columns[$sort_col] . " $sort_dir";
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
$sql .= " LIMIT $per_page OFFSET $offset";

$staff = fetchAll($sql, $params);
$total_records = fetchAll($count_sql, $count_params)[0]->{'COUNT(*)'};
$total_pages = ceil($total_records / $per_page);

include dirname(__DIR__) . '/shared/header.php';
include dirname(__DIR__) . '/shared/sidebar.php';
?>
<div class="content-wrapper with-sidebar">
    <div class="container-fluid">
        <div class="main-content">
            <h1 class="section-title mb-4">
                <i class="fas fa-briefcase"></i> <?= htmlspecialchars($pageTitle) ?>
            </h1>
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
                        <?php foreach ($appointments as $a): ?>
                            <option value="<?= htmlspecialchars($a->appt) ?>" <?= ($filter_appt == $a->appt) ? 'selected' : '' ?>><?= htmlspecialchars($a->appt) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="rankID" id="rankFilter" class="form-select">
                        <option value="">All Ranks</option>
                        <?php foreach ($ranks as $r): ?>
                            <option value="<?= $r->id ?>" <?= ($filter_rank == $r->id) ? 'selected' : '' ?>><?= htmlspecialchars($r->name) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="unitID" id="unitFilter" class="form-select">
                        <option value="">All Units</option>
                        <?php foreach ($units as $u): ?>
                            <option value="<?= $u->id ?>" <?= ($filter_unit == $u->id) ? 'selected' : '' ?>><?= htmlspecialchars($u->name) ?></option>
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
                    <input type="text" id="appointmentSearch" name="search" class="form-control" placeholder="Quick Search..." value="<?= htmlspecialchars($search) ?>">
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
                ]; foreach ($columns as $key => $label): ?>
                <input type="checkbox" checked data-col="<?= $key ?>" class="toggle-col" id="col_<?= $key ?>">
                <label for="col_<?= $key ?>" class="me-2"><?= $label ?></label>
                <?php endforeach; ?>
            </div>
            <div class="table-responsive print-friendly">
                <form id="batchForm" method="post" action="/Armis2/admin_branch/batch_action.php">
                <table class="table table-bordered table-hover align-middle" id="appointmentTable">
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
                        <?php if (!$staff): ?>
                            <tr><td colspan="<?= count($columns)+2 ?>" class="text-center text-muted">No staff found.</td></tr>
                        <?php else: foreach($staff as $s): ?>
                            <tr>
                                <td><input type="checkbox" name="selected_ids[]" value="<?= htmlspecialchars($s->id) ?>" class="rowCheckbox"></td>
                                <td class="col-appt"><?= htmlspecialchars($s->appt ?? '') ?></td>
                                <td class="col-rank"><?= htmlspecialchars($s->rankAbbr ?? '') ?></td>
                                <td class="col-svcNo"><?= htmlspecialchars($s->svcNo ?? '') ?></td>
                                <td class="col-surname"><?= htmlspecialchars(formatSentenceCase($s->lName ?? '')) ?></td>
                                <td class="col-fName"><?= htmlspecialchars(formatSentenceCase($s->fName ?? '')) ?></td>
                                <td class="col-unit"><?= htmlspecialchars($s->unitCode ?? '') ?></td>
                                <td class="col-category"><?= htmlspecialchars($s->category ?? '') ?></td>
                                <td class="col-DOB"><?= htmlspecialchars($s->DOB ?? '') ?></td>
                                <td class="col-attestDate"><?= htmlspecialchars($s->attestDate ?? '') ?></td>
                                <td>
                                    <?php if (!empty($s->id)): ?>
                                        <a href="/Armis2/admin_branch/view_staff.php?id=<?= urlencode($s->id) ?>" class="btn btn-outline-primary btn-sm" target="_blank" aria-label="View staff">View</a>
                                    <?php else: ?>
                                        <span class="text-muted">N/A</span>
                                    <?php endif; ?>
                                    <a href="/Armis2/admin_branch/edit_staff.php?svcNo=<?= urlencode($s->svcNo) ?>" class="btn btn-outline-secondary btn-sm ms-1" aria-label="Edit staff">Edit</a>
                                    <a href="/Armis2/admin_branch/reset_password.php?svcNo=<?= urlencode($s->svcNo) ?>" class="btn btn-outline-warning btn-sm ms-1" aria-label="Reset password">Reset Password</a>
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
                <div class="text-end mt-2">
                    <button onclick="window.print()" class="btn btn-outline-secondary btn-sm print-btn"><i class="fa fa-print"></i> Print Report</button>
                    <button id="exportCSVBtn" class="btn btn-outline-success btn-sm ms-2"><i class="fa fa-file-csv"></i> Export CSV</button>
                    <button id="exportExcelBtn" class="btn btn-outline-success btn-sm"><i class="fa fa-file-excel"></i> Excel</button>
                    <button id="exportPDFBtn" class="btn btn-outline-danger btn-sm"><i class="fa fa-file-pdf"></i> PDF</button>
                </div>
            </div>
            <div class="d-flex justify-content-center my-3">
                <nav aria-label="Appointments pagination">
                    <ul class="pagination pagination-sm">
                        <?php
                        $max_links = 7;
                        $start = max(1, $page - intval($max_links / 2));
                        $end = min($total_pages, $start + $max_links - 1);
                        
                        if ($page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => 1])) ?>">&laquo; First</a>
                        </li>
                        <li class="page-item">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">&lsaquo; Prev</a>
                        </li>
                        <?php endif;
                        
                        for ($p = $start; $p <= $end; $p++): ?>
                        <li class="page-item<?= ($p == $page) ? ' active' : '' ?>">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $p])) ?>"><?= $p ?></a>
                        </li>
                        <?php endfor;
                        
                        if ($page < $total_pages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">Next &rsaquo;</a>
                        </li>
                        <li class="page-item">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $total_pages])) ?>">Last &raquo;</a>
                        </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            </div>
            <p class="text-muted small">Showing <?= count($staff) ?> of <?= $total_records ?> records (Page <?= $page ?> of <?= $total_pages ?>)</p>
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
    let table = document.getElementById('appointmentTable');
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
    link.download = 'appointment_report.csv';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
});

document.getElementById('exportExcelBtn').addEventListener('click', function() {
    let table = document.getElementById('appointmentTable');
    let wb = XLSX.utils.table_to_book(table, {sheet:"Appointments"});
    XLSX.writeFile(wb, 'appointment_report.xlsx');
});

document.getElementById('exportPDFBtn').addEventListener('click', function(){
    let table = document.getElementById('appointmentTable');
    let rows = Array.from(table.rows).map(row => Array.from(row.cells).map(cell => cell.innerText));
    const { jsPDF } = window.jspdf;
    let doc = new jsPDF();
    let startY = 20;
    doc.text("Appointment Report", 14, startY);
    rows.forEach(function(row, idx){
        doc.text(row.join(" | "), 14, startY + 8 + idx*8);
    });
    doc.save("appointment_report.pdf");
});

document.querySelector('.print-btn').addEventListener('click', function(){
    window.print();
});

['apptFilter','rankFilter','unitFilter','categoryFilter'].forEach(function(id){
    document.getElementById(id).addEventListener('change', function(){
        document.forms[0].submit();
    });
});

document.getElementById('appointmentSearch').addEventListener('input', function() {
    const query = this.value.toLowerCase();
    document.querySelectorAll('#appointmentTable tbody tr').forEach(function(row) {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(query) ? '' : 'none';
    });
});
</script>

<?php include dirname(__DIR__) . '/shared/footer.php'; ?>