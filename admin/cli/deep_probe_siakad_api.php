<?php
define('CLI_SCRIPT', true);
@error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);
require(__DIR__ . '/../../config.php');

$token = "320|Gs2KVb4VtawDCnc2foDC8Up9UXbe7c95NGgu1OKZcfe5622b";

function probe_endpoint($ep) {
    global $token;
    $url = "https://siakad.sugenghartono.ac.id/api" . $ep;
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $token,
        'Accept: application/json'
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $result = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['http' => $http, 'body' => json_decode($result, true), 'raw' => $result];
}

mtrace("=== DEEP DISCOVERY SIAKAD API ENDPOINTS ===");

$endpoints = [
    '/dosen',
    '/all-dosen',
    '/list-dosen',
    '/get-dosen',
    '/lecturer',
    '/lecturers',
    '/all-lecturers',
    '/teacher',
    '/teachers',
    '/pegawai',
    '/staff',
    '/pengajar',
    '/schedule',
    '/jadwal',
    '/classes',
    '/kelas',
    '/course-teachers',
    '/dosen-matkul',
    '/users',
    '/all-users',
    '/profile',
    '/me',
    '/account',
    '/academic-year',
    '/semesters',
    '/prodi',
    '/departments',
    '/faculties'
];

foreach ($endpoints as $ep) {
    $res = probe_endpoint($ep);
    $status = ($res['http'] == 200) ? "✅ 200 OK" : "❌ {$res['http']}";
    mtrace("Endpoint: {$ep} | Status: {$status}");
    if ($res['http'] == 200) {
        $keys = is_array($res['body']) ? array_keys($res['body']) : [];
        mtrace("   Response keys: " . json_encode($keys));
        mtrace("   Sample: " . substr(json_encode($res['body']), 0, 300));
    }
}
