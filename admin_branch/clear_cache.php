<?php
// Clear dashboard cache
session_start();

// Require login — this only touches the caller's own session data, but
// there's no reason to let an unauthenticated request hit it at all.
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    die("Not authenticated.\n");
}

// Clear any cached dashboard data from session
unset($_SESSION['dashboard_cache']);
unset($_SESSION['period_filter']);

echo "Dashboard cache cleared!\n";
echo "Session ID: " . session_id() . "\n";
echo "Please refresh the admin_branch dashboard page.\n";
