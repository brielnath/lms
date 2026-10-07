<?php
/** Cek cepat SIAKAD: login, tahun di grades, endpoint kaprodi. */
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
$token = $j['token'] ?? $j['data']['token'] ?? $j['access_token'] ?? null;
if (!$token) {
    mtrace('Login gagal');
    exit(1);
}
mtrace('Login OK');
sleep(3);

function getu($url, $token) {
    $ch = curl_init($url);
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

[$http, $res] = getu($BASE . '/grades-per-course?per_page=50&page=1', $token);
mtrace("grades-per-course page1 HTTP $http");
$years = [];
$n = 0;
if ($http === 200) {
    foreach ($res['data'] ?? [] as $mhs) {
        foreach ($mhs['grade'] ?? [] as $g) {
            $n++;
            $k = trim(($g['tahun_akademik'] ?? '?') . ' | ' . ($g['periode'] ?? '?'));
            $years[$k] = ($years[$k] ?? 0) + 1;
        }
    }
}
arsort($years);
mtrace("Baris grade di page1: $n");
foreach (array_slice($years, 0, 12, true) as $k => $c) {
    mtrace("  $k → $c");
}
$has202627 = false;
foreach (array_keys($years) as $k) {
    if (str_contains($k, '2026/2027') || str_starts_with(preg_replace('/[^0-9]/', '', explode('|', $k)[0] ?? ''), '20262027')) {
        $has202627 = true;
    }
}
mtrace('Ada 2026/2027? ' . ($has202627 ? 'YA' : 'TIDAK'));

mtrace('');
mtrace('Endpoint baru?');
foreach (['/kaprodi', '/prodi', '/krs', '/academic-year', '/dosen', '/user/mahasiswa'] as $ep) {
    sleep(1);
    [$h, $b] = getu($BASE . $ep, $token);
    mtrace("  $ep → HTTP $h");
}
