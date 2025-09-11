# ARMIS System Unused Files Cleanup Script
Write-Host "🧹 ARMIS System - Comprehensive Unused Files Cleanup" -ForegroundColor Yellow
Write-Host "This script will remove development, migration, and documentation files that are no longer needed." -ForegroundColor Cyan

$rootPath = "c:\wamp64\www\Armis2"

# Categories of files to remove
$filesToRemove = @{
    "Setup and Migration Files" = @(
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
    
    "Admin Branch Setup Files" = @(
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
    
    "Documentation Files" = @(
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
    
    "Backup/Legacy Files" = @(
        "promote_staff_fixed.php",
        "emergency_admin.php"
    )
    
    "Cleanup Scripts" = @(
        "cleanup_system.php",
        "cleanup_system.ps1", 
        "secure_cleanup.php",
        "secure_cleanup.ps1",
        "production_cleanup.php",
        "cleanup_test_files.ps1"
    )
    
    "Diagnostic Files" = @(
        "admin_diagnostic.php",
        "admin_page_diagnostic.php",
        "force_admin_login.php",
        "session_check.php",
        "js_structure_validator.html",
        "dashboard_validation.html"
    )
    
    "Operations Setup" = @(
        "setup_database.php",
        "setup_operations_tables.php"
    )
}

$removedCount = 0
$totalSize = 0

# Process each category
foreach ($category in $filesToRemove.Keys) {
    Write-Host "`n📁 Processing: $category" -ForegroundColor Cyan
    $files = $filesToRemove[$category]
    
    foreach ($fileName in $files) {
        # Check in root directory
        $rootFile = Join-Path $rootPath $fileName
        if (Test-Path $rootFile) {
            try {
                $fileSize = (Get-Item $rootFile).Length
                Remove-Item $rootFile -Force
                Write-Host "  ✅ Removed: $fileName ($([math]::Round($fileSize/1KB, 1)) KB)" -ForegroundColor Green
                $removedCount++
                $totalSize += $fileSize
            } catch {
                Write-Host "  ❌ Failed to remove: $fileName - $($_.Exception.Message)" -ForegroundColor Red
            }
        }
        
        # Check in admin_branch directory  
        $adminFile = Join-Path "$rootPath\admin_branch" $fileName
        if (Test-Path $adminFile) {
            try {
                $fileSize = (Get-Item $adminFile).Length
                Remove-Item $adminFile -Force
                Write-Host "  ✅ Removed: admin_branch\$fileName ($([math]::Round($fileSize/1KB, 1)) KB)" -ForegroundColor Green
                $removedCount++
                $totalSize += $fileSize
            } catch {
                Write-Host "  ❌ Failed to remove: admin_branch\$fileName - $($_.Exception.Message)" -ForegroundColor Red
            }
        }
    }
}

# Remove empty SQL files that are no longer needed
Write-Host "`n📁 Processing: SQL Enhancement Files" -ForegroundColor Cyan
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
    $fullPath = Join-Path $rootPath $sqlFile
    if (Test-Path $fullPath) {
        try {
            $fileSize = (Get-Item $fullPath).Length
            Remove-Item $fullPath -Force
            Write-Host "  ✅ Removed: $sqlFile ($([math]::Round($fileSize/1KB, 1)) KB)" -ForegroundColor Green
            $removedCount++
            $totalSize += $fileSize
        } catch {
            Write-Host "  ❌ Failed to remove: $sqlFile - $($_.Exception.Message)" -ForegroundColor Red
        }
    }
}

# Remove the analysis script itself
$analysisScript = Join-Path $rootPath "analyze_file_usage.ps1"
if (Test-Path $analysisScript) {
    Remove-Item $analysisScript -Force
    Write-Host "  ✅ Removed: analyze_file_usage.ps1" -ForegroundColor Green
    $removedCount++
}

Write-Host "`n📊 Cleanup Summary:" -ForegroundColor Magenta
Write-Host "Files Removed: $removedCount" -ForegroundColor White
Write-Host "Space Saved: $([math]::Round($totalSize/1MB, 2)) MB" -ForegroundColor White

# Final verification
Write-Host "`n🔍 Final Verification:" -ForegroundColor Cyan
$remainingSetup = Get-ChildItem -Path $rootPath -Recurse -File | Where-Object { $_.Name -match "(setup_|fix_|check_|validate_)" }
$remainingDocs = Get-ChildItem -Path $rootPath -Recurse -File | Where-Object { $_.Extension -match '\.md$' -and $_.Name -match "(COMPLETE|FIXED|REPORT)" }

if ($remainingSetup.Count -gt 0) {
    Write-Host "⚠️  Remaining setup/fix files:" -ForegroundColor Yellow
    $remainingSetup | ForEach-Object { Write-Host "  - $($_.Name)" -ForegroundColor Gray }
}

if ($remainingDocs.Count -gt 0) {
    Write-Host "⚠️  Remaining documentation files:" -ForegroundColor Yellow  
    $remainingDocs | ForEach-Object { Write-Host "  - $($_.Name)" -ForegroundColor Gray }
}

Write-Host "`n🎉 System cleanup completed successfully!" -ForegroundColor Green
Write-Host "The ARMIS system is now optimized for production use." -ForegroundColor Green
