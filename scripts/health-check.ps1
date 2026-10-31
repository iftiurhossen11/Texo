$ErrorActionPreference = 'Continue'
$failed = $false

function Test-TexoEndpoint {
    param(
        [string]$Name,
        [string]$Url,
        [scriptblock]$Validate,
        [switch]$Json
    )

    try {
        $response = Invoke-WebRequest -Uri $Url -TimeoutSec 5 -UseBasicParsing
        $body = $null
        if ($Json) {
            $body = $response.Content | ConvertFrom-Json -ErrorAction Stop
        }
        if ($response.StatusCode -ne 200 -or -not (& $Validate $body)) {
            Write-Output "[FAIL] $Name"
            $script:failed = $true
            return
        }

        Write-Output "[OK]   $Name"
    }
    catch {
        Write-Output "[FAIL] $Name"
        $script:failed = $true
    }
}

Write-Output '=== Texo Health Check ==='
Write-Output ''

Test-TexoEndpoint -Name 'RAG :8001' -Url 'http://127.0.0.1:8001/health' `
    -Validate { param($body) $body.status -eq 'ok' } -Json
Test-TexoEndpoint -Name 'Laravel :8000' -Url 'http://127.0.0.1:8000/api/health' `
    -Validate { param($body) $body.status -eq 'ok' -and $body.db -eq 'connected' -and $body.rag_service -eq 'ok' } -Json
Test-TexoEndpoint -Name 'Frontend :5173' -Url 'http://127.0.0.1:5173' `
    -Validate { param($body) $true }

$root = Split-Path -Parent $PSScriptRoot
$stateFile = Join-Path $root 'backend-laravel\storage\logs\texo-processes.json'
$queueHealthy = $false
if (Test-Path -LiteralPath $stateFile) {
    try {
        $services = Get-Content -LiteralPath $stateFile -Raw | ConvertFrom-Json
        $queue = $services | Where-Object Name -eq 'queue' | Select-Object -First 1
        if ($queue) {
            $process = Get-CimInstance Win32_Process -ErrorAction Stop |
                Where-Object ProcessId -eq $queue.ProcessId |
                Select-Object -First 1
            $queueHealthy = $process.Name -eq 'php.exe' `
                -and $process.ExecutablePath -eq $queue.Executable `
                -and $process.CommandLine -match 'artisan"?\s+queue:work'
        }
    }
    catch {
        $queueHealthy = $false
    }
}

if ($queueHealthy) {
    Write-Output '[OK]   Laravel queue worker'
}
else {
    Write-Output '[FAIL] Laravel queue worker'
    $failed = $true
}

Write-Output ''
if ($failed) {
    Write-Output 'RESULT: UNHEALTHY - run start-demo.bat and inspect backend-laravel\storage\logs'
    exit 1
}

Write-Output 'RESULT: ALL SERVICES OK'
exit 0
