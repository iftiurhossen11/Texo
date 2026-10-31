<?php

declare(strict_types=1);

$files = [
    ['path' => dirname(__DIR__, 2).'/demo-content/sample-defect-codes.csv', 'mime' => 'text/csv'],
    ['path' => dirname(__DIR__, 2).'/demo-content/sample-demo-page.pdf', 'mime' => 'application/pdf'],
    ['path' => dirname(__DIR__, 2).'/demo-content/garment-quality-inspection-sop.docx', 'mime' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
];

$fail = 0;
foreach ($files as $f) {
    if (! file_exists($f['path'])) {
        echo '[FAIL] Missing '.$f['path']."\n";
        $fail++;
        continue;
    }
    $ch = curl_init('http://127.0.0.1:8000/api/documents');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
        CURLOPT_POSTFIELDS => [
            'file' => new CURLFile($f['path'], $f['mime'], basename($f['path'])),
            'title' => basename($f['path']),
            'doc_type' => 'sop',
            'department' => 'qa',
        ],
    ]);
    $raw = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $ok = $code === 201;
    echo ($ok ? '[OK]' : '[FAIL]').' '.basename($f['path'])." HTTP {$code}\n";
    if (! $ok) {
        $fail++;
    }
}

exit($fail > 0 ? 1 : 0);
