<?php
define('CLI_SCRIPT', true);
@error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);
require(__DIR__ . '/../../config.php');

mtrace("=== VERIFIKASI DOSEN DI MOODLE ===");

$teachers = $DB->get_records_sql("
    SELECT u.id, u.username, u.firstname, u.lastname, u.email, COUNT(ra.id) as course_count
    FROM {user} u
    JOIN {role_assignments} ra ON ra.userid = u.id
    JOIN {role} r ON r.id = ra.roleid
    WHERE r.shortname = 'editingteacher' AND u.deleted = 0 AND u.username LIKE 'dosen_%'
    GROUP BY u.id, u.username, u.firstname, u.lastname, u.email
    ORDER BY course_count DESC
");

mtrace("📋 Total Dosen Resmi Terdaftar sebagai Editing Teacher: " . count($teachers));
foreach (array_slice($teachers, 0, 15) as $t) {
    mtrace("  • {$t->firstname} {$t->lastname} ({$t->email}) -> Mengampu {$t->course_count} Mata Kuliah");
}
