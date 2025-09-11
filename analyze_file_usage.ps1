# ARMIS System File Usage Analysis Script
Write-Host "🔍 Starting ARMIS System File Usage Analysis..." -ForegroundColor Yellow

$rootPath = "c:\wamp64\www\Armis2"
$allFiles = Get-ChildItem -Path $rootPath -Recurse -File | Where-Object { $_.Extension -match '\.(php|html|js|css)$' }
$totalFiles = $allFiles.Count

Write-Host "📊 Found $totalFiles files to analyze" -ForegroundColor Cyan

# Categories to analyze
$categories = @{
    "Setup/Migration Files" = @("setup_", "migration_", "fix_", "check_", "validate_", "enhance_", "add_", "update_", "run_")
    "Documentation Files" = @("\.md$", "\.txt$", "COMPLETE\.md$", "FIXED\.md$", "REPORT\.md$")
    "Legacy/Backup Files" = @("_backup", "_old", "_fixed", "_legacy", "emergency_", "_original")  
    "Cleanup Files" = @("cleanup_", "secure_cleanup", "production_cleanup")
    "Diagnostic Files" = @("diagnostic", "_check", "_validation", "verify_")
}

$potentialUnused = @()
$usageMap = @{}

Write-Host "`n🔎 Analyzing file references..." -ForegroundColor Cyan

# Build usage map by searching for file references
foreach ($file in $allFiles) {
    $fileName = $file.Name
    $fileNameWithoutExt = [System.IO.Path]::GetFileNameWithoutExtension($fileName)
    
    # Search for references to this file in other files
    $references = 0
    
    try {
        # Search for includes/requires
        $includeResults = Select-String -Path "$rootPath\*.php" -Pattern "include|require" -SimpleMatch 2>$null | Where-Object { $_.Line -match $fileName }
        $references += ($includeResults | Measure-Object).Count
        
        # Search for links/hrefs
        $linkResults = Select-String -Path "$rootPath\*.php", "$rootPath\*.html" -Pattern "href=|action=" -SimpleMatch 2>$null | Where-Object { $_.Line -match $fileName }
        $references += ($linkResults | Measure-Object).Count
        
        # Search for AJAX calls
        $ajaxResults = Select-String -Path "$rootPath\*.js", "$rootPath\*.php" -Pattern "url:" -SimpleMatch 2>$null | Where-Object { $_.Line -match $fileName }
        $references += ($ajaxResults | Measure-Object).Count
        
    } catch {
        # File might be locked or inaccessible
    }
    
    $usageMap[$fileName] = $references
    
    if ($references -eq 0) {
        $potentialUnused += $file
    }
}

Write-Host "`n📋 Analysis Results:" -ForegroundColor Green

# Categorize potentially unused files
foreach ($category in $categories.Keys) {
    $patterns = $categories[$category]
    $categoryFiles = @()
    
    foreach ($file in $potentialUnused) {
        foreach ($pattern in $patterns) {
            if ($file.Name -match $pattern) {
                $categoryFiles += $file
                break
            }
        }
    }
    
    if ($categoryFiles.Count -gt 0) {
        Write-Host "`n🔸 $category ($($categoryFiles.Count) files):" -ForegroundColor Yellow
        $categoryFiles | Sort-Object Name | ForEach-Object {
            $sizeKB = [math]::Round($_.Length / 1KB, 1)
            Write-Host "  - $($_.Name) ($sizeKB KB)" -ForegroundColor Gray
        }
    }
}

# Show files with no apparent usage
$uncategorizedUnused = $potentialUnused | Where-Object { 
    $file = $_
    $isCategorized = $false
    foreach ($category in $categories.Keys) {
        foreach ($pattern in $categories[$category]) {
            if ($file.Name -match $pattern) {
                $isCategorized = $true
                break
            }
        }
        if ($isCategorized) { break }
    }
    -not $isCategorized
}

if ($uncategorizedUnused.Count -gt 0) {
    Write-Host "`n🔸 Uncategorized Files (Need Manual Review) ($($uncategorizedUnused.Count) files):" -ForegroundColor Red
    $uncategorizedUnused | Sort-Object Name | ForEach-Object {
        $sizeKB = [math]::Round($_.Length / 1KB, 1)
        Write-Host "  - $($_.Name) ($sizeKB KB)" -ForegroundColor Gray
    }
}

Write-Host "`n📈 Summary Statistics:" -ForegroundColor Magenta
Write-Host "Total Files Analyzed: $totalFiles" -ForegroundColor White
Write-Host "Files with No References: $($potentialUnused.Count)" -ForegroundColor White
Write-Host "Potential Space Savings: $([math]::Round(($potentialUnused | Measure-Object Length -Sum).Sum / 1MB, 2)) MB" -ForegroundColor White

Write-Host "`n✅ Analysis Complete!" -ForegroundColor Green
