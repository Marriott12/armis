<?php
/**
 * ARMIS Production Configuration
 * Army Resource Management Information System
 */

// Production Environment Settings
define('ARMIS_ENV', 'production');
define('ARMIS_VERSION', '1.0.0');
define('ARMIS_NAME', 'Army Resource Management Information System');
define('ARMIS_ROOT', __DIR__);

// Database Configuration
// FIX: this used to hardcode DB_HOST/DB_USER/DB_PASS itself (root /
// empty password) using `if (!defined())` guards. shared/database_connection.php
// uses the exact same guard pattern to pull these from .env instead —
// but whichever file runs first wins the define(), and several pages
// (e.g. change_temp_password.php) required this file before
// shared/database_connection.php. That meant the .env-based credentials
// were silently skipped and the hardcoded local-dev fallback was used
// instead, regardless of what was actually configured in .env.
// Requiring the real source of truth here, before anything has a
// chance to define these constants a different way, makes the outcome
// the same no matter which file loads first.
require_once __DIR__ . '/shared/database_connection.php';

// Application Settings
define('ARMIS_TIMEZONE', 'UTC');
define('ARMIS_LANG', 'en');
define('ARMIS_THEME', 'military');

// Security Settings
// FIX: this constant used to say 1 hour but nothing in the codebase
// actually enforced it — admin_branch had its own separate, unrelated
// 20-minute timeout hardcoded in includes/auth.php instead. Now that
// shared/session_guard.php reads this constant as the single source of
// truth (see admin_branch/includes/auth.php), the value here needs to
// match what was actually protecting personnel data, not silently
// extend everyone's session by 3x. Override via SESSION_TIMEOUT_SECONDS
// in .env if a different value is needed.
define('SESSION_TIMEOUT', (int) env_get('SESSION_TIMEOUT_SECONDS', '1200')); // 20 minutes
define('CSRF_TOKEN_EXPIRY', 1800); // 30 minutes
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 900); // 15 minutes

// File Upload Settings
define('MAX_UPLOAD_SIZE', 10 * 1024 * 1024); // 10MB
define('ALLOWED_FILE_TYPES', 'jpg,jpeg,png,pdf,doc,docx,xls,xlsx');

// System Paths
define('ARMIS_UPLOADS_DIR', ARMIS_ROOT . '/uploads');
define('ARMIS_LOGS_DIR', ARMIS_ROOT . '/logs');
define('ARMIS_CACHE_DIR', ARMIS_ROOT . '/cache');

// Production Security Settings
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', ARMIS_LOGS_DIR . '/php_errors.log');

// Session Configuration - Important: These must be set before session_start()
// They will cause warnings if session is already started
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', 0); // Set to 1 for HTTPS
    ini_set('session.use_strict_mode', 1);
    ini_set('session.cookie_samesite', 'Strict');
}

// Production URLs (Update for your domain)
define('ARMIS_BASE_URL', 'http://localhost/Armis2');
define('ARMIS_ADMIN_EMAIL', 'admin@yourunit.mil');

// Module Settings
define('ENABLE_ADMIN_MODULE', true);
define('ENABLE_COMMAND_MODULE', true);
define('ENABLE_TRAINING_MODULE', true);
define('ENABLE_OPERATIONS_MODULE', true);

// Logging Levels
define('LOG_LEVEL_ERROR', 1);
define('LOG_LEVEL_WARNING', 2);
define('LOG_LEVEL_INFO', 3);
define('LOG_LEVEL_DEBUG', 4);
define('CURRENT_LOG_LEVEL', LOG_LEVEL_ERROR); // Only errors in production

/**
 * Get configuration value
 */
function get_config($key, $default = null) {
    return defined($key) ? constant($key) : $default;
}

/**
 * Check if module is enabled
 */
function is_module_enabled($module) {
    $constant = 'ENABLE_' . strtoupper($module) . '_MODULE';
    return defined($constant) && constant($constant) === true;
}

// Include file restoration prevention configuration
require_once __DIR__ . '/config/file_restoration_prevention.php';
?>
