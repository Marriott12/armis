<?php
// Define module constants
define('ARMIS_ADMIN_BRANCH', true);

// Include admin branch authentication and database
require_once __DIR__ . '/includes/auth.php';
require_once dirname(__DIR__) . '/shared/database_connection.php';

// Require authentication
requireAuth();

$pageTitle = "Appointment Report as at " . date('d-M-Y');
$moduleName = "Admin Branch";
$moduleIcon = "users-cog";
$currentPage = "reports";

$sidebarLinks = [
    ['title' => 'Dashboard', 'url' => '/Armis2/admin_branch/index.php', 'icon' => 'tachometer-alt', 'page' => 'dashboard'],
    ['title' => 'Create Staff', 'url' => '/Armis2/admin_branch/create_staff.php', 'icon' => 'user-plus', 'page' => 'create_staff'],
    ['title' => 'Edit Staff', 'url' => '/Armis2/admin_branch/edit_staff.php', 'icon' => 'user-edit', 'page' => 'edit_staff'],
    ['title' => 'Appointments', 'url' => '/Armis2/admin_branch/appointments.php', 'icon' => 'briefcase', 'page' => 'appointments'],
    ['title' => 'Batch Appointments', 'url' => '/Armis2/admin_branch/batch_appointments.php', 'icon' => 'tasks', 'page' => 'batch_appointments'],
    ['title' => 'Pending Approvals', 'url' => '/Armis2/admin_branch/pending_appointments.php', 'icon' => 'clock', 'page' => 'pending_appointments'],
    ['title' => 'Appointment History', 'url' => '/Armis2/admin_branch/appointment_history.php', 'icon' => 'history', 'page' => 'appointment_history'],
    ['title' => 'Appointment Types', 'url' => '/Armis2/admin_branch/appointment_types.php', 'icon' => 'clipboard-list', 'page' => 'appointment_types'],
    ['title' => 'Medals', 'url' => '/Armis2/admin_branch/medals.php', 'icon' => 'medal', 'page' => 'medals'],
    [
        'title' => 'Reports',
        'icon' => 'chart-bar',
        'page' => 'reports',
        'children' => [
            ['title' => 'Appointments', 'url' => '/Armis2/admin_branch/reports_appointment.php'],
            ['title' => 'Seniority', 'url' => '/Armis2/admin_branch/reports_seniority.php'],
            ['title' => 'Unit List', 'url' => '/Armis2/admin_branch/reports_units.php'],
            ['title' => 'Contracts', 'url' => '/Armis2/admin_branch/reports_contract.php'],
            ['title' => 'Courses', 'url' => '/Armis2/admin_branch/reports_courses.php'],
            ['title' => 'Deceased', 'url' => '/Armis2/admin_branch/reports_deceased.php'],
            ['title' => 'Gender', 'url' => '/Armis2/admin_branch/reports_gender.php'],
            ['title' => 'Marital', 'url' => '/Armis2/admin_branch/reports_marital.php'],
            ['title' => 'Rank', 'url' => '/Armis2/admin_branch/reports_rank.php'],
            ['title' => 'Retired', 'url' => '/Armis2/admin_branch/reports_retired.php'],
            ['title' => 'Trade', 'url' => '/Armis2/admin_branch/reports_trade.php'],
            ['title' => 'Corps', 'url' => '/Armis2/admin_branch/reports_corps.php']
        ]
    ],
];

$pdo = getDbConnection();

// Helper function to format names in title case (capitalize each word)
function formatSentenceCase($name) {
    if (empty($name)) return '';
    return ucwords(strtolower(trim($name)));
}

function getDynamicOptions($pdo, $selectedRank, $selectedUnit, $selectedCategory, $selectedAppt) {
    $rankSql = "SELECT DISTINCT r.id, r.name FROM ranks r JOIN staff s ON s.rank_id = r.id WHERE s.svcStatus = 'Active'";
    $unitSql = "SELECT DISTINCT u.id, u.name FROM units u JOIN staff s ON s.unit_id = u.id WHERE s.svcStatus = 'Active'";
    $catSql = "SELECT DISTINCT s.category FROM staff s WHERE s.category IS NOT NULL AND s.category <> '' AND s.svcStatus = 'Active'";
    $apptSql = "SELECT DISTINCT s.appt FROM staff s WHERE s.appt IS NOT NULL AND s.appt <> '' AND s.svcStatus = 'Active'";
    
    $rankParams = [];
    $unitParams = [];
    $catParams = [];
    $apptParams = [];

    if ($selectedUnit) {
        $rankSql .= " AND s.unit_id = ?";
        $rankParams[] = $selectedUnit;
    }
    if ($selectedCategory) {
        $rankSql .= " AND s.category = ?";
        $rankParams[] = $selectedCategory;
    }
    if ($selectedAppt) {
        $rankSql .= " AND s.appt = ?";
        $rankParams[] = $selectedAppt;
    }
    if ($selectedRank) {
        $unitSql .= " AND s.rank_id = ?";
        $unitParams[] = $selectedRank;
    }
    if ($selectedCategory) {
        $unitSql .= " AND s.category = ?";
        $unitParams[] = $selectedCategory;
    }
    if ($selectedAppt) {
        $unitSql .= " AND s.appt = ?";
        $unitParams[] = $selectedAppt;
    }
    if ($selectedUnit) {
        $catSql .= " AND s.unit_id = ?";
        $catParams[] = $selectedUnit;
    }
    if ($selectedRank) {
        $catSql .= " AND s.rank_id = ?";
        $catParams[] = $selectedRank;
    }
    if ($selectedAppt) {
        $catSql .= " AND s.appt = ?";
        $catParams[] = $selectedAppt;
    }
    if ($selectedUnit) {
        $apptSql .= " AND s.unit_id = ?";
        $apptParams[] = $selectedUnit;
    }
    if ($selectedRank) {
        $apptSql .= " AND s.rank_id = ?";
        $apptParams[] = $selectedRank;
    }
    if ($selectedCategory) {
        $apptSql .= " AND s.category = ?";
        $apptParams[] = $selectedCategory;
    }

    $ranks = fetchAll($rankSql . " ORDER BY r.name ASC", $rankParams);
    $units = fetchAll($unitSql . " ORDER BY u.name ASC", $unitParams);
    $categories = fetchAll($catSql . " ORDER BY s.category ASC", $catParams);
    $appointments = fetchAll($apptSql . " ORDER BY s.appt ASC", $apptParams);

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
    'appt' => 's.appt',
    'rank' => 'r.level',
    'service_number' => 's.service_number',
    'surname' => 's.last_name',
    'first_name' => 's.first_name',
    'unit' => 'u.name',
    'category' => 's.category',
    'DOB' => 's.DOB',
    'attestDate' => 's.attestDate'
];
$sort_col = $_GET['sort_col'] ?? '';
$sort_dir = strtolower($_GET['sort_dir'] ?? 'asc') === 'desc' ? 'DESC' : 'ASC';

$sql = "SELECT s.*, r.name as rankName, r.abbreviation as rankAbbr, r.level as rankIndex, u.name as unitName, u.code as unitCode
        FROM staff s
        LEFT JOIN ranks r ON s.rank_id = r.id
        LEFT JOIN units u ON s.unit_id = u.id
        WHERE s.svcStatus = 'Active'";

$count_sql = "SELECT COUNT(*) FROM staff s
        LEFT JOIN ranks r ON s.rank_id = r.id
        LEFT JOIN units u ON s.unit_id = u.id
        WHERE s.svcStatus = 'Active'";
$count_params = [];

if ($filter_rank !== '') {
    $sql .= " AND s.rank_id = ?";
    $count_sql .= " AND s.rank_id = ?";
    $params[] = $filter_rank;
    $count_params[] = $filter_rank;
}
if ($filter_unit !== '') {
    $sql .= " AND s.unit_id = ?";
    $count_sql .= " AND s.unit_id = ?";
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
    $sql .= " AND s.appt = ?";
    $count_sql .= " AND s.appt = ?";
    $params[] = $filter_appt;
    $count_params[] = $filter_appt;
}
if ($search !== '') {
    $sql .= " AND (s.appt LIKE ? OR s.service_number LIKE ? OR s.last_name LIKE ? OR s.first_name LIKE ? OR r.name LIKE ? OR u.name LIKE ? OR s.category LIKE ?)";
    $count_sql .= " AND (s.appt LIKE ? OR s.service_number LIKE ? OR s.last_name LIKE ? OR s.first_name LIKE ? OR r.name LIKE ? OR u.name LIKE ? OR s.category LIKE ?)";
    for ($i = 0; $i < 7; $i++) {
        $params[] = "%$search%";
        $count_params[] = "%$search%";
    }
}

// Handle sorting
$order_clause = '';
if ($sort_col && isset($sortable_columns[$sort_col])) {
    $order_clause = " ORDER BY " . $sortable_columns[$sort_col] . " $sort_dir";
} else {
    $order_clause = " ORDER BY r.level ASC, s.last_name ASC, s.first_name ASC";
}
$sql .= $order_clause;
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
                    'appt'=>'Appointment','rank'=>'Rank','service_number'=>'Service No','surname'=>'Surname','first_name'=>'First Name(s)',
                    'unit'=>'Unit','category'=>'Category','DOB'=>'Date of Birth','attestDate'=>'Date of Enlistment'
                ]; foreach ($columns as $key => $label): ?>
                <input type="checkbox" checked data-col="<?= $key ?>" class="toggle-col" id="col_<?= $key ?>">
                <label for="col_<?= $key ?>" class="me-2"><?= $label ?></label>
                <?php endforeach; ?>
            </div>
            <div class="table-responsive print-friendly">
                <table class="table table-bordered table-hover align-middle" id="appointmentTable">
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
                                <td class="col-appt"><?= htmlspecialchars($s->appt ?? '') ?></td>
                                <td class="col-rank"><?= htmlspecialchars($s->rankAbbr ?? '') ?></td>
                                <td class="col-service_number"><?= htmlspecialchars($s->service_number ?? '') ?></td>
                                <td class="col-surname"><?= htmlspecialchars(formatSentenceCase($s->last_name ?? '')) ?></td>
                                <td class="col-first_name"><?= htmlspecialchars(formatSentenceCase($s->first_name ?? '')) ?></td>
                                <td class="col-unit"><?= htmlspecialchars($s->unitCode ?? '') ?></td>
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