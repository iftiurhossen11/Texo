$ErrorActionPreference = 'Stop'

$root = Split-Path -Parent $PSScriptRoot
$backend = Join-Path $root 'backend-laravel'
$frontend = Join-Path $root 'frontend-react'
$rag = Join-Path $root 'rag-service'
$logs = Join-Path $backend 'storage\logs'
$stateFile = Join-Path $logs 'texo-processes.json'

New-Item -ItemType Directory -Path $logs -Force | Out-Null
foreach ($directory in @(
    (Join-Path $backend 'storage\app\public'),
    (Join-Path $backend 'storage\framework\cache\data'),
    (Join-Path $backend 'storage\framework\sessions'),
    (Join-Path $backend 'storage\framework\views')
)) {
    New-Item -ItemType Directory -Path $directory -Force | Out-Null
}

$phpDir = & (Join-Path $PSScriptRoot 'resolve-php82.ps1')
$php = if ($phpDir) { Join-Path $phpDir 'php.exe' } else { $null }
if (-not $php -or -not (Test-Path -LiteralPath $php)) {
    throw 'PHP 8.2 or newer was not found.'
}

$python = Join-Path $rag 'venv\Scripts\python.exe'
$node = (Get-Command node.exe -ErrorAction Stop).Source
$vite = Join-Path $frontend 'node_modules\vite\bin\vite.js'
$artisan = Join-Path $backend 'artisan'

foreach ($port in @(5173, 8000, 8001)) {
    $probe = [System.Net.Sockets.TcpListener]::new([System.Net.IPAddress]::Loopback, $port)
    try {
        $probe.Start()
        $probe.Stop()
    }
    catch [System.Net.Sockets.SocketException] {
        $listener = Get-NetTCPConnection -LocalPort $port -State Listen -ErrorAction SilentlyContinue |
            Where-Object OwningProcess -gt 0 |
            Select-Object -First 1
        $owner = if ($listener) { " by process $($listener.OwningProcess)" } else { '' }
        throw "Port $port is already in use$owner. Stop the existing service before starting Texo."
    }
}

if (-not (Test-Path -LiteralPath $python)) {
    throw "RAG Python environment is missing: $python"
}

$phpModules = & $php -m
if ($LASTEXITCODE -ne 0 -or $phpModules -notcontains 'pdo_sqlite') {
    throw 'The selected PHP installation does not have the PDO SQLite driver enabled.'
}

$database = Join-Path $backend 'database\database.sqlite'
if (-not (Test-Path -LiteralPath $database)) {
    New-Item -ItemType File -Path $database -Force | Out-Null
}

Push-Location $backend
try {
    & $php artisan config:clear
    if ($LASTEXITCODE -ne 0) {
        throw 'Laravel configuration cache could not be cleared.'
    }
    & $php artisan migrate --force
    if ($LASTEXITCODE -ne 0) {
        throw 'Laravel database migrations failed.'
    }
}
finally {
    Pop-Location
}

$started = [System.Collections.Generic.List[object]]::new()

function Start-TexoProcess {
    param(
        [string]$Name,
        [string]$FilePath,
        [string[]]$ArgumentList,
        [string]$WorkingDirectory
    )

    $stdout = Join-Path $logs "texo-$Name.out.log"
    $stderr = Join-Path $logs "texo-$Name.err.log"
    $process = Start-Process -FilePath $FilePath -ArgumentList $ArgumentList `
        -WorkingDirectory $WorkingDirectory -WindowStyle Hidden `
        -RedirectStandardOutput $stdout -RedirectStandardError $stderr -PassThru
    $started.Add([pscustomobject]@{
        Name = $Name
        ProcessId = $process.Id
        Executable = $FilePath
    })
}

Start-TexoProcess -Name 'rag' -FilePath $python `
    -ArgumentList @('-m', 'uvicorn', 'main:app', '--host', '127.0.0.1', '--port', '8001') `
    -WorkingDirectory $rag
Start-TexoProcess -Name 'laravel' -FilePath $php `
    -ArgumentList @("`"$artisan`"", 'serve', '--host=127.0.0.1', '--port=8000') `
    -WorkingDirectory $backend
Start-TexoProcess -Name 'queue' -FilePath $php `
    -ArgumentList @("`"$artisan`"", 'queue:work', '--tries=3', '--timeout=60') `
    -WorkingDirectory $backend
Start-TexoProcess -Name 'frontend' -FilePath $node `
    -ArgumentList @("`"$vite`"", '--host', '127.0.0.1') `
    -WorkingDirectory $frontend

$started | ConvertTo-Json | Set-Content -LiteralPath $stateFile

$checks = @(
    'http://127.0.0.1:5173',
    'http://127.0.0.1:8001/health',
    'http://127.0.0.1:8000/api/health'
)
$deadline = (Get-Date).AddSeconds(180)
do {
    $healthy = $true
    foreach ($url in $checks) {
        try {
            $response = Invoke-WebRequest -Uri $url -TimeoutSec 3 -UseBasicParsing
            if ($response.StatusCode -ne 200) {
                $healthy = $false
            }
        }
        catch {
            $healthy = $false
        }
    }
    if (-not $healthy) {
        Start-Sleep -Seconds 1
    }
} while (-not $healthy -and (Get-Date) -lt $deadline)

if (-not $healthy) {
    & (Join-Path $PSScriptRoot 'stop-demo.ps1')
    throw "Texo services failed health checks. Inspect service logs in $logs."
}

Write-Output 'Texo services started in the background.'
Write-Output 'Frontend: http://127.0.0.1:5173 (HTTP 200)'
Write-Output 'RAG: http://127.0.0.1:8001/health (HTTP 200)'
Write-Output 'Laravel: http://127.0.0.1:8000/api/health (HTTP 200)'
Write-Output "Service logs: $logs"
