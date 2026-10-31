<?php

declare(strict_types=1);

function parseCookies(string $jarPath): array
{
    $cookies = [];
    foreach (file($jarPath) ?: [] as $line) {
        if ($line[0] === '#' || trim($line) === '') {
            continue;
        }
        $parts = explode("\t", trim($line));
        if (count($parts) >= 7) {
            $cookies[$parts[5]] = $parts[6];
        }
    }

    return $cookies;
}

$cookieJar = tempnam(sys_get_temp_dir(), 'texo-cookie');
file_put_contents($cookieJar, "# Netscape HTTP Cookie File\n");

$base = 'http://127.0.0.1:8000';
$origin = 'http://127.0.0.1:5173';

$ch = curl_init("{$base}/sanctum/csrf-cookie");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_COOKIEJAR => $cookieJar,
    CURLOPT_COOKIEFILE => $cookieJar,
    CURLOPT_HTTPHEADER => ["Origin: {$origin}"],
]);
curl_exec($ch);
curl_close($ch);

$cookies = parseCookies($cookieJar);
$xsrf = urldecode($cookies['XSRF-TOKEN'] ?? '');

if ($xsrf === '') {
    echo "[FAIL] Sanctum login: no XSRF-TOKEN cookie\n";
    @unlink($cookieJar);
    exit(1);
}

$ch = curl_init("{$base}/api/login");
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_COOKIEJAR => $cookieJar,
    CURLOPT_COOKIEFILE => $cookieJar,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Accept: application/json',
        "Origin: {$origin}",
        "Referer: {$origin}",
        'X-XSRF-TOKEN: '.$xsrf,
    ],
    CURLOPT_POSTFIELDS => json_encode([
        'email' => 'admin@texo.local',
        'password' => 'admin1234',
    ]),
]);
$body = curl_exec($ch);
$code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
@unlink($cookieJar);

$data = json_decode($body ?: '{}', true);
$email = $data['user']['email'] ?? null;

echo $code === 200 && $email ? "[OK] Sanctum login: {$email}\n" : "[FAIL] Sanctum login HTTP {$code}\n";
exit($code === 200 && $email ? 0 : 1);
