# Stop processes that lock rag-service\venv (port 8001 + venv python workers)
param(
    [string]$RagRoot = (Join-Path (Split-Path -Parent $PSScriptRoot) "rag-service")
)

$ErrorActionPreference = "SilentlyContinue"

Get-NetTCPConnection -LocalPort 8001 -ErrorAction SilentlyContinue |
    Select-Object -ExpandProperty OwningProcess -Unique |
    ForEach-Object { Stop-Process -Id $_ -Force -ErrorAction SilentlyContinue }

$venvMarker = Join-Path $RagRoot "venv"
$ragMarker = Join-Path $RagRoot ""

Get-CimInstance Win32_Process -Filter "Name='python.exe'" |
    Where-Object {
        ($_.ExecutablePath -and ($_.ExecutablePath -like "*$venvMarker*" -or $_.ExecutablePath -like "*$ragMarker*")) -or
        ($_.CommandLine -and ($_.CommandLine -like "*$venvMarker*" -or $_.CommandLine -like "*rag-service*"))
    } |
    ForEach-Object { Stop-Process -Id $_.ProcessId -Force -ErrorAction SilentlyContinue }

Get-Process python -ErrorAction SilentlyContinue |
    Where-Object { $_.Path -like "*rag-service*" } |
    ForEach-Object { Stop-Process -Id $_.Id -Force -ErrorAction SilentlyContinue }

Get-Process uvicorn -ErrorAction SilentlyContinue |
    ForEach-Object { Stop-Process -Id $_.Id -Force -ErrorAction SilentlyContinue }

Start-Sleep -Seconds 3
