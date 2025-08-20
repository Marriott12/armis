<?php
/**
 * ARMIS Command Module Authentication
 * Handles authentication and authorization for command module
 */

// Include base configuration
require_once __DIR__ . '/config.php';

// Include shared authentication functions
require_once dirname(dirname(__DIR__)) . '/shared/session_init.php';
require_once dirname(dirname(__DIR__)) . '/shared/rbac.php';

/**
 * Require authentication for command module
 */
function requireAuth() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: ' . dirname($_SERVER['REQUEST_URI'], 2) . '/login.php');
        exit();
    }
}

/**
 * Check if user has command module access
 */
function hasCommandAccess($userRole = null) {
    return hasModuleAccess('command', $userRole);
}

/**
 * Require command module access
 */
function requireCommandAccess() {
    requireModuleAccess('command');
}

/**
 * Check specific command permissions
 */
function hasCommandPermission($permission) {
    $userRole = $_SESSION['role'] ?? 'user';
    
    $permissions = [
        'view_hierarchy' => ['admin', 'command'],
        'edit_hierarchy' => ['admin', 'command'],
        'manage_missions' => ['admin', 'command'],
        'view_reports' => ['admin', 'command', 'operations'],
        'manage_assignments' => ['admin', 'command'],
        'access_communications' => ['admin', 'command']
    ];
    
    if (!isset($permissions[$permission])) {
        return false;
    }
    
    return in_array($userRole, $permissions[$permission]);
}

/**
 * Log command module activity
 */
function logCommandActivity($action, $description, $additional_data = []) {
    logActivity('command_' . $action, $description, array_merge([
        'module' => 'command',
        'user_id' => $_SESSION['user_id'] ?? null,
        'user_role' => $_SESSION['role'] ?? null,
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
    ], $additional_data));
}

/**
 * Validate command module session
 */
function validateCommandSession() {
    if (!isset($_SESSION['user_id']) || !hasCommandAccess()) {
        session_destroy();
        header('Location: ' . dirname($_SERVER['REQUEST_URI'], 2) . '/login.php');
        exit();
    }
    
    // Update last activity
    $_SESSION['last_activity'] = time();
}

/**
 * Get current user command permissions
 */
function getCurrentUserCommandPermissions() {
    $userRole = $_SESSION['role'] ?? 'user';
    
    return [
        'can_view_hierarchy' => hasCommandPermission('view_hierarchy'),
        'can_edit_hierarchy' => hasCommandPermission('edit_hierarchy'),
        'can_manage_missions' => hasCommandPermission('manage_missions'),
        'can_view_reports' => hasCommandPermission('view_reports'),
        'can_manage_assignments' => hasCommandPermission('manage_assignments'),
        'can_access_communications' => hasCommandPermission('access_communications'),
        'user_role' => $userRole
    ];
}