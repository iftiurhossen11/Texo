# Texo - Booth Laptop One-Click Setup (Windows)
# Run as normal user from project root: powershell -ExecutionPolicy Bypass -File setup-booth-laptop.ps1

$ErrorActionPreference = "Stop"
$Root = Split-Path -Parent $MyInvocation.MyCommand.Path
Set-Location $Root

Write-Host "=== Texo Booth Laptop Setup ===" -ForegroundColor Cyan

# Require PHP 8.2+ (Laravel 12 needs it; XAMPP PHP 8.0 is too old)
$resolveScript = Join-Path $Root "scripts\resolve-php82.ps1"
$phpDir = & powershell -NoProfile -ExecutionPolicy Bypass -File $resolveScript
if (-not $phpDir -or -not (Test-Path (Join-Path $phpDir "php.exe"))) {
    Write-Host "[FAIL] PHP 8.2+ required. Install: winget install PHP.PHP.8.2" -ForegroundColor Red
    Write-Host "       Then re-run this script." -ForegroundColor Red
    exit 1
}
$env:PATH = "$phpDir;$env:PATH"
$phpVer = & (Join-Path $phpDir "php.exe") -r "echo PHP_VERSION;"
Write-Host "[OK]   PHP $phpVer - $phpDir" -ForegroundColor Green

function Test-Cmd($name) {
    try { Get-Command $name -ErrorAction Stop | Out-Null; return $true } catch { return $false }
}

$checks = @(
    @{ Name = "composer"; Ok = (Test-Cmd "composer") },
    @{ Name = "node";     Ok = (Test-Cmd "node") },
    @{ Name = "npm";      Ok = (Test-Cmd "npm") },
    @{ Name = "python";   Ok = (Test-Cmd "python") }
)

foreach ($c in $checks) {
    if ($c.Ok) {
        Write-Host "[OK]   $($c.Name) found" -ForegroundColor Green
    } else {
        Write-Host "[FAIL] $($c.Name) NOT found - install before continuing" -ForegroundColor Red
        exit 1
    }
}

# MySQL check (service may be named MySQL80)
$mysqlRunning = Get-Service -Name "MySQL*" -ErrorAction SilentlyContinue | Where-Object { $_.Status -eq "Running" }
if ($mysqlRunning) {
    Write-Host "[OK]   MySQL service running" -ForegroundColor Green
} else {
    Write-Host "[WARN] MySQL service not detected - start MySQL before migrate" -ForegroundColor Yellow
}

Write-Host ""
Write-Host "--- Laravel ---" -ForegroundColor Cyan
Set-Location "$Root\backend-laravel"
if (-not (Test-Path ".env")) {
    Copy-Item ".env.example" ".env"
    Write-Host "[INFO] Created .env from .env.example - EDIT DB_PASSWORD and API keys!" -ForegroundColor Yellow
}
composer install --no-interaction
php artisan key:generate --force 2>$null
php artisan migrate --force
php artisan db:seed --class=AdminUserSeeder --force 2>$null

Write-Host ""
Write-Host "--- RAG Service ---" -ForegroundColor Cyan
Set-Location "$Root\rag-service"

$installRag = Join-Path $Root "scripts\install-rag-deps.ps1"
& powershell -NoProfile -ExecutionPolicy Bypass -File $installRag
if ($LASTEXITCODE -ne 0) {
    Write-Host "[FAIL] RAG setup failed. Run: scripts\install-rag-deps.bat" -ForegroundColor Red
    exit 1
}

Write-Host ""
Write-Host "--- Frontend ---" -ForegroundColor Cyan
Set-Location "$Root\frontend-react"
if (-not (Test-Path ".env")) { Copy-Item ".env.example" ".env" }
npm install

Set-Location $Root
Write-Host ""
Write-Host "=== Setup complete ===" -ForegroundColor Green
Write-Host "Next steps:"
Write-Host '  1. Edit backend-laravel\.env - set DB_PASSWORD and GROQ_API_KEY'
Write-Host "  2. Run: start-demo.bat"
Write-Host "  3. Run: pre-exhibition-check.bat"
Write-Host "  4. Run: rehearsal-helper.bat"
