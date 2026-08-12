<?php
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/course/lib.php');

$crm = $DB->get_record('course', ['shortname' => 'SBD403']);
if (!$crm) {
    $crm = $DB->get_record_select('course', "fullname LIKE '%Customer Relationship Management%'");
}

mtrace("=== CHECK CRM COURSE ===");
mtrace("Course ID: " . $crm->id);
mtrace("Fullname: " . $crm->fullname);

$sections = $DB->get_records('course_sections', ['course' => $crm->id], 'section ASC');
foreach ($sections as $s) {
    mtrace("Section {$s->section}: name='{$s->name}'");
}
