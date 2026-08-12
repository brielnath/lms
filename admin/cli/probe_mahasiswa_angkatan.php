<?php
define('CLI_SCRIPT', true);
@error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);
require(__DIR__ . '/../../config.php');

function api_get($endpoint) {
    $url = "https://siakad.sugenghartono.ac.id/api" . $endpoint;
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

mtrace("=== PROBE DATA MAHASISWA PER ANGKATAN ===\n");

// Test student-related endpoints
$endpoints = [
    '/all-students', '/students', '/mahasiswa',
    '/all-mahasiswa', '/students?angkatan=2023',
    '/students?angkatan=2026', '/students?tahun=2023',
    '/mahasiswa-baru', '/new-students',
];

foreach ($endpoints as $ep) {
    $url = "https://siakad.sugenghartono.ac.id/api" . $ep;
    $ch  = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer 320|Gs2KVb4VtawDCnc2foDC8Up9UXbe7c95NGgu1OKZcfe5622b',
        'Accept: application/json'
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 8);
    $result = curl_exec($ch);
    $http   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $data = json_decode($result, true);
    $status = $http == 200 ? "✅ 200 OK" : "❌ $http";
    mtrace("  $status | $ep");

    if ($http == 200 && !empty($data['data'])) {
        $first = reset($data['data']);
        mtrace("    Keys: " . json_encode(array_keys((array)$first)));
        mtrace("    Total: " . count($data['data']));
        if (isset($first['nim'])) {
            // Show angkatan distribution
            $angkatan_map = [];
            foreach ($data['data'] as $s) {
                $nim = $s['nim'] ?? '';
                $ang = substr($nim, 0, 2); // first 2 digits of NIM = angkatan
                $angkatan_map[$ang] = ($angkatan_map[$ang] ?? 0) + 1;
            }
            ksort($angkatan_map);
            mtrace("    Distribusi Angkatan (dari NIM):");
            foreach ($angkatan_map as $ang => $count) {
                mtrace("      Angkatan 20$ang: $count mahasiswa");
            }
        }
    }
}

// Check grades-per-course for student NIM patterns
mtrace("\n=== CEK POLA NIM MAHASISWA DI DATA NILAI ===");
$res = api_get('/grades-per-course?page=1');
if (!empty($res['data'])) {
    $nim_map = [];
    foreach (array_slice($res['data'], 0, 200) as $mhs) {
        $nim = $mhs['nim'] ?? '';
        $ang = substr($nim, 0, 2);
        if ($ang) $nim_map["20$ang"] = ($nim_map["20$ang"] ?? 0) + 1;
    }
    ksort($nim_map);
    mtrace("  Distribusi Angkatan dari data nilai:");
    foreach ($nim_map as $ang => $cnt) {
        mtrace("    Angkatan $ang: $cnt mahasiswa (di sample 200)");
    }

    // Show sample student data structure
    $sample = reset($res['data']);
    mtrace("\n  Struktur data mahasiswa dari /grades-per-course:");
    mtrace("  " . json_encode(array_keys((array)$sample)));
    mtrace("  Contoh NIM: " . ($sample['nim'] ?? '-'));
    mtrace("  Contoh Nama: " . ($sample['name'] ?? '-'));
    mtrace("  Contoh Semester: " . ($sample['semester'] ?? '-'));
    mtrace("  Contoh Status: " . ($sample['status'] ?? '-'));
}

// Check existing Moodle students
mtrace("\n=== CEK MAHASISWA DI MOODLE SAAT INI ===");
$student_role = $DB->get_record('role', ['shortname' => 'student']);
$total_students = $DB->count_records_sql("
    SELECT COUNT(DISTINCT u.id)
    FROM {user} u
    JOIN {role_assignments} ra ON ra.userid = u.id
    WHERE ra.roleid = ?
", [$student_role->id]);
mtrace("  Total Mahasiswa di Moodle: $total_students akun");

$all_students = $DB->get_records_sql("
    SELECT DISTINCT u.id, u.username, u.firstname, u.lastname
    FROM {user} u
    JOIN {role_assignments} ra ON ra.userid = u.id
    WHERE ra.roleid = ?
    LIMIT 5
", [$student_role->id]);

foreach ($all_students as $s) {
    mtrace("  • [{$s->username}] {$s->firstname} {$s->lastname}");
}

// NIM pattern from username
$ang_map = [];
foreach ($DB->get_records_sql("SELECT DISTINCT u.username FROM {user} u JOIN {role_assignments} ra ON ra.userid = u.id WHERE ra.roleid = ?", [$student_role->id]) as $u) {
    $ang = substr($u->username, 0, 2);
    $ang_map["20$ang"] = ($ang_map["20$ang"] ?? 0) + 1;
}
ksort($ang_map);
mtrace("\n  Distribusi Angkatan Mahasiswa di Moodle:");
foreach ($ang_map as $ang => $cnt) {
    mtrace("    Angkatan $ang: $cnt mahasiswa");
}
