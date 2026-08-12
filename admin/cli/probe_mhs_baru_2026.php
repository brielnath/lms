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

mtrace("=== CEK SISA ENDPOINT SIAKAD UNTUK DATA MAHASISWA BARU (2026) ===\n");

// Try more potential endpoints for students/PMB
$endpoints = [
    '/pmb', '/pendaftaran', '/calon-mahasiswa',
    '/user-mahasiswa', '/users?role=student',
    '/all-users', '/users', '/user',
    '/grades-per-course?page=1',
    '/grades-per-course?page=2',
    '/grades-per-course?page=3'
];

$found_2026 = 0;
$total_mhs = 0;
$nim_prefixes = [];

foreach ($endpoints as $ep) {
    $res = api_get($ep);
    if (!empty($res['data'])) {
        $data = $res['data'];
        if (isset($data['data'])) $data = $data['data']; // nested pagination
        if (is_array($data)) {
            foreach ($data as $row) {
                $nim = $row['nim'] ?? $row['username'] ?? '';
                if ($nim) {
                    $total_mhs++;
                    $prefix = substr($nim, 0, 4);
                    $nim_prefixes[$prefix] = ($nim_prefixes[$prefix] ?? 0) + 1;
                    if (str_contains($nim, '26') || str_starts_with($nim, '0626')) {
                        $found_2026++;
                    }
                }
            }
        }
    }
}

mtrace("Prefix NIM yang terdeteksi di SIAKAD API:");
ksort($nim_prefixes);
foreach ($nim_prefixes as $p => $c) {
    mtrace("  • NIM $p... : $c data");
}

mtrace("\nJumlah Mahasiswa Angkatan 2026 di SIAKAD API saat ini: $found_2026");
