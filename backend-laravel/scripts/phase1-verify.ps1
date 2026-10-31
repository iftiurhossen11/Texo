# Phase 1 API verification script
$base = "http://127.0.0.1:8000/api"
$root = Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)
$sop = Join-Path $root "demo-content\garment-quality-inspection-sop.md"
$results = @()

function Test-Result($name, $ok, $detail) {
    script:results += [pscustomobject]@{ Test = $name; Pass = $ok; Detail = $detail }
    Write-Output ("[{0}] {1} - {2}" -f $(if($ok){"PASS"}else{"FAIL"}), $name, $detail)
}

Start-Sleep 2

# Health (Phase 1 partial: status + db)
try {
    $h = Invoke-RestMethod "$base/health"
    Test-Result "GET /health has db" ($null -ne $h.db) "db=$($h.db) status=$($h.status)"
} catch { Test-Result "GET /health" $false $_.Exception.Message }

# Upload valid txt
try {
    $form = @{
        file = Get-Item $sop
        doc_type = "sop"
        department = "qa"
    }
    $up = Invoke-RestMethod "$base/documents" -Method Post -Form $form
    $docId = $up.document_id
    Test-Result "POST /documents valid file" ($up.status -eq "uploaded") "doc_id=$docId"
} catch { Test-Result "POST /documents valid" $false $_.Exception.Message; $docId = $null }

# GET list and show
if ($docId) {
    try {
        $list = Invoke-RestMethod "$base/documents"
        $show = Invoke-RestMethod "$base/documents/$docId"
        Test-Result "GET /documents list+show" ($list.data.Count -ge 1 -and $show.data.id -eq $docId) "found id=$docId"
    } catch { Test-Result "GET documents" $false $_.Exception.Message }
}

# Invalid mime (upload .php renamed as txt with binary)
$badFile = "$env:TEMP\fake.txt"
[byte[]](0x89,0x50,0x4E,0x47) + [Text.Encoding]::UTF8.GetBytes("fake") | Set-Content $badFile -Encoding Byte
try {
    Invoke-RestMethod "$base/documents" -Method Post -Form @{ file = Get-Item $badFile; doc_type = "sop" } -ErrorAction Stop
    Test-Result "Reject fake txt magic bytes" $false "should have failed"
} catch {
    Test-Result "Reject fake txt magic bytes" ($_.Exception.Response.StatusCode -eq 422) "422 as expected"
}

# Chat session
try {
    $sess = Invoke-RestMethod "$base/chat/sessions" -Method Post -Body (@{context_tag="exhibition_booth"} | ConvertTo-Json) -ContentType "application/json"
    Test-Result "POST /chat/sessions" ($sess.context_tag -eq "exhibition_booth") "session_id=$($sess.session_id)"
    $sid = $sess.session_id
} catch { Test-Result "POST /chat/sessions" $false $_.Exception.Message; $sid = $null }

# Message skeleton (saves user msg)
if ($sid) {
    try {
        $msg = Invoke-RestMethod "$base/chat/sessions/$sid/messages" -Method Post -Body (@{content="What about broken stitch?"} | ConvertTo-Json) -ContentType "application/json"
        Test-Result "POST chat message" ($msg.user_message.content -ne $null) "assistant saved"
    } catch { Test-Result "POST chat message" $false $_.Exception.Message }

    # Query length >500
    $long = "x" * 501
    try {
        Invoke-RestMethod "$base/chat/sessions/$sid/messages" -Method Post -Body (@{content=$long} | ConvertTo-Json) -ContentType "application/json" -ErrorAction Stop
        Test-Result "Reject >500 char query" $false "should fail"
    } catch {
        Test-Result "Reject >500 char query" ($_.Exception.Response.StatusCode -eq 422) "422"
    }
}

# Rate limit burst
if ($sid) {
    $blocked = $false
    1..25 | ForEach-Object {
        try {
            Invoke-RestMethod "$base/chat/sessions/$sid/messages" -Method Post -Body (@{content="q$_"} | ConvertTo-Json) -ContentType "application/json" -ErrorAction Stop | Out-Null
        } catch {
            if ($_.Exception.Response.StatusCode -eq 429) { $blocked = $true }
        }
    }
    Test-Result "Rate limit 429" $blocked "throttle triggered"
}

# DELETE document + file
if ($docId) {
    $storagePath = (Invoke-RestMethod "$base/documents/$docId").data.storage_path
    $fullPath = Join-Path (Join-Path $root "backend-laravel\storage\app") $storagePath
    Start-Sleep 1
    try {
        Invoke-RestMethod "$base/documents/$docId" -Method Delete | Out-Null
        $dbGone = -not (Invoke-RestMethod "$base/documents/$docId" -ErrorAction SilentlyContinue)
    } catch {
        $dbGone = ($_.Exception.Response.StatusCode -eq 404)
    }
    $fileGone = -not (Test-Path $fullPath)
    Test-Result "DELETE document+file" ($fileGone) "file_exists=$(Test-Path $fullPath)"
}

Write-Output "`n=== SUMMARY ==="
$results | Format-Table -AutoSize
$fail = ($results | Where-Object { -not $_.Pass }).Count
Write-Output "Passed: $($results.Count - $fail)/$($results.Count)"
