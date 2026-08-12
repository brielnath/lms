<?php
define('CLI_SCRIPT', true);
@error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);
require(__DIR__ . '/../../config.php');

mtrace("=== SEARCH TYPOS IN DATABASE ===");

// Check courses
$courses = $DB->get_records_select('course', "fullname LIKE '%Thinks%' OR fullname LIKE '%Intellegent%' OR shortname LIKE '%Thinks%' OR shortname LIKE '%Intellegent%'");
foreach ($courses as $c) {
    mtrace("Course ID: {$c->id} | Shortname: {$c->shortname} | Fullname: {$c->fullname}");
}

// Check categories
$cats = $DB->get_records_select('course_categories', "name LIKE '%Thinks%' OR name LIKE '%Intellegent%'");
foreach ($cats as $cat) {
    mtrace("Category ID: {$cat->id} | Name: {$cat->name}");
}

// Check theme academi settings
$settings = $DB->get_records('config_plugins', ['plugin' => 'theme_academi']);
mtrace("\n=== ALL THEME ACADEMI SETTINGS ===");
foreach ($settings as $s) {
    mtrace("{$s->name} => {$s->value}");
}
