# Install / repair RAG Python dependencies (Windows)
param(
    [switch]$RecreateVenv
)

$ErrorActionPreference = "Stop"
$Root = Split-Path -Parent $PSScriptRoot
$RagRoot = Join-Path $Root "rag-service"
Set-Location $RagRoot

Write-Host "=== Texo RAG Dependencies ===" -ForegroundColor Cyan
Write-Host "[INFO] Close start-demo / RAG / queue windows before continuing." -ForegroundColor Yellow

function Stop-RagProcesses {
    & powershell -NoProfile -ExecutionPolicy Bypass -File (Join-Path $PSScriptRoot "stop-rag.ps1") -RagRoot $RagRoot
}

function Remove-VenvSafe {
    Stop-RagProcesses
    if (-not (Test-Path "venv")) { return }

    $trashName = "venv.trash." + (Get-Date -Format "yyyyMMddHHmmss")
    Rename-Item -LiteralPath "venv" -NewName $trashName -Force

    if (Test-Path "venv") {
        throw "Could not move venv aside. Close all Texo service windows and retry."
    }
}

Stop-RagProcesses

$lockers = @(
    Get-Process python -ErrorAction SilentlyContinue | Where-Object { $_.Path -like "*rag-service*" }
)
if ($lockers.Count -gt 0) {
    Write-Host "[FAIL] RAG Python still running (PID: $($lockers.Id -join ', '))." -ForegroundColor Red
    Write-Host "       Close start-demo / RAG / queue windows, then retry." -ForegroundColor Red
    exit 1
}

$resolvePy = Join-Path $PSScriptRoot "resolve-python312.ps1"
$pythonExe = & powershell -NoProfile -ExecutionPolicy Bypass -File $resolvePy
if (-not $pythonExe -or -not (Test-Path $pythonExe)) {
    Write-Host "[FAIL] Python 3.11+ required. Install: winget install Python.Python.3.12" -ForegroundColor Red
    exit 1
}

$pyVer = & $pythonExe -c "import sys; print(f'{sys.version_info.major}.{sys.version_info.minor}')"
Write-Host "[OK]   Python $pyVer - $pythonExe" -ForegroundColor Green

$venvPython = Join-Path $RagRoot "venv\Scripts\python.exe"
$venvCfg = Join-Path $RagRoot "venv\pyvenv.cfg"
$reqFile = Join-Path $RagRoot "requirements.txt"

if (-not (Test-Path $reqFile)) {
    Write-Host "[FAIL] Missing $reqFile" -ForegroundColor Red
    exit 1
}

function Test-VenvHealthy {
    return (Test-Path $venvCfg) -and (Test-Path $venvPython)
}

$needsVenv = [bool]$RecreateVenv -or -not (Test-VenvHealthy)

if (-not $needsVenv) {
    $prev = $ErrorActionPreference
    $ErrorActionPreference = "SilentlyContinue"
    $check = (& $venvPython -c "import fastapi, chromadb, sentence_transformers; print('ok')" 2>$null).Trim()
    $ErrorActionPreference = $prev
    if ($check -ne "ok") {
        Write-Host "[INFO] Recreating venv (incomplete install detected)..." -ForegroundColor Yellow
        $needsVenv = $true
    }
}

if ($needsVenv) {
    Remove-VenvSafe
    & $pythonExe -m venv venv
    if (-not (Test-VenvHealthy)) {
        Write-Host "[FAIL] venv creation failed." -ForegroundColor Red
        exit 1
    }
    $pipCheck = (& $venvPython -m pip --version 2>&1)
    if ($LASTEXITCODE -ne 0) {
        & $venvPython -m ensurepip --default-pip 2>&1 | Out-Null
    }
}

Write-Host "[INFO] Installing packages (5-15 min first time)..." -ForegroundColor Yellow

function Invoke-PipInstall([string]$PythonExe) {
    # Use relative requirements.txt — path contains spaces ("project x")
    $proc = Start-Process -FilePath $PythonExe `
        -ArgumentList @("-m", "pip", "install", "--no-cache-dir", "-r", "requirements.txt") `
        -WorkingDirectory $RagRoot `
        -Wait -PassThru -NoNewWindow
    return [int]$proc.ExitCode
}

$pipExit = Invoke-PipInstall -PythonExe $venvPython
if ($pipExit -ne 0) {
    Write-Host "[WARN] pip failed (code $pipExit). Retrying once after stop-rag..." -ForegroundColor Yellow
    Stop-RagProcesses
    Start-Sleep -Seconds 5
    $pipExit = Invoke-PipInstall -PythonExe $venvPython
}

if ($pipExit -ne 0) {
    Write-Host "[FAIL] pip install failed with exit code $pipExit" -ForegroundColor Red
    Write-Host "       1. Close ALL Texo service windows (start-demo, RAG, queue)" -ForegroundColor Red
    Write-Host "       2. Run: scripts\install-rag-deps.bat" -ForegroundColor Red
    exit 1
}

$verify = (& $venvPython -c "import fastapi, chromadb, sentence_transformers; print('ok')" 2>&1).Trim()
if ($verify -ne "ok") {
    Write-Host "[FAIL] Import verification failed: $verify" -ForegroundColor Red
    exit 1
}

Write-Host "[OK]   RAG dependencies installed and verified" -ForegroundColor Green
exit 0
