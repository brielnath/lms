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
    return ['http' => curl_getinfo($ch, CURLINFO_HTTP_CODE), 'data' => json_decode($result, true)];
}

mtrace("=== PROBE DATA KAPRODI DARI SIAKAD API ===\n");

$endpoints = [
    '/prodi', '/program-studi', '/study-programs',
    '/departments', '/faculties', '/fakultas',
    '/kaprodi', '/head-of-program', '/lecturers',
    '/dosen', '/all-lecturers', '/teachers',
    '/structure', '/organization'
];

foreach ($endpoints as $ep) {
    $res = api_get($ep);
    $status = $res['http'] == 200 ? "✅ 200 OK" : "❌ {$res['http']}";
    mtrace("  $status | $ep");
    if ($res['http'] == 200 && !empty($res['data'])) {
        mtrace("    Data keys: " . json_encode(array_keys((array)$res['data'])));
        $items = $res['data']['data'] ?? $res['data'];
        if (is_array($items) && !empty($items)) {
            $first = reset($items);
            if (is_array($first) || is_object($first)) {
                mtrace("    Item keys: " . json_encode(array_keys((array)$first)));
                mtrace("    Sample: " . json_encode($first));
            }
        }
    }
}

// Inspect grades-per-course to see if lecture records have any title / position / role
mtrace("\n=== INSPEKSI AKUN DOSEN DI DATA SIAKAD ===");
$res = api_get('/grades-per-course?page=1');
if (!empty($res['data']['data'])) {
    $dosen_set = [];
    foreach ($res['data']['data'] as $mhs) {
        foreach ($mhs['grade'] ?? [] as $g) {
            $id = $g['id_lecture'] ?? null;
            $name = $g['lecture_name'] ?? '';
            if ($id && $name && $name !== 'Unknown') {
                $dosen_set[$id] = $name;
            }
        }
    }
    mtrace("  Total Dosen Terdeteksi dari Nilai: " . count($dosen_set));
    foreach (array_slice($dosen_set, 0, 15, true) as $id => $name) {
        mtrace("    • [ID: $id] $name");
    }
}
