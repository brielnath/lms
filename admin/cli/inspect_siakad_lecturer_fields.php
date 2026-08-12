<?php
define('CLI_SCRIPT', true);
@error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);
require(__DIR__ . '/../../config.php');

$token = "320|Gs2KVb4VtawDCnc2foDC8Up9UXbe7c95NGgu1OKZcfe5622b";

function api_get($url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer 320|Gs2KVb4VtawDCnc2foDC8Up9UXbe7c95NGgu1OKZcfe5622b',
        'Accept: application/json'
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    $result = curl_exec($ch);
    curl_close($ch);
    return json_decode($result, true);
}

// 1. Check all-lessons sample structure
$lessons = api_get("https://siakad.sugenghartono.ac.id/api/all-lessons?page=1");
mtrace("=== LESSON KEYS ===");
if (!empty($lessons['data'][0])) {
    mtrace(print_r(array_keys($lessons['data'][0]), true));
    mtrace(json_encode($lessons['data'][0], JSON_PRETTY_PRINT));
}

// 2. Check grades-per-course sample
$grades = api_get("https://siakad.sugenghartono.ac.id/api/grades-per-course?page=1");
mtrace("\n=== GRADES KEYS ===");
if (!empty($grades['data'][0])) {
    mtrace(print_r(array_keys($grades['data'][0]), true));
}
