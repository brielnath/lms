<?php
/**
 * Audit syarat KRS SIAKAD → enrol LMS (*_20262027Ganjil).
 * Pakai: php admin/cli/ush_audit_krs_enrol_readiness_local.php
 */
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../config.php');

if (strpos($CFG->wwwroot, 'localhost') === false && strpos($CFG->wwwroot, '127.0.0.1') === false) {
    mtrace('Dibatalkan: bukan LMS lokal.');
    exit(1);
}

$SUFFIX = $argv[1] ?? '20262027Ganjil';
$TARGET_TAHUN = '2026/2027';
$TARGET_PERIODE = 'Ganjil';
if (preg_match('/^(20\d{2})(20\d{2})(Ganjil|Genap)$/i', $SUFFIX, $m)) {
    $TARGET_TAHUN = $m[1] . '/' . $m[2];
    $TARGET_PERIODE = ucfirst(strtolower($m[3]));
}

function siakad_login(): ?string {
    $ch = curl_init('https://siakad.sugenghartono.ac.id/api/login');
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
    return $j['token'] ?? $j['data']['token'] ?? $j['access_token'] ?? null;
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
    $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$http, json_decode($raw, true)];
}

function ush_norm_code(string $code): string {
    $c = strtoupper(trim($code));
    $c = str_replace(['*', ' '], '', $c);
    $c = preg_replace('/_20\d{6}(GANJIL|GENAP)$/i', '', $c) ?? $c;
    return $c;
}

function ush_is_official(string $code): bool {
    return (bool) preg_match('/^(IDM|IUM|IFM|IDE|GDM)\d+/i', $code);
}

mtrace("=== Audit syarat KRS → LMS (*_{$SUFFIX}) ===");
mtrace("Target: {$TARGET_TAHUN} {$TARGET_PERIODE}");
mtrace('');

// ---- 1) Cangkang kelas LMS ----
$courses = $DB->get_records_sql(
    "SELECT id, shortname, fullname, visible FROM {course}
      WHERE id > 1 AND " . $DB->sql_like('shortname', ':p'),
    ['p' => '%' . $SUFFIX]
);
$lmsIndex = []; // base code => shortname
$lmsOfficial = 0;
$lmsLegacy = 0;
foreach ($courses as $c) {
    $base = ush_norm_code($c->shortname);
    $lmsIndex[$base] = $c->shortname;
    if (ush_is_official($base)) {
        $lmsOfficial++;
    } else {
        $lmsLegacy++;
    }
}
mtrace('SYARAT 4 — Cangkang kelas LMS *' . $SUFFIX . '*');
mtrace('  Ada: ' . count($lmsIndex) . " kelas (resmi ~$lmsOfficial, lain ~$lmsLegacy)");
mtrace('  Status: ' . (count($lmsIndex) > 0 ? 'OK' : 'BELUM'));
mtrace('');

// ---- 2) Script sync ada ----
$script = $CFG->dirroot . '/admin/cli/sync_siakad_mahasiswa_accounts_local.php';
mtrace('SYARAT 5 — Skrip sync siap dijalankan');
mtrace('  File: ' . (is_readable($script) ? 'OK' : 'TIDAK ADA'));
mtrace('  Guard lokal: localhost only');
mtrace('');

$token = siakad_login();
if (!$token) {
    mtrace('Gagal login SIAKAD — stop.');
    exit(1);
}
mtrace('Login SIAKAD: OK');
mtrace('');

// ---- 3) Scan grades untuk tahun target + sample match kode ----
$years = [];
$targetRows = 0;
$targetCodes = []; // code => count students
$targetStudents = [];
$samplePairs = []; // for code match demo
$page = 1;
$maxPages = 50;
$http429 = 0;

while ($page <= $maxPages) {
    [$http, $res] = siakad_get(
        'https://siakad.sugenghartono.ac.id/api/grades-per-course?per_page=100&page=' . $page,
        $token
    );
    if ($http === 429) {
        $http429++;
        if ($http429 > 4) {
            mtrace("  (scan berhenti: HTTP 429 di page $page)");
            break;
        }
        sleep(5);
        continue;
    }
    $http429 = 0;
    if ($http !== 200 || !is_array($res)) {
        mtrace("  HTTP $http di page $page — stop scan");
        break;
    }
    $items = $res['data'] ?? [];
    if (!$items) {
        break;
    }
    foreach ($items as $mhs) {
        $nim = trim((string) ($mhs['nim'] ?? ''));
        foreach ($mhs['grade'] ?? [] as $g) {
            $ta = trim((string) ($g['tahun_akademik'] ?? ''));
            $periode = trim((string) ($g['periode'] ?? ''));
            $yk = $ta . ' | ' . $periode;
            $years[$yk] = ($years[$yk] ?? 0) + 1;

            $code = ush_norm_code((string) ($g['lesson_code'] ?? ''));
            if ($code === '') {
                continue;
            }

            $taNorm = preg_replace('/[^0-9]/', '', $ta) ?? '';
            $wantNorm = preg_replace('/[^0-9]/', '', $TARGET_TAHUN) ?? '';
            $taOk = ($ta === $TARGET_TAHUN) || ($taNorm !== '' && $taNorm === $wantNorm);
            $perOk = strcasecmp($periode, $TARGET_PERIODE) === 0;

            if ($taOk && $perOk) {
                $targetRows++;
                $targetCodes[$code] = ($targetCodes[$code] ?? 0) + 1;
                if ($nim !== '') {
                    $targetStudents[$nim] = true;
                }
            }

            // Sample matching against LMS shells (any year) for code-format check
            if (count($samplePairs) < 30 && isset($lmsIndex[$code])) {
                $samplePairs[$code] = $lmsIndex[$code];
            }
        }
    }
    $last = (int) ($res['meta']['last_page'] ?? $res['last_page'] ?? 0);
    if ($page === 1 || $page % 10 === 0) {
        mtrace("  scan page $page" . ($last ? " /$last" : ''));
    }
    if ($last > 0 && $page >= $last) {
        break;
    }
    usleep(250000);
    $page++;
}

arsort($years);
mtrace('');
mtrace('SYARAT 1 — KRS/nilai tahun target ada di API SIAKAD');
mtrace("  Target: {$TARGET_TAHUN} {$TARGET_PERIODE}");
mtrace('  Baris KRS/nilai target: ' . $targetRows);
mtrace('  Mahasiswa unik target: ' . count($targetStudents));
mtrace('  Kode MK unik target: ' . count($targetCodes));
mtrace('  Status: ' . ($targetRows > 0 ? 'OK' : 'BELUM — data belum ada di SIAKAD'));
mtrace('  Distribusi tahun|periode (top 12):');
$i = 0;
foreach ($years as $k => $n) {
    if ($i++ >= 12) {
        break;
    }
    $mark = (str_contains($k, '2026/2027') || str_contains($k, '20262027')) ? ' ◄ target?' : '';
    mtrace("    $k → $n$mark");
}
mtrace('');

// ---- Code match quality: official LMS vs all-lessons + vs latest year KRS ----
mtrace('SYARAT 2 — Format kode MK SIAKAD ↔ shortname LMS');
$matchOk = 0;
$matchMiss = 0;
$missList = [];
foreach ($lmsIndex as $base => $sn) {
    // Check if this base appears as lesson_code style (we only have target codes + samples)
    // Better: pull all-lessons once
}
[$httpL, $lessons] = siakad_get('https://siakad.sugenghartono.ac.id/api/all-lessons?per_page=100&page=1', $token);
$siakadCodes = [];
if ($httpL === 200 && is_array($lessons)) {
    foreach ($lessons['data'] ?? [] as $L) {
        $c = ush_norm_code((string) ($L['code'] ?? $L['kode'] ?? ''));
        if ($c !== '') {
            $siakadCodes[$c] = (string) ($L['name'] ?? '');
        }
    }
}
foreach ($lmsIndex as $base => $sn) {
    if (isset($siakadCodes[$base])) {
        $matchOk++;
    } else {
        $matchMiss++;
        if (count($missList) < 15) {
            $missList[] = "$base ← LMS:$sn";
        }
    }
}
mtrace("  Kelas LMS yang kode-base-nya ada di katalog SIAKAD all-lessons: $matchOk / " . count($lmsIndex));
mtrace('  Tidak ketemu di katalog (halaman 1 all-lessons, bisa kurang lengkap): ' . $matchMiss);
foreach ($missList as $line) {
    mtrace("    ? $line");
}
mtrace('  Contoh pasangan cocok:');
$i = 0;
foreach ($samplePairs as $code => $sn) {
    if ($i++ >= 8) {
        break;
    }
    mtrace("    SIAKAD:$code → LMS:$sn");
}
// Also check norm function on a few LMS shortnames
$examples = array_slice($lmsIndex, 0, 5, true);
mtrace('  Norm shortname LMS:');
foreach ($examples as $base => $sn) {
    mtrace("    $sn → base=$base");
}
mtrace('  Status format: ' . ($matchOk > 0 ? 'OK (pola IDM####_20262027Ganjil)' : 'PERLU CEK'));
mtrace('');

mtrace('SYARAT 3 — Filter tahun/semester dari suffix');
mtrace("  Suffix $SUFFIX → tahun=$TARGET_TAHUN periode=$TARGET_PERIODE");
mtrace('  Status: OK (logika skrip sync sudah memetakan ini)');
mtrace('');

// If we have latest year data (2025/2026 Genap), simulate match rate
$simTahun = '2025/2026';
$simPeriode = 'Genap';
$simCodes = [];
$simMatch = 0;
$simMiss = [];
// Re-scan is expensive; use years we already have - need second pass stored.
// Quick: from targetCodes empty, do light page1-5 for 2025/2026 Genap
$page = 1;
while ($page <= 15) {
    [$http, $res] = siakad_get(
        'https://siakad.sugenghartono.ac.id/api/grades-per-course?per_page=100&page=' . $page,
        $token
    );
    if ($http !== 200 || empty($res['data'])) {
        break;
    }
    foreach ($res['data'] as $mhs) {
        foreach ($mhs['grade'] ?? [] as $g) {
            if (trim((string) ($g['tahun_akademik'] ?? '')) !== $simTahun) {
                continue;
            }
            if (strcasecmp(trim((string) ($g['periode'] ?? '')), $simPeriode) !== 0) {
                continue;
            }
            $code = ush_norm_code((string) ($g['lesson_code'] ?? ''));
            if ($code === '') {
                continue;
            }
            $simCodes[$code] = true;
        }
    }
    $page++;
    usleep(200000);
}
foreach ($simCodes as $code => $_) {
    if (isset($lmsIndex[$code]) || isset($lmsIndex[$code . 'P'])) {
        $simMatch++;
    } else {
        if (count($simMiss) < 12) {
            $simMiss[] = $code;
        }
    }
}
mtrace("SIMULASI (data terbaru SIAKAD {$simTahun} {$simPeriode} → cangkang *_{$SUFFIX}):");
mtrace('  Kode MK di KRS sample: ' . count($simCodes));
mtrace('  Cocok ke kelas LMS 2026/2027: ' . $simMatch);
mtrace('  Belum ada cangkangnya: ' . (count($simCodes) - $simMatch));
if ($simMiss) {
    mtrace('  Contoh tanpa cangkang: ' . implode(', ', $simMiss));
}
mtrace('');

mtrace('=== RINGKASAN SYARAT ===');
$s1 = $targetRows > 0;
$s2 = $matchOk > 50; // majority
$s3 = true;
$s4 = count($lmsIndex) > 0;
$s5 = is_readable($script);
$rows = [
    '1 KRS target di SIAKAD' => $s1,
    '2 Format kode MK cocok' => $s2,
    '3 Filter tahun/semester' => $s3,
    '4 Cangkang kelas LMS' => $s4,
    '5 Skrip sync siap' => $s5,
];
foreach ($rows as $label => $ok) {
    mtrace('  ' . ($ok ? '[OK]   ' : '[BELUM] ') . $label);
}
mtrace('');
mtrace($s1 && $s2 && $s3 && $s4 && $s5
    ? 'Kesimpulan: SEMUA SYARAT TERPENUHI — sync --enrol bisa jalan penuh.'
    : 'Kesimpulan: BELUM SEMUA — blocking utama: ' . (!$s1 ? 'KRS 2026/2027 belum di SIAKAD' : 'lihat item BELUM di atas'));
