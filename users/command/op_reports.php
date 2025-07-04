<?php
// filepath: users/statistical_reports.php

require_once '../init.php';
require_once $abs_us_root . $us_url_root . 'users/includes/template/prep.php';

if (!securePage($_SERVER['PHP_SELF'])) { die(); }

// Handle search input
$search = Input::get('search', '');

// Prepare query
$params = [];
$where = '';
if ($search) {
    $where = "WHERE unitID LIKE ? OR province LIKE ?";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// Fetch staff per unit
$staffByUnit = $db->query(
    "SELECT unitID, COUNT(*) as total FROM Staff $where GROUP BY unitID ORDER BY total DESC LIMIT 100",
    $params
)->results();

// Fetch staff per province
$staffByProvince = $db->query(
    "SELECT province, COUNT(*) as total FROM Staff $where GROUP BY province ORDER BY total DESC LIMIT 100",
    $params
)->results();
?>

<div class="container my-5">
    <div class="mb-3">
        <a href="../command_reports.php" class="btn btn-outline-secondary">
            <i class="fa fa-arrow-left"></i> Back to Command Reports
        </a>
    </div>
    <h2 class="mb-4" style="color:#355E3B;"><i class="fa fa-bar-chart"></i> Statistical Reports</h2>
    <div class="row">
        <!-- Search Bar -->
        <div class="col-md-4 mb-4">
            <form class="mb-3" method="get" action="">
                <div class="input-group">
                    <input type="text" name="search" class="form-control" placeholder="Search by Unit or Province..." value="<?=htmlspecialchars($search)?>">
                    <button class="btn btn-success" type="submit"><i class="fa fa-search"></i> Search</button>
                </div>
            </form>
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-success text-white">
                    <strong>Staff Per Unit</strong>
                </div>
                <div class="card-body p-0" style="max-height: 350px; overflow-y: auto;">
                    <?php if(count($staffByUnit)): ?>
                        <table class="table table-sm table-bordered mb-0">
                            <thead class="thead-light"><tr><th>Unit</th><th>Total</th></tr></thead>
                            <tbody>
                                <?php foreach($staffByUnit as $row): ?>
                                    <tr>
                                        <td><?=htmlspecialchars($row->unitID)?></td>
                                        <td><?=htmlspecialchars($row->total)?></td>
                                    </tr>
                                <?php endforeach;?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="p-3 text-muted">No units found.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <!-- Staff Per Province -->
        <div class="col-md-8 mb-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-info text-white">
                    <strong>Staff Per Province</strong>
                </div>
                <div class="card-body p-0" style="max-height: 350px; overflow-y: auto;">
                    <?php if(count($staffByProvince)): ?>
                        <table class="table table-sm table-bordered mb-0">
                            <thead class="thead-light"><tr><th>Province</th><th>Total</th></tr></thead>
                            <tbody>
                                <?php foreach($staffByProvince as $row): ?>
                                    <tr>
                                        <td><?=htmlspecialchars($row->province)?></td>
                                        <td><?=htmlspecialchars($row->total)?></td>
                                    </tr>
                                <?php endforeach;?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="p-3 text-muted">No provinces found.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once $abs_us_root . $us_url_root . 'users/includes/html_footer.php'; ?>