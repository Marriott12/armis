<?php
require_once '../auth.php';
require_once 'db.php';
requireAdmin();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");

// Check if dashboard_data.php exists and include it
if (file_exists('command/partials/dashboard_data.php')) {
    require_once 'command/partials/dashboard_data.php';
    $filters = get_filters();
    $data = getDashboardData($pdo, $filters);
} else {
    $filters = [];
    $data = [];
}

// Get categories using PDO with error handling
try {
    $stmt = $pdo->query("SELECT DISTINCT category FROM Staff");
    $allCategories = $stmt->fetchAll();
} catch (PDOException $e) {
    $allCategories = [];
    error_log("Database error in command_reports.php: " . $e->getMessage());
}

try {
    $stmt = $pdo->query("SELECT DISTINCT province FROM Staff WHERE province IS NOT NULL AND province != ''");
    $allProvinces = $stmt->fetchAll();
} catch (PDOException $e) {
    $allProvinces = [];
}

try {
    $stmt = $pdo->query("SELECT DISTINCT unitID FROM Staff WHERE unitID IS NOT NULL AND unitID != ''");
    $allUnits = $stmt->fetchAll();
} catch (PDOException $e) {
    $allUnits = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ARMIS - Command Reports</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
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
<style>
  .dashboard-tile { transition: box-shadow .2s, transform .2s; background: linear-gradient(120deg,#f0f4f9 0,#f8f9fa 100%);}
  .dashboard-tile:hover { box-shadow: 0 4px 24px rgba(53,94,59,.15); transform: translateY(-4px) scale(1.02);}
  .scroll-to-top { display: none; position: fixed; bottom: 24px; right: 32px; z-index: 9999; }
  @media(max-width: 767px){ .dashboard-tile{margin-bottom:12px;} }
</style>
<div class="container my-5 px-3" style="background: #fff; border-radius: 10px; box-shadow: 0 2px 12px rgba(0,0,0,.07);">
  <h2 class="mb-4 pt-4" style="color:var(--primary);"><i class="fa fa-bar-chart"></i> Command Dashboard Reports</h2>
  <?php 
  if (file_exists('command/partials/filters.php')) include 'command/partials/filters.php'; 
  if (file_exists('command/partials/tiles.php')) include 'command/partials/tiles.php'; 
  if (file_exists('command/partials/menu.php')) include 'command/partials/menu.php'; 
  ?>
  <div id="dashboardCharts">
    <?php 
    if (file_exists('command/partials/chart_gender.php')) include 'command/partials/chart_gender.php'; 
    if (file_exists('command/partials/chart_rank.php')) include 'command/partials/chart_rank.php'; 
    ?>
    <div class="row mb-4">
      <div class="col-lg-6 mb-4"><?php if (file_exists('command/partials/chart_unit.php')) include 'command/partials/chart_unit.php'; ?></div>
      <div class="col-lg-6 mb-4"><?php if (file_exists('command/partials/chart_province.php')) include 'command/partials/chart_province.php'; ?></div>
    </div>
    <?php 
    if (file_exists('command/partials/chart_course.php')) include 'command/partials/chart_course.php'; 
    if (file_exists('command/partials/chart_ops.php')) include 'command/partials/chart_ops.php'; 
    if (file_exists('command/partials/chart_forecast.php')) include 'command/partials/chart_forecast.php'; 
    ?>
  </div>
</div>
<?php if (file_exists('command/partials/modal_drilldown.php')) include 'command/partials/modal_drilldown.php'; ?>
<button class="btn btn-secondary scroll-to-top" id="scrollBtn" title="Scroll to top"><i class="fa fa-arrow-up"></i></button>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
<?php if (file_exists('command/partials/dashboard.js.php')) include 'command/partials/dashboard.js.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php require_once $abs_us_root . $us_url_root . 'users/includes/html_footer.php'; ?>