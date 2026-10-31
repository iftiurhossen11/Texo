<?php

declare(strict_types=1);

$base = 'http://127.0.0.1:8000/api';

function post(string $url, array $body): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
        CURLOPT_POSTFIELDS => json_encode($body, JSON_UNESCAPED_UNICODE),
    ]);
    $raw = curl_exec($ch);
    curl_close($ch);

    return json_decode($raw ?: '{}', true);
}

function chat(string $question): array
{
    global $base;
    $session = post("{$base}/chat/sessions", ['context_tag' => 'exhibition_booth']);
    $sid = $session['session_id'];
    $result = post("{$base}/chat/sessions/{$sid}/messages", ['content' => $question]);

    return [
        'chunks' => count($result['retrieved_chunks'] ?? []),
        'no_answer' => $result['no_answer'] ?? null,
        'preview' => mb_substr($result['assistant_message']['content'] ?? '', 0, 100),
    ];
}

$tests = [
    'primary_en' => 'What should an inspector do for a broken stitch?',
    'no_answer' => 'What is the capital city of Mars and its official textile export policy?',
    'primary_bn' => 'ভাঙা সেলাই পেলে ইন্সপেক্টর কী করবে?',
    'hr' => 'What must a new floor employee complete on their first day?',
    'cyber' => 'What is the first step if an employee receives a suspicious phishing email?',
    'supply' => 'What checks are required when raw materials arrive at the factory gate?',
    'compliance' => 'What AQL should we use for major defects during a buyer audit?',
    'production' => 'What should happen when a defective piece is found at an inline inspection point?',
];

foreach ([1, 2, 3] as $pass) {
    echo "=== PASS {$pass} ===\n";
    foreach ($tests as $name => $q) {
        $r = chat($q);
        echo sprintf(
            "%s | chunks=%d no_answer=%s | %s\n",
            $name,
            $r['chunks'],
            $r['no_answer'] === true ? 'true' : ($r['no_answer'] === false ? 'false' : 'null'),
            $r['preview']
        );
    }
    echo "\n";
}
