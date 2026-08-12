<?php
define('CLI_SCRIPT', true);

require(__DIR__ . '/../../config.php');

mtrace("==================================================");
mtrace("🚀 RESTORING CMID 36 TO CURRENT QUIZ FOR INSTANT FIX");
mtrace("==================================================");

$course = $DB->get_record('course', ['shortname' => 'SIF105']);
$quiz = $DB->get_record('quiz', ['course' => $course->id]);
$mod_quiz = $DB->get_record('modules', ['name' => 'quiz']);

if (!$course || !$quiz || !$mod_quiz) {
    mtrace("❌ Course atau Quiz tidak ditemukan!");
    exit(1);
}

// 1. Pastikan ID 36 ada di mdl_course_modules dan mengarah ke Quiz ini
$cm36 = $DB->get_record('course_modules', ['id' => 36]);

if (!$cm36) {
    $DB->execute("INSERT INTO {course_modules} (id, course, module, instance, section, added, score, indent, visible, visibleold, groupmode, groupingid, completion, completionview) VALUES (36, ?, ?, ?, 8, ?, 0, 0, 1, 1, 0, 0, 2, 1)", [$course->id, $mod_quiz->id, $quiz->id, time()]);
    mtrace("     ✅ ID 36 berhasil disisipkan kembali ke tabel course_modules!");
} else {
    $cm36->course = $course->id;
    $cm36->module = $mod_quiz->id;
    $cm36->instance = $quiz->id;
    $cm36->section = 8;
    $cm36->visible = 1;
    $DB->update_record('course_modules', $cm36);
    mtrace("     ✅ ID 36 berhasil dihubungkan ke Quiz!");
}

// 2. Update sequence section 8
$sec = $DB->get_record('course_sections', ['course' => $course->id, 'section' => 8]);
if ($sec) {
    $sec->sequence = '35,36';
    $DB->update_record('course_sections', $sec);
}

rebuild_course_cache($course->id, true);

mtrace("==================================================");
mtrace("🎉 CMID 36 KINI MULTI-COMPATIBLE! TOMBOL DI BROWSER ANDA BISA DIKLIK LANGSUNG!");
mtrace("==================================================");
