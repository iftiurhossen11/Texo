$ErrorActionPreference = 'Stop'

$root = Split-Path -Parent $PSScriptRoot
$stateFile = Join-Path $root 'backend-laravel\storage\logs\texo-processes.json'
$backend = Join-Path $root 'backend-laravel'
if (Test-Path -LiteralPath $stateFile) {
    $services = @(Get-Content -LiteralPath $stateFile -Raw | ConvertFrom-Json)
}
else {
    $services = @()
}

$processes = @(Get-CimInstance Win32_Process)
$targets = [System.Collections.Generic.List[object]]::new()
$targetIds = [System.Collections.Generic.HashSet[int]]::new()
$expectedCommands = @{
    rag = 'uvicorn main:app'
    laravel = 'artisan"?\s+serve'
    queue = 'artisan"?\s+queue:work'
    frontend = 'vite\.js'
}
$rag = Join-Path $root 'rag-service'
$vite = Join-Path $root 'frontend-react\node_modules\vite\bin\vite.js'

foreach ($service in $services) {
    $process = $processes | Where-Object ProcessId -eq $service.ProcessId | Select-Object -First 1
    if (-not $process -or $process.ExecutablePath -ne $service.Executable) {
        continue
    }

    $expectedCommand = $expectedCommands[$service.Name]
    if ($expectedCommand -and $process.CommandLine -match $expectedCommand) {
        [void]$targetIds.Add([int]$process.ProcessId)
        $targets.Add([pscustomobject]@{ Process = $process; Depth = 0 })
    }
}

foreach ($process in $processes) {
    $isTexoRag = $process.CommandLine -like "*$rag*" `
        -and $process.CommandLine -match '-m\s+uvicorn\s+main:app'
    $isTexoFrontend = $process.CommandLine -like "*$vite*"
    $isTexoLaravel = $process.CommandLine -like "*$backend*" `
        -and $process.CommandLine -match 'artisan"?\s+(?:serve|queue:work)'
    if (
        $isTexoRag `
        -or $isTexoFrontend `
        -or $isTexoLaravel `
        -or (
            $process.CommandLine -like "*$backend*" `
            -and $process.CommandLine -match 'php(?:\.exe)?\s+-S\s+127\.0\.0\.1:8000'
        )
    ) {
        if ($targetIds.Add([int]$process.ProcessId)) {
            $targets.Add([pscustomobject]@{ Process = $process; Depth = 0 })
        }

        $parent = $processes |
            Where-Object ProcessId -eq $process.ParentProcessId |
            Select-Object -First 1
        if ($parent -and $parent.Name -eq 'cmd.exe' -and $targetIds.Add([int]$parent.ProcessId)) {
            $targets.Add([pscustomobject]@{ Process = $parent; Depth = 1 })
        }
    }
}

for ($index = 0; $index -lt $targets.Count; $index++) {
    $parent = $targets[$index]
    foreach ($child in $processes | Where-Object ParentProcessId -eq $parent.Process.ProcessId) {
        if ($targetIds.Add([int]$child.ProcessId)) {
            $targets.Add([pscustomobject]@{
                Process = $child
                Depth = $parent.Depth + 1
            })
        }
    }
}

foreach ($target in ($targets | Sort-Object Depth -Descending)) {
    Stop-Process -Id $target.Process.ProcessId -Force -ErrorAction SilentlyContinue
}

if (Test-Path -LiteralPath $stateFile) {
    Remove-Item -LiteralPath $stateFile -Force
}

if ($targets.Count -gt 0) {
    Write-Output 'Texo background services stopped.'
}
else {
    Write-Output 'No Texo background services were running.'
}
