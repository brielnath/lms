<?php
define('CLI_SCRIPT', true);
@error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);
require(__DIR__ . '/../../config.php');

$token = "320|Gs2KVb4VtawDCnc2foDC8Up9UXbe7c95NGgu1OKZcfe5622b";
$base_api = "https://siakad.sugenghartono.ac.id/api";

function api_get($endpoint, $token) {
    $url = "https://siakad.sugenghartono.ac.id/api" . $endpoint;
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $token,
        'Accept: application/json'
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    $result = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['http' => $http, 'data' => json_decode($result, true)];
}

mtrace("=== TESTING SIAKAD DOSEN ENDPOINTS ===");

$possible_endpoints = [
    '/lecturers',
    '/all-lecturers',
    '/dosen',
    '/all-dosen',
    '/teachers',
    '/users?role=dosen',
    '/user'
];

foreach ($possible_endpoints as $ep) {
    $res = api_get($ep, $token);
    mtrace("Endpoint: {$ep} | HTTP: {$res['http']}");
    if ($res['http'] == 200 && !empty($res['data'])) {
        $sample = is_array($res['data']) ? array_slice($res['data'], 0, 2) : $res['data'];
        mtrace("  Data sample: " . json_encode($sample, JSON_PRETTY_PRINT));
    }
}
