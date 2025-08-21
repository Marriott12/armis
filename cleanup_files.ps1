# ARMIS File Cleanup Script
# This script removes unused debug, test, and documentation files

Write-Host "ARMIS File Cleanup Starting..." -ForegroundColor Green

$baseDir = "c:\wamp64\www\Armis2"

# List of files to delete
$filesToDelete = @(
    # Debug files
    "debug_database.php",
    "debug_db.php", 
    "debug_tables.php",
    "quick_debug.php",
    "quick_db_test.php",
    "admin_branch\debug_edit_log.php",
    "admin_branch\debug_form_data.php",
    "admin_branch\debug_photo_paths.php",
    "users\debug_cv.php",
    "users\debug_session.php",
    
    # Test files
    "test_database_setup.php",
    "test_login_session.php",
    "test_central_access.php",
    "test_rbac.php",
    "test_rbac_live.php",
    "test_db.php",
    "test_profile.php", 
    "test_auth.php",
    "test_dashboard_hierarchy.php",
    "test_email.php",
    "test_login.php",
    "test_corps_dropdown.php",
    "test_edit_staff_functionality.php",
    "login_test.php",
    "admin_branch\test_dashboard.php",
    "admin_branch\final_test.php",
    "users\test_profile_system.php",
    "users\test_photo_upload.php",
    
    # Validation/Check files
    "validate_dashboard.php",
    "validate_fixes.php",
    "check_columns.php",
    "check_staff_columns.php", 
    "check_tables.php",
    "check_admin_role.php",
    "check_db.php",
    "check_ranks.php",
    "check_staff_table.php",
    "users\check_contact_table.php",
    
    # Database setup files (one-time use)
    "setup_database.php",
    "database_enhancements.php",
    "run_profile_migration.php",
    "fix_missing_columns.php",
    "fix_staff_columns.php",
    "fix_profile_column.php",
    "add_corps_column.php",
    "change_temp_password.php",
    
    # Access demo
    "access_demo.php",
    
    # System monitoring
    "system_status.php",
    
    # Old database connection
    "admin_branch\includes\db_connection_old.php",
    
    # Log files
    "admin_branch\search_staff_debug.log",
    
    # Cleanup scripts
    "cleanup_script.ps1",
    "cleanup_unused_files.php"
)

# Documentation files to move to docs folder
$docsToArchive = @(
    "ACADEMIC_CONDITIONAL_FIELDS_COMPLETE.md",
    "BUG_FIXES_REPORT.md", 
    "CLEANUP_DOCUMENTATION.md",
    "CLEANUP_REPORT.md",
    "COMPREHENSIVE_ENHANCEMENT_RECOMMENDATIONS.md",
    "DATABASE_SCHEMA_ALIGNMENT_COMPLETE.md",
    "EXECUTIVE_ENHANCEMENT_SUMMARY.md",
    "IMPLEMENTATION_COMPLETE.md",
    "IMPLEMENTATION_ROADMAP.md",
    "PERSONAL_FORM_ENHANCEMENTS.md",
    "PROJECT_COMPLETION_SUMMARY.md",
    "admin_branch\DASHBOARD_CLEANUP_REPORT.md",
    "admin_branch\DASHBOARD_STRUCTURE_ANALYSIS.md",
    "admin_branch\DYNAMIC_DASHBOARD_DOCUMENTATION.md"
)

# Create archive directory
$archiveDir = "$baseDir\archived_documentation"
if (!(Test-Path $archiveDir)) {
    New-Item -ItemType Directory -Path $archiveDir -Force | Out-Null
    Write-Host "Created archive directory: $archiveDir" -ForegroundColor Blue
}

# Archive documentation files
$archivedCount = 0
foreach ($doc in $docsToArchive) {
    $sourcePath = Join-Path $baseDir $doc
    if (Test-Path $sourcePath) {
        $fileName = Split-Path $doc -Leaf
        $destPath = Join-Path $archiveDir $fileName
        try {
            Move-Item $sourcePath $destPath -Force
            Write-Host "ARCHIVED: $doc" -ForegroundColor Blue
            $archivedCount++
        } catch {
            Write-Host "ERROR archiving: $doc - $($_.Exception.Message)" -ForegroundColor Red
        }
    }
}

# Delete unnecessary files
$deletedCount = 0
$totalSizeFreed = 0

foreach ($file in $filesToDelete) {
    $filePath = Join-Path $baseDir $file
    if (Test-Path $filePath) {
        try {
            $fileSize = (Get-Item $filePath).Length
            Remove-Item $filePath -Force
            Write-Host "DELETED: $file ($fileSize bytes)" -ForegroundColor Green
            $deletedCount++
            $totalSizeFreed += $fileSize
        } catch {
            Write-Host "ERROR deleting: $file - $($_.Exception.Message)" -ForegroundColor Red
        }
    }
}

# Summary
Write-Host "`n=== CLEANUP SUMMARY ===" -ForegroundColor Yellow
Write-Host "Files deleted: $deletedCount" -ForegroundColor Green
Write-Host "Files archived: $archivedCount" -ForegroundColor Blue  
Write-Host "Space freed: $([math]::Round($totalSizeFreed / 1KB, 2)) KB" -ForegroundColor Green

Write-Host "`nCleanup completed!" -ForegroundColor Green
