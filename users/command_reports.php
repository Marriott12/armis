<?php
require_once '../users/init.php';
require_once $abs_us_root . $us_url_root . 'users/includes/template/prep.php';
if (!securePage($_SERVER['PHP_SELF'])) { die(); }
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
require_once 'command/partials/dashboard_data.php';

$filters = get_filters();
$data = getDashboardData($db, $filters);
$allCategories = $db->query("SELECT DISTINCT category FROM Staff")->results();
$allProvinces  = $db->query("SELECT DISTINCT province FROM Staff WHERE province IS NOT NULL AND province != ''")->results();
$allUnits      = $db->query("SELECT DISTINCT unitID FROM Staff WHERE unitID IS NOT NULL AND unitID != ''")->results();
?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
<style>
  .dashboard-tile { transition: box-shadow .2s, transform .2s; background: linear-gradient(120deg,#f0f4f9 0,#f8f9fa 100%);}
  .dashboard-tile:hover { box-shadow: 0 4px 24px rgba(53,94,59,.15); transform: translateY(-4px) scale(1.02);}
  .scroll-to-top { display: none; position: fixed; bottom: 24px; right: 32px; z-index: 9999; }
  @media(max-width: 767px){ .dashboard-tile{margin-bottom:12px;} }
</style>
<div class="container my-5 px-3" style="background: #fff; border-radius: 10px; box-shadow: 0 2px 12px rgba(0,0,0,.07);">
  <h2 class="mb-4 pt-4" style="color:#355E3B;"><i class="fa fa-bar-chart"></i> Command Dashboard Reports</h2>
  <?php include 'command/partials/filters.php'; ?>
  <?php include 'command/partials/tiles.php'; ?>
  <?php include 'command/partials/menu.php'; ?>
  <div id="dashboardCharts">
    <?php include 'command/partials/chart_gender.php'; ?>
    <?php include 'command/partials/chart_rank.php'; ?>
    <div class="row mb-4">
      <div class="col-lg-6 mb-4"><?php include 'command/partials/chart_unit.php'; ?></div>
      <div class="col-lg-6 mb-4"><?php include 'command/partials/chart_province.php'; ?></div>
    </div>
    <?php include 'command/partials/chart_course.php'; ?>
    <?php include 'command/partials/chart_ops.php'; ?>
    <?php include 'command/partials/chart_forecast.php'; ?>
  </div>
</div>
<?php include 'command/partials/modal_drilldown.php'; ?>
<button class="btn btn-secondary scroll-to-top" id="scrollBtn" title="Scroll to top"><i class="fa fa-arrow-up"></i></button>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
<script src="command/partials/dashboard.js.php"></script>
<?php require_once $abs_us_root . $us_url_root . 'users/includes/html_footer.php'; ?>