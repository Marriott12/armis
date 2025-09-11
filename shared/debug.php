<?php
/**
 * Debug helper functions for tracking session and login issues
 */

/**
 * Log debug information to error log
 * 
 * @param string $message The message to log
 * @param mixed $data Optional data to include in log
 */
function debug_log($message, $data = null) {
    $log = "[DEBUG] " . $message;
    
    if ($data !== null) {
        if (is_array($data) || is_object($data)) {
            $log .= " - Data: " . print_r($data, true);
        } else {
            $log .= " - Data: " . $data;
        }
    }
    
    error_log($log);
}

/**
 * Log current session data to error log
 */
function debug_session() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    debug_log("Current Session Data", $_SESSION);
}

/**
 * Check if session variables are properly set
 * 
 * @return array Array of missing or invalid session variables
 */
function check_session_integrity() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    $requiredVars = [
        'user_id' => 'numeric',
        'username' => 'string',
        'role' => 'string'
    ];
    
    $issues = [];
    
    foreach ($requiredVars as $var => $type) {
        if (!isset($_SESSION[$var])) {
            $issues[] = "Missing: {$var}";
        } else if ($type === 'numeric' && !is_numeric($_SESSION[$var])) {
            $issues[] = "Invalid type for {$var}: Expected numeric, got " . gettype($_SESSION[$var]);
        } else if ($type === 'string' && !is_string($_SESSION[$var])) {
            $issues[] = "Invalid type for {$var}: Expected string, got " . gettype($_SESSION[$var]);
        }
    }
    
    return $issues;
}
