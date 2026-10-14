<?php
define('ARMIS_ADMIN_BRANCH', true);
define('ARMIS_JSON', false);
require_once __DIR__ . '/includes/rbac_guard.php';
adminBranchRequirePermission(PERM_SYSTEM_SETTINGS);
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Allow: POST'); http_response_code(405); exit("POST required.\n");
}
adminBranchRequireCsrf();
unset($_SESSION['dashboard_cache'], $_SESSION['period_filter'], $_SESSION['dropdown_cache']);
logActivity('admin_branch_cache_clear', 'Dashboard/session cache cleared');
echo "Dashboard cache cleared successfully.\n";
