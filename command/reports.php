<?php
// filepath: users/command/reports.php

require_once '../init.php';
require_once $abs_us_root . $us_url_root . 'users/includes/template/prep.php';

if (!securePage($_SERVER['PHP_SELF'])) { die(); }

// Handle search input
$search = trim(Input::get('search', ''));
$params = [];
$where = '';
if ($search !== '') {
    $where = "WHERE s.svcNo LIKE ? OR s.fname LIKE ? OR s.lname LIKE ?";
    $params = ["%$search%", "%$search%", "%$search%"];
}

// Helper for queries
function statQuery($db, $sql, $params = []) {
    return $db->query($sql, $params)->first();
}
function statList($db, $sql, $params = []) {
    return $db->query($sql, $params)->results();
}

// Total staff
$totalStaff = statQuery($db, "SELECT COUNT(*) as total FROM staff s $where", $params)->total;

// By category
$byCategory = statList($db, "SELECT s.category, COUNT(*) as total FROM staff s $where GROUP BY s.category", $params);

// By gender
$byGender = statList($db, "SELECT s.gender, COUNT(*) as total FROM staff s $where GROUP BY s.gender", $params);

// By unit
$byUnit = statList($db, "SELECT IFNULL(u.unitName, 'Unknown') as unitName, COUNT(*) as total FROM staff s LEFT JOIN units u ON s.unitID = u.unitID $where GROUP BY u.unitName", $params);

// By rank
$byRank = statList($db, "SELECT IFNULL(r.rankName, 'Unknown') as rankName, COUNT(*) as total FROM staff s LEFT JOIN ranks r ON s.rankID = r.rankID $where GROUP BY r.rankName ORDER BY COUNT(*) DESC", $params);

// By province
$byProvince = statList($db, "SELECT s.province, COUNT(*) as total FROM staff s $where GROUP BY s.province", $params);

// By blood group
$byBlood = statList($db, "SELECT s.bloodGp, COUNT(*) as total FROM staff s $where GROUP BY s.bloodGp", $params);

// Age distribution
$byAge = statList($db, "
    SELECT 
        CASE 
            WHEN TIMESTAMPDIFF(YEAR, s.DOB, CURDATE()) < 25 THEN '<25'
            WHEN TIMESTAMPDIFF(YEAR, s.DOB, CURDATE()) BETWEEN 25 AND 34 THEN '25-34'
            WHEN TIMESTAMPDIFF(YEAR, s.DOB, CURDATE()) BETWEEN 35 AND 44 THEN '35-44'
            WHEN TIMESTAMPDIFF(YEAR, s.DOB, CURDATE()) BETWEEN 45 AND 54 THEN '45-54'
            ELSE '55+' END as age_group,
        COUNT(*) as total
    FROM staff s
    $where
    GROUP BY age_group
    ORDER BY age_group
", $params);

?>

<style>
.stat-card {
    border-radius: 0.5rem;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    margin-bottom: 1.5rem;
}
.stat-title {
    font-size: 1.1rem;
    color: #355E3B;
    font-weight: 600;
}
.stat-value {
    font-size: 2rem;
    font-weight: bold;
    color: #198754;
}
@media (max-width: 767px) {
    .stat-value { font-size: 1.3rem; }
}
</style>

<div class="container my-5">
    <div class="mb-3">
        <a href="../command_reports.php" class="btn btn-outline-secondary">
            <i class="fa fa-arrow-left"></i> Back to Command Dashboard
        </a>
    </div>
    <h2 class="mb-4" style="color:#355E3B;"><i class="fa fa-chart-bar"></i> Command Statistical Report</h2>
    <form class="mb-4" method="get" action="">
        <div class="input-group">
            <input type="text" name="search" class="form-control" placeholder="Search" value="<?=htmlspecialchars($search)?>">
            <button class="btn btn-success" type="submit"><i class="fa fa-search"></i> Search</button>
        </div>
    </form>

    <!-- Top Stats -->
    <div class="row mb-4">
        <div class="col-md-3 col-6">
            <div class="stat-card p-3 text-center bg-light">
                <div class="stat-title">Total Staff</div>
                <div class="stat-value"><?=number_format($totalStaff)?></div>
            </div>
        </div>
        <?php foreach($byCategory as $cat): ?>
        <div class="col-md-3 col-6">
            <div class="stat-card p-3 text-center">
                <div class="stat-title"><?=htmlspecialchars($cat->category ?: 'Unknown')?>s</div>
                <div class="stat-value"><?=number_format($cat->total)?></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="row mb-4">
        <div class="col-md-3 col-6">
            <div class="stat-card p-3 text-center">
                <div class="stat-title">Male</div>
                <div class="stat-value"><?=number_format(array_sum(array_map(function($g){return strtolower($g->gender)=='male'?$g->total:0;},$byGender)))?></div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="stat-card p-3 text-center">
                <div class="stat-title">Female</div>
                <div class="stat-value"><?=number_format(array_sum(array_map(function($g){return strtolower($g->gender)=='female'?$g->total:0;},$byGender)))?></div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="stat-card p-3 text-center">
                <div class="stat-title">Other</div>
                <div class="stat-value"><?=number_format(array_sum(array_map(function($g){return !in_array(strtolower($g->gender),['male','female'])?$g->total:0;},$byGender)))?></div>
            </div>
        </div>
    </div>

    <!-- By Unit -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="stat-card p-3">
                <div class="stat-title mb-2"><i class="fa fa-building"></i> Staff by Unit</div>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered align-middle mb-0">
                        <thead class="table-success">
                            <tr>
                                <th>Unit</th>
                                <th>Count</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($byUnit as $unit): ?>
                                <tr>
                                    <td><?=htmlspecialchars($unit->unitName ?: 'Unknown')?></td>
                                    <td><?=number_format($unit->total)?></td>
                                </tr>
                            <?php endforeach;?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- By Rank -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="stat-card p-3">
                <div class="stat-title mb-2"><i class="fa fa-medal"></i> Staff by Rank</div>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered align-middle mb-0">
                        <thead class="table-success">
                            <tr>
                                <th>Rank</th>
                                <th>Count</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($byRank as $rank): ?>
                                <tr>
                                    <td><?=htmlspecialchars($rank->rankName ?: 'Unknown')?></td>
                                    <td><?=number_format($rank->total)?></td>
                                </tr>
                            <?php endforeach;?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- By Province -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="stat-card p-3">
                <div class="stat-title mb-2"><i class="fa fa-map-marker-alt"></i> Staff by Province</div>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered align-middle mb-0">
                        <thead class="table-success">
                            <tr>
                                <th>Province</th>
                                <th>Count</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($byProvince as $prov): ?>
                                <tr>
                                    <td><?=htmlspecialchars($prov->province ?: 'Unknown')?></td>
                                    <td><?=number_format($prov->total)?></td>
                                </tr>
                            <?php endforeach;?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- By Blood Group -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="stat-card p-3">
                <div class="stat-title mb-2"><i class="fa fa-tint"></i> Staff by Blood Group</div>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered align-middle mb-0">
                        <thead class="table-success">
                            <tr>
                                <th>Blood Group</th>
                                <th>Count</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($byBlood as $blood): ?>
                                <tr>
                                    <td><?=htmlspecialchars($blood->bloodGp ?: 'Unknown')?></td>
                                    <td><?=number_format($blood->total)?></td>
                                </tr>
                            <?php endforeach;?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- By Age Group -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="stat-card p-3">
                <div class="stat-title mb-2"><i class="fa fa-birthday-cake"></i> Staff by Age Group</div>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered align-middle mb-0">
                        <thead class="table-success">
                            <tr>
                                <th>Age Group</th>
                                <th>Count</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($byAge as $age): ?>
                                <tr>
                                    <td><?=htmlspecialchars($age->age_group)?></td>
                                    <td><?=number_format($age->total)?></td>
                                </tr>
                            <?php endforeach;?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

</div>

<?php require_once $abs_us_root . $us_url_root . 'users/includes/html_footer.php'; ?>