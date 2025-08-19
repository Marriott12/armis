<?php
/**
 * Ordinance Module Authentication
 */

if (!defined('ARMIS_ORDINANCE')) {
    define('ARMIS_ORDINANCE', true);
}

require_once dirname(__DIR__, 2) . '/shared/session_init.php';
require_once dirname(__DIR__, 2) . '/shared/rbac.php';

function isAuthenticated() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function requireAuth() {
    if (!isAuthenticated()) {
        $redirectUrl = dirname($_SERVER['PHP_SELF']) . '/../login.php';
        header('Location: ' . $redirectUrl);
        exit();
    }
}

function hasOrdinanceAccess($userRole = null) {
    return hasModuleAccess('ordinance', $userRole);
}

function requireOrdinanceAccess() {
    requireAuth();
    requireModuleAccess('ordinance');
}

function logOrdinanceActivity($action, $description, $additionalData = []) {
    if (function_exists('logActivity')) {
        $data = array_merge([
            'module' => 'ordinance',
            'user_id' => $_SESSION['user_id'] ?? null,
            'action' => $action,
            'description' => $description,
            'timestamp' => date('Y-m-d H:i:s')
        ], $additionalData);
        
        logActivity($action, $description, $data);
    }
}

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