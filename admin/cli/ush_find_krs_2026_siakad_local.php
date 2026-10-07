<?php
/**
 * Cari KRS/nilai angkatan 2026 atau tahun 2026/2027 di API SIAKAD.
 */
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../config.php');

$BASE = 'https://siakad.sugenghartono.ac.id/api';

function login(): ?string {
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
    curl_close($ch);
    $j = json_decode($raw, true);
    return $j['token']
        ?? $j['access_token']
        ?? $j['data']['token']
        ?? $j['data']['access_token']
        ?? null;
}

function getu(string $path, string $token): array {
    global $BASE;
    $ch = curl_init($BASE . $path);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token, 'Accept: application/json'],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 50,
    ]);
    $raw = curl_exec($ch);
    $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$http, json_decode($raw, true)];
}

$token = login();
if (!$token) {
    // Debug login body without secrets.
    global $BASE;
    sleep(6);
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
    mtrace("Login gagal HTTP $http keys=" . (is_array($j) ? implode(',', array_keys($j)) : 'n/a'));
    if (is_array($j) && isset($j['message'])) {
        mtrace('message=' . (is_string($j['message']) ? $j['message'] : json_encode($j['message'])));
    }
    // Retry once more.
    sleep(8);
    $token = login();
}
if (!$token) {
    mtrace('Login gagal setelah retry');
    exit(1);
}
mtrace('Login OK — cari KRS 2026 / angkatan 26');

$years = [];
$nimPrefix = [];
$hits2026 = []; // examples
$hitsNim26 = [];
$lessonCodes2026 = [];
$page = 1;
$maxPages = 120;
$mhsTotal = 0;
$gradeTotal = 0;
$http429 = 0;

while ($page <= $maxPages) {
    [$http, $res] = getu('/grades-per-course?page=' . $page, $token);
    if ($http === 429) {
        $http429++;
        if ($http429 > 6) {
            mtrace("HTTP 429 berulang di page $page — stop");
            break;
        }
        mtrace("429 page $page — sleep 8s");
        sleep(8);
        continue;
    }
    $http429 = 0;
    if ($http !== 200 || !is_array($res)) {
        mtrace("HTTP $http page $page — stop");
        break;
    }
    $items = $res['data'] ?? [];
    if (!$items) {
        mtrace("page $page kosong — selesai");
        break;
    }

    $mhsTotal += count($items);
    foreach ($items as $mhs) {
        $nim = trim((string)($mhs['nim'] ?? ''));
        $smt = (string)($mhs['semester'] ?? '');
        $status = (string)($mhs['status'] ?? '');

        if ($nim !== '') {
            // Prefix angkatan heuristics
            if (preg_match('/^(\d{2})/', $nim, $m)) {
                $nimPrefix[$m[1]] = ($nimPrefix[$m[1]] ?? 0) + 1;
            }
            if (preg_match('/(^|[^0-9])26|^(?:\d{2})?26/', $nim) || str_contains($nim, '26')) {
                // narrower: positions often XX26... or 26...
                if (preg_match('/^26\d+/', $nim) || preg_match('/^\d{2}26\d+/', $nim) || preg_match('/^0\d26/', $nim)) {
                    if (count($hitsNim26) < 20) {
                        $hitsNim26[] = [
                            'nim' => $nim,
                            'semester' => $smt,
                            'status' => $status,
                            'grades' => count($mhs['grade'] ?? []),
                        ];
                    }
                }
            }
        }

        foreach ($mhs['grade'] ?? [] as $g) {
            $gradeTotal++;
            $ta = trim((string)($g['tahun_akademik'] ?? ''));
            $periode = trim((string)($g['periode'] ?? ''));
            $key = ($ta !== '' ? $ta : '?') . ' | ' . ($periode !== '' ? $periode : '?');
            $years[$key] = ($years[$key] ?? 0) + 1;

            $is2026 = false;
            if ($ta !== '') {
                if (str_contains($ta, '2026/2027') || str_contains($ta, '20262027') || $ta === '2026' || $ta === '2026/27') {
                    $is2026 = true;
                }
                // also catch if they encode as 2026 only for ganjil start
                if (preg_match('/^2026/', $ta) && !str_contains($ta, '2025/2026')) {
                    $is2026 = true;
                }
            }
            if ($is2026) {
                $code = (string)($g['lesson_code'] ?? '');
                $lessonCodes2026[$code] = ($lessonCodes2026[$code] ?? 0) + 1;
                if (count($hits2026) < 25) {
                    $hits2026[] = [
                        'nim' => $nim,
                        'ta' => $ta,
                        'periode' => $periode,
                        'code' => $code,
                        'name' => (string)($g['lesson_name'] ?? ''),
                        'lecture' => (string)($g['lecture_name'] ?? ''),
                    ];
                }
            }
        }
    }

    $pag = $res['pagination'] ?? [];
    $last = (int)($pag['last_page'] ?? $pag['total_pages'] ?? $res['meta']['last_page'] ?? 0);
    if ($page === 1) {
        mtrace('pagination: ' . json_encode($pag));
    }
    if ($page === 1 || $page % 20 === 0) {
        mtrace("page $page" . ($last ? "/$last" : '') . " — mhs $mhsTotal grades $gradeTotal hits2026=" . count($hits2026) . " nim26=" . count($hitsNim26));
    }
    if ($last > 0 && $page >= $last) {
        break;
    }
    usleep(350000);
    $page++;
}

arsort($years);
mtrace('');
mtrace("Mahasiswa discan: $mhsTotal");
mtrace("Baris grade: $gradeTotal");
mtrace('Semua tahun|periode:');
foreach ($years as $k => $c) {
    mtrace("  $k → $c");
}
ksort($nimPrefix);
mtrace('Prefix 2 digit NIM (count mhs rows): ' . json_encode($nimPrefix));

mtrace('');
mtrace('HIT tahun 2026*: ' . count($hits2026) . ' contoh, kode MK unik ' . count($lessonCodes2026));
foreach ($hits2026 as $h) {
    mtrace("  {$h['nim']} | {$h['ta']} {$h['periode']} | {$h['code']} | {$h['name']}");
}
mtrace('HIT NIM angkatan-ish 26: ' . count($hitsNim26));
foreach ($hitsNim26 as $h) {
    mtrace("  nim={$h['nim']} smt={$h['semester']} status={$h['status']} grades={$h['grades']}");
}

if (!$hits2026 && !$hitsNim26) {
    mtrace('');
    mtrace('KESIMPULAN: Di endpoint grades-per-course yang kita akses, KRS 2026/2027 / NIM 26 belum terlihat.');
    mtrace('Kalau di web SIAKAD sudah ada, kemungkinan belum masuk API ini atau endpoint/kredensial beda.');
} else {
    mtrace('');
    mtrace('KESIMPULAN: Ketemu data terkait 2026 — sync LMS bisa dilanjut dengan filter yang cocok.');
}
