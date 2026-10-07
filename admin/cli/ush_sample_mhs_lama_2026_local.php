<?php
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../config.php');

$BASE = 'https://siakad.sugenghartono.ac.id/api';
$ch = curl_init($BASE . '/login');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode([
        'email' => 'akademik@sugenghartono.ac.id',
        'password' => '321',
    ]),
    CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_TIMEOUT => 25,
]);
$raw = curl_exec($ch);
curl_close($ch);
$j = json_decode($raw, true);
$token = $j['token'] ?? $j['access_token'] ?? $j['data']['token'] ?? $j['data']['access_token'] ?? null;
if (!$token) {
    mtrace('Login gagal');
    exit(1);
}

function getpage($token, $page) {
    global $BASE;
    $ch = curl_init($BASE . '/grades-per-course?page=' . $page);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token, 'Accept: application/json'],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 40,
    ]);
    $raw = curl_exec($ch);
    $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$http, json_decode($raw, true)];
}

$found = null;
for ($page = 35; $page <= 70; $page++) {
    [$http, $res] = getpage($token, $page);
    if ($http !== 200) {
        mtrace("HTTP $http page $page");
        sleep(3);
        continue;
    }
    foreach ($res['data'] ?? [] as $mhs) {
        $nim = (string)($mhs['nim'] ?? '');
        // Mahasiswa lama: bukan prefix angkatan 26 di posisi tipikal 0626...
        if (preg_match('/^0626/', $nim)) {
            continue;
        }
        $krs2026 = [];
        foreach ($mhs['grade'] ?? [] as $g) {
            $ta = (string)($g['tahun_akademik'] ?? '');
            $periode = (string)($g['periode'] ?? '');
            if ($ta === '2026/2027' && strcasecmp($periode, 'Ganjil') === 0) {
                $krs2026[] = $g;
            }
        }
        if ($krs2026) {
            $found = ['mhs' => $mhs, 'krs' => $krs2026];
            break 2;
        }
    }
    usleep(250000);
}

if (!$found) {
    mtrace('Tidak ketemu di range page 35-70');
    exit(0);
}

$m = $found['mhs'];
$nim = (string)$m['nim'];
$masked = substr($nim, 0, 4) . '****' . substr($nim, -2);
mtrace('=== 1 mahasiswa lama (contoh) ===');
mtrace('NIM (masked): ' . $masked);
mtrace('Nama: ' . ($m['name'] ?? '?'));
mtrace('Semester field: ' . ($m['semester'] ?? '?'));
mtrace('Status: ' . ($m['status'] ?? '?'));
mtrace('Total baris grade (semua tahun): ' . count($m['grade'] ?? []));
mtrace('KRS 2026/2027 Ganjil: ' . count($found['krs']) . ' MK');
foreach ($found['krs'] as $g) {
    mtrace(sprintf(
        '  - %s | %s | dosen: %s | nilai: %s',
        $g['lesson_code'] ?? '?',
        $g['lesson_name'] ?? '?',
        $g['lecture_name'] ?? '?',
        ($g['nilai_huruf'] ?? '') !== '' ? $g['nilai_huruf'] : '(belum ada)'
    ));
}
