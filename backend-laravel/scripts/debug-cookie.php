<?php
$cookieJar = tempnam(sys_get_temp_dir(), 'texo-cookie');
file_put_contents($cookieJar, '');
$ch = curl_init('http://localhost:8000/sanctum/csrf-cookie');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_COOKIEJAR => $cookieJar,
    CURLOPT_COOKIEFILE => $cookieJar,
    CURLOPT_HTTPHEADER => ['Origin: http://localhost:5173'],
]);
$body = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
echo "HTTP $code\n";
echo file_get_contents($cookieJar);
