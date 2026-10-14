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
define('PERM_CREATE_MEDAL', 'create_medal');
define('PERM_MANAGE_POSTINGS', 'manage_postings');
define('PERM_MANAGE_EDUCATION', 'manage_education');
define('PERM_VIEW_DASHBOARD', 'view_dashboard');

/**
 * Check a granular action permission against the canonical role_permissions table.
 * Module access remains governed by shared/rbac.php + roles/role_modules.
 */
function hasPermission($permission, $userRole = null) {
    if ($userRole === null) {
        $userRole = $_SESSION['role'] ?? '';
    }

    static $cache = [];
    $userRole = strtolower(trim((string)$userRole));
    $permission = trim((string)$permission);
    if ($userRole === '' || $permission === '') return false;

    $cacheKey = $userRole . ':' . $permission;
    if (array_key_exists($cacheKey, $cache)) return $cache[$cacheKey];

    // System administrator is the only unrestricted role.
    if ($userRole === 'admin') return $cache[$cacheKey] = true;

    // Specialized branch permissions are both role- and branch-gated.
    if ($permission === PERM_MANAGE_POSTINGS || $permission === PERM_MANAGE_EDUCATION) {
        $branchId = getUserBranch();
        if (!$branchId) return $cache[$cacheKey] = false;
        $branch = getBranchById($branchId);
        if (!$branch) return $cache[$cacheKey] = false;
        if ($permission === PERM_MANAGE_POSTINGS && strtolower($branch['code']) !== 'operations') return $cache[$cacheKey] = false;
        if ($permission === PERM_MANAGE_EDUCATION && strtolower($branch['code']) !== 'training') return $cache[$cacheKey] = false;
    }

    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare('SELECT 1 FROM role_permissions WHERE role_code = ? AND permission_code = ? LIMIT 1');
        $stmt->execute([$userRole, $permission]);
        return $cache[$cacheKey] = (bool)$stmt->fetchColumn();
    } catch (Throwable $e) {
        // Fail closed if the centralized permission policy has not been migrated.
        error_log('ARMIS permission policy lookup failed: ' . $e->getMessage());
        return $cache[$cacheKey] = false;
    }
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
 * Get all permissions for the current user from the canonical role policy.
 */
function getUserPermissions($userRole = null) {
    if ($userRole === null) $userRole = $_SESSION['role'] ?? '';
    $userRole = strtolower(trim((string)$userRole));
    if ($userRole === '') return [];

    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare('SELECT permission_code FROM role_permissions WHERE role_code = ? ORDER BY permission_code');
        $stmt->execute([$userRole]);
        return array_values(array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN)));
    } catch (Throwable $e) {
        error_log('ARMIS permission list lookup failed: ' . $e->getMessage());
        return [];
    }
}

/**
 * NOTE: requireModuleAccess() also used to be defined here. Removed for the
 * same reason as hasModuleAccess() above — shared/rbac.php's version is now
 * the single canonical implementation used everywhere, admin_branch included.
 */
