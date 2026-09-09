<?php
/**
 * Admin Session Validator
 * 
 * This script checks and fixes admin session variables to ensure proper dashboard access.
 * It should be included at the top of admin/index.php to validate the admin session.
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Validate and fix admin session variables
 * 
 * @return bool True if the session is valid for admin, false otherwise
 */
function validateAdminSession() {
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        debug_log("Admin session validation failed: No user_id");
        return false;
    }
    
    // Check role
    if (!isset($_SESSION['role'])) {
        debug_log("Admin session validation failed: No role set");
        return false;
    }
    
    // Normalize admin role (fix case sensitivity issues)
    if (strtolower($_SESSION['role']) === 'admin' || 
        strtolower($_SESSION['role']) === 'administrator') {
        
        // Log the original role value
        debug_log("Admin session validation: Original role value: " . $_SESSION['role']);
        
        // Leave the role as is if it's capitalized - don't force lowercase
        // This preserves what's shown in the UI but allows case-insensitive checks
        // Uncomment the line below if you want to force lowercase
        // $_SESSION['role'] = 'admin';
        
        // Ensure all required admin session variables are set
        $requiredVars = [
            'username', 'rank', 'name', 'unit', 'last_login_time'
        ];
        
        foreach ($requiredVars as $var) {
            if (!isset($_SESSION[$var])) {
                debug_log("Admin session fix: Setting missing {$var}");
                
                // Set default value if missing
                switch ($var) {
                    case 'username':
                        $_SESSION[$var] = 'admin';
                        break;
                    case 'rank':
                        $_SESSION[$var] = 'Colonel';
                        break;
                    case 'name':
                        $_SESSION[$var] = 'System Administrator';
                        break;
                    case 'unit':
                        $_SESSION[$var] = 'HQ Command';
                        break;
                    case 'last_login_time':
                        $_SESSION[$var] = time();
                        break;
                    default:
                        $_SESSION[$var] = 'default_value';
                        break;
                }
            }
        }
        
        debug_log("Admin session validated and fixed");
        return true;
    }
    
    debug_log("Admin session validation failed: Non-admin role: " . $_SESSION['role']);
    return false;
}

/**
 * Log debug information if debug.php is not included
 */
if (!function_exists('debug_log')) {
    function debug_log($message, $data = null) {
        $log = "[ADMIN DEBUG] " . $message;
        
        if ($data !== null) {
            if (is_array($data) || is_object($data)) {
                $log .= " - Data: " . print_r($data, true);
            } else {
                $log .= " - Data: " . $data;
            }
        }
        
        error_log($log);
    }
}

// If this file is accessed directly, output debugging information
if (basename($_SERVER['SCRIPT_NAME']) == basename(__FILE__)) {
    header('Content-Type: text/plain');
    echo "Admin Session Validator\n";
    echo "===================\n\n";
    
    if (validateAdminSession()) {
        echo "Admin session validated successfully\n";
        echo "Session Variables:\n";
        print_r($_SESSION);
    } else {
        echo "Admin session validation failed\n";
        echo "Current Session Variables:\n";
        print_r($_SESSION);
    }
    
    exit;
}
