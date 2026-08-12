<?php
/**
 * TAHAP 3 - DOSEN: Aktivasi & Persiapan Materi Semester Baru
 *
 * Fungsi:
 * A. Kirim notifikasi selamat datang ke semua kelas baru
 * B. Buat template aktivitas standar (Pertemuan 1-8): Folder Materi + Forum Diskusi + Tugas
 * C. Buat wadah Kuis UTS & UAS per kelas
 * D. Injeksi RPS dari SIAKAD ke Section 0 kelas baru
 */
define('CLI_SCRIPT', true);
@error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);
require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/mod/forum/lib.php');

$NEW_SEMESTER_LABEL = '2025/2026 - Ganjil';
$SHORTNAME_SUFFIX   = '_20252026Ganjil';

mtrace("==================================================");
mtrace("👨‍🏫 TAHAP 3 - DOSEN: AKTIVASI KELAS SEMESTER BARU");
mtrace("   Target Semester: $NEW_SEMESTER_LABEL");
mtrace("==================================================\n");

// Get all new semester courses
$new_courses = $DB->get_records_sql("
    SELECT c.id, c.fullname, c.shortname
    FROM {course} c
    WHERE c.shortname LIKE ?
    ORDER BY c.shortname ASC
", ['%' . $SHORTNAME_SUFFIX]);

mtrace("📋 Total Kelas Semester Baru: " . count($new_courses));

$forum_created   = 0;
$assign_created  = 0;
$quiz_created    = 0;
$rps_injected    = 0;
$notif_created   = 0;

function api_get($ep) {
    $url = "https://siakad.sugenghartono.ac.id/api" . $ep;
    $ch = curl_init($url);
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

// Fetch RPS map from SIAKAD
$rps_map = [];
$page = 1;
while (true) {
    $res = api_get("/all-lessons?page=$page");
    $items = $res['data'] ?? [];
    if (empty($items)) break;
    foreach ($items as $l) {
        $code = trim($l['code'] ?? '');
        if ($code && !empty($l['file_rps'])) {
            $rps_map[$code] = $l['file_rps'];
        }
    }
    if (count($items) < 15) break;
    $page++;
    if ($page > 50) break;
}
mtrace("📄 RPS tersedia dari SIAKAD: " . count($rps_map) . " mata kuliah\n");

$i = 0;
foreach ($new_courses as $course) {
    $i++;
    $code = str_replace($SHORTNAME_SUFFIX, '', $course->shortname);
    $course_name = str_replace(" — $NEW_SEMESTER_LABEL", '', $course->fullname);

    // Ensure course sections exist (16 sections)
    course_create_sections_if_missing($course->id, range(0, 16));

    // ─── A. NOTIFIKASI di Section 0 ───────────────────────────────────────
    $section0 = $DB->get_record('course_sections', ['course' => $course->id, 'section' => 0]);
    if ($section0 && !$DB->record_exists('forum', ['course' => $course->id, 'type' => 'news'])) {
        // Forum Pengumuman sudah auto-dibuat oleh Moodle saat course dibuat
        // Update summary section 0
        $welcome_text = "<div style='background:#f0f7ff;border-left:4px solid #0284c7;padding:14px 18px;border-radius:8px;font-family:sans-serif;'>
            <h3 style='color:#0284c7;margin:0 0 6px;'>📢 Selamat Datang di Semester $NEW_SEMESTER_LABEL</h3>
            <p style='margin:0;color:#334155;font-size:14px;'>Halo Mahasiswa! Ini adalah kelas <strong>$course_name</strong> untuk semester $NEW_SEMESTER_LABEL. 
            Silakan pelajari RPS/Silabus di bawah ini dan ikuti pertemuan sesuai jadwal. Semangat belajar! 🚀</p></div>
            <hr><h4>📋 Rencana Pembelajaran Semester (RPS)</h4>";
        $DB->set_field('course_sections', 'summary', $welcome_text, ['id' => $section0->id]);
        $notif_created++;
    }

    // ─── B. INJEKSI RPS ke Section 0 ──────────────────────────────────────
    if (isset($rps_map[$code]) && $section0) {
        $existing_rps = $DB->record_exists_sql("
            SELECT 1 FROM {course_modules} cm
            JOIN {modules} m ON m.id = cm.module
            WHERE cm.course = ? AND m.name = 'resource' AND cm.section = ?
        ", [$course->id, $section0->id]);

        if (!$existing_rps) {
            $rps_url = $rps_map[$code];
            if (!str_starts_with($rps_url, 'http')) {
                $rps_url = 'https://siakad.sugenghartono.ac.id/storage/' . $rps_url;
            }

            // Add URL resource for RPS
            $mod = new stdClass();
            $mod->course      = $course->id;
            $mod->name        = "📄 RPS - $course_name";
            $mod->intro       = "<p>Rencana Pembelajaran Semester (RPS) resmi mata kuliah $course_name dari SIAKAD USH.</p>";
            $mod->introformat = FORMAT_HTML;
            $mod->externalurl = $rps_url;
            $mod->display     = 0;
            $mod->displayoptions = serialize(['printintro' => 0, 'printheading' => 1]);
            $mod->timemodified = time();

            $module_id = $DB->get_field('modules', 'id', ['name' => 'url']);
            if ($module_id) {
                $mod->id = $DB->insert_record('url', $mod);
                $cm = new stdClass();
                $cm->course   = $course->id;
                $cm->module   = $module_id;
                $cm->instance = $mod->id;
                $cm->section  = $section0->id;
                $cm->visible  = 1;
                $cm->added    = time();
                $DB->insert_record('course_modules', $cm);
                $rps_injected++;
            }
        }
    }

    // ─── C. TEMPLATE PERTEMUAN 1-8 ────────────────────────────────────────
    for ($pertemuan = 1; $pertemuan <= 8; $pertemuan++) {
        $section = $DB->get_record('course_sections', ['course' => $course->id, 'section' => $pertemuan]);
        if (!$section) continue;

        // Update section name if blank
        if (empty($section->name)) {
            $DB->set_field('course_sections', 'name', "📅 Pertemuan $pertemuan", ['id' => $section->id]);
        }

        // Forum Diskusi per Pertemuan
        $forum_exists = $DB->record_exists_sql("
            SELECT 1 FROM {course_modules} cm
            JOIN {modules} m ON m.id = cm.module
            JOIN {forum} f ON f.id = cm.instance
            WHERE cm.course = ? AND m.name = 'forum' AND cm.section = ?
        ", [$course->id, $section->id]);

        if (!$forum_exists) {
            $forum = new stdClass();
            $forum->course       = $course->id;
            $forum->type         = 'general';
            $forum->name         = "💬 Diskusi Pertemuan $pertemuan";
            $forum->intro        = "<p>Forum diskusi materi Pertemuan $pertemuan.</p>";
            $forum->introformat  = FORMAT_HTML;
            $forum->timemodified = time();
            $forum->id = $DB->insert_record('forum', $forum);

            $module_id = $DB->get_field('modules', 'id', ['name' => 'forum']);
            if ($module_id) {
                $cm = new stdClass();
                $cm->course   = $course->id;
                $cm->module   = $module_id;
                $cm->instance = $forum->id;
                $cm->section  = $section->id;
                $cm->visible  = 1;
                $cm->added    = time();
                $DB->insert_record('course_modules', $cm);
                $forum_created++;
            }
        }

        // Tugas Assignment per Pertemuan
        $assign_exists = $DB->record_exists_sql("
            SELECT 1 FROM {course_modules} cm
            JOIN {modules} m ON m.id = cm.module
            WHERE cm.course = ? AND m.name = 'assign' AND cm.section = ?
        ", [$course->id, $section->id]);

        if (!$assign_exists) {
            // Deadline: pertemuan pertama + (pertemuan * 7 hari)
            $deadline = mktime(23, 59, 0, 9, 1 + ($pertemuan * 7), 2025);

            $assign = new stdClass();
            $assign->course             = $course->id;
            $assign->name               = "📝 Tugas Pertemuan $pertemuan";
            $assign->intro              = "<p>Kerjakan tugas sesuai materi Pertemuan $pertemuan. Upload file jawaban Anda sebelum batas waktu.</p>";
            $assign->introformat        = FORMAT_HTML;
            $assign->duedate            = $deadline;
            $assign->cutoffdate         = $deadline + (3 * 86400);
            $assign->allowsubmissionsfromdate = 0;
            $assign->grade              = 100;
            $assign->submissiondrafts   = 0;
            $assign->requiresubmissionstatement = 0;
            $assign->teamsubmission     = 0;
            $assign->maxattempts        = -1;
            $assign->timemodified       = time();
            $assign->id = $DB->insert_record('assign', $assign);

            $module_id = $DB->get_field('modules', 'id', ['name' => 'assign']);
            if ($module_id) {
                $cm = new stdClass();
                $cm->course   = $course->id;
                $cm->module   = $module_id;
                $cm->instance = $assign->id;
                $cm->section  = $section->id;
                $cm->visible  = 1;
                $cm->added    = time();
                $DB->insert_record('course_modules', $cm);
                $assign_created++;
            }
        }
    }

    // ─── D. KUIS UTS (Section 9) & UAS (Section 16) ───────────────────────
    foreach ([9 => 'UTS', 16 => 'UAS'] as $sec_num => $exam_type) {
        $section = $DB->get_record('course_sections', ['course' => $course->id, 'section' => $sec_num]);
        if (!$section) continue;

        if (empty($section->name)) {
            $label = $sec_num == 9 ? '🏆 Ujian Tengah Semester (UTS)' : '🎓 Ujian Akhir Semester (UAS)';
            $DB->set_field('course_sections', 'name', $label, ['id' => $section->id]);
        }

        $quiz_exists = $DB->record_exists_sql("
            SELECT 1 FROM {course_modules} cm
            JOIN {modules} m ON m.id = cm.module
            WHERE cm.course = ? AND m.name = 'quiz' AND cm.section = ?
        ", [$course->id, $section->id]);

        if (!$quiz_exists) {
            $quiz_date   = ($exam_type == 'UTS') ? mktime(8, 0, 0, 11, 17, 2025) : mktime(8, 0, 0, 1, 19, 2026);
            $quiz_close  = $quiz_date + (2 * 3600);

            $quiz = new stdClass();
            $quiz->course        = $course->id;
            $quiz->name          = "📝 $exam_type — $course_name";
            $quiz->intro         = "<p>Kuis $exam_type untuk mata kuliah $course_name. Perhatikan batas waktu pengerjaan.</p>";
            $quiz->introformat   = FORMAT_HTML;
            $quiz->timeopen      = $quiz_date;
            $quiz->timeclose     = $quiz_close;
            $quiz->timelimit     = 5400;
            $quiz->grade         = 100;
            $quiz->attempts      = 1;
            $quiz->grademethod   = 1;
            $quiz->shuffleanswers = 1;
            $quiz->questionsperpage = 5;
            $quiz->timemodified  = time();
            $quiz->id = $DB->insert_record('quiz', $quiz);

            $module_id = $DB->get_field('modules', 'id', ['name' => 'quiz']);
            if ($module_id) {
                $cm = new stdClass();
                $cm->course   = $course->id;
                $cm->module   = $module_id;
                $cm->instance = $quiz->id;
                $cm->section  = $section->id;
                $cm->visible  = 1;
                $cm->added    = time();
                $DB->insert_record('course_modules', $cm);
                $quiz_created++;
            }
        }
    }

    if ($i % 20 == 0) mtrace("  ... $i kelas diproses ...");
}

rebuild_course_cache(0, true);
purge_all_caches();

mtrace("\n==================================================");
mtrace("🎉 TAHAP 3 - DOSEN SELESAI!");
mtrace("   📢 Notifikasi Selamat Datang  : $notif_created kelas");
mtrace("   📄 RPS Diinjeksi              : $rps_injected kelas");
mtrace("   💬 Forum Diskusi Dibuat       : $forum_created forum");
mtrace("   📝 Tugas Assignment Dibuat    : $assign_created tugas");
mtrace("   📊 Kuis UTS & UAS Dibuat      : $quiz_created kuis");
mtrace("==================================================");
