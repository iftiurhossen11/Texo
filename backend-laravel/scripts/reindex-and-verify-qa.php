<?php

declare(strict_types=1);

/**
 * Upload updated SOP + verify all backup Q&A including supply chain.
 */

$base = 'http://127.0.0.1:8000/api';
$rag = 'http://127.0.0.1:8001';

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

    return ['code' => $code, 'body' => json_decode($raw ?: '{}', true)];
}

function uploadFile(string $path, string $title): ?int
{
    global $base;
    if (! file_exists($path)) {
        echo "[SKIP] Missing file: {$path}\n";

        return null;
    }

    $ch = curl_init("{$base}/documents");
    $mime = match (pathinfo($path, PATHINFO_EXTENSION)) {
        'csv' => 'text/csv',
        'pdf' => 'application/pdf',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        default => 'text/plain',
    };
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
        CURLOPT_POSTFIELDS => [
            'file' => new CURLFile($path, $mime, basename($path)),
            'title' => $title,
            'doc_type' => 'sop',
            'department' => 'qa',
        ],
    ]);
    $raw = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $data = json_decode($raw ?: '{}', true);
    if ($code !== 201) {
        echo "[FAIL] Upload {$path} HTTP {$code}: ".substr($raw ?: '', 0, 120)."\n";

        return null;
    }
    echo "[OK] Uploaded {$path} → doc #{$data['document_id']}\n";

    return (int) $data['document_id'];
}

function waitIndexed(int $id): bool
{
    global $base;
    for ($i = 0; $i < 40; $i++) {
        sleep(2);
        $ch = curl_init("{$base}/documents/{$id}");
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => ['Accept: application/json']]);
        $data = json_decode(curl_exec($ch) ?: '{}', true);
        curl_close($ch);
        $status = $data['data']['status'] ?? '';
        if ($status === 'indexed') {
            return true;
        }
        if ($status === 'failed') {
            return false;
        }
    }

    return false;
}

function chat(string $question): array
{
    global $base;
    $session = postJson("{$base}/chat/sessions", ['context_tag' => 'exhibition_booth']);
    $sid = $session['body']['session_id'] ?? 0;
    $r = postJson("{$base}/chat/sessions/{$sid}/messages", ['content' => $question]);

    return [
        'code' => $r['code'],
        'chunks' => count($r['body']['retrieved_chunks'] ?? []),
        'no_answer' => $r['body']['no_answer'] ?? false,
        'preview' => mb_substr($r['body']['assistant_message']['content'] ?? '', 0, 80),
    ];
}

echo "=== Re-index updated SOP ===\n";
$root = dirname(__DIR__, 2);
$sopPath = "{$root}/demo-content/garment-quality-inspection-sop.txt";
copy("{$root}/demo-content/garment-quality-inspection-sop.md", $sopPath);
$docId = uploadFile($sopPath, 'Garment SOP Final v2');
if ($docId && waitIndexed($docId)) {
    echo "[OK] SOP indexed\n";
} else {
    echo "[WARN] SOP indexing slow or failed — continuing\n";
}

echo "\n=== Backup Q&A verification ===\n";
$tests = [
    'primary' => 'What should an inspector do for a broken stitch?',
    'production' => 'What should happen when a defective piece is found at an inline inspection point?',
    'compliance' => 'What AQL should we use for major defects during a buyer audit?',
    'hr' => 'What must a new floor employee complete on their first day?',
    'supply' => 'What checks are required when raw materials arrive at the factory gate?',
    'cyber' => 'What is the first step if an employee receives a suspicious phishing email?',
    'no_answer' => 'What is the capital city of Mars and its official textile export policy?',
];

$fail = 0;
foreach ($tests as $name => $q) {
    $r = chat($q);
    $ok = $name === 'no_answer'
        ? ($r['no_answer'] === true && $r['chunks'] === 0)
        : ($r['chunks'] > 0 && $r['no_answer'] !== true);
    if (! $ok) {
        $fail++;
    }
    echo sprintf(
        "%s %s | chunks=%d no_answer=%s | %s\n",
        $ok ? '[OK]' : '[FAIL]',
        $name,
        $r['chunks'],
        $r['no_answer'] ? 'true' : 'false',
        $r['preview']
    );
}

exit($fail > 0 ? 1 : 0);
