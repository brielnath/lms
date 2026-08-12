<?php
define('CLI_SCRIPT', true);
@error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);
require(__DIR__ . '/../../config.php');

$crm = $DB->get_record('course', ['shortname' => 'SBD403']);
if (!$crm) {
    $crm = $DB->get_record_select('course', "fullname LIKE '%Customer Relationship Management%'");
}

echo "=== CHECK CRM COURSE ===" . PHP_EOL;
echo "Course ID: " . $crm->id . PHP_EOL;
echo "Fullname: " . $crm->fullname . PHP_EOL;

$sections = $DB->get_records('course_sections', ['course' => $crm->id], 'section ASC');
foreach ($sections as $s) {
    echo "Section {$s->section}: name='{$s->name}'" . PHP_EOL;
}
