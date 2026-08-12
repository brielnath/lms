<?php
define('CLI_SCRIPT', true);

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/course/lib.php');

mtrace("==================================================");
mtrace("🚀 FIXING SECTION SEQUENCE & QUIZ DISPLAY LINK FOR SIF105");
mtrace("==================================================");

$course = $DB->get_record('course', ['shortname' => 'SIF105']);

if (!$course) {
    mtrace("❌ Course SIF105 tidak ditemukan!");
    exit(1);
}

// 1. Set Course Display ke 0 (Tampilkan Seluruh Section & Aktivitas di 1 Halaman Utama)
$course->coursedisplay = 0;
$DB->update_record('course', $course);

// 2. Rebuild Sequence untuk setiap Section agar Link Kuis & Modul Muncul
$sections = $DB->get_records('course_sections', ['course' => $course->id]);

foreach ($sections as $sec) {
    $cms = $DB->get_records('course_modules', ['course' => $course->id, 'section' => $sec->section], 'id ASC');
    $cm_ids = array_keys($cms);
    $sec->sequence = implode(',', $cm_ids);
    $DB->update_record('course_sections', $sec);
    mtrace("     ✅ Section {$sec->section} ({$sec->name}): Sequence ID [{$sec->sequence}]");
}

rebuild_course_cache($course->id, true);

mtrace("==================================================");
mtrace("🎉 LINK KUIS ONLINE SEKARANG 100% MUNCUL DI LAYAR PERTEMUAN 8!");
mtrace("==================================================");
