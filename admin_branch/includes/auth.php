<?php
/**
 * ARMIS Admin Branch Authentication and Session Handler
 * Provides consistent authentication and session management
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include configuration and utilities
require_once __DIR__ . '/config.php';
require_once dirname(dirname(__DIR__)) . '/shared/database_connection.php';
require_once __DIR__ . '/utils.php';

// Include military formatting functions
require_once dirname(dirname(__DIR__)) . '/shared/military_formatting.php';

/**
 * Execute a database query using PDO
 */
function executeQuery($sql, $params = []) {
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare($sql);
        return $stmt->execute($params);
    } catch (Exception $e) {
        error_log("Query execution failed: " . $e->getMessage());
        throw $e;
    }
}

/**
 * Check if user is authenticated
 */
function isAuthenticated() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Require authentication - redirect to login if not authenticated
 */
function requireAuth() {
    if (!isAuthenticated()) {
        $redirectUrl = '/Armis2/login.php';
        header('Location: ' . $redirectUrl);
        exit();
    }
}

/**
 * Check if user has admin privileges
 */
function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'administrator';
}

/**
 * Require admin privileges
 */
function requireAdmin() {
    requireAuth();
    if (!isAdmin()) {
        header('HTTP/1.1 403 Forbidden');
        die('Access denied. Administrator privileges required.');
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
        'user_id' => $_SESSION['user_id'] ?? null,
        'username' => $_SESSION['username'] ?? '',
        'svcNo' => $_SESSION['svcNo'] ?? '',
        'rank' => $_SESSION['rank'] ?? '',
        'rank_abbr' => $_SESSION['rank_abbr'] ?? '',
        'fname' => $_SESSION['fname'] ?? '',
        'lname' => $_SESSION['lname'] ?? '',
        'category' => $_SESSION['category'] ?? '',
        'role' => $_SESSION['role'] ?? '',
        'unit' => $_SESSION['unit'] ?? '',
        'unit_name' => $_SESSION['unit_name'] ?? ''
    ];
}

/**
 * Get formatted military name for current user
 */
function getCurrentUserMilitaryName() {
    $user = getCurrentUser();
    if (!$user) {
        return 'Guest';
    }
    
    return formatMilitaryName(
        $user['rank_abbr'],
        $user['fname'],
        $user['lname'],
        $user['category']
    );
}

/**
 * Initialize default session data if not present
 */
function initializeDefaultSession() {
    if (!isAuthenticated()) {
        // Set default admin session for development/testing
        $_SESSION['user_id'] = 1;
        $_SESSION['username'] = 'admin';
        $_SESSION['svcNo'] = 'AR001001';
        $_SESSION['rank'] = 'Colonel';
        $_SESSION['rank_abbr'] = 'Col';
        $_SESSION['fname'] = 'John';
        $_SESSION['lname'] = 'Smith';
        $_SESSION['category'] = 'Officer';
        $_SESSION['role'] = 'administrator';
        $_SESSION['unit'] = 'Headquarters Command';
        $_SESSION['unit_name'] = 'HQ Command';
        $_SESSION['last_login'] = date('Y-m-d H:i:s');
    }
}

/**
 * Log user activity
 */
function logActivity($action, $details = '') {
    $user = getCurrentUser();
    if (!$user) return;
    
    try {
        $sql = "INSERT INTO activity_log (user_id, username, action, details, ip_address, user_agent, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, NOW())";
        $params = [
            $user['user_id'],
            $user['username'],
            $action,
            $details,
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ];
        executeQuery($sql, $params);
    } catch (Exception $e) {
        error_log("Failed to log activity: " . $e->getMessage());
    }
}

/**
 * Create activity log table if it doesn't exist
 */
function createActivityLogTable() {
    try {
        $sql = "CREATE TABLE IF NOT EXISTS activity_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            username VARCHAR(50) NOT NULL,
            action VARCHAR(100) NOT NULL,
            details TEXT,
            ip_address VARCHAR(45),
            user_agent TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user_id (user_id),
            INDEX idx_action (action),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        executeQuery($sql);
    } catch (Exception $e) {
        error_log("Failed to create activity_log table: " . $e->getMessage());
    }
}

// Initialize activity log table
createActivityLogTable();

// Compatibility aliases for common function names
function isLoggedIn() {
    return isAuthenticated();
}

function requireAdminAccess() {
    return requireAdmin();
}

// For development - initialize default session if no user is logged in
if (defined('ARMIS_DEVELOPMENT') && ARMIS_DEVELOPMENT === true) {
    initializeDefaultSession();
}
