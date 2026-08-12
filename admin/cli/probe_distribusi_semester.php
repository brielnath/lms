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

mtrace("=== DISTRIBUSI MATKUL PER SEMESTER & ANGKATAN ===\n");

// Fetch all lessons dan grouping per semester
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

// Group by semester number
$by_semester = [];
$by_prodi    = [];
foreach ($raw as $code => $l) {
    $sem  = intval($l['semester'] ?? 0);
    $name = $l['name'] ?? '';
    $sks  = $l['sks_total'] ?? 0;

    // Detect prodi from code
    if (preg_match('/^(SIF|SBD|SGZ|HKM|IDM|GDM|FIK|FTB|[0-9]{3})/', $code, $m)) {
        $prodi = $m[1];
    } else {
        $prodi = 'MKU';
    }

    $by_semester[$sem][] = ['code' => $code, 'name' => $name, 'sks' => $sks, 'prodi' => $prodi];
    $by_prodi[$prodi][$sem][] = $code;
}

ksort($by_semester);

// Map semester number to angkatan (for 2025/2026 Ganjil):
// Sem 1 → Angkatan 2026 (baru)
// Sem 3 → Angkatan 2025
// Sem 5 → Angkatan 2024
// Sem 7 → Angkatan 2023
$angkatan_map = [
    1 => 'Angkatan 2026 (Mahasiswa Baru)',
    2 => 'Angkatan 2026 (Genap)',
    3 => 'Angkatan 2025',
    4 => 'Angkatan 2025 (Genap)',
    5 => 'Angkatan 2024',
    6 => 'Angkatan 2024 (Genap)',
    7 => 'Angkatan 2023',
    8 => 'Angkatan 2023 (Genap)',
    9 => 'Angkatan 2022 (Ganjil lanjut)',
];

mtrace("Semester Akademik 2025/2026 GANJIL — Distribusi Matkul:");
mtrace(str_repeat("─", 65));

foreach ($by_semester as $sem => $matkuls) {
    $angkatan = $angkatan_map[$sem] ?? "Semester $sem";
    $count    = count($matkuls);
    $total_sks = array_sum(array_column($matkuls, 'sks'));
    mtrace("\n📚 Semester $sem → $angkatan");
    mtrace("   Jumlah Matkul: $count | Total SKS: $total_sks");

    // Group by prodi
    $prodi_count = [];
    foreach ($matkuls as $m) {
        $prodi_count[$m['prodi']] = ($prodi_count[$m['prodi']] ?? 0) + 1;
    }
    arsort($prodi_count);
    $prodi_str = [];
    foreach ($prodi_count as $p => $c) $prodi_str[] = "$p($c)";
    mtrace("   Prodi: " . implode(' | ', $prodi_str));

    // Sample matkul
    foreach (array_slice($matkuls, 0, 3) as $m) {
        mtrace("   • [{$m['code']}] {$m['name']} ({$m['sks']} SKS)");
    }
    if ($count > 3) mtrace("   ... dan " . ($count - 3) . " matkul lainnya");
}

mtrace("\n" . str_repeat("─", 65));
mtrace("TOTAL SELURUH MATKUL: " . count($raw));

// Check which semesters are ODD (ganjil) = relevant for Sep 2025
mtrace("\n\n✅ Yang aktif di Semester GANJIL 2025/2026:");
mtrace("   (Semester 1, 3, 5, 7, 9 dari kurikulum)");
$ganjil_total = 0;
foreach ($by_semester as $sem => $matkuls) {
    if ($sem % 2 != 1) continue; // hanya semester ganjil (1,3,5,7,9)
    $angkatan = $angkatan_map[$sem] ?? "Semester $sem";
    mtrace("   Sem $sem ($angkatan): " . count($matkuls) . " matkul");
    $ganjil_total += count($matkuls);
}
mtrace("   TOTAL MATKUL GANJIL: $ganjil_total");
