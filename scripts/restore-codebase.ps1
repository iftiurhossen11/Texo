# Texo — restore missing Laravel/React from transcript extract
$ErrorActionPreference = 'Stop'
$Root = Split-Path -Parent $PSScriptRoot
$Extract = Join-Path $Root '_restore_from_transcripts'
$PhpDir = 'C:\Users\Raiyan Ibne Kamal\AppData\Local\Microsoft\WinGet\Packages\PHP.PHP.8.2_Microsoft.Winget.Source_8wekyb3d8bbwe'
$env:PATH = "$PhpDir;$env:PATH"

Write-Host '=== Step 1: Copy extracted custom files ==='
Get-ChildItem $Extract -Recurse -File | ForEach-Object {
    $rel = $_.FullName.Substring($Extract.Length + 1)
    if ($rel -like 'rag-service\*') { return }
    $dest = Join-Path $Root $rel
    $destDir = Split-Path $dest -Parent
    if (-not (Test-Path $destDir)) { New-Item -ItemType Directory -Path $destDir -Force | Out-Null }
    Copy-Item $_.FullName $dest -Force
}

Write-Host '=== Step 2: Scaffold Laravel if artisan missing ==='
$Laravel = Join-Path $Root 'backend-laravel'
if (-not (Test-Path (Join-Path $Laravel 'artisan'))) {
    $tmp = Join-Path $Root '_laravel_scaffold'
    if (Test-Path $tmp) { Remove-Item $tmp -Recurse -Force }
    Push-Location $Root
    composer create-project laravel/laravel _laravel_scaffold --no-interaction --prefer-dist 2>&1
    if ($LASTEXITCODE -ne 0) { throw 'composer create-project failed' }
    Pop-Location
    Get-ChildItem $tmp -Force | ForEach-Object {
        $dest = Join-Path $Laravel $_.Name
        if ($_.Name -in @('storage', 'public')) { return }
        if (Test-Path $dest) { Remove-Item $dest -Recurse -Force -ErrorAction SilentlyContinue }
        Move-Item $_.FullName $dest -Force
    }
    Remove-Item $tmp -Recurse -Force -ErrorAction SilentlyContinue
    # Re-copy custom files over scaffold
    Get-ChildItem $Extract -Recurse -File | Where-Object { $_.FullName -like '*backend-laravel*' } | ForEach-Object {
        $rel = $_.FullName.Substring($Extract.Length + 1)
        $dest = Join-Path $Root $rel
        Copy-Item $_.FullName $dest -Force
    }
}

Write-Host '=== Step 3: Scaffold React if package.json missing ==='
$Frontend = Join-Path $Root 'frontend-react'
if (-not (Test-Path (Join-Path $Frontend 'package.json'))) {
    $tmp = Join-Path $Root '_react_scaffold'
    if (Test-Path $tmp) { Remove-Item $tmp -Recurse -Force }
    Push-Location $Root
    npm create vite@latest _react_scaffold -- --template react-ts 2>&1
    Pop-Location
    Get-ChildItem $tmp -Force | ForEach-Object {
        $dest = Join-Path $Frontend $_.Name
        if ($_.Name -eq 'node_modules') { return }
        if (Test-Path $dest) { Remove-Item $dest -Recurse -Force -ErrorAction SilentlyContinue }
        Move-Item $_.FullName $dest -Force
    }
    Remove-Item $tmp -Recurse -Force -ErrorAction SilentlyContinue
    Get-ChildItem $Extract -Recurse -File | Where-Object { $_.FullName -like '*frontend-react*' } | ForEach-Object {
        $rel = $_.FullName.Substring($Extract.Length + 1)
        $dest = Join-Path $Root $rel
        $destDir = Split-Path $dest -Parent
        if (-not (Test-Path $destDir)) { New-Item -ItemType Directory -Path $destDir -Force | Out-Null }
        Copy-Item $_.FullName $dest -Force
    }
}

Write-Host '=== Done scaffold ==='
