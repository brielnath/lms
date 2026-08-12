<?php
define('CLI_SCRIPT', true);
@error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);
require(__DIR__ . '/../../config.php');

$u = $DB->get_record('user', ['username' => 'dosen_71']);
mtrace("=== USER DOSEN_71 INFO ===");
mtrace("ID: " . $u->id);
mtrace("Username: " . $u->username);
mtrace("Firstname: " . $u->firstname);
mtrace("Lastname: " . $u->lastname);
mtrace("Email: " . $u->email);

// Check role assignments for dosen_71
$roles = $DB->get_records_sql("
    SELECT r.shortname, c.shortname as course_code
    FROM {role_assignments} ra
    JOIN {role} r ON r.id = ra.roleid
    JOIN {context} ctx ON ctx.id = ra.contextid
    LEFT JOIN {course} c ON c.id = ctx.instanceid
    WHERE ra.userid = ?
", [$u->id]);

mtrace("\nRoles count: " . count($roles));
foreach ($roles as $r) {
    mtrace("Role: {$r->shortname} | Course: {$r->course_code}");
}
