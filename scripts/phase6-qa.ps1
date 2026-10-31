# Phase 6 automated QA — run on booth laptop before exhibition
# Usage: powershell -ExecutionPolicy Bypass -File scripts\phase6-qa.ps1

$ErrorActionPreference = "Continue"
$Root = Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)
$phpDir = & powershell -NoProfile -ExecutionPolicy Bypass -File (Join-Path $Root "scripts\resolve-php82.ps1")
if (-not $phpDir) { Write-Host "[FAIL] PHP 8.2+ not found"; exit 1 }
$PHP = Join-Path $phpDir "php.exe"
$Base = "http://127.0.0.1:8000/api"
$Results = @()

function Record($name, $pass, $detail = "") {
    $script:Results += [PSCustomObject]@{ Test = $name; Pass = $pass; Detail = $detail }
    $status = if ($pass) { "PASS" } else { "FAIL" }
    Write-Host "[$status] $name $(if ($detail) { "- $detail" })"
}

# 1. Health
try {
    $h = Invoke-RestMethod -Uri "$Base/health" -TimeoutSec 10
    Record "API health" ($h.status -eq "ok" -and $h.rag_service -eq "ok") "db=$($h.db) llm=$($h.llm_provider)"
} catch { Record "API health" $false $_.Exception.Message }

# 2. Primary demo Q
try {
    $s = Invoke-RestMethod -Uri "$Base/chat/sessions" -Method POST -ContentType "application/json" -Body '{"context_tag":"exhibition_booth"}'
    $r = Invoke-RestMethod -Uri "$Base/chat/sessions/$($s.session_id)/messages" -Method POST -ContentType "application/json" -Body '{"content":"What should an inspector do for a broken stitch?"}'
    Record "Primary Q grounded" (($r.retrieved_chunks | Measure-Object).Count -gt 0) "chunks=$((($r.retrieved_chunks | Measure-Object).Count))"
} catch { Record "Primary Q grounded" $false $_.Exception.Message }

# 3. No-answer
try {
    $s = Invoke-RestMethod -Uri "$Base/chat/sessions" -Method POST -ContentType "application/json" -Body '{"context_tag":"exhibition_booth"}'
    $r = Invoke-RestMethod -Uri "$Base/chat/sessions/$($s.session_id)/messages" -Method POST -ContentType "application/json" -Body '{"content":"What is the capital city of Mars and its official textile export policy?"}'
    Record "No-answer honest" ($r.no_answer -eq $true -and (($r.retrieved_chunks | Measure-Object).Count -eq 0)) "no_answer=$($r.no_answer)"
} catch { Record "No-answer honest" $false $_.Exception.Message }

# 4. Empty query blocked (server-side)
try {
    $s = Invoke-RestMethod -Uri "$Base/chat/sessions" -Method POST -ContentType "application/json" -Body '{"context_tag":"exhibition_booth"}'
    $tmp = Join-Path $env:TEMP "texo-empty.json"
    Set-Content -Path $tmp -Value '{"content":"   "}' -NoNewline
    $r = curl.exe -s -w "`nHTTP:%{http_code}" -X POST "$Base/chat/sessions/$($s.session_id)/messages" -H "Content-Type: application/json" -H "Accept: application/json" -d "@$tmp"
    Remove-Item $tmp -Force -ErrorAction SilentlyContinue
    Record "Empty query blocked" ($r -match "HTTP:422") ($r -split "`n" | Select-Object -Last 1)
} catch { Record "Empty query blocked" $false $_.Exception.Message }

# 5. Long query blocked (>500 chars)
try {
    $s = Invoke-RestMethod -Uri "$Base/chat/sessions" -Method POST -ContentType "application/json" -Body '{"context_tag":"exhibition_booth"}'
    $tmp = Join-Path $env:TEMP "texo-long.json"
    Set-Content -Path $tmp -Value (@{ content = ("x" * 501) } | ConvertTo-Json -Compress) -NoNewline
    $r = curl.exe -s -w "`nHTTP:%{http_code}" -X POST "$Base/chat/sessions/$($s.session_id)/messages" -H "Content-Type: application/json" -H "Accept: application/json" -d "@$tmp"
    Remove-Item $tmp -Force -ErrorAction SilentlyContinue
    Record "Long query blocked" ($r -match "HTTP:422") ($r -split "`n" | Select-Object -Last 1)
} catch { Record "Long query blocked" $false $_.Exception.Message }

# 6. Fake PDF magic bytes (wrong header, .pdf extension)
try {
    $fake = Join-Path $Root "demo-content\fake-not-pdf.pdf"
    $r = curl.exe -s -X POST "$Base/documents" -H "Accept: application/json" -F "file=@$fake;type=application/pdf" -F "title=fake" -F "doc_type=sop"
    Record "Fake PDF rejected" ($r -match "422|does not match|must be a file of type") ($r.Substring(0, [Math]::Min(80, $r.Length)))
} catch { Record "Fake PDF rejected" $false $_.Exception.Message }

# 7. Rate limit (21 requests/min)
try {
    $s = Invoke-RestMethod -Uri "$Base/chat/sessions" -Method POST -ContentType "application/json" -Body '{"context_tag":"exhibition_booth"}'
    $sid = $s.session_id
    $blocked = $false
    for ($i = 1; $i -le 22; $i++) {
        try {
            Invoke-RestMethod -Uri "$Base/chat/sessions/$sid/messages" -Method POST -ContentType "application/json" -Body '{"content":"test"}' -ErrorAction Stop | Out-Null
        } catch {
            if ($_.Exception.Response.StatusCode.value__ -eq 429) { $blocked = $true; break }
        }
    }
    Record "Rate limit throttle" $blocked "429 after burst"
} catch { Record "Rate limit throttle" $false $_.Exception.Message }

# 8. Sanctum login (curl.exe CSRF cookie flow — matches frontend)
try {
    $jar = Join-Path $env:TEMP "texo-sanctum-cookie.txt"
    Remove-Item $jar -Force -ErrorAction SilentlyContinue
    curl.exe -s -c $jar "http://127.0.0.1:8000/sanctum/csrf-cookie" -H "Origin: http://127.0.0.1:5173" | Out-Null
    $xsrfLine = Get-Content $jar | Where-Object { $_ -match "`tXSRF-TOKEN`t" }
    $xsrf = [System.Uri]::UnescapeDataString(($xsrfLine -split "`t")[-1])
    $loginBody = '{"email":"admin@texo.local","password":"admin1234"}'
    $loginTmp = Join-Path $env:TEMP "texo-login.json"
    Set-Content -Path $loginTmp -Value $loginBody -NoNewline
    $r = curl.exe -s -w "`nHTTP:%{http_code}" -b $jar -c $jar -X POST "http://127.0.0.1:8000/api/login" -H "Content-Type: application/json" -H "Accept: application/json" -H "Origin: http://127.0.0.1:5173" -H "Referer: http://127.0.0.1:5173" -H "X-XSRF-TOKEN: $xsrf" -d "@$loginTmp"
    Remove-Item $loginTmp -Force -ErrorAction SilentlyContinue
    Record "Sanctum login" ($r -match "HTTP:200" -and $r -match "admin@texo.local") ($r -split "`n" | Select-Object -Last 1)
} catch { Record "Sanctum login" $false $_.Exception.Message }

# 9. PHP upload limits
try {
    $limits = & $PHP -r "echo ini_get('upload_max_filesize').'|'.ini_get('post_max_size');"
    Record "PHP upload limits" ($limits -match "25M") $limits
} catch { Record "PHP upload limits" $false $_.Exception.Message }

Write-Host ""
Write-Host "=== Summary ==="
$pass = ($Results | Where-Object { $_.Pass }).Count
$fail = ($Results | Where-Object { -not $_.Pass }).Count
Write-Host "Passed: $pass / $($Results.Count)  Failed: $fail"
if ($fail -gt 0) { exit 1 }
