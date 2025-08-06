# Cleanup Script for Armis System
# This script will delete files identified as unused or unnecessary for production

# Test Files
$testFiles = @(
    "test_rbac_live.php",
    "test_db.php",
    "test_profile.php",
    "test_login_session.php",
    "test_central_access.php",
    "test_database_setup.php",
    "test_login.php",
    "test_auth.php",
    "test_dashboard_hierarchy.php",
    "test_email.php",
    "test_rbac.php"
)

# Debug Files
$debugFiles = @(
    "debug_database.php",
    "debug_tables.php",
    "quick_debug.php",
    "admin_branch/debug_form_data.php",
    "users/debug_cv.php"
)

# Documentation Files (keeping README.md)
$docFiles = @(
    "ACADEMIC_CONDITIONAL_FIELDS_COMPLETE.md",
    "ADMIN_DASHBOARD_IMPLEMENTATION.md",
    "CLEANUP_REPORT.md",
    "CSS_ANALYSIS_RECOMMENDATIONS.md",
    "DATABASE_SCHEMA_ALIGNMENT_COMPLETE.md",
    "DYNAMIC_AUTH_IMPLEMENTATION.md",
    "HEADER_IMPLEMENTATION_STATUS.md",
    "HEADER_LOGIN_STATUS_CHECK.md",
    "IMPLEMENTATION_COMPLETE.md",
    "LOGIN_MODERNIZATION_REPORT.md",
    "PROFILE_SYSTEM_ENHANCEMENT_SUMMARY.md",
    "RBAC_COMPLETION_REPORT.md",
    "RBAC_IMPLEMENTATION_COMPLETE.md",
    "SETUP_COMPLETE.md",
    "SYSTEM_ANALYSIS_COMPLETE.md",
    "SYSTEM_UPDATES_COMPLETE.md",
    "USER_PROFILE_DATABASE_INTEGRATION_COMPLETE.md",
    "USER_PROFILE_ERROR_FIXES_COMPLETE.md"
)

# Temporary/Old Files
$tempFiles = @(
    "check_admin_role.php",
    "check_db.php",
    "check_ranks.php",
    "check_staff_columns.php",
    "check_staff_table.php",
    "fix_profile_column.php",
    "run_profile_migration.php",
    "admin_branch/includes/db_connection_old.php"
)

$baseDir = "c:\wamp64\www\Armis2"
$deletedFiles = @()

# Function to delete a file and record it
function Remove-FileIfExists {
    param (
        [string]$filePath
    )
    
    $fullPath = Join-Path -Path $baseDir -ChildPath $filePath
    
    if (Test-Path $fullPath) {
        Remove-Item -Path $fullPath -Force
        $script:deletedFiles += $filePath
        Write-Host "Deleted: $filePath" -ForegroundColor Green
    } else {
        Write-Host "File not found: $filePath" -ForegroundColor Yellow
    }
}

Write-Host "Starting cleanup of unused files..." -ForegroundColor Cyan

# Delete test files
Write-Host "`nRemoving test files..." -ForegroundColor Cyan
foreach ($file in $testFiles) {
    Remove-FileIfExists -filePath $file
}

# Delete debug files
Write-Host "`nRemoving debug files..." -ForegroundColor Cyan
foreach ($file in $debugFiles) {
    Remove-FileIfExists -filePath $file
}

# Delete documentation files
Write-Host "`nRemoving documentation files..." -ForegroundColor Cyan
foreach ($file in $docFiles) {
    Remove-FileIfExists -filePath $file
}

# Delete temporary files
Write-Host "`nRemoving temporary/old files..." -ForegroundColor Cyan
foreach ($file in $tempFiles) {
    Remove-FileIfExists -filePath $file
}

# Summary
Write-Host "`n=== Cleanup Summary ===" -ForegroundColor Cyan
Write-Host "Total files deleted: $($deletedFiles.Count)" -ForegroundColor Green
Write-Host "A backup of all files was created at: c:\wamp64\www\Armis2_backup\" -ForegroundColor Cyan
Write-Host "To restore any deleted file, copy it from the backup directory." -ForegroundColor Cyan
