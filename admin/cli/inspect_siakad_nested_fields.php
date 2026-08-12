<?php
define('CLI_SCRIPT', true);
@error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);
require(__DIR__ . '/../../config.php');

$token = "320|Gs2KVb4VtawDCnc2foDC8Up9UXbe7c95NGgu1OKZcfe5622b";

function api_get($endpoint) {
    global $token;
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
    curl_close($ch);
    return json_decode($result, true);
}

mtrace("=== CHECKING ALL LESSONS FOR LECTURER / DOSEN FIELDS ===");
$res = api_get("/all-lessons?page=1");
if (!empty($res['data'])) {
    foreach (array_slice($res['data'], 0, 5) as $idx => $item) {
        mtrace("\nItem #{$idx}:");
        mtrace(json_encode($item, JSON_PRETTY_PRINT));
    }
}

mtrace("\n=== CHECKING GRADES PER COURSE SAMPLE ===");
$res_g = api_get("/grades-per-course?page=1");
if (!empty($res_g['data'])) {
    foreach (array_slice($res_g['data'], 0, 3) as $idx => $item) {
        mtrace("\nGrade Item #{$idx}:");
        mtrace(json_encode($item, JSON_PRETTY_PRINT));
    }
}
