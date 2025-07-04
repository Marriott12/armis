<?php
    require_once '../users/init.php';
    require_once $abs_us_root . $us_url_root . 'users/includes/template/prep.php';
    if (!securePage($_SERVER['PHP_SELF'])) { die(); }
    $userId = $user->data()->id;
    $userdetails = $user->data();
    function calculateAge($dob) {
    if (!$dob) return 'N/A';
    $dobDate = new DateTime($dob);
    $now = new DateTime();
    return $now->diff($dobDate)->y;
}

// Fetch Courses
$courses = $db->query("
    SELECT sc.cseStart, sc.cseEnd, c.cseID, i.instID, i.instLoc, sc.result
    FROM Staff_Course sc
    LEFT JOIN Course c ON sc.cseID = c.cseID
    LEFT JOIN Institution i ON sc.instID = i.instID
    WHERE sc.svcNo = ?
    ORDER BY sc.cseStart DESC
", [$userdetails->svcNo])->results();

// Fetch Operations
$operations = $db->query("
    SELECT so.opStart, so.opEnd, o.opID, o.opType, so.opLoc
    FROM Staff_Operation so
    LEFT JOIN Operation o ON so.opID = o.opID
    WHERE so.svcNo = ?
    ORDER BY so.opStart DESC
", [$userdetails->svcNo])->results();

// Fetch Medals
$medals = $db->query("
    SELECT sm.issueDate, m.medID, m.medDesc, sm.auth, sm.comment
    FROM Staff_Medal sm
    LEFT JOIN Medal m ON sm.medID = m.medID
    WHERE sm.svcNo = ?
    ORDER BY sm.issueDate DESC
", [$userdetails->svcNo])->results();
?>
<!DOCTYPE html>
<html>
<head>
  <title>My CV</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <style>
    @media print { .no-print { display: none; } }
    .section-title { border-bottom: 2px solid #333; margin-top: 2rem; margin-bottom: 1rem; }
    .cv-table th, .cv-table td { vertical-align: middle !important; }
  </style>
</head>
<body>
<div class="container my-4">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Curriculum Vitae</h2>
    <button class="btn btn-secondary no-print" onclick="window.print()">Print</button>
  </div>
  <h4><?= htmlspecialchars($userdetails->rank . ' ' . $userdetails->fname . ' ' . $userdetails->lname); ?></h4>
  <dl class="row">
    <dt class="col-sm-3">Service Number</dt>
    <dd class="col-sm-9"><?= htmlspecialchars($userdetails->svcNo); ?></dd>
    <dt class="col-sm-3">Gender</dt>
    <dd class="col-sm-9"><?= htmlspecialchars($userdetails->gender); ?></dd>
    <dt class="col-sm-3">Date of Birth / Age</dt>
    <dd class="col-sm-9"><?= htmlspecialchars($userdetails->dob); ?> (Age: <?= calculateAge($userdetails->dob); ?>)</dd>
    <dt class="col-sm-3">Unit</dt>
    <dd class="col-sm-9"><?= htmlspecialchars($userdetails->unit); ?></dd>
    <dt class="col-sm-3">Category</dt>
    <dd class="col-sm-9"><?= htmlspecialchars($userdetails->category); ?></dd>
    <dt class="col-sm-3">Email</dt>
    <dd class="col-sm-9"><?= htmlspecialchars($userdetails->email); ?></dd>
    <dt class="col-sm-3">Phone</dt>
    <dd class="col-sm-9"><?= htmlspecialchars($userdetails->tel); ?></dd>
    <dt class="col-sm-3">Province</dt>
    <dd class="col-sm-9"><?= htmlspecialchars($userdetails->province); ?></dd>
    <dt class="col-sm-3">Blood Group</dt>
    <dd class="col-sm-9"><?= htmlspecialchars($userdetails->bloodGp); ?></dd>
    <dt class="col-sm-3">Date of Enlistment</dt>
    <dd class="col-sm-9"><?= htmlspecialchars($userdetails->attestDate); ?></dd>
    <dt class="col-sm-3">Intake</dt>
    <dd class="col-sm-9"><?= htmlspecialchars($userdetails->intake); ?></dd>
    <!-- Add more fields as needed -->
  </dl>

  <!-- Courses Section -->
  <h5 class="section-title">Courses Attended</h5>
  <?php if(count($courses)): ?>
  <div class="table-responsive">
    <table class="table table-bordered cv-table">
      <thead class="table-light">
        <tr>
          <th>Course</th>
          <th>Institution</th>
          <th>Location</th>
          <th>Start Date</th>
          <th>End Date</th>
          <th>Result</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($courses as $c): ?>
        <tr>
          <td><?= htmlspecialchars($c->cseID); ?></td>
          <td><?= htmlspecialchars($c->instID); ?></td>
          <td><?= htmlspecialchars($c->instLoc); ?></td>
          <td><?= htmlspecialchars($c->cseStart); ?></td>
          <td><?= htmlspecialchars($c->cseEnd); ?></td>
          <td><?= htmlspecialchars($c->result); ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php else: ?>
    <p class="text-muted">No courses recorded.</p>
  <?php endif; ?>

  <!-- Operations Section -->
  <h5 class="section-title">Operations Participated</h5>
  <?php if(count($operations)): ?>
  <div class="table-responsive">
    <table class="table table-bordered cv-table">
      <thead class="table-light">
        <tr>
          <th>Operation</th>
          <th>Type</th>
          <th>Location</th>
          <th>Start Date</th>
          <th>End Date</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($operations as $op): ?>
        <tr>
          <td><?= htmlspecialchars($op->opID); ?></td>
          <td><?= htmlspecialchars($op->opType); ?></td>
          <td><?= htmlspecialchars($op->opLoc); ?></td>
          <td><?= htmlspecialchars($op->opStart); ?></td>
          <td><?= htmlspecialchars($op->opEnd); ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php else: ?>
    <p class="text-muted">No operations recorded.</p>
  <?php endif; ?>

  <!-- Medals Section -->
  <h5 class="section-title">Medals Awarded</h5>
  <?php if(count($medals)): ?>
  <div class="table-responsive">
    <table class="table table-bordered cv-table">
      <thead class="table-light">
        <tr>
          <th>Medal</th>
          <th>Description</th>
          <th>Date Awarded</th>
          <th>Authority</th>
          <th>Comment</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($medals as $m): ?>
        <tr>
          <td><?= htmlspecialchars($m->medID); ?></td>
          <td><?= htmlspecialchars($m->medDesc); ?></td>
          <td><?= htmlspecialchars($m->issueDate); ?></td>
          <td><?= htmlspecialchars($m->auth); ?></td>
          <td><?= htmlspecialchars($m->comment); ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php else: ?>
    <p class="text-muted">No medals recorded.</p>
  <?php endif; ?>

</div>
<?php require_once $abs_us_root . $us_url_root . 'users/includes/html_footer.php'; ?>