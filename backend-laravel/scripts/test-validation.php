<?php

declare(strict_types=1);

$base = 'http://127.0.0.1:8000/api';

function postJson(string $url, string $body): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
        CURLOPT_POSTFIELDS => $body,
    ]);
    $raw = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ['code' => $code, 'body' => $raw ?: ''];
}

$session = postJson("{$base}/chat/sessions", '{"context_tag":"exhibition_booth"}');
$sid = json_decode($session['body'], true)['session_id'] ?? 0;

$empty = postJson("{$base}/chat/sessions/{$sid}/messages", '{"content":"   "}');
$long = postJson("{$base}/chat/sessions/{$sid}/messages", '{"content":"'.str_repeat('x', 501).'"}');

echo "Empty whitespace: HTTP {$empty['code']}\n";
echo "Long 501 chars: HTTP {$long['code']}\n";
