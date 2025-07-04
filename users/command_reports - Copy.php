<?php
// filepath: usersc/modules/command_dashboard_reports.php

require_once '../users/init.php';
require_once $abs_us_root . $us_url_root . 'users/includes/template/prep.php';

if (!securePage($_SERVER['PHP_SELF'])) { die(); }

// Main totals
$totalUsers   = $db->query("SELECT COUNT(*) as total FROM Staff")->first()->total ?? '';
$totalOfficers = $db->query("SELECT COUNT(*) as total FROM Staff WHERE category = 'Officer'")->first()->total ?? '';
$totalNCOs     = $db->query("SELECT COUNT(*) as total FROM Staff WHERE category = 'Non-Commissioned Officer'")->first()->total ?? '';
$totalRecruits = $db->query("SELECT COUNT(*) as total FROM Staff WHERE category = 'Recruit'")->first()->total ?? '';
$totalCivilians = $db->query("SELECT COUNT(*) as total FROM Staff WHERE category = 'Civilian Employee'")->first()->total ?? '';
$totalMales   = $db->query("SELECT COUNT(*) as total FROM Staff WHERE gender = 'Male'")->first()->total ?? '';
$totalFemales = $db->query("SELECT COUNT(*) as total FROM Staff WHERE gender = 'Female'")->first()->total ?? '';
$totalUnits   = $db->query("SELECT COUNT(DISTINCT unitID) as total FROM Staff")->first()->total ?? '';
$totalCourses = $db->query("SELECT COUNT(DISTINCT courseName) as total FROM Courses")->first()->total ?? '';
$totalOps     = $db->query("SELECT COUNT(*) as total FROM Operations")->first()->total ?? '';

// Gender totals by category
$genderByCategory = $db->query("
    SELECT category, 
        SUM(gender = 'Male') as males, 
        SUM(gender = 'Female') as females, 
        COUNT(*) as total 
    FROM Staff 
    GROUP BY category
")->results();

// Staff by rank
$staffByRank = $db->query("SELECT rank, COUNT(*) as total FROM Staff GROUP BY rank ORDER BY total DESC")->results() ?? [];
// Staff per unit
$staffByUnit = $db->query("SELECT unitID, COUNT(*) as total FROM Staff GROUP BY unitID ORDER BY total DESC")->results() ?? [];
// Staff per province (assuming province column exists)
$staffByProvince = $db->query("SELECT province, COUNT(*) as total FROM Staff GROUP BY province ORDER BY total DESC")->results() ?? [];
// Staff by course
$staffByCourse = $db->query("SELECT c.courseName, COUNT(sc.svcNo) as total 
    FROM StaffCourses sc 
    JOIN Courses c ON sc.courseID = c.id 
    GROUP BY c.courseName ORDER BY total DESC")->results() ?? [];
// Operational reports (example: count by type)
$opsByType = $db->query("SELECT opType, COUNT(*) as total FROM Operations GROUP BY opType")->results();
?>

<div class="container mt-4 mb-4" style="background: #fff; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.07); padding: 32px;">
  <h2 class="mb-4" style="color:#355E3B;"><i class="fa fa-bar-chart"></i> Command Dashboard Reports</h2>
  <!-- Top Tiles -->
  <div class="row text-center mb-4">
    <div class="col-md-2 col-sm-4 col-xs-6 mb-3">
      <div class="p-3 border rounded bg-light">
        <span class="count_top"><i class="fa fa-users"></i> Total Staff</span>
        <div class="h3 my-2"><?= (int)$totalUsers ?></div>
        <span class="count_bottom text-success">100% Active</span>
      </div>
    </div>
    <div class="col-md-2 col-sm-4 col-xs-6 mb-3">
      <div class="p-3 border rounded bg-light">
        <span class="count_top"><i class="fa fa-user-secret"></i> Officers</span>
        <div class="h3 my-2"><?= (int)$totalOfficers ?? '' ?></div>
        <span class="count_bottom text-success"><?= $totalUsers > 0 ? round($totalOfficers/$totalUsers*100) : 0 ?>% of Staff</span>
      </div>
    </div>
    <div class="col-md-2 col-sm-4 col-xs-6 mb-3">
      <div class="p-3 border rounded bg-light">
        <span class="count_top"><i class="fa fa-users"></i> Non-Commissioned Officers</span>
        <div class="h3 my-2"><?= (int)$totalNCOs ?? '' ?></div>
        <span class="count_bottom text-info"><?= $totalUsers > 0 ? round($totalNCOs/$totalUsers*100) : 0 ?>% of Staff</span>
      </div>
    </div>
    <div class="col-md-2 col-sm-4 col-xs-6 mb-3">
      <div class="p-3 border rounded bg-light">
        <span class="count_top"><i class="fa fa-user-plus"></i> Recruits</span>
        <div class="h3 my-2"><?= (int)$totalRecruits ?? '' ?></div>
        <span class="count_bottom text-info"><?= $totalUsers > 0 ? round($totalRecruits/$totalUsers*100) : 0 ?>% of Staff</span>
      </div>
    </div>
    <div class="col-md-2 col-sm-4 col-xs-6 mb-3">
      <div class="p-3 border rounded bg-light">
        <span class="count_top"><i class="fa fa-briefcase"></i> Civilian Employees</span>
        <div class="h3 my-2"><?= (int)$totalCivilians ?></div>
        <span class="count_bottom text-info"><?= $totalUsers > 0 ? round($totalCivilians/$totalUsers*100) : 0 ?>% of Staff</span>
      </div>
    </div>
    <div class="col-md-2 col-sm-4 col-xs-6 mb-3">
      <div class="p-3 border rounded bg-light">
        <span class="count_top"><i class="fa fa-male"></i> Males</span>
        <div class="h3 my-2 text-success"><?= (int)$totalMales ?? '' ?></div>
        <span class="count_bottom text-success"><?= $totalUsers > 0 ? round($totalMales/$totalUsers*100) : 0 ?>% of Staff</span>
      </div>
    </div>
    <div class="col-md-2 col-sm-4 col-xs-6 mb-3">
      <div class="p-3 border rounded bg-light">
        <span class="count_top"><i class="fa fa-female"></i> Females</span>
        <div class="h3 my-2"><?= (int)$totalFemales ?? '' ?></div>
        <span class="count_bottom text-danger"><?= $totalUsers > 0 ? round($totalFemales/$totalUsers*100) : 0 ?>% of Staff</span>
      </div>
    </div>
    <div class="col-md-2 col-sm-4 col-xs-6 mb-3">
      <div class="p-3 border rounded bg-light">
        <span class="count_top"><i class="fa fa-building"></i> Units</span>
        <div class="h3 my-2"><?= (int)$totalUnits ?></div>
        <span class="count_bottom text-success">Active</span>
      </div>
    </div>
    <div class="col-md-2 col-sm-4 col-xs-6 mb-3">
      <div class="p-3 border rounded bg-light">
        <span class="count_top"><i class="fa fa-graduation-cap"></i> Courses</span>
        <div class="h3 my-2"><?= (int)$totalCourses ?></div>
        <span class="count_bottom text-success">All Time</span>
      </div>
    </div>
    <div class="col-md-2 col-sm-4 col-xs-6 mb-3">
      <div class="p-3 border rounded bg-light">
        <span class="count_top"><i class="fa fa-flag"></i> Operations</span>
        <div class="h3 my-2"><?= (int)$totalOps ?></div>
        <span class="count_bottom text-success">All Time</span>
      </div>
    </div>
  </div>
  <!-- /Top Tiles -->

  <!-- Gender Totals by Category -->
  <div class="row mb-4">
    <div class="col-12">
      <div class="card border-0 shadow-sm">
        <div class="card-header bg-secondary text-white"><h5 class="mb-0">Gender Totals by Category</h5></div>
        <div class="card-body">
          <table class="table table-sm table-bordered mt-2">
            <thead class="thead-light">
              <tr>
                <th>Category</th>
                <th>Males</th>
                <th>Females</th>
                <th>Total</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach($genderByCategory as $row): ?>
                <tr>
                  <td><?=htmlspecialchars($row->category)?></td>
                  <td><?= (int)$row->males ?></td>
                  <td><?= (int)$row->females ?></td>
                  <td><?= (int)$row->total ?></td>
                </tr>
              <?php endforeach;?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- Staff by Rank -->
  <div class="row mb-4">
    <div class="col-12">
      <div class="card border-0 shadow-sm">
        <div class="card-header bg-primary text-white"><h5 class="mb-0">Staff by Rank</h5></div>
        <div class="card-body">
          <table class="table table-sm table-bordered mt-2">
            <thead class="thead-light"><tr><th>Rank</th><th>Total</th></tr></thead>
            <tbody>
              <?php foreach($staffByRank as $row): ?>
                <tr>
                  <td><?=htmlspecialchars($row->rank)?></td>
                  <td><?=htmlspecialchars($row->total)?></td>
                </tr>
              <?php endforeach;?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- Menu -->
  <div class="row mb-4">
    <div class="col-12">
      <div class="btn-group">
        <a href="command/profiles.php" class="btn btn-primary"><i class="fa fa-user"></i> View Individual Profiles</a>
        <a href="command/reports.php" class="btn btn-info">Statistical Reports</a>
        <a href="#courses" class="btn btn-success">Course Reports</a>
        <a href="#operations" class="btn btn-warning">Operational Reports</a>
      </div>
    </div>
  </div>

  <!-- Statistical Reports -->
  <div class="row" id="statistical">
    <div class="col-md-6 mb-4">
      <div class="card border-0 shadow-sm">
        <div class="card-header bg-success text-white"><h5 class="mb-0">Staff Per Unit</h5></div>
        <div class="card-body">
          <canvas id="staffByUnitChart" height="120"></canvas>
          <table class="table table-sm table-bordered mt-2">
            <thead class="thead-light"><tr><th>Unit</th><th>Total</th></tr></thead>
            <tbody>
              <?php foreach($staffByUnit as $row): ?>
                <tr>
                  <td><?=htmlspecialchars($row->unitID ?? '')?></td>
                  <td><?=htmlspecialchars($row->total ?? '')?></td>
                </tr>
              <?php endforeach;?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
    <div class="col-md-6 mb-4">
      <div class="card border-0 shadow-sm">
        <div class="card-header bg-success text-white"><h5 class="mb-0">Staff Per Province</h5></div>
        <div class="card-body">
          <canvas id="staffByProvinceChart" height="120"></canvas>
          <table class="table table-sm table-bordered mt-2">
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
        </div>
      </div>
    </div>
  </div>

  <!-- Course Reports -->
  <div class="row" id="courses">
    <div class="col-md-12 mb-4">
      <div class="card border-0 shadow-sm">
        <div class="card-header bg-info text-white"><h5 class="mb-0">Staff by Course</h5></div>
        <div class="card-body">
          <canvas id="staffByCourseChart" height="120"></canvas>
          <table class="table table-sm table-bordered mt-2">
            <thead class="thead-light"><tr><th>Course</th><th>Staff Attended</th></tr></thead>
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
    <div class="col-md-12 mb-4">
      <div class="card border-0 shadow-sm">
        <div class="card-header bg-warning text-dark"><h5 class="mb-0">Operational Reports</h5></div>
        <div class="card-body">
          <canvas id="opsByTypeChart" height="120"></canvas>
          <table class="table table-sm table-bordered mt-2">
            <thead class="thead-light"><tr><th>Operation Type</th><th>Total</th></tr></thead>
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
<?php require_once $abs_us_root . $us_url_root . 'users/includes/html_footer.php'; ?>