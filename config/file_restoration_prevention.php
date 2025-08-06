<?php
/**
 * File Restoration Prevention Configuration
 * 
 * This file contains a list of files that should not be restored to the system.
 * It is used by various security checks to prevent these files from being uploaded.
 * 
 * @version 1.0
 * @date August 2, 2025
 */

// Configuration array of patterns for files that should be blocked from restoration
$BLOCKED_FILE_PATTERNS = [
    // Test files
    '/^test_.*\.php$/',
    
    // Debug files
    '/^debug_.*\.php$/',
    '/^quick_debug\.php$/',
    
    // Documentation files
    '/^.*_COMPLETE\.md$/',
    '/^.*_IMPLEMENTATION.*\.md$/',
    '/^.*_REPORT\.md$/',
    '/^.*_CHECK\.md$/',
    '/^.*_STATUS\.md$/',
    '/^SYSTEM_.*\.md$/',
    '/^CLEANUP_REPORT\.md$/',
    '/^CSS_ANALYSIS_RECOMMENDATIONS\.md$/',
    
    // Temporary/Old files
    '/^check_.*\.php$/',
    '/^fix_.*\.php$/',
    '/^run_.*_migration\.php$/',
    '/.*_old\.php$/',
];

// Specific files to block (exact matches)
$BLOCKED_FILES = [
    // Test files
    'test_rbac_live.php',
    'test_db.php',
    'test_profile.php',
    'test_login_session.php',
    'test_central_access.php',
    'test_database_setup.php',
    'test_login.php',
    'test_auth.php',
    'test_dashboard_hierarchy.php',
    'test_email.php',
    'test_rbac.php',
    
    // Debug files
    'debug_database.php',
    'debug_tables.php',
    'quick_debug.php',
    'admin_branch/debug_form_data.php',
    'users/debug_cv.php',
    
    // Documentation files
    'ACADEMIC_CONDITIONAL_FIELDS_COMPLETE.md',
    'ADMIN_DASHBOARD_IMPLEMENTATION.md',
    'CLEANUP_REPORT.md',
    'CSS_ANALYSIS_RECOMMENDATIONS.md',
    'DATABASE_SCHEMA_ALIGNMENT_COMPLETE.md',
    'DYNAMIC_AUTH_IMPLEMENTATION.md',
    'HEADER_IMPLEMENTATION_STATUS.md',
    'HEADER_LOGIN_STATUS_CHECK.md',
    'IMPLEMENTATION_COMPLETE.md',
    'LOGIN_MODERNIZATION_REPORT.md',
    'PROFILE_SYSTEM_ENHANCEMENT_SUMMARY.md',
    'RBAC_COMPLETION_REPORT.md',
    'RBAC_IMPLEMENTATION_COMPLETE.md',
    'SETUP_COMPLETE.md',
    'SYSTEM_ANALYSIS_COMPLETE.md',
    'SYSTEM_UPDATES_COMPLETE.md',
    'USER_PROFILE_DATABASE_INTEGRATION_COMPLETE.md',
    'USER_PROFILE_ERROR_FIXES_COMPLETE.md',
    
    // Temporary/Old files
    'check_admin_role.php',
    'check_db.php',
    'check_ranks.php',
    'check_staff_columns.php',
    'check_staff_table.php',
    'fix_profile_column.php',
    'run_profile_migration.php',
    'admin_branch/includes/db_connection_old.php',
];

/**
 * Check if a file is blocked from being restored
 * 
 * @param string $filename The name of the file to check
 * @return bool True if the file is blocked, false otherwise
 */
function isFileBlockedFromRestoration($filename) {
    global $BLOCKED_FILE_PATTERNS, $BLOCKED_FILES;
    
    // Check if file is in the list of specific blocked files
    if (in_array($filename, $BLOCKED_FILES)) {
        return true;
    }
    
    // Check if file matches any of the blocked patterns
    foreach ($BLOCKED_FILE_PATTERNS as $pattern) {
        if (preg_match($pattern, $filename)) {
            return true;
        }
    }
    
    return false;
}

/**
 * Add hook for blocking file uploads of restricted files
 * This function can be used in file upload handlers
 * 
 * @param string $filename The name of the file being uploaded
 * @return bool True if the upload should be allowed, false if it should be blocked
 */
function validateFileUpload($filename) {
    if (isFileBlockedFromRestoration($filename)) {
        // Log the attempt to restore a blocked file
        error_log("Attempt to upload blocked file: " . $filename);
        return false;
    }
    return true;
}
