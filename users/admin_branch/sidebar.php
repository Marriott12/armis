<?php
// Sidebar for analytics navigation. Include with: include 'sidebar.php';
?>
<aside id="dashboardSidebar" class="bg-light border-end" style="min-width:220px;max-width:270px;height:100vh;position:fixed;left:0;top:0;z-index:1040;display:flex;flex-direction:column;">
    <div class="p-3 border-bottom bg-info text-white">
        <h5 class="mb-0"><i class="fa fa-chart-bar"></i> Analytics</h5>
    </div>
    <nav class="nav flex-column p-2" aria-label="Sidebar navigation">
        <a class="nav-link" href="#overviewPanel"><i class="fa fa-home"></i> Overview</a>
        <a class="nav-link" href="#timeseriesPanel"><i class="fa fa-chart-line"></i> Trends (Time Series)</a>
        <a class="nav-link" href="#unitChart"><i class="fa fa-users"></i> By Unit</a>
        <a class="nav-link" href="#rankChart"><i class="fa fa-user-graduate"></i> By Rank</a>
        <a class="nav-link" href="#courseChart"><i class="fa fa-list"></i> By Course</a>
        <a class="nav-link" href="#courseTable"><i class="fa fa-table"></i> Data Table</a>
        <a class="nav-link" href="#dataQualityPanel"><i class="fa fa-exclamation-triangle"></i> Data Quality</a>
        <a class="nav-link" href="#exportPanel"><i class="fa fa-download"></i> Export & Save</a>
        <hr />
        <a class="nav-link" href="#" id="sidebarToggle"><i class="fa fa-chevron-left"></i> Hide Sidebar</a>
    </nav>
</aside>
<script>
document.getElementById("sidebarToggle").onclick = function(e) {
    e.preventDefault();
    document.getElementById('dashboardSidebar').style.display = 'none';
    document.body.style.paddingLeft = '0';
    document.getElementById('sidebarShowBtn').style.display = 'block';
};
if (!document.getElementById('sidebarShowBtn')) {
    var btn = document.createElement('button');
    btn.textContent = '☰';
    btn.id = 'sidebarShowBtn';
    btn.style.position = 'fixed';
    btn.style.left = '8px';
    btn.style.top = '8px';
    btn.style.zIndex = 1050;
    btn.style.display = 'none';
    btn.className = 'btn btn-info btn-sm';
    btn.onclick = function() {
        document.getElementById('dashboardSidebar').style.display = 'flex';
        document.body.style.paddingLeft = '240px';
        btn.style.display = 'none';
    };
    document.body.appendChild(btn);
}
document.body.style.paddingLeft = '240px';
</script>