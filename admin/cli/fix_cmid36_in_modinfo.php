<?php
define('CLI_SCRIPT', true);

require(__DIR__ . '/../../config.php');

mtrace("==================================================");
mtrace("🚀 FIXING CMID 36 DIRECTLY IN MOODLE MODINFO");
mtrace("==================================================");

$course = $DB->get_record('course', ['shortname' => 'SIF105']);
$quiz = $DB->get_record('quiz', ['course' => $course->id]);
$mod_quiz = $DB->get_record('modules', ['name' => 'quiz']);
$sec8 = $DB->get_record('course_sections', ['course' => $course->id, 'section' => 8]);

if (!$course || !$quiz || !$mod_quiz || !$sec8) {
    mtrace("❌ Data tidak ditemukan!");
    exit(1);
}

// 1. Sisipkan ID 36 ke mdl_course_modules
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

// 2. Masukkan 36 ke sequence section 8
$sec8->sequence = '35,36,41';
$DB->update_record('course_sections', $sec8);

rebuild_course_cache($course->id, true);
purge_all_caches();

mtrace("==================================================");
mtrace("🎉 CMID 36 SEKARANG 100% REGISTRATION ACTIVE!");
mtrace("==================================================");
