<?php
// Only include this file from the Command dashboard context

// Stats
$totalUsers = $db->query("SELECT COUNT(*) as total FROM Staff")->first()->total;
$totalMales = $db->query("SELECT COUNT(*) as total FROM Staff WHERE gender = 'Male'")->first()->total;
$totalFemales = $db->query("SELECT COUNT(*) as total FROM Staff WHERE gender = 'Female'")->first()->total;
$totalUnits = $db->query("SELECT COUNT(DISTINCT unitID) as total FROM Staff")->first()->total;
$totalCourses = $db->query("SELECT COUNT(DISTINCT courseName) as total FROM Courses")->first()->total;
$totalOps = $db->query("SELECT COUNT(*) as total FROM Operations")->first()->total;

// Staff per unit
$staffByUnit = $db->query("SELECT unitID, COUNT(*) as total FROM Staff GROUP BY unitID ORDER BY total DESC")->results();
// Staff per province (assuming province column exists)
$staffByProvince = $db->query("SELECT province, COUNT(*) as total FROM Staff GROUP BY province ORDER BY total DESC")->results();
// Staff by course
$staffByCourse = $db->query("SELECT c.courseName, COUNT(sc.svcNo) as total 
    FROM StaffCourses sc 
    JOIN Courses c ON sc.courseID = c.id 
    GROUP BY c.courseName ORDER BY total DESC")->results();
// Operational reports (example: count by type)
$opsByType = $db->query("SELECT opType, COUNT(*) as total FROM Operations GROUP BY opType")->results();
?>

<!-- page content -->
<div class="right_col" role="main">
  <!-- Top Tiles -->
  <div class="row tile_count">
    <div class="col-md-2 col-sm-4 col-xs-6 tile_stats_count">
      <span class="count_top"><i class="fa fa-users"></i> Total Staff</span>
      <div class="count"><?= (int)$totalUsers ?></div>
      <span class="count_bottom"><i class="green">100% </i> Active</span>
    </div>
    <div class="col-md-2 col-sm-4 col-xs-6 tile_stats_count">
      <span class="count_top"><i class="fa fa-male"></i> Total Males</span>
      <div class="count green"><?= (int)$totalMales ?></div>
      <span class="count_bottom"><i class="green"><?= $totalUsers > 0 ? round($totalMales/$totalUsers*100) : 0 ?>% </i> of Staff</span>
    </div>
    <div class="col-md-2 col-sm-4 col-xs-6 tile_stats_count">
      <span class="count_top"><i class="fa fa-female"></i> Total Females</span>
      <div class="count"><?= (int)$totalFemales ?></div>
      <span class="count_bottom"><i class="red"><?= $totalUsers > 0 ? round($totalFemales/$totalUsers*100) : 0 ?>% </i> of Staff</span>
    </div>
    <div class="col-md-2 col-sm-4 col-xs-6 tile_stats_count">
      <span class="count_top"><i class="fa fa-building"></i> Total Units</span>
      <div class="count"><?= (int)$totalUnits ?></div>
      <span class="count_bottom"><i class="green">Active</i></span>
    </div>
    <div class="col-md-2 col-sm-4 col-xs-6 tile_stats_count">
      <span class="count_top"><i class="fa fa-graduation-cap"></i> Courses Offered</span>
      <div class="count"><?= (int)$totalCourses ?></div>
      <span class="count_bottom"><i class="green">All Time</i></span>
    </div>
    <div class="col-md-2 col-sm-4 col-xs-6 tile_stats_count">
      <span class="count_top"><i class="fa fa-flag"></i> Operations</span>
      <div class="count"><?= (int)$totalOps ?></div>
      <span class="count_bottom"><i class="green">All Time</i></span>
    </div>
  </div>
  <!-- /Top Tiles -->

  <!-- Menu -->
  <div class="row mb-3">
    <div class="col-12">
      <div class="btn-group">
        <a href="command_profiles.php" class="btn btn-primary"><i class="fa fa-user"></i> View Individual Profiles</a>
        <a href="#statistical" class="btn btn-info">Statistical Reports</a>
        <a href="#courses" class="btn btn-success">Course Reports</a>
        <a href="#operations" class="btn btn-warning">Operational Reports</a>
      </div>
    </div>
  </div>

  <!-- Statistical Reports -->
  <div class="row" id="statistical">
    <div class="col-md-6">
      <div class="x_panel">
        <div class="x_title"><h2>Staff Per Unit</h2></div>
        <div class="x_content">
          <canvas id="staffByUnitChart" height="120"></canvas>
          <table class="table table-sm table-bordered mt-2">
            <thead><tr><th>Unit</th><th>Total</th></tr></thead>
            <tbody>
              <?php foreach($staffByUnit as $row): ?>
                <tr>
                  <td><?=htmlspecialchars($row->unitID)?></td>
                  <td><?=htmlspecialchars($row->total)?></td>
                </tr>
              <?php endforeach;?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
    <div class="col-md-6">
      <div class="x_panel">
        <div class="x_title"><h2>Staff Per Province</h2></div>
        <div class="x_content">
          <canvas id="staffByProvinceChart" height="120"></canvas>
          <table class="table table-sm table-bordered mt-2">
            <thead><tr><th>Province</th><th>Total</th></tr></thead>
            <tbody>
              <?php foreach($staffByProvince as $row): ?>
                <tr>
                  <td><?=htmlspecialchars($row->province)?></td>
                  <td><?=htmlspecialchars($row->total)?></td>
                </tr>
              <?php endforeach;?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- Course Reports -->
  <div class="row" id="courses">
    <div class="col-md-12">
      <div class="x_panel">
        <div class="x_title"><h2>Staff by Course</h2></div>
        <div class="x_content">
          <canvas id="staffByCourseChart" height="120"></canvas>
          <table class="table table-sm table-bordered mt-2">
            <thead><tr><th>Course</th><th>Staff Attended</th></tr></thead>
            <tbody>
              <?php foreach($staffByCourse as $row): ?>
                <tr>
                  <td><?=htmlspecialchars($row->courseName)?></td>
                  <td><?=htmlspecialchars($row->total)?></td>
                </tr>
              <?php endforeach;?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- Operational Reports -->
  <div class="row" id="operations">
    <div class="col-md-12">
      <div class="x_panel">
        <div class="x_title"><h2>Operational Reports</h2></div>
        <div class="x_content">
          <canvas id="opsByTypeChart" height="120"></canvas>
          <table class="table table-sm table-bordered mt-2">
            <thead><tr><th>Operation Type</th><th>Total</th></tr></thead>
            <tbody>
              <?php foreach($opsByType as $row): ?>
                <tr>
                  <td><?=htmlspecialchars($row->opType)?></td>
                  <td><?=htmlspecialchars($row->total)?></td>
                </tr>
              <?php endforeach;?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
<!-- /page content -->

<!-- Chart.js CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
  // Staff by Unit
  var ctxUnit = document.getElementById('staffByUnitChart').getContext('2d');
  new Chart(ctxUnit, {
    type: 'bar',
    data: {
      labels: [<?php foreach($staffByUnit as $row){ echo "'".addslashes($row->unitID)."',"; } ?>],
      datasets: [{
        label: 'Staff',
        data: [<?php foreach($staffByUnit as $row){ echo (int)$row->total.","; } ?>],
        backgroundColor: 'rgba(54, 162, 235, 0.7)'
      }]
    },
    options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
  });

  // Staff by Province
  var ctxProv = document.getElementById('staffByProvinceChart').getContext('2d');
  new Chart(ctxProv, {
    type: 'bar',
    data: {
      labels: [<?php foreach($staffByProvince as $row){ echo "'".addslashes($row->province)."',"; } ?>],
      datasets: [{
        label: 'Staff',
        data: [<?php foreach($staffByProvince as $row){ echo (int)$row->total.","; } ?>],
        backgroundColor: 'rgba(255, 99, 132, 0.7)'
      }]
    },
    options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
  });

  // Staff by Course
  var ctxCourse = document.getElementById('staffByCourseChart').getContext('2d');
  new Chart(ctxCourse, {
    type: 'bar',
    data: {
      labels: [<?php foreach($staffByCourse as $row){ echo "'".addslashes($row->courseName)."',"; } ?>],
      datasets: [{
        label: 'Staff Attended',
        data: [<?php foreach($staffByCourse as $row){ echo (int)$row->total.","; } ?>],
        backgroundColor: 'rgba(75, 192, 192, 0.7)'
      }]
    },
    options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
  });

  // Operations by Type
  var ctxOps = document.getElementById('opsByTypeChart').getContext('2d');
  new Chart(ctxOps, {
    type: 'bar',
    data: {
      labels: [<?php foreach($opsByType as $row){ echo "'".addslashes($row->opType)."',"; } ?>],
      datasets: [{
        label: 'Operations',
        data: [<?php foreach($opsByType as $row){ echo (int)$row->total.","; } ?>],
        backgroundColor: 'rgba(255, 206, 86, 0.7)'
      }]
    },
    options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
  });
});
</script>