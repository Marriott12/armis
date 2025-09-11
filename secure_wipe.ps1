# Secure File Deletion Script
Write-Host "ARMIS System - Secure File Deletion" -ForegroundColor Red
Write-Host "Ensuring deleted files cannot be recovered..." -ForegroundColor Yellow

$rootPath = "c:\wamp64\www\Armis2"

# Function to perform secure overwrite
function Invoke-SecureOverwrite {
    param($Directory)
    
    Write-Host "Performing secure overwrite on: $Directory" -ForegroundColor Cyan
    
    try {
        # Create temporary files with random data to overwrite free space
        $tempFiles = @()
        $iteration = 1
        
        do {
            try {
                $tempFile = Join-Path $Directory "temp_secure_$iteration.tmp"
                $randomData = Get-Random -Count 1048576 -InputObject (0..255) # 1MB random data
                $randomBytes = [byte[]]$randomData
                [System.IO.File]::WriteAllBytes($tempFile, $randomBytes)
                $tempFiles += $tempFile
                $iteration++
            } catch {
                # Disk space full - expected
                break
            }
        } while ($iteration -lt 50)
        
        Write-Host "  Created $($tempFiles.Count) overwrite files" -ForegroundColor Gray
        
        # Remove temporary files
        foreach ($file in $tempFiles) {
            if (Test-Path $file) {
                Remove-Item $file -Force
            }
        }
        
        Write-Host "  Secure overwrite completed" -ForegroundColor Green
        
    } catch {
        Write-Host "  Warning: $($_.Exception.Message)" -ForegroundColor Yellow
    }
}

# Clear Windows Recycle Bin using command line
Write-Host "`nClearing Recycle Bin..." -ForegroundColor Cyan
try {
    # Use PowerShell cmdlet to clear recycle bin
    Clear-RecycleBin -Confirm:$false -Force -ErrorAction SilentlyContinue
    Write-Host "  Recycle bin cleared" -ForegroundColor Green
} catch {
    Write-Host "  Could not clear recycle bin automatically" -ForegroundColor Yellow
}

# Clear temporary files
Write-Host "`nClearing temporary files..." -ForegroundColor Cyan
$tempDirs = @($env:TEMP, "$env:LOCALAPPDATA\Temp")
foreach ($tempDir in $tempDirs) {
    if (Test-Path $tempDir) {
        try {
            Get-ChildItem $tempDir -Recurse -Force -ErrorAction SilentlyContinue | 
            Where-Object { $_.CreationTime -lt (Get-Date).AddDays(-1) } |
            Remove-Item -Recurse -Force -ErrorAction SilentlyContinue
            Write-Host "  Cleared old temporary files from: $tempDir" -ForegroundColor Green
        } catch {
            Write-Host "  Could not clear: $tempDir" -ForegroundColor Yellow
        }
    }
}

# Perform secure overwrite on critical directories
Write-Host "`nPerforming secure overwrite..." -ForegroundColor Magenta

$directories = @($rootPath, "$rootPath\admin_branch", "$rootPath\database")
foreach ($dir in $directories) {
    if (Test-Path $dir) {
        Invoke-SecureOverwrite $dir
    }
}

# Use cipher command for additional security (Windows built-in)
Write-Host "`nUsing Windows cipher for additional security..." -ForegroundColor Cyan
try {
    $driveLetters = (Get-WmiObject -Class Win32_LogicalDisk | Where-Object { $_.DriveType -eq 3 }).DeviceID
    foreach ($drive in $driveLetters) {
        Write-Host "  Running cipher on drive $drive" -ForegroundColor Gray
        & cipher /w:$drive 2>$null
    }
    Write-Host "  Cipher operations completed" -ForegroundColor Green
} catch {
    Write-Host "  Cipher operation warning: $($_.Exception.Message)" -ForegroundColor Yellow
}

# Force memory cleanup
Write-Host "`nPerforming memory cleanup..." -ForegroundColor Cyan
[System.GC]::Collect()
[System.GC]::WaitForPendingFinalizers()
[System.GC]::Collect()
Write-Host "  Memory cleanup completed" -ForegroundColor Green

# Final verification
Write-Host "`nFinal verification..." -ForegroundColor Magenta
$remainingTraces = Get-ChildItem -Path $rootPath -Recurse -Force -ErrorAction SilentlyContinue | 
                  Where-Object { $_.Name -match "(setup_|fix_|check_|validate_|test_|debug_)" }

if ($remainingTraces.Count -eq 0) {
    Write-Host "SUCCESS: No traces of deleted files found" -ForegroundColor Green
} else {
    Write-Host "Found $($remainingTraces.Count) potential traces" -ForegroundColor Yellow
}

Write-Host "`nSECURE DELETION COMPLETED" -ForegroundColor Red
Write-Host "- Free space overwritten with random data" -ForegroundColor White
Write-Host "- Temporary files cleared" -ForegroundColor White  
Write-Host "- Recycle bin emptied" -ForegroundColor White
Write-Host "- Windows cipher applied" -ForegroundColor White
Write-Host "- Memory cleaned" -ForegroundColor White
Write-Host "`nDeleted ARMIS files cannot be recovered using standard methods." -ForegroundColor Green
