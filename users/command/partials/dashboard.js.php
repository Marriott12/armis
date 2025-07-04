<?php header('Content-Type: application/javascript'); ?>
const chartColors = [
  '#355E3B','#3E9D8F','#FF8A5C','#F9C846','#7F7FD5','#F76D6D','#3D5AFE','#00B8A9',
  '#F6416C','#FFDE7D','#43A047','#FF6F00','#00897B','#D81B60','#7E57C2'
];
let chartObjs = {};
function renderCharts(d) {
  // Destroy existing
  Object.values(chartObjs).forEach(c=>c && c.destroy && c.destroy());
  // Gender by Category
  chartObjs.gender = new Chart(document.getElementById('genderCatChart').getContext('2d'), {
    type: 'bar',
    data: {
      labels: d.gender.labels,
      datasets: [
        { label: 'Males', data: d.gender.male, backgroundColor: 'rgba(54,162,235,0.7)' },
        { label: 'Females', data: d.gender.female, backgroundColor: 'rgba(255,99,132,0.7)' }
      ]
    }, options: { plugins: { legend: { display: true } }, responsive: true, scales: { x: { stacked: true }, y: { beginAtZero: true, stacked: true } } }
  });
  // Rank
  chartObjs.rank = new Chart(document.getElementById('rankChart').getContext('2d'), {
    type: 'bar',
    data: { labels: d.rank.labels, datasets: [{ label: 'Staff', data: d.rank.data, backgroundColor: chartColors, borderRadius: 4 }] },
    options: { plugins: { legend: { display: false } }, responsive: true, scales: { y: { beginAtZero: true } } }
  });
  // Unit
  chartObjs.unit = new Chart(document.getElementById('unitChart').getContext('2d'), {
    type: 'bar',
    data: { labels: d.unit.labels, datasets: [{ label: 'Staff', data: d.unit.data, backgroundColor: chartColors, borderRadius: 4 }] },
    options: { plugins: { legend: { display: false } }, responsive: true, indexAxis: 'y', scales: { x: { beginAtZero: true } } }
  });
  // Province
  chartObjs.province = new Chart(document.getElementById('provinceChart').getContext('2d'), {
    type: 'bar',
    data: { labels: d.province.labels, datasets: [{ label: 'Staff', data: d.province.data, backgroundColor: chartColors, borderRadius: 4 }] },
    options: { plugins: { legend: { display: false } }, responsive: true, indexAxis: 'y', scales: { x: { beginAtZero: true } } }
  });
  // Course
  let courseType = d.course.labels.length <= 12 ? 'pie' : 'bar';
  chartObjs.course = new Chart(document.getElementById('courseChart').getContext('2d'), {
    type: courseType,
    data: { labels: d.course.labels, datasets: [{ label: 'Staff Attended', data: d.course.data, backgroundColor: chartColors, borderRadius: 4 }] },
    options: { plugins: { legend: { display: true } }, responsive: true, scales: courseType === 'bar' ? { y: { beginAtZero: true } } : {} }
  });
  // Ops
  let opsType = d.opsType.labels.length <= 12 ? 'doughnut' : 'bar';
  chartObjs.ops = new Chart(document.getElementById('opsChart').getContext('2d'), {
    type: opsType,
    data: { labels: d.opsType.labels, datasets: [{ label: 'Operations', data: d.opsType.data, backgroundColor: chartColors, borderRadius: 4 }] },
    options: { plugins: { legend: { display: true } }, responsive: true, scales: opsType === 'bar' ? { y: { beginAtZero: true } } : {} }
  });
  // Forecast - get AI forecast from microservice
  getAIForecast(d.forecastLabels, d.forecastVals, 6, function(forecastResult){
    let allLabels = d.forecastLabels.concat(forecastResult && forecastResult.labels ? forecastResult.labels.slice(d.forecastLabels.length) : []);
    let forecasted = forecastResult && forecastResult.forecast ? forecastResult.forecast : (d.forecastVals.concat([null,null,null,null,null,null]));
    chartObjs.forecast = new Chart(document.getElementById('forecastChart').getContext('2d'), {
      type: 'line',
      data: {
        labels: allLabels,
        datasets: [
          { label: "Historical Staff", data: d.forecastVals.concat(Array(6).fill(null)), borderColor: "#355E3B", backgroundColor: "rgba(53,94,59,0.3)", tension: 0.3, pointStyle: 'circle' },
          { label: "AI Forecast (6mo)", data: forecasted, borderColor: "#FF6F00", backgroundColor: "rgba(255,111,0,0.2)", borderDash: [8,4], tension: 0.3, pointStyle: 'rect' }
        ]
      }, options: { plugins: { legend: { display: true } }, responsive: true, scales: { y: { beginAtZero: true } } }
    });
  });
}

// AI forecast - calls PHP endpoint which proxies to Python ML
function getAIForecast(labels, vals, periods, callback) {
  axios.post('?ajax=forecast', {labels: labels, vals: vals, periods: periods})
    .then(res => callback(res.data))
    .catch(()=>callback(null));
}

function updateTiles(kpis) {
  for(let k in kpis) {
    let el = document.getElementById('kpi-'+k);
    if(el) el.textContent = kpis[k];
  }
}

// Scroll-to-top
const scrollBtn = document.getElementById('scrollBtn');
window.addEventListener('scroll', function() { scrollBtn.style.display = (window.pageYOffset > 200) ? 'block' : 'none'; });
scrollBtn.addEventListener('click', function() { window.scrollTo({ top: 0, behavior: "smooth" }); });

// Live Refresh
let liveRefreshActive = false, liveRefreshTimer;
document.getElementById('liveRefresh').addEventListener('click', function() {
  if(liveRefreshActive) {
    clearInterval(liveRefreshTimer);
    this.innerHTML = '<i class="fa fa-refresh"></i> Live Refresh'; this.classList.remove('btn-danger'); liveRefreshActive = false;
  } else {
    this.innerHTML = '<i class="fa fa-pause"></i> Stop Live Refresh'; this.classList.add('btn-danger');
    liveRefreshTimer = setInterval(()=>dashboardFetch(true), 10000); liveRefreshActive = true;
  }
});

// Filter form
document.getElementById('dashboardFilters').addEventListener('submit', function(e){
  e.preventDefault(); dashboardFetch();
});
document.getElementById('resetFilters').addEventListener('click', function(){
  document.querySelectorAll('#dashboardFilters select').forEach(sel=>sel.value=''); dashboardFetch();
});

// AJAX fetch for live refresh and filters
function dashboardFetch(isLive = false) {
  let f = document.getElementById('dashboardFilters');
  let params = [];
  f.querySelectorAll('select').forEach(sel=>{
    if(sel.value) params.push(`${encodeURIComponent(sel.name)}=${encodeURIComponent(sel.value)}`);
  });
  let url = window.location.pathname+"?ajax=1"+(params.length?'&'+params.join('&'):'');
  // Show loading overlay
  let overlay = document.createElement('div');
  overlay.className = 'position-fixed top-0 start-0 w-100 h-100 bg-light bg-opacity-75 d-flex align-items-center justify-content-center';
  overlay.style.zIndex = 9999;
  overlay.innerHTML = '<div class="spinner-border text-success" role="status"></div>';
  document.body.appendChild(overlay);
  axios.get(url).then(resp=>{
    let d = resp.data;
    if(typeof d === "string") d = JSON.parse(d);
    updateTiles(d.kpis);
    renderCharts(d);
  }).finally(()=>document.body.removeChild(overlay));
}

// Drilldown on chart click (shows modal with link to filtered profiles)
function showDrillDown(type, label) {
  let body = `<a class="btn btn-primary" href="command/profiles.php?${type}=${encodeURIComponent(label)}">View Profiles for ${label}</a>
    <div class="small text-muted mt-2">This would show a dynamic, filterable list of staff records for the selected bar.</div>`;
  document.getElementById('drilldownModalLabel').textContent = `Drill-down: ${type} - ${label}`;
  document.getElementById('drilldownModalBody').innerHTML = body;
  let modal = new bootstrap.Modal(document.getElementById('drilldownModal'));
  modal.show();
}
['genderCatChart','rankChart','unitChart','provinceChart','courseChart','opsChart'].forEach(id=>{
  let el = document.getElementById(id);
  if(el) el.onclick = function(evt) {
    let chart = Chart.getChart(el);
    if(chart) {
      let points = chart.getElementsAtEventForMode(evt, 'nearest', { intersect: true }, true);
      if(points.length) {
        let label = chart.data.labels[points[0].index];
        showDrillDown(id.replace('Chart',''), label)
      }
    }
  };
});

// Initial render
renderCharts(<?=json_encode($data)?>);