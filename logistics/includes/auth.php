<?php
/**
 * ARMIS Logistics Module Authentication
 * Extends core authentication for logistics-specific requirements
 */

// Include core authentication
require_once dirname(__DIR__, 2) . '/shared/session_init.php';
require_once dirname(__DIR__, 2) . '/shared/rbac.php';

/**
 * Require logistics module authentication
 */
function requireAuth() {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['username'])) {
        header('Location: ' . ARMIS_BASE_URL . '/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
        exit();
    }
}

/**
 * Require logistics module access
 */
function requireModuleAccess($module = 'logistics') {
    if (!hasModuleAccess($module)) {
        header('Location: ' . ARMIS_BASE_URL . '/unauthorized.php?module=' . $module);
        exit();
    }
}

/**
 * Log logistics-specific activities
 */
function logLogisticsActivity($action, $description, $entityType = null, $entityId = null) {
    try {
        $pdo = getDbConnection();
        
        $stmt = $pdo->prepare("
            INSERT INTO audit_logs (
                user_id, action, entity_type, entity_id, 
                ip_address, user_agent, module, severity, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, 'logistics', 'LOW', NOW())
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
        error_log("Logistics activity logging failed: " . $e->getMessage());
    }
}

/**
 * Check logistics permissions for specific actions
 */
function canManageInventory() {
    return hasModuleAccess('logistics') && (
        $_SESSION['role'] === 'admin' || 
        $_SESSION['role'] === 'logistics' ||
        hasMinimumLevel(60)
    );
}

function canApproveRequisitions() {
    return hasModuleAccess('logistics') && (
        $_SESSION['role'] === 'admin' || 
        $_SESSION['role'] === 'command' ||
        hasMinimumLevel(70)
    );
}

function canManageVendors() {
    return hasModuleAccess('logistics') && (
        $_SESSION['role'] === 'admin' || 
        $_SESSION['role'] === 'finance' ||
        hasMinimumLevel(60)
    );
}

function canViewReports() {
    return hasModuleAccess('logistics') && hasMinimumLevel(50);
}
?>