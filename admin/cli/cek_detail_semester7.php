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

// Fetch semua matkul
$raw = [];
$page = 1;
while (true) {
    $res = api_get("/all-lessons?page=$page");
    $items = $res['data'] ?? [];
    if (empty($items)) break;
    foreach ($items as $l) {
        $code = trim($l['code'] ?? '');
        if ($code && !isset($raw[$code])) $raw[$code] = $l;
    }
    if (count($items) < 15) break;
    $page++;
    if ($page > 50) break;
}

// ─── Cek Semester 7 secara detail ───────────────────────────────────────────
mtrace("=== DETAIL MATKUL SEMESTER 7 (Angkatan 2023) ===\n");
$sem7 = array_filter($raw, fn($l) => intval($l['semester'] ?? 0) === 7);
mtrace("Total semester 7 di SIAKAD: " . count($sem7) . " matkul\n");

foreach ($sem7 as $l) {
    mtrace("  [{$l['code']}] Sem:{$l['semester']} | {$l['name']} ({$l['sks_total']} SKS)");
}

// ─── Cek berdasarkan prefix kode per prodi ───────────────────────────────────
mtrace("\n=== CEK KODE MATKUL PER PRODI UNTUK SEMESTER 7xx ===");
$prodi_prefix = [
    'SIF' => 'Sistem Informasi',
    'SBD' => 'Bisnis Digital',
    'SGZ' => 'Ilmu Gizi',
    'HKM' => 'Hukum',
    'IDM' => 'IDM',
];

foreach ($prodi_prefix as $prefix => $nama) {
    $prodi_matkul = array_filter($raw, fn($l) => str_starts_with($l['code'], $prefix));
    mtrace("\n  📚 $nama ($prefix) — Total: " . count($prodi_matkul) . " matkul");

    // Group by semester
    $by_sem = [];
    foreach ($prodi_matkul as $l) {
        $sem = intval($l['semester'] ?? 0);
        $by_sem[$sem][] = $l['code'];
    }
    ksort($by_sem);
    foreach ($by_sem as $sem => $codes) {
        $count = count($codes);
        $tag   = ($sem % 2 == 1) ? '🟢 Ganjil' : '🔵 Genap';
        mtrace("    Sem $sem $tag: $count matkul [" . implode(', ', array_slice($codes, 0, 5)) . (count($codes) > 5 ? '...' : '') . "]");
    }
}

// ─── Cek matkul "semester tinggi" (kode 7xx) yang mungkin salah tag ──────────
mtrace("\n=== CEK KODE MATKUL DENGAN ANGKA 7 (misal SIF7xx) ===");
foreach ($raw as $code => $l) {
    if (preg_match('/^[A-Z]+7\d\d/', $code)) {
        $sem = intval($l['semester'] ?? 0);
        mtrace("  [$code] Sem:{$sem} | {$l['name']}");
    }
}

// ─── Summary: berapa yang seharusnya semester 7 berdasarkan kode ──────────────
mtrace("\n=== KESIMPULAN: ESTIMASI MATKUL SEMESTER 7 SEBENARNYA ===");
$expected_sem7 = [];
foreach ($raw as $code => $l) {
    $sem = intval($l['semester'] ?? 0);
    // Kode yang mengandung 7 di digit ke-4,5 ATAU semester = 7
    if ($sem === 7 || preg_match('/^[A-Z]+(7\d\d|0[0-9]*7)/', $code)) {
        $expected_sem7[$code] = $l;
    }
}
mtrace("  Matkul yang kemungkinan untuk Semester 7: " . count($expected_sem7));
foreach ($expected_sem7 as $code => $l) {
    mtrace("  [$code] Sem:{$l['semester']} | {$l['name']}");
}
