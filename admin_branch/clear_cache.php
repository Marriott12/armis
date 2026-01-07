<?php
// Clear dashboard cache
session_start();

// Clear any cached dashboard data from session
unset($_SESSION['dashboard_cache']);
unset($_SESSION['period_filter']);

echo "Dashboard cache cleared!\n";
echo "Session ID: " . session_id() . "\n";
echo "Please refresh the admin_branch dashboard page.\n";
