<?php
/**
 * ARMIS Admin Branch Authentication and Session Handler
 * Provides consistent authentication and session management
 */

// Start session if not already started
require_once dirname(dirname(__DIR__)) . '/shared/session_security.php';
armisStartSecureSession();

// --- SESSION TIMEOUT ENFORCEMENT ---
// FIX: this used to be its own inline 20-minute check, completely
// independent of config.php's SESSION_TIMEOUT constant (which said 1
// hour and wasn't enforced anywhere at all). Both now go through
// shared/session_guard.php's single implementation, reading the one
// SESSION_TIMEOUT value every module agrees on.
require_once dirname(dirname(__DIR__)) . '/config.php';
require_once dirname(dirname(__DIR__)) . '/shared/session_guard.php';
enforceSessionTimeout();

// Include configuration and utilities
require_once __DIR__ . '/config.php';
require_once dirname(dirname(__DIR__)) . '/shared/database_connection.php';
require_once dirname(dirname(__DIR__)) . '/shared/permissions.php';
require_once dirname(dirname(__DIR__)) . '/shared/rbac.php';
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
 * Check if user has system-administrator privileges.
 *
 * CHANGELOG (branch-scoping upgrade): this previously checked for the
 * literal role string 'administrator', which has never actually been a
 * value in staff.role (the real ENUM/seed values are 'admin', 'superadmin',
 * 'admin_branch', etc.) — so this function was effectively always false in
 * production. Fixed to check against the roles actually seeded as full
 * system administrators.
 */
function isAdmin() {
    $role = strtolower($_SESSION['role'] ?? '');
    return $role === 'admin';
}

/**
 * Require admin privileges
 */
function requireAdmin() {
    requireAuth();
    if (!isAdmin()) {
        http_response_code(403);
        die('Access denied. System-administrator privileges required.');
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
    // Intentionally empty. ARMIS must never manufacture a privileged session.
    // Development mode may change diagnostics, but it must not bypass authentication.
}

/**
 * Log user activity
 */
function logActivity($action, $details = '') {
    $user = getCurrentUser();
    if (!$user) return;
    
    try {
        $sql = "INSERT INTO activity_log (user_id, username, action, details, ip_address, user_agent, createdAt) 
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
            createdAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user_id (user_id),
            INDEX idx_action (action),
            INDEX idx_created_at (createdAt)
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

// SECURITY: never create a synthetic/default authenticated session.
