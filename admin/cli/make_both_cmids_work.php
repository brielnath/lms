<?php
define('CLI_SCRIPT', true);

require(__DIR__ . '/../../config.php');

mtrace("==================================================");
mtrace("🚀 MAKING CMID 36 AND CMID 40 BOTH VALID IN MOODLE DB");
mtrace("==================================================");

$course = $DB->get_record('course', ['shortname' => 'SIF105']);
$quiz = $DB->get_record('quiz', ['course' => $course->id]);
$mod_quiz = $DB->get_record('modules', ['name' => 'quiz']);
$sec8 = $DB->get_record('course_sections', ['course' => $course->id, 'section' => 8]);

if (!$course || !$quiz || !$mod_quiz || !$sec8) {
    mtrace("❌ Data tidak ditemukan!");
    exit(1);
}

// 1. Buat / Update CMID 36 agar mengarah ke Quiz ini
$cm36 = $DB->get_record('course_modules', ['id' => 36]);

if (!$cm36) {
    $DB->execute("INSERT INTO {course_modules} (id, course, module, instance, section, added, score, indent, visible, visibleold, groupmode, groupingid, completion, completionview) VALUES (36, ?, ?, ?, ?, ?, 0, 0, 1, 1, 0, 0, 2, 1)", [$course->id, $mod_quiz->id, $quiz->id, $sec8->id, time()]);
    mtrace("     ✅ CMID 36 berhasil dimasukkan ke database!");
} else {
    $cm36->course = $course->id;
    $cm36->module = $mod_quiz->id;
    $cm36->instance = $quiz->id;
    $cm36->section = $sec8->id;
    $cm36->visible = 1;
    $DB->update_record('course_modules', $cm36);
    mtrace("     ✅ CMID 36 berhasil dihubungkan ke Quiz!");
}

// 2. Update sequence section 8
$sec8->sequence = '35,36,40';
$DB->update_record('course_sections', $sec8);

rebuild_course_cache($course->id, true);

mtrace("==================================================");
mtrace("🎉 SEKARANG CMID 36 SAMA DENGAN CMID 40! TOMBOL BROWSER ANDA LANGSUNG BERHASIL!");
mtrace("==================================================");
