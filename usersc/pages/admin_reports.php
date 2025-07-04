<?php
require_once __DIR__ . '/../includes/admin_init.php';
require_once __DIR__ . '/../models/StaffAdmin.php';

// Example stats (replace with real queries as needed)
$totalUsers = $db->query("SELECT COUNT(*) as total FROM Staff")->first()->total;
$totalMales = $db->query("SELECT COUNT(*) as total FROM Staff WHERE gender = 'Male'")->first()->total;
$totalFemales = $db->query("SELECT COUNT(*) as total FROM Staff WHERE gender = 'Female'")->first()->total;
$totalCollections = $db->query("SELECT COUNT(*) as total FROM Medals")->first()->total;
$totalConnections = $db->query("SELECT COUNT(DISTINCT unitID) as total FROM Staff")->first()->total;

// Example: Staff by Rank for chart
$staffByRank = $db->query("SELECT rankID, COUNT(*) as total FROM Staff GROUP BY rankID ORDER BY total DESC")->results();
?>
<!-- page content -->
<div class="right_col" role="main">
  <!-- top tiles -->
  <div class="row tile_count">
    <div class="col-md-2 col-sm-4 col-xs-6 tile_stats_count">
      <span class="count_top"><i class="fa fa-user"></i> Total Users</span>
      <div class="count"><?= (int)$totalUsers ?></div>
      <span class="count_bottom"><i class="green">100% </i> Active</span>
    </div>
    <div class="col-md-2 col-sm-4 col-xs-6 tile_stats_count">
      <span class="count_top"><i class="fa fa-user"></i> Total Males</span>
      <div class="count green"><?= (int)$totalMales ?></div>
      <span class="count_bottom"><i class="green"><?= $totalUsers > 0 ? round($totalMales/$totalUsers*100) : 0 ?>% </i> of Users</span>
    </div>
    <div class="col-md-2 col-sm-4 col-xs-6 tile_stats_count">
      <span class="count_top"><i class="fa fa-user"></i> Total Females</span>
      <div class="count"><?= (int)$totalFemales ?></div>
      <span class="count_bottom"><i class="red"><?= $totalUsers > 0 ? round($totalFemales/$totalUsers*100) : 0 ?>% </i> of Users</span>
    </div>
    <div class="col-md-2 col-sm-4 col-xs-6 tile_stats_count">
      <span class="count_top"><i class="fa fa-trophy"></i> Total Medals Awarded</span>
      <div class="count"><?= (int)$totalCollections ?></div>
      <span class="count_bottom"><i class="green">All Time</i></span>
    </div>
    <div class="col-md-2 col-sm-4 col-xs-6 tile_stats_count">
      <span class="count_top"><i class="fa fa-building"></i> Total Units</span>
      <div class="count"><?= (int)$totalConnections ?></div>
      <span class="count_bottom"><i class="green">Active</i></span>
    </div>
  </div>
  <!-- /top tiles -->

  <div class="row">
    <div class="col-md-12 col-sm-12 col-xs-12">
      <div class="dashboard_graph">
        <div class="row x_title">
          <div class="col-md-6">
            <h3>Staff by Rank <small>Distribution</small></h3>
          </div>
        </div>
        <div class="col-md-9 col-sm-9 col-xs-12">
          <canvas id="staffByRankChart" height="120"></canvas>
        </div>
        <div class="col-md-3 col-sm-3 col-xs-12 bg-white">
          <div class="x_title">
            <h2>Top Ranks</h2>
            <div class="clearfix"></div>
          </div>
          <table class="table table-sm table-bordered mb-0">
            <thead>
              <tr>
                <th>Rank</th>
                <th>Total</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach($staffByRank as $row): ?>
                <tr>
                  <td><?=htmlspecialchars($row->rankID)?></td>
                  <td><?=htmlspecialchars($row->total)?></td>
                </tr>
              <?php endforeach;?>
            </tbody>
          </table>
        </div>
        <div class="clearfix"></div>
      </div>
    </div>
  </div>
  <br />
</div>
<!-- /page content -->

<!-- Chart.js for Staff by Rank -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
  var ctx = document.getElementById('staffByRankChart').getContext('2d');
  var chart = new Chart(ctx, {
    type: 'bar',
    data: {
      labels: [<?php foreach($staffByRank as $row){ echo "'".addslashes($row->rankID)."',"; } ?>],
      datasets: [{
        label: 'Staff Count',
        data: [<?php foreach($staffByRank as $row){ echo (int)$row->total.","; } ?>],
        backgroundColor: 'rgba(54, 162, 235, 0.7)'
      }]
    },
    options: {
      responsive: true,
      plugins: {
        legend: { display: false }
      },
      scales: {
        y: { beginAtZero: true }
      }
    }
  });
});
</script>
<?php require_once $abs_us_root . $us_url_root . 'users/includes/html_footer.php'; ?>