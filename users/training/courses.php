<?php
  ini_set("allow_url_fopen", 1);
  require_once '../users/init.php';
  require_once $abs_us_root . $us_url_root . 'users/includes/template/prep.php';
  require_once __DIR__ . '/../includes/admin_init.php';

  // --- Handle Course/Institution Creation ---
  $errors = $successes = [];
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
      if (!Token::check($_POST['csrf'] ?? '')) {
          $errors[] = "Invalid CSRF token.";
      } elseif (isset($_POST['addCourse'])) {
          $name = trim($_POST['courseName'] ?? '');
          if ($name === '') $errors[] = "Course name required.";
          else {
              $db->insert('Courses', ['courseName' => $name]);
              $successes[] = "Course created.";
          }
      } elseif (isset($_POST['addInstitution'])) {
          $name = trim($_POST['institutionName'] ?? '');
          if ($name === '') $errors[] = "Institution name required.";
          else {
              $db->insert('Institutions', ['institutionName' => $name]);
              $successes[] = "Institution created.";
          }
      } elseif (isset($_POST['assignStaff'])) {
          $svcNo = trim($_POST['svcNo'] ?? '');
          $courseID = trim($_POST['courseID'] ?? '');
          if ($svcNo === '' || $courseID === '') $errors[] = "All fields required.";
          else {
              $db->insert('StaffCourses', ['svcNo' => $svcNo, 'courseID' => $courseID]);
              $successes[] = "Staff assigned to course.";
          }
      }
  }

  // --- Data for Reports ---
  $totalCourses = $db->query("SELECT COUNT(*) as total FROM Courses")->first()->total;
  $staffByCourse = $db->query("SELECT c.courseName, COUNT(sc.svcNo) as total 
      FROM StaffCourses sc 
      JOIN Courses c ON sc.courseID = c.id 
      GROUP BY c.courseName ORDER BY total DESC")->results();
  $courses = $db->query("SELECT * FROM Courses ORDER BY courseName")->results();
  $institutions = $db->query("SELECT * FROM Institutions ORDER BY institutionName")->results();
  $staffList = $db->query("SELECT svcNo, fName, sName FROM Staff ORDER BY fName, sName")->results();
?>
<!-- page content -->
<div class="right_col" role="main">
  <div class="row tile_count">
    <div class="col-md-3 col-sm-6 col-xs-12 tile_stats_count">
      <span class="count_top"><i class="fa fa-graduation-cap"></i> Total Courses</span>
      <div class="count"><?= (int)$totalCourses ?></div>
      <span class="count_bottom"><i class="green">All Time</i></span>
    </div>
  </div>

  <!-- Alerts -->
  <?php foreach($errors as $e): ?>
    <div class="alert alert-danger"><?=htmlspecialchars($e)?></div>
  <?php endforeach; ?>
  <?php foreach($successes as $s): ?>
    <div class="alert alert-success"><?=htmlspecialchars($s)?></div>
  <?php endforeach; ?>

  <!-- Actions -->
  <div class="row mb-3">
    <div class="col-md-4">
      <div class="x_panel">
        <div class="x_title"><h2>Create Course</h2></div>
        <div class="x_content">
          <form method="post">
            <input type="hidden" name="csrf" value="<?=Token::generate();?>">
            <div class="form-group">
              <input type="text" name="courseName" class="form-control" placeholder="Course Name" required>
            </div>
            <button type="submit" name="addCourse" class="btn btn-success btn-block">Create Course</button>
          </form>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="x_panel">
        <div class="x_title"><h2>Create Institution</h2></div>
        <div class="x_content">
          <form method="post">
            <input type="hidden" name="csrf" value="<?=Token::generate();?>">
            <div class="form-group">
              <input type="text" name="institutionName" class="form-control" placeholder="Institution Name" required>
            </div>
            <button type="submit" name="addInstitution" class="btn btn-primary btn-block">Create Institution</button>
          </form>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="x_panel">
        <div class="x_title"><h2>Assign Staff to Course</h2></div>
        <div class="x_content">
          <form method="post">
            <input type="hidden" name="csrf" value="<?=Token::generate();?>">
            <div class="form-group">
              <select name="svcNo" class="form-control" required>
                <option value="">Select Staff</option>
                <?php foreach($staffList as $s): ?>
                  <option value="<?=htmlspecialchars($s->svcNo)?>">
                    <?=htmlspecialchars($s->svcNo . ' - ' . $s->fName . ' ' . $s->sName)?>
                  </option>
                <?php endforeach;?>
              </select>
            </div>
            <div class="form-group">
              <select name="courseID" class="form-control" required>
                <option value="">Select Course</option>
                <?php foreach($courses as $c): ?>
                  <option value="<?=htmlspecialchars($c->id)?>"><?=htmlspecialchars($c->courseName)?></option>
                <?php endforeach;?>
              </select>
            </div>
            <button type="submit" name="assignStaff" class="btn btn-warning btn-block">Assign</button>
          </form>
        </div>
      </div>
    </div>
  </div>

  <!-- Reports -->
  <div class="row" id="reports">
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
</div>
<!-- /page content -->

<!-- Chart.js CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
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
});
</script>
<?php require_once $abs_us_root . $us_url_root . 'users/includes/html_footer.php'; ?>