<?php
// Define module constants
define('ARMIS_ADMIN_BRANCH', true);
// Include admin branch authentication and database
require_once __DIR__ . '/includes/auth.php';
require_once dirname(__DIR__) . '/shared/database_connection.php';

// Require authentication
requireAuth();

$pageTitle = "Corps Report as at " . date('d-M-Y');
$moduleName = "Admin Branch";
$moduleIcon = "users-cog";
$currentPage = "reports";

$pdo = getDbConnection();

// Sidebar navigation 
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

    // Helper function to format names in title case (capitalize each word)
    function formatSentenceCase($name) {
        if (empty($name)) return '';
        return ucwords(strtolower(trim($name)));
    }

    // Dynamic filter options (for active staff)
    function getDynamicOptions($pdo, $selectedRank, $selectedUnit, $selectedCategory, $selectedCorps) {
    $rankSql = "SELECT DISTINCT r.rankId as id, COALESCE(r.rankId, r.rankId) as name FROM `rank` r JOIN staff s ON s.rankId = r.rankId WHERE s.svcStatus = 'Active'";
        $unitSql = "SELECT DISTINCT u.unitId, u.name FROM unit u JOIN staff s ON s.unitId = u.unitId WHERE s.svcStatus = 'Active'";
        $catSql  = "SELECT DISTINCT s.category FROM staff s WHERE s.category IS NOT NULL AND s.category <> '' AND s.svcStatus = 'Active'";
        $corpsSql = "SELECT DISTINCT s.corps FROM staff s WHERE s.corps IS NOT NULL AND s.corps <> '' AND s.svcStatus = 'Active'";

        $rankParams = [];
        $unitParams = [];
        $catParams  = [];
        $corpsParams = [];

        if ($selectedUnit) {
            $rankSql .= " AND s.unitId = ?";
            $rankParams[] = $selectedUnit;
        }
        if ($selectedCategory) {
            $rankSql .= " AND s.category = ?";
            $rankParams[] = $selectedCategory;
        }
        if ($selectedCorps) {
            $rankSql .= " AND s.corps = ?";
            $rankParams[] = $selectedCorps;
        }
        if ($selectedRank) {
            $unitSql .= " AND s.rankId = ?";
            $unitParams[] = $selectedRank;
        }
        if ($selectedCategory) {
            $unitSql .= " AND s.category = ?";
            $unitParams[] = $selectedCategory;
        }
        if ($selectedCorps) {
            $unitSql .= " AND s.corps = ?";
            $unitParams[] = $selectedCorps;
        }
        if ($selectedUnit) {
            $catSql .= " AND s.unitId = ?";
            $catParams[] = $selectedUnit;
        }
        if ($selectedRank) {
            $catSql .= " AND s.rankId = ?";
            $catParams[] = $selectedRank;
        }
        if ($selectedCorps) {
            $catSql .= " AND s.corps = ?";
            $catParams[] = $selectedCorps;
        }
        if ($selectedUnit) {
            $corpsSql .= " AND s.unitId = ?";
            $corpsParams[] = $selectedUnit;
        }
        if ($selectedRank) {
            $corpsSql .= " AND s.rankId = ?";
            $corpsParams[] = $selectedRank;
        }
        if ($selectedCategory) {
            $corpsSql .= " AND s.category = ?";
            $corpsParams[] = $selectedCategory;
        }

        $ranks = fetchAll($rankSql . " ORDER BY r.rankId ASC", $rankParams);
        $units = fetchAll($unitSql . " ORDER BY u.name ASC", $unitParams);
        $categories = fetchAll($catSql . " ORDER BY s.category ASC", $catParams);
        $corps = fetchAll($corpsSql . " ORDER BY s.corps ASC", $corpsParams);

        return [$ranks, $units, $categories, $corps];
    }

    $filter_rank = $_GET['rankID'] ?? '';
    $filter_unit = $_GET['unitID'] ?? '';
    $filter_category = $_GET['category'] ?? '';
    $filter_corps = $_GET['corps'] ?? '';
    $search = trim($_GET['search'] ?? '');
    $params = [];

    list($ranks, $units, $categories, $corpsList) = getDynamicOptions($pdo, $filter_rank, $filter_unit, $filter_category, $filter_corps);

    $per_page = intval($_GET['per_page'] ?? 25);
    $page = max(1, intval($_GET['page'] ?? 1));
    $offset = ($page - 1) * $per_page;

    $sortable_columns = [
        'corps' => 's.corps',
        'rank' => 'r.level',
        'svcNo' => 's.svcNo',
        'surname' => 's.lName',
        'fName' => 's.fName',
        'unit' => 'u.name',
        'category' => 's.category',
        'DOB' => 's.DOB',
        'attestDate' => 's.attestDate'
    ];
    $sort_col = $_GET['sort_col'] ?? '';
    $sort_dir = strtolower($_GET['sort_dir'] ?? 'asc') === 'desc' ? 'DESC' : 'ASC';

    $sql = "SELECT s.*, COALESCE(r.rankId, s.rankId) as rankName, r.rankId as rankAbbr, r.level as rankIndex, u.name as unitName, u.code as unitCode
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
    if ($filter_corps !== '') {
        $sql .= " AND s.corps = ?";
        $count_sql .= " AND s.corps = ?";
        $params[] = $filter_corps;
        $count_params[] = $filter_corps;
    }
    if ($search !== '') {
    $sql .= " AND (s.svcNo LIKE ? OR s.lName LIKE ? OR s.fName LIKE ? OR COALESCE(r.rankId, r.rankId) LIKE ? OR u.name LIKE ? OR s.category LIKE ? OR s.corps LIKE ? OR s.DOB LIKE ? OR s.attestDate LIKE ?)";
    $count_sql .= " AND (s.svcNo LIKE ? OR s.lName LIKE ? OR s.fName LIKE ? OR COALESCE(r.rankId, r.rankId) LIKE ? OR u.name LIKE ? OR s.category LIKE ? OR s.corps LIKE ? OR s.DOB LIKE ? OR s.attestDate LIKE ?)";
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
    $columns = [
        'corps' => 'Corps',
        'rank' => 'Rank',
        'svcNo' => 'Service No',
        'surname' => 'Surname',
        'fName' => 'First Name(s)',
        'unit' => 'Unit',
        'category' => 'Category',
        'DOB' => 'Date of Birth',
        'attestDate' => 'Date of Enlistment'
    ];
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
            <div class="modal fade" id="helpModal" tabindex="-1" aria-labelledby="helpModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="helpModalLabel">Report Help</h5>
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
                    <select name="corps" id="corpsFilter" class="form-select" aria-label="Filter by corps">
                        <option value="">All Corps</option>
                        <?php foreach ($corpsList as $c): ?>
                            <option value="<?= htmlspecialchars($c->corps) ?>" <?= ($filter_corps == $c->corps) ? 'selected' : '' ?>><?= htmlspecialchars($c->corps) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
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
                    <input type="text" id="corpsSearch" name="search" class="form-control" placeholder="Quick Search..." aria-label="Quick search" value="<?=htmlspecialchars($search)?>">
                </div>
                <div class="col-md-1">
                    <select name="per_page" class="form-select" title="Records per page">
                        <?php foreach ([10,25,50,100] as $pp): ?>
                        <option value="<?= $pp ?>" <?= ($per_page == $pp) ? 'selected' : '' ?>><?= $pp ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>
            <div class="mb-2">
                <strong>Show/Hide Columns:</strong>
                <?php foreach ($columns as $key => $label): ?>
                    <label class="me-3">
                        <input type="checkbox" class="toggle-col" data-col="<?= $key ?>" checked> <?= $label ?>
                    </label>
                <?php endforeach; ?>
            </div>
            <!-- Removed duplicate Show/Hide Columns block here -->
            <div class="table-responsive print-friendly">
                <form id="batchForm" method="post" action="/Armis2/admin_branch/batch_action.php">
                <table class="table table-bordered table-hover align-middle" id="corpsTable">
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
                        <?php else: $i=1; foreach($staff as $s): ?>
                            <tr ondblclick="alert('Audit/History details coming soon.')">
                                <td><input type="checkbox" name="selected_ids[]" value="<?= htmlspecialchars($s->id) ?>" class="rowCheckbox"></td>
                                <td class="col-corps"><?= htmlspecialchars($s->corps ?? '') ?></td>
                                <td class="col-rank"><?= htmlspecialchars($s->rankAbbr ?? $s->rankName ?? '') ?></td>
                                <td class="col-svcNo"><?= htmlspecialchars($s->svcNo ?? '') ?></td>
                                <td class="col-surname"><?= htmlspecialchars(formatSentenceCase($s->lName ?? '')) ?></td>
                                <td class="col-fName"><?= htmlspecialchars(formatSentenceCase($s->fName ?? '')) ?></td>
                                <td class="col-unit"><?= htmlspecialchars($s->unitCode ?? $s->unitName ?? '') ?></td>
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
                <nav aria-label="Corps pagination">
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
function updateFilterOptions(changed) {
    const corps = document.getElementById('corpsFilter').value;
    const rank = document.getElementById('rankFilter').value;
    const unit = document.getElementById('unitFilter').value;
    const category = document.getElementById('categoryFilter').value;
    const filters = {corps, rankID: rank, unitID: unit, category};
    const endpoints = [
        {id: 'corpsFilter', type: 'corps'},
        {id: 'rankFilter', type: 'rank'},
        {id: 'unitFilter', type: 'unit'},
        {id: 'categoryFilter', type: 'category'}
    ];
    endpoints.forEach(ep => {
        if (ep.id === changed) return; // Don't update the changed filter
        fetch(`ajax_corps_filters.php?type=${ep.type}&corps=${encodeURIComponent(corps)}&rankID=${encodeURIComponent(rank)}&unitID=${encodeURIComponent(unit)}&category=${encodeURIComponent(category)}`)
            .then(r => r.json())
            .then(options => {
                const sel = document.getElementById(ep.id);
                const prev = sel.value;
                sel.innerHTML = `<option value="">All ${sel.getAttribute('aria-label').replace('Filter by ','')}</option>`;
                options.forEach(opt => {
                    sel.innerHTML += `<option value="${opt.value}"${opt.value==prev?' selected':''}>${opt.label}</option>`;
                });
            });
    });
}
['corpsFilter','rankFilter','unitFilter','categoryFilter'].forEach(function(id){
    document.getElementById(id).addEventListener('change', function(){
        updateFilterOptions(id);
    });
});
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
document.getElementById('corpsSearch').addEventListener('input', function() {
    const query = this.value.toLowerCase();
    document.querySelectorAll('#corpsTable tbody tr').forEach(function(row) {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(query) ? '' : 'none';
    });
});
// Submit form on enter in search box or per_page change
['corpsSearch','per_page'].forEach(function(id){
    var el = document.getElementById(id);
    if (el) {
        if (id==='corpsSearch') {
            el.addEventListener('keydown', function(e){ if(e.key==='Enter'){ this.form.submit(); }});
        } else {
            el.addEventListener('change', function(){ this.form.submit(); });
        }
    }
});
</script>
<?php include dirname(__DIR__) . '/shared/footer.php'; ?>