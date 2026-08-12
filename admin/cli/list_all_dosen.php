<?php
define('CLI_SCRIPT', true);
@error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);
require(__DIR__ . '/../../config.php');

mtrace("=== LIST ALL LECTURER ACCOUNTS IN MOODLE ===");

$teachers = $DB->get_records_sql("
    SELECT u.id, u.username, u.firstname, u.lastname, u.email, COUNT(ra.id) as course_count
    FROM {user} u
    JOIN {role_assignments} ra ON ra.userid = u.id
    JOIN {role} r ON r.id = ra.roleid
    WHERE r.shortname = 'editingteacher' AND u.deleted = 0 AND u.username LIKE 'dosen_%'
    GROUP BY u.id, u.username, u.firstname, u.lastname, u.email
    ORDER BY u.id ASC
");

echo "NO | USERNAME | NAMA LENGKAP & GELAR | EMAIL | JUMLAH KELAS" . PHP_EOL;
echo "--------------------------------------------------------------------------------" . PHP_EOL;

$no = 1;
foreach ($teachers as $t) {
    $fullname = trim($t->firstname . ' ' . $t->lastname);
    echo sprintf("%2d | %-12s | %-45s | %-32s | %d kelas\n", 
        $no++, 
        $t->username, 
        mb_strimwidth($fullname, 0, 45, '...'), 
        $t->email, 
        $t->course_count
    );
}
