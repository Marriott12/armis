<?php
/**
 * Permission Management System
 * 
 * This file handles role-based access control (RBAC) and permission checks
 * for the ARMIS system. It provides centralized management of permissions.
 */

// Permission constants
define('PERM_VIEW_STAFF', 'view_staff');
define('PERM_EDIT_STAFF', 'edit_staff');
define('PERM_CREATE_STAFF', 'create_staff');
define('PERM_DELETE_STAFF', 'delete_staff');
define('PERM_PROMOTE_STAFF', 'promote_staff');
define('PERM_MANAGE_APPOINTMENTS', 'manage_appointments');
define('PERM_ASSIGN_MEDALS', 'assign_medals');
define('PERM_VIEW_REPORTS', 'view_reports');
define('PERM_ADMIN_ACCESS', 'admin_access');
define('PERM_ADMIN_BRANCH_ACCESS', 'admin_branch_access');
define('PERM_SYSTEM_SETTINGS', 'system_settings');

/**
 * Check if the user has a specific permission
 * 
 * @param string $permission The permission to check
 * @param string|null $userRole Optional user role, defaults to current user
 * @return bool Whether the user has the permission
 */
function hasPermission($permission, $userRole = null) {
    // If no role provided, get from session
    if ($userRole === null) {
        $userRole = $_SESSION['role'] ?? '';
    }
    
    // Admin/administrator role has all permissions
    if (strtolower($userRole) === 'admin' || strtolower($userRole) === 'administrator') {
        return true;
    }
    
    // Define permissions for each role
    $rolePermissions = [
        'admin' => [
            // Admins have all permissions
            PERM_VIEW_STAFF, PERM_EDIT_STAFF, PERM_CREATE_STAFF, PERM_DELETE_STAFF,
            PERM_PROMOTE_STAFF, PERM_MANAGE_APPOINTMENTS, PERM_ASSIGN_MEDALS,
            PERM_VIEW_REPORTS, PERM_ADMIN_ACCESS, PERM_ADMIN_BRANCH_ACCESS, PERM_SYSTEM_SETTINGS
        ],
        'administrator' => [
            // Admins have all permissions
            PERM_VIEW_STAFF, PERM_EDIT_STAFF, PERM_CREATE_STAFF, PERM_DELETE_STAFF,
            PERM_PROMOTE_STAFF, PERM_MANAGE_APPOINTMENTS, PERM_ASSIGN_MEDALS,
            PERM_VIEW_REPORTS, PERM_ADMIN_ACCESS, PERM_ADMIN_BRANCH_ACCESS, PERM_SYSTEM_SETTINGS
        ],
        'hr_officer' => [
            PERM_VIEW_STAFF, PERM_EDIT_STAFF, PERM_CREATE_STAFF, 
            PERM_PROMOTE_STAFF, PERM_MANAGE_APPOINTMENTS, PERM_VIEW_REPORTS,
            PERM_ADMIN_BRANCH_ACCESS
        ],
        'records_officer' => [
            PERM_VIEW_STAFF, PERM_EDIT_STAFF, PERM_VIEW_REPORTS, PERM_ADMIN_BRANCH_ACCESS
        ],
        'commander' => [
            PERM_VIEW_STAFF, PERM_VIEW_REPORTS, PERM_ASSIGN_MEDALS
        ],
        'staff_officer' => [
            PERM_VIEW_STAFF, PERM_VIEW_REPORTS
        ],
        'user' => [
            PERM_VIEW_STAFF
        ]
    ];
    
    // Check if the role exists and has the permission
    if (isset($rolePermissions[$userRole]) && in_array($permission, $rolePermissions[$userRole])) {
        return true;
    }
    
    // Default to false for undefined roles or permissions
    return false;
}

/**
 * Check if the user has access to a specific module
 * 
 * @param string $module The module to check
 * @param string|null $userRole Optional user role, defaults to current user
 * @return bool Whether the user has access to the module
 */
function hasModuleAccess($module, $userRole = null) {
    // Map modules to required permissions
    $modulePermissions = [
        'admin' => PERM_ADMIN_ACCESS,
        'admin_branch' => PERM_ADMIN_BRANCH_ACCESS,
        'staff_management' => PERM_VIEW_STAFF,
        'promotions' => PERM_PROMOTE_STAFF,
        'appointments' => PERM_MANAGE_APPOINTMENTS,
        'medals' => PERM_ASSIGN_MEDALS,
        'reports' => PERM_VIEW_REPORTS,
        'settings' => PERM_SYSTEM_SETTINGS
    ];
    
    // Check if the module exists and the user has the required permission
    if (isset($modulePermissions[$module])) {
        return hasPermission($modulePermissions[$module], $userRole);
    }
    
    // Default to false for undefined modules
    return false;
}

/**
 * Get all permissions for the current user
 * 
 * @param string|null $userRole Optional user role, defaults to current user
 * @return array Array of permission strings the user has
 */
function getUserPermissions($userRole = null) {
    // If no role provided, get from session
    if ($userRole === null) {
        $userRole = $_SESSION['role'] ?? '';
    }
    
    // All available permissions
    $allPermissions = [
        PERM_VIEW_STAFF, PERM_EDIT_STAFF, PERM_CREATE_STAFF, PERM_DELETE_STAFF,
        PERM_PROMOTE_STAFF, PERM_MANAGE_APPOINTMENTS, PERM_ASSIGN_MEDALS,
        PERM_VIEW_REPORTS, PERM_ADMIN_ACCESS, PERM_ADMIN_BRANCH_ACCESS, PERM_SYSTEM_SETTINGS
    ];
    
    // For admin/administrator, return all permissions
    if (strtolower($userRole) === 'admin' || strtolower($userRole) === 'administrator') {
        return $allPermissions;
    }
    
    // For other roles, check each permission
    $userPermissions = [];
    foreach ($allPermissions as $permission) {
        if (hasPermission($permission, $userRole)) {
            $userPermissions[] = $permission;
        }
    }
    
    return $userPermissions;
}

/**
 * Require access to a specific module
 * Redirects to unauthorized page if access is denied
 * 
 * @param string $module The module to check
 * @param string|null $userRole Optional user role, defaults to current user
 */
function requireModuleAccess($module, $userRole = null) {
    if (!hasModuleAccess($module, $userRole)) {
        // Check if we're in admin_branch
        $scriptPath = $_SERVER['SCRIPT_NAME'];
        if (strpos($scriptPath, '/admin_branch/') !== false) {
            header('Location: unauthorized.php?reason=' . urlencode($module) . '_access');
        } else {
            header('Location: /Armis2/unauthorized.php?reason=' . urlencode($module) . '_access');
        }
        exit;
    }
}
