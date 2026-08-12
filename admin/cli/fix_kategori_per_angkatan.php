<?php
/**
 * PERBAIKAN STRUKTUR SEMESTER:
 * 1. Buat kategori per Angkatan (bukan hanya per Prodi)
 * 2. Pindahkan kelas ke kategori yang benar sesuai semester kurikulum
 * 3. Hapus/sembunyikan kelas semester GENAP (tidak aktif di Ganjil)
 */
define('CLI_SCRIPT', true);
@error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);
require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/course/lib.php');

$NEW_SEMESTER_LABEL = '2025/2026 - Ganjil';
$SHORTNAME_SUFFIX   = '_20252026Ganjil';

// Map semester kurikulum → angkatan & info
$SEMESTER_MAP = [
    1 => ['angkatan' => '2026', 'label' => 'Semester 1 — Angkatan 2026 (Mahasiswa Baru)', 'is_ganjil' => true],
    2 => ['angkatan' => '2026', 'label' => 'Semester 2 — Angkatan 2026 (Genap)',          'is_ganjil' => false],
    3 => ['angkatan' => '2025', 'label' => 'Semester 3 — Angkatan 2025',                  'is_ganjil' => true],
    4 => ['angkatan' => '2025', 'label' => 'Semester 4 — Angkatan 2025 (Genap)',          'is_ganjil' => false],
    5 => ['angkatan' => '2024', 'label' => 'Semester 5 — Angkatan 2024',                  'is_ganjil' => true],
    6 => ['angkatan' => '2024', 'label' => 'Semester 6 — Angkatan 2024 (Genap)',          'is_ganjil' => false],
    7 => ['angkatan' => '2023', 'label' => 'Semester 7 — Angkatan 2023',                  'is_ganjil' => true],
    8 => ['angkatan' => '2023', 'label' => 'Semester 8 — Angkatan 2023 (Genap)',          'is_ganjil' => false],
    9 => ['angkatan' => '2022', 'label' => 'Semester 9 — Angkatan 2022 (Lanjutan)',       'is_ganjil' => true],
];

mtrace("==================================================");
mtrace("🔧 PERBAIKAN STRUKTUR KATEGORI PER ANGKATAN");
mtrace("   Semester: $NEW_SEMESTER_LABEL");
mtrace("==================================================\n");

// Get parent category
$parent_cat = $DB->get_record('course_categories', ['name' => "TA $NEW_SEMESTER_LABEL"]);
if (!$parent_cat) {
    mtrace("❌ Kategori induk tidak ditemukan! Jalankan admin_prepare_new_semester.php dulu.");
    exit(1);
}
$parent_id = $parent_cat->id;

// ─── STEP 1: Buat Sub-Kategori per Angkatan ─────────────────────────────────
mtrace("📂 [1] Membuat Kategori per Angkatan...\n");

$angkatan_cat_ids = [];
foreach ($SEMESTER_MAP as $sem_num => $info) {
    if (!$info['is_ganjil']) continue; // hanya semester ganjil

    $cat_name = $info['label'];
    $existing = $DB->get_record('course_categories', ['name' => $cat_name]);
    if (!$existing) {
        $newcat = new stdClass();
        $newcat->name      = $cat_name;
        $newcat->idnumber  = "SEM{$sem_num}_ANG{$info['angkatan']}_" . str_replace(['/', ' ', '-'], '_', $NEW_SEMESTER_LABEL);
        $newcat->parent    = $parent_id;
        $newcat->visible   = 1;
        $newcat->sortorder = $sem_num * 10;
        $newcat->description = "Mata kuliah semester $sem_num untuk Angkatan {$info['angkatan']} — TA $NEW_SEMESTER_LABEL";
        $cat_id = $DB->insert_record('course_categories', $newcat);
        fix_course_sortorder();
        mtrace("  ✅ Dibuat: $cat_name (ID: $cat_id)");
    } else {
        $cat_id = $existing->id;
        mtrace("  ℹ️  Sudah Ada: $cat_name (ID: $cat_id)");
    }
    $angkatan_cat_ids[$sem_num] = $cat_id;
}

// ─── STEP 2: Fetch data SIAKAD untuk dapat semester per kode matkul ─────────
mtrace("\n🔄 [2] Mengambil data semester per matkul dari SIAKAD...");

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

$siakad_sem = []; // [code => semester_number]
$raw = [];
$page = 1;
while (true) {
    $res = api_get("/all-lessons?page=$page");
    $items = $res['data'] ?? [];
    if (empty($items)) break;
    foreach ($items as $l) {
        $code = trim($l['code'] ?? '');
        if ($code && !isset($raw[$code])) {
            $raw[$code]       = $l;
            $siakad_sem[$code] = intval($l['semester'] ?? 0);
        }
    }
    if (count($items) < 15) break;
    $page++;
    if ($page > 50) break;
}
mtrace("  ✅ " . count($siakad_sem) . " matkul berhasil diambil dari SIAKAD");

// ─── STEP 3: Pindahkan & Sembunyikan Kelas ──────────────────────────────────
mtrace("\n📦 [3] Mengatur ulang kelas ke kategori yang benar...\n");

$new_courses = $DB->get_records_sql("
    SELECT c.id, c.fullname, c.shortname, c.category, c.visible
    FROM {course} c
    WHERE c.shortname LIKE ?
    ORDER BY c.shortname ASC
", ['%' . $SHORTNAME_SUFFIX]);

$moved_ganjil  = 0;
$hidden_genap  = 0;
$not_found     = 0;

foreach ($new_courses as $course) {
    $code = str_replace($SHORTNAME_SUFFIX, '', $course->shortname);
    $sem  = $siakad_sem[$code] ?? 0;

    if ($sem === 0) {
        $not_found++;
        continue;
    }

    if (isset($SEMESTER_MAP[$sem]) && $SEMESTER_MAP[$sem]['is_ganjil']) {
        // Pindah ke kategori angkatan yang benar
        $target_cat = $angkatan_cat_ids[$sem] ?? $parent_id;
        if ($course->category != $target_cat) {
            $DB->set_field('course', 'category', $target_cat, ['id' => $course->id]);
        }
        // Pastikan visible
        if (!$course->visible) {
            $DB->set_field('course', 'visible', 1, ['id' => $course->id]);
        }
        $moved_ganjil++;
    } else {
        // Semester GENAP → sembunyikan (tidak relevan di Ganjil)
        $DB->set_field('course', 'visible', 0, ['id' => $course->id]);
        $hidden_genap++;
    }
}

fix_course_sortorder();
rebuild_course_cache(0, true);
purge_all_caches();

// ─── STEP 4: Laporan Akhir ───────────────────────────────────────────────────
mtrace("\n=== 📊 LAPORAN HASIL ===\n");
mtrace("  Distribusi Kelas per Angkatan (Semester Ganjil):");
foreach ($SEMESTER_MAP as $sem_num => $info) {
    if (!$info['is_ganjil']) continue;
    if (!isset($angkatan_cat_ids[$sem_num])) continue;
    $count = $DB->count_records('course', ['category' => $angkatan_cat_ids[$sem_num], 'visible' => 1]);
    $bar   = str_repeat('█', min($count, 30));
    mtrace(sprintf("  Sem %d | Angkatan %s | %3d kelas | %s", $sem_num, $info['angkatan'], $count, $bar));
}

mtrace("\n==================================================");
mtrace("🎉 PERBAIKAN SELESAI!");
mtrace("   ✅ Kelas Ganjil Dipindah   : $moved_ganjil kelas");
mtrace("   🙈 Kelas Genap Disembunyikan: $hidden_genap kelas");
mtrace("   ❓ Kelas Tidak Dikenali     : $not_found kelas");
mtrace("==================================================");
