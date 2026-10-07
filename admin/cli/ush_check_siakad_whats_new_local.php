<?php
/**
 * Ringkas: data baru di SIAKAD vs Moodle lokal (agregat saja).
 * Pakai: php admin/cli/ush_check_siakad_whats_new_local.php
 */
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../config.php');

if (strpos($CFG->wwwroot, 'localhost') === false && strpos($CFG->wwwroot, '127.0.0.1') === false) {
    mtrace('Dibatalkan: bukan LMS lokal.');
    exit(1);
}

$BASE = 'https://siakad.sugenghartono.ac.id/api';
$USER = 'akademik@sugenghartono.ac.id';
$PASS = '321';

function siakad_login(string $base, string $user, string $pass): ?string {
    $ch = curl_init($base . '/login');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode(['email' => $user, 'password' => $pass]),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 20,
    ]);
    $raw = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($http !== 200 || !$raw) {
        return null;
    }
    $j = json_decode($raw, true);
    return $j['token'] ?? $j['data']['token'] ?? $j['access_token'] ?? $j['data']['access_token'] ?? null;
}

function siakad_get(string $url, string $token): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token, 'Accept: application/json'],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 60,
    ]);
    $raw = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$http, json_decode($raw, true)];
}

function ush_norm_code(string $code): string {
    $code = strtoupper(trim($code));
    $code = preg_replace('/\s+/', '', $code);
    // IDM0629_20262027Ganjil -> IDM0629
    if (preg_match('/^([A-Z]{3}\d+[A-Z]?)/', $code, $m)) {
        return $m[1];
    }
    return preg_replace('/[^A-Z0-9]/', '', $code);
}

/** Kurikulum resmi baru: IDM/IUM/IFM/IDE/GDM (+ digit). */
function ush_is_official(string $code): bool {
    return (bool) preg_match('/^(IDM|IUM|IFM|IDE|GDM)\d+/i', $code);
}

mtrace('=== CEK SIAKAD vs LMS lokal ===');
mtrace('Waktu: ' . date('Y-m-d H:i:s'));

$token = siakad_login($BASE, $USER, $PASS);
if (!$token) {
    mtrace('Gagal login SIAKAD');
    exit(1);
}
mtrace('Login SIAKAD: OK');

// --- all-lessons (paginated if needed) ---
$codes = []; // code => name
$page = 1;
while ($page <= 20) {
    [$http, $res] = siakad_get($BASE . '/all-lessons?per_page=100&page=' . $page, $token);
    if ($http !== 200 || !is_array($res)) {
        mtrace("all-lessons page $page HTTP $http");
        break;
    }
    $rows = $res['data'] ?? [];
    if (!$rows) {
        break;
    }
    foreach ($rows as $L) {
        if (!is_array($L)) {
            continue;
        }
        $code = ush_norm_code((string)($L['code'] ?? $L['kode'] ?? ''));
        $name = (string)($L['name'] ?? $L['nama'] ?? '');
        if ($code !== '') {
            $codes[$code] = $name;
        }
    }
    $last = (int)($res['meta']['last_page'] ?? $res['last_page'] ?? 0);
    mtrace("  all-lessons page $page: " . count($rows) . ' item, unik ' . count($codes));
    if ($last > 0 && $page >= $last) {
        break;
    }
    if (count($rows) < 50 && $last === 0) {
        break;
    }
    $page++;
}

$official = array_filter($codes, fn($n, $c) => ush_is_official($c), ARRAY_FILTER_USE_BOTH);
$legacy = array_filter($codes, fn($n, $c) => !ush_is_official($c), ARRAY_FILTER_USE_BOTH);
mtrace('Total kode MK SIAKAD: ' . count($codes) . ' (resmi ' . count($official) . ', kurikulum lama ' . count($legacy) . ')');

// Moodle 2026/2027
$moodle2026 = $DB->get_records_sql(
    "SELECT id, shortname, fullname FROM {course}
      WHERE " . $DB->sql_like('shortname', ':p'),
    ['p' => '%20262027%']
);
$moodleIndex = [];
foreach ($moodle2026 as $c) {
    $moodleIndex[ush_norm_code($c->shortname)] = $c->shortname;
}
mtrace('Kelas Moodle *20262027*: ' . count($moodle2026) . ' (kode unik ' . count($moodleIndex) . ')');

$missingOfficial = [];
$missingLegacy = [];
foreach ($codes as $code => $name) {
    if (isset($moodleIndex[$code]) || isset($moodleIndex[$code . 'P'])) {
        continue;
    }
    if (ush_is_official($code)) {
        $missingOfficial[$code] = $name;
    } else {
        $missingLegacy[$code] = $name;
    }
}
ksort($missingOfficial);
mtrace('');
mtrace('>>> MK RESMI di SIAKAD yang BELUM ada di Moodle 2026/2027: ' . count($missingOfficial));
$i = 0;
foreach ($missingOfficial as $code => $name) {
    if ($i++ >= 40) {
        mtrace('  ... (+' . (count($missingOfficial) - 40) . ' lagi)');
        break;
    }
    mtrace("  $code — $name");
}
mtrace('(Kurikulum lama SIF/SBD/... masih di katalog SIAKAD: ' . count($missingLegacy) . ' — sengaja tidak dibuat di Moodle baru)');

// --- grades-per-course ---
mtrace('');
mtrace('--- Scan grades-per-course ---');
$years = [];
$lecturers = [];
$gpcOfficial = [];
$gpc2026 = [];
$page = 1;
$mhsCount = 0;
$gradeCount = 0;
while ($page <= 100) {
    [$http, $res] = siakad_get($BASE . '/grades-per-course?per_page=100&page=' . $page, $token);
    if ($http !== 200 || !is_array($res)) {
        mtrace("grades page $page HTTP $http");
        break;
    }
    $items = $res['data'] ?? [];
    if (!$items) {
        break;
    }
    $mhsCount += count($items);
    foreach ($items as $mhs) {
        foreach ($mhs['grade'] ?? [] as $g) {
            $gradeCount++;
            $ta = trim((string)($g['tahun_akademik'] ?? ''));
            $periode = trim((string)($g['periode'] ?? ''));
            $key = ($ta !== '' ? $ta : '?') . ' | ' . ($periode !== '' ? $periode : '?');
            $years[$key] = ($years[$key] ?? 0) + 1;
            $code = ush_norm_code((string)($g['lesson_code'] ?? ''));
            $lec = trim((string)($g['lecture_name'] ?? ''));
            $lecid = (int)($g['id_lecture'] ?? 0);
            if ($lecid > 0 && $lec !== '' && strcasecmp($lec, 'Unknown') !== 0) {
                $lecturers[$lecid] = $lec;
            }
            if ($code && ush_is_official($code)) {
                $gpcOfficial[$code] = true;
            }
            if ($ta !== '' && (str_contains($ta, '2026') || str_contains($ta, '20262027'))) {
                $gpc2026[$code ?: '(tanpa kode)'] = ($gpc2026[$code ?: '(tanpa kode)'] ?? 0) + 1;
            }
        }
    }
    $last = (int)($res['meta']['last_page'] ?? $res['last_page'] ?? 0);
    if ($page === 1 || $page % 10 === 0 || ($last && $page >= $last)) {
        mtrace("  page $page / " . ($last ?: '?') . " — mhs kumulatif $mhsCount, grade $gradeCount");
    }
    if ($last > 0 && $page >= $last) {
        break;
    }
    $page++;
}

arsort($years);
mtrace("Mahasiswa di grades-per-course: $mhsCount");
mtrace("Baris nilai: $gradeCount");
mtrace('Dosen unik ber-id: ' . count($lecturers));
mtrace('Kode MK resmi yang muncul di nilai: ' . count($gpcOfficial));
mtrace('');
mtrace('Distribusi tahun akademik | periode (top 20):');
$i = 0;
foreach ($years as $k => $n) {
    if ($i++ >= 20) {
        mtrace('  ... (+' . (count($years) - 20) . ' kombinasi lain)');
        break;
    }
    mtrace("  $k → $n");
}
mtrace('');
if ($gpc2026) {
    mtrace('ADA data nilai tahun 2026 di SIAKAD: ' . array_sum($gpc2026) . ' baris, ' . count($gpc2026) . ' kode MK');
    $i = 0;
    arsort($gpc2026);
    foreach ($gpc2026 as $code => $n) {
        if ($i++ >= 15) {
            break;
        }
        mtrace("  $code → $n");
    }
} else {
    mtrace('BELUM ada data nilai / KRS bertahun 2026 di grades-per-course.');
}

// Sample first student grade keys (structure check)
[$http, $sample] = siakad_get($BASE . '/grades-per-course?per_page=1&page=1', $token);
if (!empty($sample['data'][0]['grade'][0])) {
    mtrace('');
    mtrace('Sample field grade: ' . implode(', ', array_keys($sample['data'][0]['grade'][0])));
}

mtrace('');
mtrace('Selesai.');
