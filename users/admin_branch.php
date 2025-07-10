<?php
// filepath: users/admin_branch.php

require_once '../auth.php';
requireAdmin();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ARMIS - Admin Branch Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="css/armis_custom.css">
    <style>
        :root {
            --primary: #355E3B;
            --yellow: #f1c40f;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg armis-navbar">
        <div class="container">
            <a class="navbar-brand armis-brand" href="../">ARMIS</a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="admin.php">Dashboard</a>
                <a class="nav-link" href="../logout.php">Logout</a>
            </div>
        </div>
    </nav>

<div class="container mt-4 mb-4" style="background: #fff; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.07); padding: 32px;">
  <h2 class="mb-4" style="color:var(--primary);"><i class="fa fa-cogs"></i> Admin Branch Dashboard</h2>

  <!-- Quick Actions -->
  <div class="row mb-4">
    <div class="col-md-3 col-sm-6 mb-3">
      <a href="admin_branch/create_staff.php" class="btn btn-success w-100 py-3"><i class="fa fa-user-plus"></i> Create Staff Member</a>
    </div>
    <div class="col-md-3 col-sm-6 mb-3">
      <a href="admin_branch/edit_staff.php" class="btn btn-primary w-100 py-3"><i class="fa fa-edit"></i> Edit Staff Details</a>
    </div>
    <div class="col-md-3 col-sm-6 mb-3">
      <a href="admin_branch/delete_staff.php" class="btn btn-danger w-100 py-3"><i class="fa fa-trash"></i> Delete Staff Member</a>
    </div>
    <div class="col-md-3 col-sm-6 mb-3">
      <a href="admin_branch/promote_staff.php" class="btn btn-warning w-100 py-3"><i class="fa fa-arrow-up"></i> Promote Staff</a>
    </div>
  </div>

  <!-- Staff Management -->
  <div class="row mb-4">
    <div class="col-md-3 col-sm-6 mb-3">
      <a href="admin_branch/medals.php" class="btn btn-outline-info w-100 py-3"><i class="fa fa-medal"></i> Create Medals</a>
    </div>
    <div class="col-md-3 col-sm-6 mb-3">
      <a href="admin_branch/assign_medal.php" class="btn btn-outline-success w-100 py-3"><i class="fa fa-award"></i> Assign Medals</a>
    </div>
    <div class="col-md-3 col-sm-6 mb-3">
      <a href="admin_branch/appointments.php" class="btn btn-outline-dark w-100 py-3"><i class="fa fa-briefcase"></i> Appointments</a>
    </div>
  </div>

  <hr class="my-4" style="border-top: 3px solid var(--primary); opacity: 1;">

<!-- Reports Section -->
<div class="row mb-4">
  <div class="col-12 mb-3">
    <input type="text" id="reportSearch" class="form-control" placeholder="Type to filter reports...">
  </div>
  <div id="reportsLinks" class="row">
    <div class="col-md-2 col-sm-4 mb-3">
      <a href="admin_branch/reports_seniority.php" class="btn btn-light w-100 py-3"><i class="fa fa-list-ol"></i> Seniority Roll</a>
    </div>
    <div class="col-md-2 col-sm-4 mb-3">
      <a href="admin_branch/reports_units.php" class="btn btn-light w-100 py-3"><i class="fa fa-building"></i> Unit List</a>
    </div>
    <div class="col-md-2 col-sm-4 mb-3">
      <a href="admin_branch/reports_rank.php" class="btn btn-light w-100 py-3"><i class="fa fa-medal"></i> List by Ranks</a>
    </div>
    <div class="col-md-2 col-sm-4 mb-3">
      <a href="admin_branch/reports_corps.php" class="btn btn-light w-100 py-3"><i class="fa fa-shield-alt"></i> List by Corps</a>
    </div>
    <div class="col-md-2 col-sm-4 mb-3">
      <a href="admin_branch/reports_gender.php" class="btn btn-light w-100 py-3"><i class="fa fa-venus-mars"></i> List by Gender</a>
    </div>
    <div class="col-md-2 col-sm-4 mb-3">
      <a href="admin_branch/reports_appointment.php" class="btn btn-light w-100 py-3"><i class="fa fa-briefcase"></i> List by Appointment</a>
    </div>
    <div class="col-md-2 col-sm-4 mb-3">
      <a href="admin_branch/reports_courses.php" class="btn btn-light w-100 py-3"><i class="fa fa-graduation-cap"></i> List by Courses Done</a>
    </div>
    <div class="col-md-2 col-sm-4 mb-3">
      <a href="admin_branch/reports_retired.php" class="btn btn-light w-100 py-3"><i class="fa fa-user-times"></i> Retired Staff</a>
    </div>
    <div class="col-md-2 col-sm-4 mb-3">
      <a href="admin_branch/reports_contract.php" class="btn btn-light w-100 py-3"><i class="fa fa-file-contract"></i> Contract Staff</a>
    </div>
    <div class="col-md-2 col-sm-4 mb-3">
      <a href="admin_branch/reports_deceased.php" class="btn btn-light w-100 py-3"><i class="fa fa-user-slash"></i> Deceased Staff</a>
    </div>
    <div class="col-md-2 col-sm-4 mb-3">
      <a href="admin_branch/reports_marital.php" class="btn btn-light w-100 py-3"><i class="fa fa-ring"></i> List by Marital Status</a>
    </div>
    <div class="col-md-2 col-sm-4 mb-3">
      <a href="admin_branch/reports_trade.php" class="btn btn-light w-100 py-3"><i class="fa fa-tools"></i> List by Trade</a>
    </div>
  </div>
</div>

  <!-- Info Section -->
  <div class="row mt-4">
    <div class="col-12">
      <div class="alert alert-info">The options above help to manage all aspects of staff administration, including creation, editing, promotion, postings, medals, and comprehensive reporting.
      </div>
    </div>
  </div>
</div>


<script>
document.getElementById('reportSearch').addEventListener('keyup', function() {
  const query = this.value.toLowerCase();
  document.querySelectorAll('#reportsLinks .col-md-2, #reportsLinks .col-sm-4').forEach(function(col) {
    const text = col.textContent.toLowerCase();
    col.style.display = text.includes(query) ? '' : 'none';
  });
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php require_once $abs_us_root . $us_url_root . 'users/includes/html_footer.php'; ?>