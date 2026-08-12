<?php
/**
 * TAHAP 2 - PRODI: Validasi Semester Baru
 * 
 * Fungsi:
 * A. Validasi Dosen Pengampu per kelas baru
 * B. Cek kelengkapan RPS di Section 0
 * C. Laporan kelas paralel
 */
define('CLI_SCRIPT', true);
@error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);
require(__DIR__ . '/../../config.php');

$NEW_SEMESTER_LABEL = '2025/2026 - Ganjil';
$SHORTNAME_SUFFIX   = '_20252026Ganjil';

mtrace("==================================================");
mtrace("🏢 TAHAP 2 - PRODI: VALIDASI SEMESTER BARU");
mtrace("   Target Semester: $NEW_SEMESTER_LABEL");
mtrace("==================================================\n");

// Fetch all new semester courses
$new_courses = $DB->get_records_sql("
    SELECT c.id, c.fullname, c.shortname, c.idnumber, c.category
    FROM {course} c
    WHERE c.shortname LIKE ?
    ORDER BY c.shortname ASC
", ['%' . $SHORTNAME_SUFFIX]);

mtrace("📋 Total Kelas Semester Baru Ditemukan: " . count($new_courses));

$teacher_role     = $DB->get_record('role', ['shortname' => 'editingteacher']);
$no_teacher       = [];
$has_rps          = [];
$no_rps           = [];
$parallel_map     = [];

foreach ($new_courses as $course) {
    $code = str_replace($SHORTNAME_SUFFIX, '', $course->shortname);

    // A. Cek apakah ada dosen pengampu
    $ctx = context_course::instance($course->id);
    $teachers = get_role_users($teacher_role->id, $ctx, false, 'u.id, u.firstname, u.lastname');
    if (empty($teachers)) {
        $no_teacher[] = ['code' => $code, 'name' => $course->fullname, 'id' => $course->id];
    }

    // B. Cek RPS di Section 0
    $section0 = $DB->get_record('course_sections', ['course' => $course->id, 'section' => 0]);
    $has_rps_file = false;
    if ($section0) {
        $mods = $DB->get_records('course_modules', ['course' => $course->id, 'section' => $section0->id]);
        $has_rps_file = !empty($mods);
    }

    if ($has_rps_file) {
        $has_rps[] = $code;
    } else {
        $no_rps[] = ['code' => $code, 'name' => str_replace(" — $NEW_SEMESTER_LABEL", '', $course->fullname)];
    }

    // C. Deteksi kelas paralel (kode sama, suffix berbeda)
    $base_code = preg_replace('/[A-Z]$/', '', $code); // Remove last letter if it's a class label
    $parallel_map[$base_code][] = $code;
}

// ─── LAPORAN A: Dosen Pengampu ───
mtrace("\n=== 📊 LAPORAN A: VALIDASI DOSEN PENGAMPU ===");
mtrace("  Kelas SUDAH memiliki Dosen: " . (count($new_courses) - count($no_teacher)) . " kelas");
mtrace("  Kelas BELUM memiliki Dosen: " . count($no_teacher) . " kelas");
if (!empty($no_teacher)) {
    mtrace("\n  ⚠️  Daftar Kelas Tanpa Dosen Pengampu:");
    foreach (array_slice($no_teacher, 0, 20) as $c) {
        mtrace("     • [{$c['code']}] {$c['name']}");
    }
    if (count($no_teacher) > 20) mtrace("     ... dan " . (count($no_teacher) - 20) . " lainnya");
}

// ─── LAPORAN B: RPS ─────────────
mtrace("\n=== 📄 LAPORAN B: KELENGKAPAN RPS ===");
mtrace("  Kelas SUDAH ada RPS : " . count($has_rps) . " kelas");
mtrace("  Kelas BELUM ada RPS : " . count($no_rps) . " kelas");

// ─── LAPORAN C: Kelas Paralel ───
$parallel_classes = array_filter($parallel_map, fn($v) => count($v) > 1);
mtrace("\n=== 🔀 LAPORAN C: KELAS PARALEL ===");
mtrace("  Jumlah Grup Paralel: " . count($parallel_classes));
foreach (array_slice($parallel_classes, 0, 10) as $base => $codes) {
    mtrace("  • $base → " . implode(', ', $codes));
}

// ─── AUTO-ASSIGN DOSEN dari SIAKAD ─────
mtrace("\n📌 [AUTO-ASSIGN] Menghubungkan Dosen SIAKAD ke Kelas Baru...");

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

// Build lecturer-course map from SIAKAD grades (2025/2026 Ganjil only)
$siakad_map = []; // [lesson_code => [id_lecture => lecture_name]]
$page = 1;
while (true) {
    $res = api_get("/grades-per-course?page=$page");
    $items = $res['data'] ?? [];
    if (empty($items)) break;
    foreach ($items as $mhs) {
        foreach ($mhs['grade'] ?? [] as $g) {
            if (($g['tahun_akademik'] ?? '') === '2025/2026' && ($g['periode'] ?? '') === 'Ganjil') {
                $code    = trim($g['lesson_code'] ?? '');
                $lec_id  = $g['id_lecture'] ?? null;
                $lec_nm  = trim($g['lecture_name'] ?? '');
                if ($code && $lec_id && $lec_nm !== 'Unknown') {
                    $siakad_map[$code][$lec_id] = $lec_nm;
                }
            }
        }
    }
    if (count($items) < 15) break;
    $page++;
    if ($page > 20) break;
}

mtrace("  SIAKAD: " . count($siakad_map) . " mata kuliah dengan dosen di semester 2025/2026 Ganjil");

$assigned = 0;
foreach ($new_courses as $course) {
    $code = str_replace($SHORTNAME_SUFFIX, '', $course->shortname);
    if (!isset($siakad_map[$code])) continue;

    $ctx = context_course::instance($course->id);
    foreach ($siakad_map[$code] as $lec_id => $lec_name) {
        $moodle_user = $DB->get_record('user', ['username' => 'dosen_' . $lec_id]);
        if (!$moodle_user) continue;

        $is_enrolled = $DB->record_exists_sql("
            SELECT 1 FROM {enrol} e
            JOIN {user_enrolments} ue ON ue.enrolid = e.id
            WHERE e.courseid = ? AND ue.userid = ?
        ", [$course->id, $moodle_user->id]);

        if (!$is_enrolled) {
            enrol_try_internal_enrol($course->id, $moodle_user->id, $teacher_role->id);
            $assigned++;
        }
    }
}

mtrace("  ✅ $assigned Dosen berhasil di-assign ke kelas semester baru");

rebuild_course_cache(0, true);

mtrace("\n==================================================");
mtrace("🎉 TAHAP 2 - PRODI SELESAI!");
mtrace("   ✅ Kelas Berhasil Divalidasi : " . count($new_courses));
mtrace("   ⚠️  Kelas Tanpa Dosen       : " . count($no_teacher));
mtrace("   📄 Kelas Tanpa RPS          : " . count($no_rps));
mtrace("   🔀 Grup Paralel Terdeteksi  : " . count($parallel_classes));
mtrace("   👨‍🏫 Dosen Terassign Baru    : $assigned");
mtrace("==================================================");
