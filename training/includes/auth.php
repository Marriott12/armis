<?php
/**
 * ARMIS Training Module Authentication
 * Handles authentication and authorization for training module
 */

// Include base configuration
require_once __DIR__ . '/config.php';

// Include shared authentication functions
require_once dirname(dirname(__DIR__)) . '/shared/session_init.php';
require_once dirname(dirname(__DIR__)) . '/shared/rbac.php';

/**
 * Require authentication for training module
 */
function requireAuth() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: ' . dirname($_SERVER['REQUEST_URI'], 2) . '/login.php');
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
    requireModuleAccess('training');
}

/**
 * Check specific training permissions
 */
function hasTrainingPermission($permission) {
    $userRole = $_SESSION['role'] ?? 'user';
    
    $permissions = [
        'manage_courses' => ['admin', 'training'],
        'manage_records' => ['admin', 'training'],
        'manage_certifications' => ['admin', 'training'],
        'manage_schedule' => ['admin', 'training'],
        'view_reports' => ['admin', 'training', 'command'],
        'manage_instructors' => ['admin', 'training'],
        'enroll_students' => ['admin', 'training']
    ];
    
    if (!isset($permissions[$permission])) {
        return false;
    }
    
    return in_array($userRole, $permissions[$permission]);
}

/**
 * Log training module activity
 */
function logTrainingActivity($action, $description, $additional_data = []) {
    logActivity('training_' . $action, $description, array_merge([
        'module' => 'training',
        'user_id' => $_SESSION['user_id'] ?? null,
        'user_role' => $_SESSION['role'] ?? null,
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
    ], $additional_data));
}

/**
 * Validate training module session
 */
function validateTrainingSession() {
    if (!isset($_SESSION['user_id']) || !hasTrainingAccess()) {
        session_destroy();
        header('Location: ' . dirname($_SERVER['REQUEST_URI'], 2) . '/login.php');
        exit();
    }
    
    // Update last activity
    $_SESSION['last_activity'] = time();
}

/**
 * Get current user training permissions
 */
function getCurrentUserTrainingPermissions() {
    $userRole = $_SESSION['role'] ?? 'user';
    
    return [
        'can_manage_courses' => hasTrainingPermission('manage_courses'),
        'can_manage_records' => hasTrainingPermission('manage_records'),
        'can_manage_certifications' => hasTrainingPermission('manage_certifications'),
        'can_manage_schedule' => hasTrainingPermission('manage_schedule'),
        'can_view_reports' => hasTrainingPermission('view_reports'),
        'can_manage_instructors' => hasTrainingPermission('manage_instructors'),
        'can_enroll_students' => hasTrainingPermission('enroll_students'),
        'user_role' => $userRole
    ];
}