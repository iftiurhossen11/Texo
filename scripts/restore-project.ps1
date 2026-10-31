# Restore missing Laravel skeleton + frontend config from _laravel_scaffold
$ErrorActionPreference = "Stop"
$Root = Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)
$Scaffold = Join-Path $Root "_laravel_scaffold"
$Laravel = Join-Path $Root "backend-laravel"
$Frontend = Join-Path $Root "frontend-react"

function Copy-Tree($src, $dst) {
    if (Test-Path $src) {
        New-Item -ItemType Directory -Force -Path $dst | Out-Null
        robocopy $src $dst /E /NFL /NDL /NJH /NJS /nc /ns /np | Out-Null
    }
}

# Laravel skeleton (preserve existing app, routes/api.php, config/cors.php, database custom migrations)
$dirs = @("bootstrap", "public", "resources", "tests")
foreach ($d in $dirs) { Copy-Tree (Join-Path $Scaffold $d) (Join-Path $Laravel $d) }

Copy-Tree (Join-Path $Scaffold "config") (Join-Path $Laravel "config")
# Re-apply project-specific config after scaffold copy
$projectConfigs = @("cors.php", "services.php", "sanctum.php")
foreach ($name in $projectConfigs) {
    $src = Join-Path $Laravel "config\$name"
    if (Test-Path "$src.bak") { Copy-Item "$src.bak" $src -Force }
}

foreach ($f in @("artisan", "composer.json", "composer.lock", "phpunit.xml", ".editorconfig", ".gitattributes")) {
    Copy-Item (Join-Path $Scaffold $f) (Join-Path $Laravel $f) -Force
}

New-Item -ItemType Directory -Force -Path (Join-Path $Laravel "routes") | Out-Null
Copy-Item (Join-Path $Scaffold "routes\web.php") (Join-Path $Laravel "routes\web.php") -Force
Copy-Item (Join-Path $Scaffold "routes\console.php") (Join-Path $Laravel "routes\console.php") -Force

$migSrc = Join-Path $Scaffold "database\migrations"
$migDst = Join-Path $Laravel "database\migrations"
New-Item -ItemType Directory -Force -Path $migDst | Out-Null
Get-ChildItem $migSrc -Filter "*.php" | Copy-Item -Destination $migDst -Force

Write-Host "[OK] Laravel skeleton copied from _laravel_scaffold"
