<?php
/**
 * Dashboard Access Verification
 * Checks if a user should have access to the current dashboard
 */

// Include required files
require_once __DIR__ . '/database_connection.php';
require_once __DIR__ . '/rbac.php';

/**
 * Verify user has access to current dashboard and redirect if not
 * 
 * @param string $requiredRole The role required for this dashboard
 * @param array $allowedRoles Additional roles that can access this dashboard
 * @return bool True if access is allowed, redirects otherwise
 */
function verifyDashboardAccess($requiredRole, $allowedRoles = []) {
    // Make sure we have a session
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Check if user is logged in
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
        // Not logged in, redirect to login page
        error_log("Dashboard access denied: User not logged in");
        header('Location: /Armis2/login.php');
        exit();
    }
    
    $userRole = $_SESSION['role'];
    $userName = $_SESSION['username'] ?? 'Unknown';
    $userId = $_SESSION['user_id'];
    
    // Allow admin access to all dashboards
    if ($userRole === 'admin') {
        error_log("Dashboard access granted: Admin user {$userName} (ID: {$userId}) has full access");
        return true;
    }
    
    // Combine required role with allowed roles
    $validRoles = array_merge([$requiredRole], $allowedRoles);
    
    // Check if user has one of the valid roles
    if (in_array($userRole, $validRoles)) {
        error_log("Dashboard access granted: User {$userName} (ID: {$userId}, Role: {$userRole}) accessing {$requiredRole} dashboard");
        return true;
    }
    
    // User doesn't have proper access, redirect to their dashboard
    error_log("Dashboard access denied: User {$userName} (ID: {$userId}, Role: {$userRole}) attempted to access {$requiredRole} dashboard");
    
    // Check if we should show an error page
    if (isset($_GET['error']) && $_GET['error'] === 'unauthorized') {
        // Already showing error, prevent redirect loop
        return false;
    }
    
    // Get proper dashboard for this user
    $properDashboard = getRoleDashboardUrl($userRole);
    
    // Check for redirect loop (if we're sending user back to the same URL)
    $currentUrl = $_SERVER['REQUEST_URI'];
    if (strpos($currentUrl, $properDashboard) !== false) {
        // Redirect to unauthorized page to prevent loops
        header('Location: /Armis2/unauthorized.php?role=' . urlencode($requiredRole) . '&current=' . urlencode($userRole));
    } else {
        // Redirect to proper dashboard
        header('Location: ' . $properDashboard);
    }
    
    exit();
}

/**
 * Get array of roles that can access a specific module
 * 
 * @param string $module The module name
 * @return array Array of roles that can access this module
 */
function getRolesForModule($module) {
    $roles = ARMIS_ROLES;
    $accessRoles = [];
    
    foreach ($roles as $role => $info) {
        if (in_array($module, $info['modules'])) {
            $accessRoles[] = $role;
        }
    }
    
    return $accessRoles;
}
