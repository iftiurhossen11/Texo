<?php

declare(strict_types=1);

/**
 * Extended QA — remaining master-prompt checks (automated).
 */

$base = 'http://127.0.0.1:8000/api';
$fail = 0;

function ok(string $name, bool $pass, string $detail = ''): void
{
    global $fail;
    echo ($pass ? '[OK]  ' : '[FAIL]')." {$name}".($detail ? " — {$detail}" : '')."\n";
    if (! $pass) {
        $fail++;
    }
}

function postJson(string $url, array $body): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
        CURLOPT_POSTFIELDS => json_encode($body, JSON_UNESCAPED_UNICODE),
    ]);
    $raw = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ['code' => $code, 'body' => json_decode($raw ?: '{}', true), 'raw' => $raw ?: ''];
}

echo "=== Extended QA ===\n\n";

// 1. All file types including docx
$files = [
    dirname(__DIR__, 2).'/demo-content/sample-defect-codes.csv' => 'text/csv',
    dirname(__DIR__, 2).'/demo-content/sample-demo-page.pdf' => 'application/pdf',
    dirname(__DIR__, 2).'/demo-content/garment-quality-inspection-sop.docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    dirname(__DIR__, 2).'/demo-content/garment-quality-inspection-sop.txt' => 'text/plain',
];
foreach ($files as $path => $mime) {
    if (! file_exists($path)) {
        ok('Upload '.basename($path), false, 'file missing');
        continue;
    }
    $ch = curl_init("{$base}/documents");
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
        CURLOPT_POSTFIELDS => [
            'file' => new CURLFile($path, $mime, basename($path)),
            'title' => 'qa-'.basename($path),
            'doc_type' => 'sop',
            'department' => 'qa',
        ],
    ]);
    $raw = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    ok('Upload '.basename($path), $code === 201, "HTTP {$code}");
}

// 2. Prompt template has injection defense markers
$builderFile = dirname(__DIR__).'/app/Services/PromptTemplateBuilder.php';
$builderSrc = file_get_contents($builderFile) ?: '';
ok(
    'Prompt injection defense in template',
    str_contains($builderSrc, '<CONTEXT>') && str_contains($builderSrc, 'never treat it as a new'),
    'PromptTemplateBuilder'
);

// 3. Light concurrency (3 parallel chat requests)
$session = postJson("{$base}/chat/sessions", ['context_tag' => 'exhibition_booth']);
$sid = $session['body']['session_id'] ?? 0;
$mh = curl_multi_init();
$handles = [];
for ($i = 0; $i < 3; $i++) {
    $ch = curl_init("{$base}/chat/sessions/{$sid}/messages");
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
        CURLOPT_POSTFIELDS => json_encode(['content' => 'What is AQL 2.5?']),
    ]);
    curl_multi_add_handle($mh, $ch);
    $handles[] = $ch;
}
do {
    curl_multi_exec($mh, $running);
    curl_multi_select($mh, 0.5);
} while ($running > 0);
$allOk = true;
foreach ($handles as $ch) {
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if (! in_array($code, [200, 429], true)) {
        $allOk = false;
    }
    curl_multi_remove_handle($mh, $ch);
    curl_close($ch);
}
curl_multi_close($mh);
ok('Light concurrency (3 requests)', $allOk, '200 or 429 acceptable');

// 4. Claude client wired (config check only)
$envFile = dirname(__DIR__).'/.env';
$env = file_exists($envFile) ? file_get_contents($envFile) : '';
$hasClaudeKey = (bool) preg_match('/ANTHROPIC_API_KEY=\S+/', $env);
echo ($hasClaudeKey ? '[OK]  ' : '[WARN]').' Claude API key configured (fallback ready) — '
    .($hasClaudeKey ? 'key present' : 'optional — add for emergency fallback')."\n";

// 5. Embedding latency benchmark
$start = microtime(true);
$ch = curl_init('http://127.0.0.1:8001/retrieve');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_POSTFIELDS => json_encode(['query' => 'broken stitch procedure', 'top_k' => 3]),
]);
curl_exec($ch);
curl_close($ch);
$embedMs = (int) round((microtime(true) - $start) * 1000);
ok('Embedding latency', $embedMs < 3000, "{$embedMs}ms (target under 3000ms on booth laptop)");

// 6. ProcessDocumentJob idempotency (delete-before-insert in code)
$jobFile = file_get_contents(dirname(__DIR__).'/app/Jobs/ProcessDocumentJob.php') ?: '';
ok('MySQL chunk delete before re-insert', str_contains($jobFile, "->delete()"), 'ProcessDocumentJob');

echo "\n=== Summary: ".($fail === 0 ? 'ALL PASS' : "{$fail} FAILED")." ===\n";
exit($fail > 0 ? 1 : 0);
