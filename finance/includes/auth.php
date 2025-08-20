<?php
/**
 * ARMIS Finance Module Authentication
 * Handles authentication and authorization for finance module
 */

// Include base configuration
require_once __DIR__ . '/config.php';

// Include shared authentication functions
require_once dirname(dirname(__DIR__)) . '/shared/session_init.php';
require_once dirname(dirname(__DIR__)) . '/shared/rbac.php';

/**
 * Require authentication for finance module
 */
function requireAuth() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: ' . dirname($_SERVER['REQUEST_URI'], 2) . '/login.php');
        exit();
    }
}

/**
 * Check if user has finance module access
 */
function hasFinanceAccess($userRole = null) {
    return hasModuleAccess('finance', $userRole);
}

/**
 * Require finance module access
 */
function requireFinanceAccess() {
    requireModuleAccess('finance');
}

/**
 * Check specific finance permissions
 */
function hasFinancePermission($permission) {
    $userRole = $_SESSION['role'] ?? 'user';
    
    $permissions = [
        'manage_budgets' => ['admin', 'finance'],
        'approve_expenditures' => ['admin', 'finance'],
        'manage_procurement' => ['admin', 'finance'],
        'view_reports' => ['admin', 'finance', 'command'],
        'conduct_audits' => ['admin', 'finance'],
        'approve_large_purchases' => ['admin'],
        'financial_oversight' => ['admin', 'finance']
    ];
    
    if (!isset($permissions[$permission])) {
        return false;
    }
    
    return in_array($userRole, $permissions[$permission]);
}

/**
 * Log finance module activity
 */
function logFinanceActivity($action, $description, $additional_data = []) {
    logActivity('finance_' . $action, $description, array_merge([
        'module' => 'finance',
        'user_id' => $_SESSION['user_id'] ?? null,
        'user_role' => $_SESSION['role'] ?? null,
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
        'requires_audit' => true
    ], $additional_data));
}

/**
 * Validate finance module session
 */
function validateFinanceSession() {
    if (!isset($_SESSION['user_id']) || !hasFinanceAccess()) {
        session_destroy();
        header('Location: ' . dirname($_SERVER['REQUEST_URI'], 2) . '/login.php');
        exit();
    }
    
    // Update last activity
    $_SESSION['last_activity'] = time();
}

/**
 * Get current user finance permissions
 */
function getCurrentUserFinancePermissions() {
    $userRole = $_SESSION['role'] ?? 'user';
    
    return [
        'can_manage_budgets' => hasFinancePermission('manage_budgets'),
        'can_approve_expenditures' => hasFinancePermission('approve_expenditures'),
        'can_manage_procurement' => hasFinancePermission('manage_procurement'),
        'can_view_reports' => hasFinancePermission('view_reports'),
        'can_conduct_audits' => hasFinancePermission('conduct_audits'),
        'can_approve_large_purchases' => hasFinancePermission('approve_large_purchases'),
        'has_financial_oversight' => hasFinancePermission('financial_oversight'),
        'user_role' => $userRole
    ];
}