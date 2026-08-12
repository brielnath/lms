<?php
define('CLI_SCRIPT', true);
@error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);
require(__DIR__ . '/../../config.php');

mtrace("=== COURSES TO FEATURE ===");
$courses = $DB->get_records_select('course', "shortname IN ('SBD403', 'SIF105', 'IDM0619', 'IDM0509', 'IDM0310', 'IDM0708')", null, 'id ASC');
$ids = [];
foreach ($courses as $c) {
    mtrace("ID: {$c->id} | Shortname: {$c->shortname} | Fullname: {$c->fullname}");
    $ids[] = $c->id;
}

if (!empty($ids)) {
    $promoted = implode(',', $ids);
    set_config('promotedcourses', $promoted, 'theme_academi');
    mtrace("\n✅ Updated theme_academi promotedcourses to real USH courses: {$promoted}");
}

// Disable/hide dummy courses (PHP Course, Japanese Course) if they exist
$dummy_courses = $DB->get_records_select('course', "fullname LIKE '%PHP Course%' OR fullname LIKE '%Japanese Course%'");
foreach ($dummy_courses as $d) {
    $d->visible = 0;
    $DB->update_record('course', $d);
    mtrace("🙈 Hidden dummy course: {$d->fullname}");
}

rebuild_course_cache(0, true);
purge_all_caches();
