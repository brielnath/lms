<?php
define('CLI_SCRIPT', true);
error_reporting(E_ALL);
ini_set('display_errors', 1);
require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/course/lib.php');

mtrace("=== TESTING CREATE_COURSE ===");

// Test 1: Check if shortname already taken
$exists = $DB->get_record('course', ['shortname' => 'SIF101_20252026Ganjil']);
if ($exists) {
    mtrace("Course already exists: " . $exists->fullname);
} else {
    mtrace("No duplicate, safe to create");
}

// Test 2: Create one minimal test course
try {
    $c = new stdClass();
    $c->fullname    = 'TEST Mata Kuliah Semester Baru';
    $c->shortname   = 'TEST_MKBARU_001';
    $c->category    = 3; // SIF category
    $c->visible     = 1;
    $c->format      = 'weeks';
    $c->numsections = 16;
    $c->startdate   = mktime(0, 0, 0, 9, 1, 2025);
    $c->enddate     = mktime(0, 0, 0, 2, 28, 2026);
    $result = create_course($c);
    mtrace("✅ Test course created! ID: " . $result->id);
    
    // Delete test course
    delete_course($result->id, false);
    mtrace("✅ Test course deleted cleanly");
} catch (Exception $e) {
    mtrace("❌ Error: " . $e->getMessage());
}

// Test 3: List current categories
$cats = $DB->get_records('course_categories', [], 'sortorder ASC', 'id, name, parent');
mtrace("\nKategori yang Ada:");
foreach ($cats as $cat) {
    mtrace("  ID:{$cat->id} | Parent:{$cat->parent} | {$cat->name}");
}
