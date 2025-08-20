<?php
/**
 * ARMIS Messaging Module Authentication
 * Extends core authentication for messaging-specific requirements
 */

// Include core authentication
require_once dirname(__DIR__, 2) . '/shared/session_init.php';
require_once dirname(__DIR__, 2) . '/shared/rbac.php';

/**
 * Require messaging module authentication
 */
function requireAuth() {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['username'])) {
        header('Location: ' . ARMIS_BASE_URL . '/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
        exit();
    }
}

/**
 * Require messaging module access
 */
function requireModuleAccess($module = 'messaging') {
    if (!hasModuleAccess($module)) {
        header('Location: ' . ARMIS_BASE_URL . '/unauthorized.php?module=' . $module);
        exit();
    }
}

/**
 * Log messaging-specific activities
 */
function logMessagingActivity($action, $description, $entityType = null, $entityId = null) {
    try {
        $pdo = getDbConnection();
        
        $stmt = $pdo->prepare("
            INSERT INTO audit_logs (
                user_id, action, entity_type, entity_id, 
                ip_address, user_agent, module, severity, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, 'messaging', 'LOW', NOW())
        ");
        
        $stmt->execute([
            $_SESSION['user_id'],
            $action,
            $entityType,
            $entityId,
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ]);
        
        // Also log to activity log if it exists
        if (function_exists('logActivity')) {
            logActivity($action, $description);
        }
        
    } catch (Exception $e) {
        error_log("Messaging activity logging failed: " . $e->getMessage());
    }
}

/**
 * Check messaging permissions for specific actions
 */
function canSendMessages() {
    return hasModuleAccess('messaging') && hasMinimumLevel(10);
}

function canCreateAnnouncements() {
    return hasModuleAccess('messaging') && (
        $_SESSION['role'] === 'admin' || 
        $_SESSION['role'] === 'command' ||
        hasMinimumLevel(60)
    );
}

function canManageDocuments() {
    return hasModuleAccess('messaging') && hasMinimumLevel(30);
}

function canViewAllMessages() {
    return hasModuleAccess('messaging') && (
        $_SESSION['role'] === 'admin' || 
        $_SESSION['role'] === 'command' ||
        hasMinimumLevel(80)
    );
}

function canModerateMessages() {
    return hasModuleAccess('messaging') && (
        $_SESSION['role'] === 'admin' || 
        hasMinimumLevel(70)
    );
}
?>