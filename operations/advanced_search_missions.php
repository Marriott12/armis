<?php
require_once dirname(__DIR__) . '/shared/header.php';
require_once dirname(__DIR__) . '/shared/sidebar.php';
require_once 'operations_manager.php';
$manager = new OperationsManager();
?>
<div class="main-content">
    <h2 class="mt-4 mb-4">Advanced Mission Search</h2>
    <form id="mission-search-form" class="row g-3 mb-4">
        <div class="col-md-4">
            <label for="search" class="form-label">Mission Name</label>
            <input type="text" name="search" id="search" class="form-control" placeholder="Enter mission name">
        </div>
        <div class="col-md-3">
            <label for="status" class="form-label">Status</label>
            <input type="text" name="status" id="status" class="form-control" placeholder="Enter status">
        </div>
        <div class="col-md-3">
            <label for="location" class="form-label">Location</label>
            <input type="text" name="location" id="location" class="form-control" placeholder="Enter location">
        </div>
        <div class="col-md-2 d-flex align-items-end">
            <button type="button" id="search-btn" class="btn btn-primary w-100">Search</button>
        </div>
    </form>
    <div id="mission-results"></div>
</div>
<script>
document.getElementById('search-btn').onclick = function() {
    var form = document.getElementById('mission-search-form');
    var data = new FormData(form);
    var params = new URLSearchParams(data).toString();
    fetch('ajax_search_missions.php?' + params)
        .then(response => response.text())
        .then(html => {
            document.getElementById('mission-results').innerHTML = html;
        });
};
</script>
<?php require_once dirname(__DIR__) . '/shared/footer.php'; ?>
