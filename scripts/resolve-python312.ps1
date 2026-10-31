# Find Python 3.11-3.13 for RAG (prefer 3.12). Prints python.exe path to stdout.
$ErrorActionPreference = "SilentlyContinue"

function Get-PyExe([string]$Version) {
    $out = & py "-$Version" -c "import sys; print(sys.executable)" 2>$null
    if ($out) { return $out.Trim() }
    return $null
}

function Test-PyVersion([string]$exe) {
    $line = & $exe -c "import sys; print(sys.version_info.major, sys.version_info.minor)" 2>$null
    if (-not $line) { return $false }
    $parts = $line.Trim().Split(" ")
    $major = [int]$parts[0]
    $minor = [int]$parts[1]
    return ($major -eq 3 -and $minor -ge 11 -and $minor -le 13)
}

foreach ($ver in @("3.12", "3.11", "3.13")) {
    $exe = Get-PyExe $ver
    if ($exe -and (Test-PyVersion $exe)) {
        Write-Output $exe
        exit 0
    }
}

$fallback = (Get-Command python -ErrorAction SilentlyContinue).Source
if ($fallback) {
    $line = & $fallback -c "import sys; print(sys.version_info.major, sys.version_info.minor)" 2>$null
    if ($line) {
        $parts = $line.Trim().Split(" ")
        $major = [int]$parts[0]
        $minor = [int]$parts[1]
        if ($major -eq 3 -and $minor -ge 11) {
            [Console]::Error.WriteLine("WARN: Using default python $major.$minor - prefer: winget install Python.Python.3.12")
            Write-Output $fallback
            exit 0
        }
    }
}

[Console]::Error.WriteLine("Python 3.11+ not found. Install: winget install Python.Python.3.12")
exit 1
