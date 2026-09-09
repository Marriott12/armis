<?php
/**
 * Permission Management System
 *
 * This file handles granular action-level permission checks (hasPermission,
 * PERM_* constants) for the ARMIS system.
 *
 * CHANGELOG (branch-scoping upgrade): this file used to ALSO define its own
 * hasModuleAccess()/requireModuleAccess(), unconditionally (no function_exists
 * guard). Because admin_branch/includes/auth.php requires this file BEFORE
 * shared/rbac.php, this file's versions were winning silently across the
 * entire admin_branch module — admin_branch never actually saw shared/
 * rbac.php's module-access logic at all. Those two functions have been
 * removed from here; shared/rbac.php's canonical versions (now guaranteed
 * loaded via the require_once below) are what's actually called everywhere,
 * including admin_branch. No call site needed to change — every caller in
 * the codebase invokes requireModuleAccess($module) with a single argument.
 */

require_once __DIR__ . '/rbac.php';

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
    
    // Admin/administrator/admin_branch roles have all permissions
    $adminRoles = ['admin', 'administrator', 'admin_branch'];
    if (in_array(strtolower($userRole), $adminRoles)) {
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
        'admin_branch' => [
            // Admin Branch has all admin permissions
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
        'command' => [
            // Command role has similar permissions to commander
            PERM_VIEW_STAFF, PERM_VIEW_REPORTS, PERM_ASSIGN_MEDALS, PERM_ADMIN_BRANCH_ACCESS
        ],
        'training' => [
            // Training role can view and manage staff for training purposes
            PERM_VIEW_STAFF, PERM_EDIT_STAFF, PERM_VIEW_REPORTS, PERM_ADMIN_BRANCH_ACCESS
        ],
        'operations' => [
            // Operations role can view and manage staff for operational purposes
            PERM_VIEW_STAFF, PERM_EDIT_STAFF, PERM_VIEW_REPORTS, PERM_ADMIN_BRANCH_ACCESS
        ],
        'staff_officer' => [
            PERM_VIEW_STAFF, PERM_VIEW_REPORTS
        ],

        // --- Branch RBAC roles (added with the branches/roles upgrade) ---
        // cc/soi/soii/soiii: full write actions, but ALWAYS additionally
        // gated by canAlterRecord()/canAlterStaffRecord() at the point of
        // write (see admin_branch/edit_staff.php, promote_staff.php,
        // assign_medal.php) — hasPermission() alone does not know which
        // branch a given record belongs to, only rbac.php's branch-aware
        // functions do that check.
        'cc' => [
            PERM_VIEW_STAFF, PERM_EDIT_STAFF, PERM_CREATE_STAFF, PERM_DELETE_STAFF,
            PERM_PROMOTE_STAFF, PERM_MANAGE_APPOINTMENTS, PERM_ASSIGN_MEDALS, PERM_VIEW_REPORTS
        ],
        'soi' => [
            PERM_VIEW_STAFF, PERM_EDIT_STAFF, PERM_PROMOTE_STAFF, PERM_MANAGE_APPOINTMENTS,
            PERM_ASSIGN_MEDALS, PERM_VIEW_REPORTS
        ],
        'soii' => [
            PERM_VIEW_STAFF, PERM_EDIT_STAFF, PERM_MANAGE_APPOINTMENTS, PERM_VIEW_REPORTS
        ],
        'soiii' => [
            PERM_VIEW_STAFF, PERM_EDIT_STAFF, PERM_VIEW_REPORTS
        ],
        // dg / ag: read-only oversight roles, no write permissions at all
        'dg' => [
            PERM_VIEW_STAFF, PERM_VIEW_REPORTS
        ],
        'ag' => [
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
 * NOTE: hasModuleAccess() used to be defined here, mapping modules to
 * PERM_* constants. It has been removed — shared/rbac.php's hasModuleAccess()
 * (loaded above via require_once) is now the single canonical implementation,
 * driven by the `branches`/`roles` tables instead of a hardcoded map. This
 * also means new branches created from the admin UI get module access
 * automatically, which this old hardcoded map could never do.
 */

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
    
    // For admin/administrator/admin_branch, return all permissions
    $adminRoles = ['admin', 'administrator', 'admin_branch'];
    if (in_array(strtolower($userRole), $adminRoles)) {
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
 * NOTE: requireModuleAccess() also used to be defined here. Removed for the
 * same reason as hasModuleAccess() above — shared/rbac.php's version is now
 * the single canonical implementation used everywhere, admin_branch included.
 */
