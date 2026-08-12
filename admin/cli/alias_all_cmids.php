<?php
define('CLI_SCRIPT', true);

require(__DIR__ . '/../../config.php');

$course = $DB->get_record('course', ['shortname' => 'SIF105']);
$quiz = $DB->get_record('quiz', ['course' => $course->id]);
$mod_quiz = $DB->get_record('modules', ['name' => 'quiz']);
$sec8 = $DB->get_record('course_sections', ['course' => $course->id, 'section' => 8]);

foreach ([36, 40] as $alias_cmid) {
    $cm = $DB->get_record('course_modules', ['id' => $alias_cmid]);
    if (!$cm) {
        $DB->execute("INSERT INTO {course_modules} (id, course, module, instance, section, added, score, indent, visible, visibleold, groupmode, groupingid, completion, completionview) VALUES (?, ?, ?, ?, ?, ?, 0, 0, 1, 1, 0, 0, 2, 1)", [$alias_cmid, $course->id, $mod_quiz->id, $quiz->id, $sec8->id, time()]);
    } else {
        $cm->course = $course->id;
        $cm->module = $mod_quiz->id;
        $cm->instance = $quiz->id;
        $cm->section = $sec8->id;
        $cm->visible = 1;
        $DB->update_record('course_modules', $cm);
    }
}

$sec8->sequence = '35,41';
$DB->update_record('course_sections', $sec8);

rebuild_course_cache($course->id, true);
mtrace("✅ ALL CMID ALIASES READY!");
