<?php
/**
 * Training Module Authentication
 * Centralized authentication and authorization for training module
 */

// Prevent direct access
if (!defined('ARMIS_TRAINING')) {
    define('ARMIS_TRAINING', true);
}

// Include core authentication
require_once dirname(__DIR__, 2) . '/shared/session_init.php';
require_once dirname(__DIR__, 2) . '/shared/rbac.php';

/**
 * Check if user is authenticated
 */
function isAuthenticated() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Require authentication - redirect if not logged in
 */
function requireAuth() {
    if (!isAuthenticated()) {
        $redirectUrl = dirname($_SERVER['PHP_SELF']) . '/../login.php';
        header('Location: ' . $redirectUrl);
        exit();
    }
}

/**
 * Check if user has training module access
 */
function hasTrainingAccess($userRole = null) {
    return hasModuleAccess('training', $userRole);
}

/**
 * Require training module access
 */
function requireTrainingAccess() {
    requireAuth();
    requireModuleAccess('training');
}

/**
 * Log training activity
 */
function logTrainingActivity($action, $description, $additionalData = []) {
    if (function_exists('logActivity')) {
        $data = array_merge([
            'module' => 'training',
            'user_id' => $_SESSION['user_id'] ?? null,
            'action' => $action,
            'description' => $description,
            'timestamp' => date('Y-m-d H:i:s')
        ], $additionalData);
        
        logActivity($action, $description, $data);
    }
}

/**
 * Get current user information
 */
function getCurrentUser() {
    if (!isAuthenticated()) {
        return null;
    }
    
    return [
        'id' => $_SESSION['user_id'],
        'name' => $_SESSION['name'] ?? '',
        'rank' => $_SESSION['rank'] ?? '',
        'role' => $_SESSION['role'] ?? '',
        'first_name' => $_SESSION['first_name'] ?? $_SESSION['fname'] ?? '',
        'last_name' => $_SESSION['last_name'] ?? $_SESSION['lname'] ?? ''
    ];
}
?>