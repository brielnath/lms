<?php
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../config.php');

// Find user Andini Zafira Keyzia
$users = $DB->get_records_select('user', "firstname LIKE '%Andini%' OR lastname LIKE '%Keyzia%' OR username LIKE '%keyzia%'");
mtrace("=== USER INFO ===");
foreach ($users as $u) {
    mtrace("ID: {$u->id} | Username: {$u->username} | Name: {$u->firstname} {$u->lastname}");
}

// Find course Customer Relationship Management
$courses = $DB->get_records_select('course', "fullname LIKE '%Customer Relationship Management%' OR shortname LIKE '%CRM%'");
mtrace("\n=== COURSE INFO ===");
foreach ($courses as $c) {
    mtrace("ID: {$c->id} | Shortname: {$c->shortname} | Fullname: {$c->fullname}");
    
    // Get sections for this course
    $sections = $DB->get_records('course_sections', ['course' => $c->id], 'section ASC');
    mtrace("Sections count: " . count($sections));
    foreach ($sections as $s) {
        mtrace("  Section {$s->section}: name='{$s->name}' | summary_len=" . strlen($s->summary) . " | sequence='{$s->sequence}'");
    }
}
