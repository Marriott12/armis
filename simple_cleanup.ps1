# ARMIS System Cleanup Script - Production Ready
Write-Host "ARMIS System - Comprehensive Unused Files Cleanup" -ForegroundColor Yellow
Write-Host "This script will remove development, migration, and documentation files." -ForegroundColor Cyan

$rootPath = "c:\wamp64\www\Armis2"

# Files to remove - organized by category
$setupFiles = @(
    "add_first_login_support.php",
    "check_nok_column.php", 
    "check_schema_for_temp_password.php",
    "check_staff_columns.php",
    "check_staff_structure.php", 
    "check_tables.php",
    "check_users.php",
    "fix_family_table.php",
    "fix_family_table_web.php", 
    "fix_missing_columns.php",
    "fix_staff_columns.php",
    "run_enhancements.php",
    "setup_admin.php",
    "setup_db_trigger.php", 
    "setup_email_template.php",
    "update_email_template.php",
    "update_role_schema.php",
    "update_role_schema_web.php",
    "validate_dashboard.php",
    "validate_fixes.php",
    "database_enhancements.php",
    "staff_columns_info.php",
    "syntax_check.php"
)

$adminBranchFiles = @(
    "add_missing_columns.php",
    "check_all_field_names.php",
    "check_database_structure.php", 
    "check_exact_columns.php",
    "check_rank_display.php",
    "check_search.php",
    "check_staff_table.php",
    "check_units_ranks_structure.php",
    "enhance_appointments.php",
    "validate_appointments_fixes.php",
    "verify_system.php",
    "field_coverage_check.php",
    "comprehensive_field_validation.php"
)

$documentationFiles = @(
    "ADMIN_ACCESS_CLEANUP_REPORT.md",
    "ADMIN_REDIRECT_FIX_SUMMARY.md", 
    "ALL_ISSUES_FIXED_SUMMARY.md",
    "APPOINTMENT_ENHANCEMENT_CHANGELOG.md",
    "COMPLETE_FIELD_NAME_FIXES.md", 
    "DATABASE_SCHEMA_VERIFICATION_COMPLETE.md",
    "DYNAMIC_FIELDS_ENHANCEMENT.md",
    "FIELD_NAME_STANDARDIZATION_COMPLETE.md",
    "FIRST_LOGIN_IMPLEMENTATION_COMPLETE.md",
    "JAVASCRIPT_FIXES_COMPLETE.md",
    "JAVASCRIPT_SYNTAX_FIXES_COMPLETE.md", 
    "NEXT_RANK_AUTOLOAD_FIXED.md",
    "PERMISSIONS_ENHANCEMENT_COMPLETE.md",
    "PHP_SYNTAX_ERROR_FIXED.md",
    "PRODUCTION_READINESS_REPORT.md",
    "PROFILE_RANK_FIXES_ANALYSIS.md", 
    "RBAC_COMPATIBILITY_SOLUTION.md",
    "SECURITY_CLEANUP_REPORT.md",
    "SIZE_DROPDOWN_ENHANCEMENT.md",
    "STAFF_CREATION_COMPLETE.md",
    "STAFF_CREATION_SUCCESS_COMPLETE.md", 
    "SYSTEM_SECURITY_CLEANUP.md",
    "USER_PROFILE_DISPLAY_FIX.md",
    "PERMANENTLY_DELETED_FILES.txt"
)

$backupFiles = @(
    "promote_staff_fixed.php",
    "emergency_admin.php"
)

$cleanupScripts = @(
    "cleanup_system.php",
    "cleanup_system.ps1", 
    "secure_cleanup.php",
    "secure_cleanup.ps1",
    "production_cleanup.php",
    "cleanup_test_files.ps1"
)

$removedCount = 0
$totalSize = 0

# Function to remove files safely
function Remove-FileIfExists {
    param($FilePath, $DisplayName)
    
    if (Test-Path $FilePath) {
        try {
            $fileSize = (Get-Item $FilePath).Length
            Remove-Item $FilePath -Force
            Write-Host "  REMOVED: $DisplayName ($(([math]::Round($fileSize/1KB, 1))) KB)" -ForegroundColor Green
            $script:removedCount++
            $script:totalSize += $fileSize
        } catch {
            Write-Host "  ERROR: Failed to remove $DisplayName - $($_.Exception.Message)" -ForegroundColor Red
        }
    }
}

# Remove setup files
Write-Host "`nProcessing: Setup and Migration Files" -ForegroundColor Cyan
foreach ($fileName in $setupFiles) {
    Remove-FileIfExists (Join-Path $rootPath $fileName) $fileName
}

# Remove admin branch files
Write-Host "`nProcessing: Admin Branch Setup Files" -ForegroundColor Cyan
foreach ($fileName in $adminBranchFiles) {
    Remove-FileIfExists (Join-Path "$rootPath\admin_branch" $fileName) "admin_branch\$fileName"
}

# Remove documentation files
Write-Host "`nProcessing: Documentation Files" -ForegroundColor Cyan
foreach ($fileName in $documentationFiles) {
    Remove-FileIfExists (Join-Path $rootPath $fileName) $fileName
}

# Remove backup files
Write-Host "`nProcessing: Backup/Legacy Files" -ForegroundColor Cyan
foreach ($fileName in $backupFiles) {
    Remove-FileIfExists (Join-Path $rootPath $fileName) $fileName
}

# Remove cleanup scripts
Write-Host "`nProcessing: Cleanup Scripts" -ForegroundColor Cyan
foreach ($fileName in $cleanupScripts) {
    Remove-FileIfExists (Join-Path $rootPath $fileName) $fileName
}

# Remove diagnostic files
Write-Host "`nProcessing: Diagnostic Files" -ForegroundColor Cyan
$diagnosticFiles = @(
    "admin_diagnostic.php",
    "admin_page_diagnostic.php",
    "force_admin_login.php",
    "session_check.php",
    "js_structure_validator.html",
    "dashboard_validation.html"
)

foreach ($fileName in $diagnosticFiles) {
    Remove-FileIfExists (Join-Path $rootPath $fileName) $fileName
}

# Remove SQL enhancement files
Write-Host "`nProcessing: SQL Enhancement Files" -ForegroundColor Cyan
$sqlFiles = @(
    "admin_branch\sql\enhance_appointments.sql",
    "database\enhanced_personnel_schema.sql",
    "database\file_management_schema.sql", 
    "database\profile_enhancements.sql",
    "database\standardization_migration.sql",
    "database\user_account_enhancement.sql",
    "operations_constraints.sql"
)

foreach ($sqlFile in $sqlFiles) {
    Remove-FileIfExists (Join-Path $rootPath $sqlFile) $sqlFile
}

Write-Host "`nCleanup Summary:" -ForegroundColor Magenta
Write-Host "Files Removed: $removedCount" -ForegroundColor White
Write-Host "Space Saved: $(([math]::Round($totalSize/1MB, 2))) MB" -ForegroundColor White

Write-Host "`nSystem cleanup completed successfully!" -ForegroundColor Green
