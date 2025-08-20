<?php
/**
 * ARMIS Operations Module Authentication
 * Handles authentication and authorization for operations module
 */

// Include base configuration
require_once __DIR__ . '/config.php';

// Include shared authentication functions
require_once dirname(dirname(__DIR__)) . '/shared/session_init.php';
require_once dirname(dirname(__DIR__)) . '/shared/rbac.php';

/**
 * Require authentication for operations module
 */
function requireAuth() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: ' . dirname($_SERVER['REQUEST_URI'], 2) . '/login.php');
        exit();
    }
}

/**
 * Check if user has operations module access
 */
function hasOperationsAccess($userRole = null) {
    return hasModuleAccess('operations', $userRole);
}

/**
 * Require operations module access
 */
function requireOperationsAccess() {
    requireModuleAccess('operations');
}

/**
 * Check specific operations permissions
 */
function hasOperationsPermission($permission) {
    $userRole = $_SESSION['role'] ?? 'user';
    
    $permissions = [
        'manage_missions' => ['admin', 'operations', 'command'],
        'manage_deployments' => ['admin', 'operations', 'command'],
        'manage_resources' => ['admin', 'operations'],
        'view_field_ops' => ['admin', 'operations', 'command'],
        'manage_field_ops' => ['admin', 'operations'],
        'view_reports' => ['admin', 'operations', 'command'],
        'classified_access' => ['admin', 'operations']
    ];
    
    if (!isset($permissions[$permission])) {
        return false;
    }
    
    return in_array($userRole, $permissions[$permission]);
}

/**
 * Log operations module activity
 */
function logOperationsActivity($action, $description, $additional_data = []) {
    logActivity('operations_' . $action, $description, array_merge([
        'module' => 'operations',
        'user_id' => $_SESSION['user_id'] ?? null,
        'user_role' => $_SESSION['role'] ?? null,
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
        'security_level' => 'classified'
    ], $additional_data));
}

/**
 * Validate operations module session
 */
function validateOperationsSession() {
    if (!isset($_SESSION['user_id']) || !hasOperationsAccess()) {
        session_destroy();
        header('Location: ' . dirname($_SERVER['REQUEST_URI'], 2) . '/login.php');
        exit();
    }
    
    // Update last activity
    $_SESSION['last_activity'] = time();
}

/**
 * Get current user operations permissions
 */
function getCurrentUserOperationsPermissions() {
    $userRole = $_SESSION['role'] ?? 'user';
    
    return [
        'can_manage_missions' => hasOperationsPermission('manage_missions'),
        'can_manage_deployments' => hasOperationsPermission('manage_deployments'),
        'can_manage_resources' => hasOperationsPermission('manage_resources'),
        'can_view_field_ops' => hasOperationsPermission('view_field_ops'),
        'can_manage_field_ops' => hasOperationsPermission('manage_field_ops'),
        'can_view_reports' => hasOperationsPermission('view_reports'),
        'has_classified_access' => hasOperationsPermission('classified_access'),
        'user_role' => $userRole
    ];
}