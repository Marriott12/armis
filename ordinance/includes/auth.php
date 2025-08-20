<?php
/**
 * ARMIS Ordinance Module Authentication
 * Handles authentication and authorization for ordinance module
 */

// Include base configuration
require_once __DIR__ . '/config.php';

// Include shared authentication functions
require_once dirname(dirname(__DIR__)) . '/shared/session_init.php';
require_once dirname(dirname(__DIR__)) . '/shared/rbac.php';

/**
 * Require authentication for ordinance module
 */
function requireAuth() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: ' . dirname($_SERVER['REQUEST_URI'], 2) . '/login.php');
        exit();
    }
}

/**
 * Check if user has ordinance module access
 */
function hasOrdinanceAccess($userRole = null) {
    return hasModuleAccess('ordinance', $userRole);
}

/**
 * Require ordinance module access
 */
function requireOrdinanceAccess() {
    requireModuleAccess('ordinance');
}

/**
 * Check specific ordinance permissions
 */
function hasOrdinancePermission($permission) {
    $userRole = $_SESSION['role'] ?? 'user';
    
    $permissions = [
        'manage_inventory' => ['admin', 'ordinance'],
        'manage_weapons' => ['admin', 'ordinance'],
        'manage_ammunition' => ['admin', 'ordinance'],
        'schedule_maintenance' => ['admin', 'ordinance'],
        'view_security_logs' => ['admin', 'ordinance'],
        'approve_transactions' => ['admin', 'ordinance'],
        'security_oversight' => ['admin'],
        'weapon_assignments' => ['admin', 'ordinance']
    ];
    
    if (!isset($permissions[$permission])) {
        return false;
    }
    
    return in_array($userRole, $permissions[$permission]);
}

/**
 * Log ordinance module activity
 */
function logOrdinanceActivity($action, $description, $additional_data = []) {
    logActivity('ordinance_' . $action, $description, array_merge([
        'module' => 'ordinance',
        'user_id' => $_SESSION['user_id'] ?? null,
        'user_role' => $_SESSION['role'] ?? null,
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
        'security_level' => 'restricted',
        'requires_audit' => true
    ], $additional_data));
}

/**
 * Validate ordinance module session
 */
function validateOrdinanceSession() {
    if (!isset($_SESSION['user_id']) || !hasOrdinanceAccess()) {
        session_destroy();
        header('Location: ' . dirname($_SERVER['REQUEST_URI'], 2) . '/login.php');
        exit();
    }
    
    // Update last activity
    $_SESSION['last_activity'] = time();
    
    // Log session validation for security audit
    logOrdinanceActivity('session_validation', 'User session validated for ordinance access');
}

/**
 * Get current user ordinance permissions
 */
function getCurrentUserOrdinancePermissions() {
    $userRole = $_SESSION['role'] ?? 'user';
    
    return [
        'can_manage_inventory' => hasOrdinancePermission('manage_inventory'),
        'can_manage_weapons' => hasOrdinancePermission('manage_weapons'),
        'can_manage_ammunition' => hasOrdinancePermission('manage_ammunition'),
        'can_schedule_maintenance' => hasOrdinancePermission('schedule_maintenance'),
        'can_view_security_logs' => hasOrdinancePermission('view_security_logs'),
        'can_approve_transactions' => hasOrdinancePermission('approve_transactions'),
        'has_security_oversight' => hasOrdinancePermission('security_oversight'),
        'can_manage_weapon_assignments' => hasOrdinancePermission('weapon_assignments'),
        'user_role' => $userRole
    ];
}