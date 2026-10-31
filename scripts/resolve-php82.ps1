# Find PHP 8.2+ on Windows. Prints directory containing php.exe to stdout.
# Usage: $dir = powershell -File scripts/resolve-php82.ps1

$ErrorActionPreference = "SilentlyContinue"

function Get-PhpVersionString([string]$phpExe) {
    $out = & $phpExe -r "echo PHP_VERSION;" 2>$null
    if ($out -match '^(\d+)\.(\d+)') {
        return [version]"$($Matches[1]).$($Matches[2]).0"
    }
    return [version]"0.0.0"
}

$candidates = New-Object System.Collections.Generic.List[string]

$wingetRoot = Join-Path $env:LOCALAPPDATA "Microsoft\WinGet\Packages"
if (Test-Path $wingetRoot) {
    Get-ChildItem $wingetRoot -Directory -Filter "PHP.PHP.8.*" | ForEach-Object {
        $exe = Join-Path $_.FullName "php.exe"
        if (Test-Path $exe) { [void]$candidates.Add($exe) }
    }
}

$commonDirs = @(
    "C:\php82",
    "C:\php",
    "C:\tools\php82",
    "$env:ProgramFiles\PHP\v8.2"
)
foreach ($dir in $commonDirs) {
    $exe = Join-Path $dir "php.exe"
    if (Test-Path $exe) { [void]$candidates.Add($exe) }
}

$whereLines = & where.exe php 2>$null
if ($whereLines) {
    foreach ($line in @($whereLines)) {
        if ($line -and (Test-Path $line)) { [void]$candidates.Add($line) }
    }
}

$bestExe = $null
$bestVer = [version]"0.0.0"
foreach ($exe in ($candidates | Select-Object -Unique)) {
    $ver = Get-PhpVersionString $exe
    if ($ver.Major -eq 8 -and $ver.Minor -ge 2 -and $ver -gt $bestVer) {
        $bestExe = $exe
        $bestVer = $ver
    }
}

if (-not $bestExe) {
    [Console]::Error.WriteLine("PHP 8.2+ not found. Install with: winget install PHP.PHP.8.2")
    [Console]::Error.WriteLine("Then close and reopen terminal, or add PHP folder to PATH before XAMPP.")
    exit 1
}

Write-Output (Split-Path $bestExe -Parent)
