<?php
define('CLI_SCRIPT', true);
@error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);
require(__DIR__ . '/../../config.php');

function api_get($ep) {
    $url = "https://siakad.sugenghartono.ac.id/api" . $ep;
    $ch  = curl_init($url);
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

mtrace("=== PENCARIAN MENDALAM TERKAIT 'KAPRODI' / 'KETUA' DI API SIAKAD ===\n");

$working_endpoints = [
    '/all-lessons?page=1',
    '/all-lessons?page=2',
    '/grades-per-course?page=1',
    '/grades-per-course?page=2'
];

$keywords = ['kaprodi', 'ketua', 'head', 'koordinator', 'manager', 'pimpinan', 'dekan', 'prodi'];
$found_matches = [];

foreach ($working_endpoints as $ep) {
    $data = api_get($ep);
    $json = json_encode($data);
    
    foreach ($keywords as $kw) {
        if (stripos($json, $kw) !== false) {
            $found_matches[$ep][] = $kw;
        }
    }
}

mtrace("Hasil Pencarian Kata Kunci:");
if (empty($found_matches)) {
    mtrace("  ❌ Tidak ditemukan atribut 'kaprodi/ketua/pimpinan' di response data API SIAKAD.");
} else {
    foreach ($found_matches as $ep => $kws) {
        mtrace("  ✅ Match di $ep : " . implode(', ', array_unique($kws)));
    }
}

// Show all unique keys present in /all-lessons items
$res = api_get('/all-lessons?page=1');
if (!empty($res['data'])) {
    $sample = reset($res['data']);
    mtrace("\nField yang ada di /all-lessons:");
    mtrace("  " . json_encode(array_keys((array)$sample)));
}

// Show all unique keys present in /grades-per-course items
$res2 = api_get('/grades-per-course?page=1');
if (!empty($res2['data'])) {
    $sample2 = reset($res2['data']);
    mtrace("\nField yang ada di /grades-per-course:");
    mtrace("  " . json_encode(array_keys((array)$sample2)));
    if (!empty($sample2['grade'])) {
        $sample_g = reset($sample2['grade']);
        mtrace("  Field di dalam 'grade': " . json_encode(array_keys((array)$sample_g)));
    }
}
