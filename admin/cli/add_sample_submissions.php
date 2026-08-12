<?php
/**
 * ADD SAMPLE STUDENT SUBMISSIONS FOR DEMO GRADING
 */
define('CLI_SCRIPT', true);
@error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);
require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/assign/locallib.php');

mtrace("=== MENAMBAHKAN SAMPLE TUGAS MAHASISWA UNTUK DEMO PENILAIAN ===");

$course = $DB->get_record('course', ['id' => 413]);
if (!$course) exit(1);

// Find assignment module in course 413
$cm_rec = $DB->get_record_sql("
    SELECT cm.id, cm.instance
    FROM {course_modules} cm
    JOIN {modules} m ON m.id = cm.module
    WHERE cm.course = ? AND m.name = 'assign'
    ORDER BY cm.id ASC
    LIMIT 1
", [$course->id]);

if (!$cm_rec) {
    mtrace("No assignment found in course 413!");
    exit(1);
}

$assign_rec = $DB->get_record('assign', ['id' => $cm_rec->instance]);

// Get student users
$students = $DB->get_records_sql("
    SELECT u.id, u.firstname, u.lastname, u.username
    FROM {user} u
    JOIN {role_assignments} ra ON ra.userid = u.id
    JOIN {role} r ON r.id = ra.roleid
    WHERE r.shortname = 'student' AND u.deleted = 0
    LIMIT 5
");

mtrace("Tugas Target: {$assign_rec->name} (ID: {$assign_rec->id})");
mtrace("Jumlah Mahasiswa Sample: " . count($students));

$samples = [
    "Berikut adalah hasil pengerjaan Tugas 1 Algoritma & Flowchart.\n\nLangkah-langkah yang saya susun:\n1. Inisialisasi variabel input N\n2. Melakukan pengecekan kondisi ganjil/genap dengan operator modulo (%)\n3. Menampilkan output sesuai hasil perkondisian.\n\nLampiran kode Python sudah diuji coba di IDLE Python 3.11 dan berjalan 100% lancar tanpa error.",
    "Tugas 1 - Pemrograman Dasar\nNama: Gabriela Florencia\nNIM: 062302001\n\nSaya telah menyusun algoritma untuk menghitung luas dan keliling lingkaran serta konversi suhu dari Celcius ke Fahrenheit dan Kelvin.\n\nCode Snippet:\ncelcius = float(input('Masukkan suhu Celcius: '))\nfahrenheit = (celcius * 9/5) + 32\nprint('Fahrenheit:', fahrenheit)",
    "Tugas 1 Pemrograman Dasar - Allegra Tabita\n\nAnalisis Studi Kasus System Kasir Sederhana:\n- Menggunakan percabangan IF-ELSE untuk menentukan diskon belanjaan di atas Rp 100.000 (diskon 10%).\n- Program mencetak struk rincian item, total harga awal, jumlah diskon, dan total bayar akhir.",
];

$cm = get_coursemodule_from_id('assign', $cm_rec->id, 0, false, MUST_EXIST);
$i = 0;
foreach ($students as $student) {
    // Enroll student to course 413 if not enrolled
    $student_role = $DB->get_record('role', ['shortname' => 'student']);
    $is_enrolled = $DB->record_exists_sql("
        SELECT 1 FROM {enrol} e
        JOIN {user_enrolments} ue ON ue.enrolid = e.id
        WHERE e.courseid = ? AND ue.userid = ?
    ", [$course->id, $student->id]);
    if (!$is_enrolled) {
        enrol_try_internal_enrol($course->id, $student->id, $student_role->id);
    }

    $submission = $DB->get_record('assign_submission', ['assignment' => $assign_rec->id, 'userid' => $student->id]);
    if (!$submission) {
        $sub = new stdClass();
        $sub->assignment   = $assign_rec->id;
        $sub->userid       = $student->id;
        $sub->timecreated  = time() - (rand(1, 5) * 86400);
        $sub->timemodified = time() - (rand(1, 5) * 3600);
        $sub->status       = 'submitted';
        $sub->groupid      = 0;
        $sub->attemptnumber = 0;
        $sub_id = $DB->insert_record('assign_submission', $sub);
    } else {
        $sub_id = $submission->id;
        $DB->set_field('assign_submission', 'status', 'submitted', ['id' => $sub_id]);
    }

    // Insert online text
    $text = $samples[$i % count($samples)];
    $plugin_data = $DB->get_record('assignsubmission_onlinetext', ['submission' => $sub_id]);
    if (!$plugin_data) {
        $data = new stdClass();
        $data->assignment   = $assign_rec->id;
        $data->submission   = $sub_id;
        $data->onlinetext   = "<p>" . nl2br($text) . "</p>";
        $data->onlineformat = FORMAT_HTML;
        $DB->insert_record('assignsubmission_onlinetext', $data);
    } else {
        $DB->set_field('assignsubmission_onlinetext', 'onlinetext', "<p>" . nl2br($text) . "</p>", ['id' => $plugin_data->id]);
    }

    mtrace("  ✅ Submission dibuat untuk: {$student->firstname} {$student->lastname} ({$student->username})");
    $i++;
}

rebuild_course_cache($course->id, true);
purge_all_caches();
mtrace("🎉 Sample tugas mahasiswa berhasil ditambahkan!");
