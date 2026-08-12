<?php
/**
 * TAHAP 1 - ADMIN: Persiapan Semester Baru
 * 
 * Fungsi:
 * A. Kunci kelas semester lama (mahasiswa bisa lihat tapi tidak bisa submit)
 * B. Buat kategori semester baru per prodi
 * C. Buat kelas BARU untuk semester baru (dari data SIAKAD all-lessons)
 */
define('CLI_SCRIPT', true);
@error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);
require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/course/lib.php');

$NEW_SEMESTER_LABEL = '2025/2026 - Ganjil';
$NEW_PERIODE        = 'Ganjil';
$NEW_TAHUN          = '2025/2026';

mtrace("==================================================");
mtrace("🏛️  TAHAP 1 - ADMIN: PERSIAPAN SEMESTER BARU");
mtrace("   Target Semester: $NEW_SEMESTER_LABEL");
mtrace("==================================================\n");

// ─── A. KUNCI SEMUA KELAS LAMA (hanya kunci submit, bukan hide) ─────────────
mtrace("📌 [A] Mengunci submission di kelas semester lama...");

// Lock all existing assign activities (no new submissions)
$assigns = $DB->get_records('assign', []);
$locked  = 0;
foreach ($assigns as $assign) {
    if (!$assign->preventsubmissionnotingroup) {
        $DB->set_field('assign', 'cutoffdate', time() - 1, ['id' => $assign->id]);
        $locked++;
    }
}
mtrace("  ✅ $locked Tugas/Assignment dikunci (cutoffdate ke masa lalu)");

// Lock all existing quizzes
$quizzes  = $DB->get_records('quiz', []);
$qlocked  = 0;
foreach ($quizzes as $quiz) {
    if (!$quiz->timeclose) {
        $DB->set_field('quiz', 'timeclose', time() - 1, ['id' => $quiz->id]);
        $qlocked++;
    }
}
mtrace("  ✅ $qlocked Kuis dikunci (timeclose ke masa lalu)");

// ─── B. BUAT KATEGORI SEMESTER BARU ─────────────────────────────────────────
mtrace("\n📂 [B] Membuat Kategori Semester Baru...");

$prodi_list = [
    'SIF' => 'Sistem Informasi',
    'SBD' => 'Bisnis Digital',
    'SGZ' => 'Ilmu Gizi',
    'HKM' => 'Hukum',
    'MKU' => 'Mata Kuliah Umum',
];

$cat_ids = [];
// Get or create parent semester category
$parent_cat = $DB->get_record('course_categories', ['name' => "TA $NEW_SEMESTER_LABEL"]);
if (!$parent_cat) {
    $newcat = new stdClass();
    $newcat->name        = "TA $NEW_SEMESTER_LABEL";
    $newcat->idnumber    = "TA_" . str_replace(['/', ' ', '-'], '_', $NEW_SEMESTER_LABEL);
    $newcat->parent      = 0;
    $newcat->visible     = 1;
    $newcat->description = "Kategori Tahun Akademik $NEW_SEMESTER_LABEL Universitas Sugeng Hartono";
    $newcat->sortorder   = 999;
    $parent_id = $DB->insert_record('course_categories', $newcat);
    fix_course_sortorder();
    mtrace("  ✅ Kategori Induk Dibuat: TA $NEW_SEMESTER_LABEL (ID: $parent_id)");
} else {
    $parent_id = $parent_cat->id;
    mtrace("  ℹ️  Kategori Induk Sudah Ada: TA $NEW_SEMESTER_LABEL (ID: $parent_id)");
}

foreach ($prodi_list as $kode => $nama) {
    $cat_name = "$nama ($kode) - $NEW_SEMESTER_LABEL";
    $existing = $DB->get_record('course_categories', ['name' => $cat_name]);
    if (!$existing) {
        $subcat = new stdClass();
        $subcat->name     = $cat_name;
        $subcat->idnumber = "CAT_{$kode}_" . str_replace(['/', ' ', '-'], '_', $NEW_SEMESTER_LABEL);
        $subcat->parent   = $parent_id;
        $subcat->visible  = 1;
        $cat_id = $DB->insert_record('course_categories', $subcat);
        fix_course_sortorder();
        $cat_ids[$kode] = $cat_id;
        mtrace("  ✅ Sub-Kategori: $cat_name (ID: $cat_id)");
    } else {
        $cat_ids[$kode] = $existing->id;
        mtrace("  ℹ️  Sub-Kategori Sudah Ada: $cat_name");
    }
}

// ─── C. BUAT KELAS BARU DARI SIAKAD ─────────────────────────────────────────
mtrace("\n🔄 [C] Menarik Data Mata Kuliah dari SIAKAD & Membuat Kelas Baru...");

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

// Fetch all lessons from SIAKAD — deduplicate by code
$raw_lessons = [];
$page = 1;
while (true) {
    $res = api_get("/all-lessons?page=$page");
    $items = $res['data'] ?? [];
    if (empty($items)) break;
    foreach ($items as $lesson) {
        $code = trim($lesson['code'] ?? '');
        if ($code && !isset($raw_lessons[$code])) {
            $raw_lessons[$code] = $lesson;
        }
    }
    if (count($items) < 15) break;
    $page++;
    if ($page > 50) break;
}
$all_lessons = array_values($raw_lessons);

mtrace("  📋 Total Mata Kuliah dari SIAKAD: " . count($all_lessons));

// Determine prodi from course code prefix
function get_prodi_code($code) {
    if (str_starts_with($code, 'SIF')) return 'SIF';
    if (str_starts_with($code, 'SBD')) return 'SBD';
    if (str_starts_with($code, 'SGZ')) return 'SGZ';
    if (str_starts_with($code, 'HKM') || str_starts_with($code, 'HK')) return 'HKM';
    return 'MKU';
}

$created = 0;
$skipped = 0;
$student_role = $DB->get_record('role', ['shortname' => 'student']);
$teacher_role = $DB->get_record('role', ['shortname' => 'editingteacher']);

global $NEW_SEMESTER_LABEL, $cat_ids;

foreach ($all_lessons as $lesson) {
    $code = trim($lesson['code'] ?? '');
    $name = trim($lesson['name'] ?? '');
    if (empty($code) || empty($name)) continue;

    // New shortname for new semester
    $new_shortname = $code . '_' . str_replace(['/', ' ', '-'], '', $NEW_SEMESTER_LABEL);

    // Skip if already exists
    if ($DB->record_exists('course', ['shortname' => $new_shortname])) {
        $skipped++;
        continue;
    }

    $prodi = get_prodi_code($code);
    $cat_id = $cat_ids[$prodi] ?? ($cat_ids['MKU'] ?? $parent_id);
    $sks = intval($lesson['sks_total'] ?? 2);

    // Create new course
    $newcourse = new stdClass();
    $newcourse->fullname    = "$name — $NEW_SEMESTER_LABEL";
    $newcourse->shortname   = $new_shortname;
    $newcourse->idnumber    = $code . '_' . str_replace(['/', ' '], '', $NEW_TAHUN);
    $newcourse->category    = $cat_id;
    $newcourse->visible     = 1;
    $newcourse->format      = 'weeks';
    $newcourse->numsections = 16;
    $newcourse->startdate   = mktime(0, 0, 0, 9, 1, 2025);  // 1 September 2025
    $newcourse->enddate     = mktime(0, 0, 0, 2, 28, 2026); // 28 Februari 2026
    $newcourse->summary     = "<p><strong>$name</strong> — Semester $NEW_SEMESTER_LABEL</p><p>Kode: $code | SKS: $sks</p><p>Program Studi: $prodi — Universitas Sugeng Hartono</p>";
    $newcourse->summaryformat = FORMAT_HTML;
    $newcourse->lang        = '';
    $newcourse->enablecompletion = 1;

    try {
        $course_created = create_course($newcourse);
        // Ensure manual enrolment method exists
        $enrol_plugin = enrol_get_plugin('manual');
        if ($enrol_plugin && !$DB->record_exists('enrol', ['courseid' => $course_created->id, 'enrol' => 'manual'])) {
            $enrol_plugin->add_instance($course_created);
        }
        $created++;
        if ($created % 30 == 0) mtrace("  ... $created kelas baru dibuat ...");
    } catch (Exception $e) {
        $skipped++;
        // mtrace("  ⚠️  Skip [$new_shortname]: " . $e->getMessage());
    }
}

rebuild_course_cache(0, true);
purge_all_caches();

mtrace("\n==================================================");
mtrace("🎉 TAHAP 1 - ADMIN SELESAI!");
mtrace("   📂 Kategori Dibuat : " . (count($cat_ids) + 1) . " kategori");
mtrace("   ✨ Kelas Baru Dibuat : $created mata kuliah");
mtrace("   ⏭️  Kelas Dilewati  : $skipped (sudah ada)");
mtrace("   🔒 Tugas Dikunci   : $locked | Kuis Dikunci: $qlocked");
mtrace("==================================================");
