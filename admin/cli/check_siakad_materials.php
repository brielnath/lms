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
    curl_close($ch);
    return json_decode($result, true);
}

// Ambil semua lessons dan cek yang punya file_rps
$response = api_get($base . "/all-lessons", $token);
$items = $response['data'] ?? [];

$has_rps = [];
$no_rps = 0;

foreach ($items as $lesson) {
    $code = $lesson['code'] ?? '';
    $name = $lesson['name'] ?? '';
    $rps = $lesson['file_rps'] ?? null;

    if (!empty($rps)) {
        $has_rps[] = [
            'code' => $code,
            'name' => $name,
            'rps' => $rps
        ];
    } else {
        $no_rps++;
    }
}

mtrace("==================================================");
mtrace("📋 ANALISIS SUMBER MATERI DARI SIAKAD USH");
mtrace("==================================================");
mtrace("Total Mata Kuliah: " . count($items));
mtrace("Punya File RPS   : " . count($has_rps));
mtrace("Belum Punya RPS  : " . $no_rps);
mtrace("");

mtrace("📄 DAFTAR MATA KULIAH YANG SUDAH PUNYA FILE RPS:");
mtrace("--------------------------------------------------");
foreach ($has_rps as $r) {
    mtrace("  ✅ [{$r['code']}] {$r['name']}");
    mtrace("     📎 File: {$r['rps']}");
}
