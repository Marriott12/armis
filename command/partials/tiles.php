<div class="row text-center mb-4 g-3" id="kpiTiles">
  <?php $k = $data['kpis']; ?>
  <div class="col-lg-2 col-md-3 col-sm-4 col-6">
    <div class="p-3 border rounded dashboard-tile"><span class="count_top"><i class="fa fa-users"></i> Total Staff</span><div class="h3 my-2" id="kpi-totalUsers"><?= (int)$k['totalUsers'] ?></div><span class="count_bottom text-success">100% Active</span></div>
  </div>
  <div class="col-lg-2 col-md-3 col-sm-4 col-6">
    <div class="p-3 border rounded dashboard-tile"><span class="count_top"><i class="fa fa-user-secret"></i> Officers</span><div class="h3 my-2" id="kpi-totalOfficers"><?= (int)$k['totalOfficers'] ?></div><span class="count_bottom text-success"><?= $k['totalUsers'] > 0 ? round($k['totalOfficers']/$k['totalUsers']*100) : 0 ?>% of Staff</span></div>
  </div>
  <div class="col-lg-2 col-md-3 col-sm-4 col-6">
    <div class="p-3 border rounded dashboard-tile"><span class="count_top"><i class="fa fa-users"></i> Non-Commissioned</span><div class="h3 my-2" id="kpi-totalNCOs"><?= (int)$k['totalNCOs'] ?></div><span class="count_bottom text-info"><?= $k['totalUsers'] > 0 ? round($k['totalNCOs']/$k['totalUsers']*100) : 0 ?>% of Staff</span></div>
  </div>
  <div class="col-lg-2 col-md-3 col-sm-4 col-6">
    <div class="p-3 border rounded dashboard-tile"><span class="count_top"><i class="fa fa-user-plus"></i> Recruits</span><div class="h3 my-2" id="kpi-totalRecruits"><?= (int)$k['totalRecruits'] ?></div><span class="count_bottom text-info"><?= $k['totalUsers'] > 0 ? round($k['totalRecruits']/$k['totalUsers']*100) : 0 ?>% of Staff</span></div>
  </div>
  <div class="col-lg-2 col-md-3 col-sm-4 col-6">
    <div class="p-3 border rounded dashboard-tile"><span class="count_top"><i class="fa fa-briefcase"></i> Civilians</span><div class="h3 my-2" id="kpi-totalCivilians"><?= (int)$k['totalCivilians'] ?></div><span class="count_bottom text-info"><?= $k['totalUsers'] > 0 ? round($k['totalCivilians']/$k['totalUsers']*100) : 0 ?>% of Staff</span></div>
  </div>
  <div class="col-lg-2 col-md-3 col-sm-4 col-6">
    <div class="p-3 border rounded dashboard-tile"><span class="count_top"><i class="fa fa-male"></i> Males</span><div class="h3 my-2 text-success" id="kpi-totalMales"><?= (int)$k['totalMales'] ?></div><span class="count_bottom text-success"><?= $k['totalUsers'] > 0 ? round($k['totalMales']/$k['totalUsers']*100) : 0 ?>% of Staff</span></div>
  </div>
  <div class="col-lg-2 col-md-3 col-sm-4 col-6">
    <div class="p-3 border rounded dashboard-tile"><span class="count_top"><i class="fa fa-female"></i> Females</span><div class="h3 my-2" id="kpi-totalFemales"><?= (int)$k['totalFemales'] ?></div><span class="count_bottom text-danger"><?= $k['totalUsers'] > 0 ? round($k['totalFemales']/$k['totalUsers']*100) : 0 ?>% of Staff</span></div>
  </div>
  <div class="col-lg-2 col-md-3 col-sm-4 col-6">
    <div class="p-3 border rounded dashboard-tile"><span class="count_top"><i class="fa fa-building"></i> Units</span><div class="h3 my-2" id="kpi-totalUnits"><?= (int)$k['totalUnits'] ?></div><span class="count_bottom text-success">Active</span></div>
  </div>
  <div class="col-lg-2 col-md-3 col-sm-4 col-6">
    <div class="p-3 border rounded dashboard-tile"><span class="count_top"><i class="fa fa-graduation-cap"></i> Courses</span><div class="h3 my-2" id="kpi-totalCourses"><?= (int)$k['totalCourses'] ?></div><span class="count_bottom text-success">All Time</span></div>
  </div>
  <div class="col-lg-2 col-md-3 col-sm-4 col-6">
    <div class="p-3 border rounded dashboard-tile"><span class="count_top"><i class="fa fa-flag"></i> Operations</span><div class="h3 my-2" id="kpi-totalOps"><?= (int)$k['totalOps'] ?></div><span class="count_bottom text-success">All Time</span></div>
  </div>
  <div class="col-lg-2 col-md-3 col-sm-4 col-6">
    <div class="p-3 border rounded dashboard-tile"><span class="count_top"><i class="fa fa-users"></i> Corps</span><div class="h3 my-2" id="kpi-totalCorps"><?= (int)$k['totalCorps'] ?></div><span class="count_bottom text-info"><?= $k['totalCorps'] ?> Distinct Corps</span></div>
  </div>
</div>