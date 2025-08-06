<?php
// Simple scheduled report/notification subscription UI (stub for integration)
// Place this at <div id="notificationsPanel"></div> in your main analytics page
?>
<div class="card border-info mt-4">
    <div class="card-header bg-info text-white">
        <i class="fa fa-bell"></i> Notifications & Scheduled Reports
    </div>
    <div class="card-body">
        <form method="post" id="notifyForm">
            <div class="mb-2">
                <label><input type="checkbox" name="daily_report" value="1"> Daily summary</label>
            </div>
            <div class="mb-2">
                <label><input type="checkbox" name="weekly_report" value="1"> Weekly summary</label>
            </div>
            <div class="mb-2">
                <label><input type="checkbox" name="notify_on_change" value="1"> Notify me when analytics change significantly</label>
            </div>
            <button type="submit" class="btn btn-info btn-sm">Save Preferences</button>
        </form>
        <div id="notifyMsg" class="mt-2"></div>
    </div>
</div>
<script>
$('#notifyForm').on('submit', function(e){
    e.preventDefault();
    // Simulate save to backend (AJAX to notifications/settings endpoint in real app)
    $('#notifyMsg').text('Preferences saved! (This is a demo. Backend integration required.)').addClass('text-success');
});
</script>