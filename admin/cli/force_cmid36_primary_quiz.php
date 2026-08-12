<?php
define('CLI_SCRIPT', true);

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/course/lib.php');

mtrace("==================================================");
mtrace("🚀 FORCING CMID 36 AS PRIMARY COURSE MODULE FOR QUIZ");
mtrace("==================================================");

$course = $DB->get_record('course', ['shortname' => 'SIF105']);
$quiz = $DB->get_record('quiz', ['course' => $course->id]);
$mod_quiz = $DB->get_record('modules', ['name' => 'quiz']);
$sec8 = $DB->get_record('course_sections', ['course' => $course->id, 'section' => 8]);

if (!$course || !$quiz || !$mod_quiz || !$sec8) {
    mtrace("❌ Data tidak ditemukan!");
    exit(1);
}

// 1. Hapus & buat ulang CMID 36 sebagai Primary CMID untuk Quiz
$DB->delete_records('course_modules', ['id' => 36]);

$cm36 = new stdClass();
$cm36->id = 36;
$cm36->course = $course->id;
$cm36->module = $mod_quiz->id;
$cm36->instance = $quiz->id;
$cm36->section = $sec8->id;
$cm36->added = time();
$cm36->score = 0;
$cm36->indent = 0;
$cm36->visible = 1;
$cm36->visibleold = 1;
$cm36->groupmode = 0;
$cm36->groupingid = 0;
$cm36->completion = 2;
$cm36->completionview = 1;

$DB->insert_record_raw('course_modules', $cm36, false, false, true);

// 2. Hubungkan context_module CMID 36 ke tabel question_references
$cm36_context = context_module::instance(36);
$DB->execute("UPDATE {question_references} SET usingcontextid = ? WHERE component = 'mod_quiz'", [$cm36_context->id]);

// 3. Update sequence section 8
$sec8->sequence = '35,36';
$DB->update_record('course_sections', $sec8);

// 4. Reset quiz attempts agar tidak ada bentrok percobaan lama
$DB->delete_records('quiz_attempts', []);

rebuild_course_cache($course->id, true);
purge_all_caches();

mtrace("==================================================");
mtrace("🎉 PRIMARY CMID 36 IS NOW LIVE AND READY FOR BROWSER!");
mtrace("==================================================");
