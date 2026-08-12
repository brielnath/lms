<?php
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../config.php');

$token = "320|Gs2KVb4VtawDCnc2foDC8Up9UXbe7c95NGgu1OKZcfe5622b";
$base = "https://siakad.sugenghartono.ac.id/api";

function api_get($url, $token) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $token,
        'Accept: application/json'
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $result = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $http, 'body' => $result];
}

// Test beberapa endpoint mahasiswa yang mungkin
$endpoints = [
    "/user/mahasiswa",
    "/user/mahasiswa?page=1",
    "/mahasiswa",
    "/grades-per-course?page=1",
    "/all-lessons?page=1",
];

foreach ($endpoints as $ep) {
    echo "=== ENDPOINT: {$ep} ===" . PHP_EOL;
    $r = api_get($base . $ep, $token);
    echo "HTTP: {$r['code']}" . PHP_EOL;
    
    // Hanya tampilkan 500 karakter pertama dari respons
    $body = $r['body'];
    if (strlen($body) > 1500) {
        echo "Response (truncated): " . substr($body, 0, 1500) . "..." . PHP_EOL;
    } else {
        echo "Response: " . $body . PHP_EOL;
    }
    echo PHP_EOL;
}
