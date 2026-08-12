<?php
define('CLI_SCRIPT', true);
@error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);
require(__DIR__ . '/../../config.php');

$token = "320|Gs2KVb4VtawDCnc2foDC8Up9UXbe7c95NGgu1OKZcfe5622b";

function api_get($endpoint) {
    $url = "https://siakad.sugenghartono.ac.id/api" . $endpoint;
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer 320|Gs2KVb4VtawDCnc2foDC8Up9UXbe7c95NGgu1OKZcfe5622b',
        'Accept: application/json'
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $result = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['http' => $http, 'data' => json_decode($result, true), 'raw' => substr($result, 0, 300)];
}

mtrace("=== PROBE SIAKAD API: KRS & SEMESTER DATA ===\n");

$endpoints = [
    '/krs',
    '/krs-mahasiswa',
    '/all-krs',
    '/student-courses',
    '/enrollments',
    '/active-students',
    '/semester',
    '/academic-year',
    '/tahun-akademik',
    '/period',
    '/all-periods',
    '/all-lessons?semester=1',
    '/all-lessons?tahun=2025',
    '/grades-per-course?semester=Ganjil',
    '/grades-per-course?tahun=2025',
];

foreach ($endpoints as $ep) {
    $res = api_get($ep);
    $status = $res['http'] == 200 ? "✅ 200 OK" : "❌ {$res['http']}";
    mtrace("  $status | $ep");
    if ($res['http'] == 200 && !empty($res['data'])) {
        mtrace("    Keys: " . json_encode(array_keys((array)$res['data'])));
        if (!empty($res['data']['data'])) {
            $first = reset($res['data']['data']);
            mtrace("    First item keys: " . json_encode(array_keys((array)$first)));
        }
    }
}

// Also check what tahun_akademik values exist in current grade data
mtrace("\n=== CEK TAHUN AKADEMIK DI DATA NILAI SIAKAD ===");
$res = api_get('/grades-per-course?page=1');
if (!empty($res['data']['data'])) {
    $tahun_set = [];
    foreach ($res['data']['data'] as $mhs) {
        foreach ($mhs['grade'] ?? [] as $g) {
            $ta = $g['tahun_akademik'] ?? '';
            $periode = $g['periode'] ?? '';
            $key = "$ta - $periode";
            $tahun_set[$key] = ($tahun_set[$key] ?? 0) + 1;
        }
    }
    arsort($tahun_set);
    mtrace("  Daftar Tahun Akademik & Semester yang Ada:");
    foreach ($tahun_set as $ta => $count) {
        mtrace("    • $ta ($count mata kuliah)");
    }
}

// Check all-lessons for semester fields
mtrace("\n=== CEK FIELD SEMESTER DI ALL-LESSONS ===");
$res2 = api_get('/all-lessons?page=1');
if (!empty($res2['data']['data'])) {
    $sample = reset($res2['data']['data']);
    mtrace("  Sample lesson keys: " . json_encode(array_keys((array)$sample)));
    mtrace("  Sample: " . json_encode($sample));
}
