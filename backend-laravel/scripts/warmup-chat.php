<?php

declare(strict_types=1);

$mode = $argv[1] ?? 'primary';
$base = 'http://127.0.0.1:8000/api';

$question = $mode === 'no-answer'
    ? 'What is the capital city of Mars and its official textile export policy?'
    : 'What should an inspector do for a broken stitch?';

$ch = curl_init("{$base}/chat/sessions");
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
    CURLOPT_POSTFIELDS => json_encode(['context_tag' => 'exhibition_booth']),
]);
$session = json_decode(curl_exec($ch) ?: '{}', true);
curl_close($ch);

if (! isset($session['session_id'])) {
    fwrite(STDERR, "Failed to create chat session\n");
    exit(1);
}

$sid = $session['session_id'];
$ch = curl_init("{$base}/chat/sessions/{$sid}/messages");
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
    CURLOPT_POSTFIELDS => json_encode(['content' => $question], JSON_UNESCAPED_UNICODE),
]);
$result = json_decode(curl_exec($ch) ?: '{}', true);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($code !== 200) {
    fwrite(STDERR, "Chat failed HTTP {$code}\n");
    exit(1);
}

$chunks = count($result['retrieved_chunks'] ?? []);
$preview = mb_substr($result['assistant_message']['content'] ?? '', 0, 60);
echo "[OK]   {$mode}: chunks={$chunks} | {$preview}...\n";

if ($mode === 'no-answer' && ($result['no_answer'] ?? false) !== true) {
    fwrite(STDERR, "Expected no_answer=true for calibration query\n");
    exit(1);
}

if ($mode === 'primary' && $chunks < 1) {
    fwrite(STDERR, "Expected retrieved chunks for primary demo question\n");
    exit(1);
}

exit(0);
