<?php
define('ARMIS_ADMIN_BRANCH', true);
require_once __DIR__ . '/includes/auth.php';
require_once dirname(__DIR__) . '/shared/database_connection.php';
requireAuth();

$pageTitle = "Corps Report as at " . date('d-M-Y');
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

// Dynamic filter options for corps report
function getCorpsOptions($pdo, $unit, $rank, $cat) {
    $corpsSql = "SELECT DISTINCT corps FROM staff WHERE corps IS NOT NULL AND corps <> '' AND svcStatus = 'Active'";
    $unitSql = "SELECT DISTINCT u.id, u.name FROM units u JOIN staff s ON s.unit_id = u.id WHERE s.svcStatus = 'Active'";
    $rankSql = "SELECT DISTINCT r.id, r.name FROM ranks r JOIN staff s ON s.rank_id = r.id WHERE s.svcStatus = 'Active'";
    $catSql  = "SELECT DISTINCT s.category FROM staff s WHERE s.category IS NOT NULL AND s.category <> '' AND s.svcStatus = 'Active'";
    return [
        $pdo->query($corpsSql)->fetchAll(PDO::FETCH_COLUMN),
        fetchAll($unitSql . " ORDER BY u.name ASC"),
        fetchAll($rankSql . " ORDER BY r.name ASC"),
        fetchAll($catSql . " ORDER BY s.category ASC")
    ];
}
$filter_corps = $_GET['corps'] ?? '';
$filter_unit = $_GET['unitID'] ?? '';
$filter_rank = $_GET['rankID'] ?? '';
$filter_category = $_GET['category'] ?? '';
$search = trim($_GET['search'] ?? '');

list($corpsList, $units, $ranks, $categories) = getCorpsOptions($pdo, $filter_unit, $filter_rank, $filter_category);

$params = [];
$sql = "SELECT s.*, r.name as rankName, u.name as unitName FROM staff s
        LEFT JOIN ranks r ON s.rank_id = r.id
        LEFT JOIN units u ON s.unit_id = u.id
        WHERE s.svcStatus = 'Active'";
if ($filter_corps !== '')      { $sql .= " AND s.corps = ?"; $params[] = $filter_corps; }
if ($filter_unit !== '')       { $sql .= " AND s.unit_id = ?"; $params[] = $filter_unit; }
if ($filter_rank !== '')       { $sql .= " AND s.rank_id = ?"; $params[] = $filter_rank; }
if ($filter_category !== '')   { $sql .= " AND s.category = ?"; $params[] = $filter_category; }
if ($search !== '') {
    $sql .= " AND (s.corps LIKE ? OR s.service_number LIKE ? OR s.last_name LIKE ? OR s.first_name LIKE ? OR r.name LIKE ? OR u.name LIKE ? OR s.category LIKE ?)";
    for ($i = 0; $i < 7; $i++) $params[] = "%$search%";
}
$sql .= " ORDER BY r.level ASC, s.last_name ASC, s.first_name ASC";
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
            <h1 class="section-title mb-4"><i class="fas fa-shield-alt"></i> <?= htmlspecialchars($pageTitle) ?></h1>
            <div class="alert alert-info d-flex align-items-center mb-3" role="alert">
                <i class="fas fa-question-circle me-2"></i>
                <span>
                    Filter by corps, unit, rank, category. Use the search box for instant filtering. Export, print, show/hide columns. Double-click row for details.
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
                    <select name="corps" id="corpsFilter" class="form-select">
                        <option value="">All Corps</option>
                        <?php foreach ($corpsList as $c): ?>
                            <option value="<?= htmlspecialchars($c) ?>" <?= ($filter_corps == $c) ? 'selected':''?>><?= htmlspecialchars($c) ?></option>
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
                    <select name="rankID" id="rankFilter" class="form-select">
                        <option value="">All Ranks</option>
                        <?php foreach ($ranks as $r): ?>
                            <option value="<?= $r->id ?>" <?= ($filter_rank == $r->id) ? 'selected':''?>><?= htmlspecialchars($r->name) ?></option>
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
                    <input type="text" id="corpsSearch" name="search" class="form-control" placeholder="Quick Search..." value="<?=htmlspecialchars($search)?>">
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
                    'corps'=>'Corps','unit'=>'Unit','rank'=>'Rank','service_number'=>'Service No','surname'=>'Surname','first_name'=>'First Name(s)','category'=>'Category','DOB'=>'Date of Birth','attestDate'=>'Date of Enlistment'
                ]; foreach ($columns as $key=>$label): ?>
                <label class="me-3"><input type="checkbox" class="toggle-col" data-col="<?= $key ?>" checked> <?= $label ?></label>
                <?php endforeach; ?>
            </div>
            <div class="table-responsive print-friendly">
                <table class="table table-bordered table-hover align-middle" id="corpsTable">
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
                                <td class="col-corps"><?= htmlspecialchars($s->corps ?? '') ?></td>
                                <td class="col-unit"><?= htmlspecialchars($s->unitName ?? '') ?></td>
                                <td class="col-rank"><?= htmlspecialchars($s->rankName ?? '') ?></td>
                                <td class="col-service_number"><?= htmlspecialchars($s->service_number ?? '') ?></td>
                                <td class="col-surname"><?= htmlspecialchars($s->last_name ?? '') ?></td>
                                <td class="col-first_name"><?= htmlspecialchars($s->first_name ?? '') ?></td>
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
            <!-- Pagination -->
            <div class="d-flex justify-content-center my-3">
                <nav aria-label="Corps pagination">
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
// Show/hide columns, export, print, instant search: identical to seniority
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
    let table = document.getElementById('corpsTable');
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
    link.download = 'corps_report.csv';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
});
document.getElementById('exportExcelBtn').addEventListener('click', function() {
    let table = document.getElementById('corpsTable');
    let wb = XLSX.utils.table_to_book(table, {sheet:"Corps"});
    XLSX.writeFile(wb, 'corps_report.xlsx');
});
document.getElementById('exportPDFBtn').addEventListener('click', function(){
    let table = document.getElementById('corpsTable');
    let rows = Array.from(table.rows).map(row => Array.from(row.cells).map(cell => cell.innerText));
    const { jsPDF } = window.jspdf;
    let doc = new jsPDF();
    let startY = 20;
    doc.text("Corps Report", 14, startY);
    rows.forEach(function(row, idx){
        doc.text(row.join(" | "), 14, startY + 8 + idx*8);
    });
    doc.save("corps_report.pdf");
});
document.querySelector('.print-btn').addEventListener('click', function(){
    window.print();
});
['corpsFilter','unitFilter','rankFilter','categoryFilter'].forEach(function(id){
    document.getElementById(id).addEventListener('change', function(){
        document.forms[0].submit();
    });
});
document.getElementById('corpsSearch').addEventListener('input', function() {
    const query = this.value.toLowerCase();
    document.querySelectorAll('#corpsTable tbody tr').forEach(function(row) {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(query) ? '' : 'none';
    });
});
</script>
<?php include dirname(__DIR__) . '/shared/footer.php'; ?>