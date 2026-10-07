<?php
/**
 * Diagnosa: apakah API SIAKAD "salah"/kosong untuk 2026/2027 & mahasiswa baru.
 */
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../config.php');

$BASE = 'https://siakad.sugenghartono.ac.id/api';

function login() {
    global $BASE;
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
    $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $j = json_decode($raw, true);
    $token = $j['token'] ?? $j['data']['token'] ?? $j['access_token'] ?? $j['data']['access_token'] ?? null;
    mtrace("Login HTTP $http token=" . ($token ? 'OK' : 'FAIL'));
    if (!$token && is_array($j)) {
        mtrace('  keys: ' . implode(',', array_keys($j)));
        if (isset($j['message'])) {
            mtrace('  message: ' . (is_string($j['message']) ? $j['message'] : json_encode($j['message'])));
        }
    }
    return $token;
}

function getu($path, $token) {
    global $BASE;
    $ch = curl_init($BASE . $path);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token, 'Accept: application/json'],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 45,
    ]);
    $raw = curl_exec($ch);
    $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$http, json_decode($raw, true), $raw];
}

$token = login();
if (!$token) {
    exit(1);
}

sleep(2);

// 1) Meta pagination grades
[$http, $res] = getu('/grades-per-course?per_page=100&page=1', $token);
mtrace('');
mtrace("=== grades-per-course page1 HTTP $http ===");
if ($http !== 200) {
    mtrace('Body: ' . substr($raw ?? json_encode($res), 0, 300));
    sleep(5);
    [$http, $res] = getu('/grades-per-course?page=1', $token);
    mtrace("Retry HTTP $http");
}

if ($http === 200 && is_array($res)) {
    mtrace('Top keys: ' . implode(',', array_keys($res)));
    if (isset($res['meta']) && is_array($res['meta'])) {
        mtrace('meta: ' . json_encode($res['meta']));
    }
    $items = $res['data'] ?? [];
    mtrace('data count: ' . (is_array($items) ? count($items) : 0));
    if ($items) {
        $first = $items[0];
        mtrace('Student keys: ' . implode(',', array_keys($first)));
        $nim = $first['nim'] ?? '?';
        $name = $first['name'] ?? $first['nama'] ?? '?';
        mtrace('Sample mhs: nim=' . substr((string)$nim, 0, 4) . '**** name_len=' . strlen((string)$name));
        $g0 = $first['grade'][0] ?? null;
        if ($g0) {
            mtrace('Sample grade keys: ' . implode(',', array_keys($g0)));
            mtrace('  tahun_akademik=' . ($g0['tahun_akademik'] ?? 'NULL'));
            mtrace('  periode=' . ($g0['periode'] ?? 'NULL'));
            mtrace('  lesson_code=' . ($g0['lesson_code'] ?? 'NULL'));
            mtrace('  lecture_name=' . ($g0['lecture_name'] ?? 'NULL'));
        } else {
            mtrace('Sample mhs punya grade kosong');
        }
    }

    // Collect years from first few pages slowly
    $years = [];
    $nims = [];
    $maxAngkatan = [];
    for ($page = 1; $page <= 8; $page++) {
        if ($page > 1) {
            sleep(2);
            [$http, $res] = getu('/grades-per-course?per_page=100&page=' . $page, $token);
            if ($http === 429) {
                mtrace("page $page HTTP 429 — stop");
                break;
            }
            if ($http !== 200) {
                mtrace("page $page HTTP $http — stop");
                break;
            }
            $items = $res['data'] ?? [];
        }
        if (!$items) {
            break;
        }
        foreach ($items as $mhs) {
            $nim = (string)($mhs['nim'] ?? '');
            if ($nim !== '') {
                $nims[$nim] = true;
                // Angkatan dari NIM tipikal: digit 3-4 atau 1-2
                if (preg_match('/^\d{2}(\d{2})/', $nim, $m) || preg_match('/^(\d{2})/', $nim, $m)) {
                    $maxAngkatan[$m[1]] = ($maxAngkatan[$m[1]] ?? 0) + 1;
                }
            }
            foreach ($mhs['grade'] ?? [] as $g) {
                $k = trim(($g['tahun_akademik'] ?? '?') . ' | ' . ($g['periode'] ?? '?'));
                $years[$k] = ($years[$k] ?? 0) + 1;
            }
        }
        mtrace("  scanned page $page — mhs unik " . count($nims) . ', year-keys ' . count($years));
    }
    arsort($years);
    mtrace('');
    mtrace('Distribusi tahun|periode (dari sample halaman):');
    foreach (array_slice($years, 0, 15, true) as $k => $c) {
        mtrace("  $k → $c");
    }
    ksort($maxAngkatan);
    mtrace('Prefix tahun di NIM (sample): ' . json_encode($maxAngkatan));
    $has = false;
    foreach (array_keys($years) as $k) {
        if (str_contains($k, '2026/2027')) {
            $has = true;
        }
    }
    mtrace('Ada string 2026/2027 di sample? ' . ($has ? 'YA' : 'TIDAK'));
}

sleep(2);
mtrace('');
mtrace('=== Coba filter query ===');
foreach ([
    '/grades-per-course?tahun_akademik=2026/2027',
    '/grades-per-course?tahun=2026/2027',
    '/grades-per-course?academic_year=2026/2027',
    '/grades-per-course?periode=Ganjil&tahun_akademik=2026/2027',
    '/grades-per-course?semester=2026/2027',
] as $path) {
    sleep(1);
    [$h, $b] = getu($path, $token);
    $n = is_array($b['data'] ?? null) ? count($b['data']) : 0;
    mtrace("  HTTP $h n=$n | $path");
}

sleep(2);
mtrace('');
mtrace('=== Endpoint mahasiswa ===');
foreach (['/user/mahasiswa', '/mahasiswa', '/students', '/all-students'] as $ep) {
    sleep(1);
    [$h, $b] = getu($ep, $token);
    mtrace("  $ep → HTTP $h");
}

mtrace('');
mtrace('Selesai diagnosa.');
