<?php
/**
 * ARMIS Workflow Module Authentication
 * Extends core authentication for workflow-specific requirements
 */

// Include core authentication
require_once dirname(__DIR__, 2) . '/shared/session_init.php';
require_once dirname(__DIR__, 2) . '/shared/rbac.php';

/**
 * Require workflow module authentication
 */
function requireAuth() {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['username'])) {
        header('Location: ' . ARMIS_BASE_URL . '/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
        exit();
    }
}

/**
 * Require workflow module access
 */
function requireModuleAccess($module = 'workflow') {
    if (!hasModuleAccess($module)) {
        header('Location: ' . ARMIS_BASE_URL . '/unauthorized.php?module=' . $module);
        exit();
    }
}

/**
 * Log workflow-specific activities
 */
function logWorkflowActivity($action, $description, $entityType = null, $entityId = null) {
    try {
        $pdo = getDbConnection();
        
        $stmt = $pdo->prepare("
            INSERT INTO audit_logs (
                user_id, action, entity_type, entity_id, 
                ip_address, user_agent, module, severity, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, 'workflow', 'LOW', NOW())
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
        error_log("Workflow activity logging failed: " . $e->getMessage());
    }
}

/**
 * Check workflow permissions for specific actions
 */
function canCreateWorkflows() {
    return hasModuleAccess('workflow') && hasMinimumLevel(50);
}

function canManageTemplates() {
    return hasModuleAccess('workflow') && (
        $_SESSION['role'] === 'admin' || 
        $_SESSION['role'] === 'command' ||
        hasMinimumLevel(70)
    );
}

function canApproveWorkflows() {
    return hasModuleAccess('workflow') && (
        $_SESSION['role'] === 'admin' || 
        $_SESSION['role'] === 'command' ||
        hasMinimumLevel(60)
    );
}

function canViewAllWorkflows() {
    return hasModuleAccess('workflow') && (
        $_SESSION['role'] === 'admin' || 
        $_SESSION['role'] === 'command' ||
        hasMinimumLevel(70)
    );
}
?>