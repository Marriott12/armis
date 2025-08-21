<?php
/**
 * ARMIS File Cleanup Script
 * Identifies and removes unused files from the system
 * 
 * RUN WITH CAUTION - This will permanently delete files
 */

require_once 'config.php';

// Files that are definitely safe to delete (debug, test, documentation)
$safe_to_delete = [
    // Debug files
    'debug_database.php',
    'debug_db.php',
    'debug_tables.php',
    'quick_debug.php',
    'quick_db_test.php',
    'admin_branch/debug_edit_log.php',
    'admin_branch/debug_form_data.php',
    'admin_branch/debug_photo_paths.php',
    'users/debug_cv.php',
    'users/debug_session.php',
    
    // Test files
    'test_database_setup.php',
    'test_login_session.php',
    'test_central_access.php',
    'test_rbac.php',
    'test_rbac_live.php',
    'test_db.php',
    'test_profile.php',
    'test_auth.php',
    'test_dashboard_hierarchy.php',
    'test_email.php',
    'test_login.php',
    'test_corps_dropdown.php',
    'test_edit_staff_functionality.php',
    'login_test.php',
    'admin_branch/test_dashboard.php',
    'admin_branch/final_test.php',
    'users/test_profile_system.php',
    'users/test_photo_upload.php',
    
    // Validation/Check files
    'validate_dashboard.php',
    'validate_fixes.php',
    'check_columns.php',
    'check_staff_columns.php',
    'check_tables.php',
    'check_admin_role.php',
    'check_db.php',
    'check_ranks.php',
    'check_staff_table.php',
    'users/check_contact_table.php',
    
    // Database setup/migration files (one-time use)
    'setup_database.php',
    'setup_tables.sql',
    'setup_users.php',
    'database_enhancements.php',
    'run_profile_migration.php',
    'fix_missing_columns.php',
    'fix_staff_columns.php',
    'fix_profile_column.php',
    'add_corps_column.php',
    'change_temp_password.php',
    
    // Access demo (development only)
    'access_demo.php',
    
    // System status/monitoring (can be removed if not needed)
    'system_status.php',
    
    // Documentation files (can be archived instead of deleted)
    'ACADEMIC_CONDITIONAL_FIELDS_COMPLETE.md',
    'BUG_FIXES_REPORT.md',
    'CLEANUP_DOCUMENTATION.md',
    'CLEANUP_REPORT.md',
    'COMPREHENSIVE_ENHANCEMENT_RECOMMENDATIONS.md',
    'DATABASE_SCHEMA_ALIGNMENT_COMPLETE.md',
    'EXECUTIVE_ENHANCEMENT_SUMMARY.md',
    'IMPLEMENTATION_COMPLETE.md',
    'IMPLEMENTATION_ROADMAP.md',
    'PERSONAL_FORM_ENHANCEMENTS.md',
    'PROJECT_COMPLETION_SUMMARY.md',
    'admin_branch/DASHBOARD_CLEANUP_REPORT.md',
    'admin_branch/DASHBOARD_STRUCTURE_ANALYSIS.md',
    'admin_branch/DYNAMIC_DASHBOARD_DOCUMENTATION.md',
    
    // Old/backup database connection
    'admin_branch/includes/db_connection_old.php',
    
    // Search debug log
    'admin_branch/search_staff_debug.log',
    
    // Cleanup script itself (after running)
    'cleanup_script.ps1'
];

// Files that need careful consideration (may be used)
$review_needed = [
    'admin_branch/database_verification.php',
    'admin_branch/operations_options.php',
    'admin_branch/live_notifications.php',
    'admin_branch/notificationsPanel.php',
    'admin_branch/mark_notification_read.php',
    'unauthorized.php',
    'reset_password.php'
];

// Create documentation directories
$docs_dir = __DIR__ . '/archived_docs';
$backups_dir = __DIR__ . '/backups/deleted_files_' . date('Y-m-d_H-i-s');

if (!is_dir($docs_dir)) {
    mkdir($docs_dir, 0755, true);
}
if (!is_dir($backups_dir)) {
    mkdir($backups_dir, 0755, true);
}

echo "ARMIS File Cleanup Script\n";
echo "========================\n\n";

$deleted_count = 0;
$archived_count = 0;
$total_size_freed = 0;

// Process safe-to-delete files
foreach ($safe_to_delete as $file) {
    $full_path = __DIR__ . '/' . $file;
    
    if (file_exists($full_path)) {
        $file_size = filesize($full_path);
        
        // Archive .md files instead of deleting
        if (pathinfo($file, PATHINFO_EXTENSION) === 'md') {
            $archive_path = $docs_dir . '/' . basename($file);
            if (copy($full_path, $archive_path)) {
                unlink($full_path);
                echo "ARCHIVED: $file ($file_size bytes)\n";
                $archived_count++;
            } else {
                echo "ERROR: Could not archive $file\n";
            }
        } else {
            // Backup to backups directory first
            $backup_path = $backups_dir . '/' . str_replace('/', '_', $file);
            if (copy($full_path, $backup_path)) {
                unlink($full_path);
                echo "DELETED: $file ($file_size bytes)\n";
                $deleted_count++;
                $total_size_freed += $file_size;
            } else {
                echo "ERROR: Could not backup/delete $file\n";
            }
        }
    } else {
        echo "NOT FOUND: $file\n";
    }
}

echo "\n";
echo "Files requiring manual review:\n";
echo "===============================\n";
foreach ($review_needed as $file) {
    $full_path = __DIR__ . '/' . $file;
    if (file_exists($full_path)) {
        $file_size = filesize($full_path);
        echo "REVIEW: $file ($file_size bytes)\n";
    }
}

echo "\n";
echo "CLEANUP SUMMARY:\n";
echo "================\n";
echo "Files deleted: $deleted_count\n";
echo "Files archived: $archived_count\n";
echo "Space freed: " . round($total_size_freed / 1024, 2) . " KB\n";
echo "\nBackups created in: $backups_dir\n";
echo "Documentation archived in: $docs_dir\n";

// Clean up empty directories
$empty_dirs = [];
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator(__DIR__),
    RecursiveIteratorIterator::CHILD_FIRST
);

foreach ($iterator as $file) {
    if ($file->isDir() && !in_array($file->getBasename(), ['.', '..'])) {
        $files = scandir($file->getPathname());
        if (count($files) <= 2) { // Only . and ..
            $empty_dirs[] = $file->getPathname();
        }
    }
}

if (!empty($empty_dirs)) {
    echo "\nEmpty directories found (not automatically removed):\n";
    foreach ($empty_dirs as $dir) {
        echo "EMPTY DIR: " . str_replace(__DIR__ . '/', '', $dir) . "\n";
    }
}

echo "\nCleanup completed successfully!\n";
?>
