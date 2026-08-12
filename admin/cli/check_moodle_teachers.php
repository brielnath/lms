<?php
define('CLI_SCRIPT', true);
@error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);
require(__DIR__ . '/../../config.php');

mtrace("=== CHECK EXISTING MOODLE USERS & TEACHERS ===");
$users = $DB->get_records_sql("
    SELECT u.id, u.username, u.firstname, u.lastname, u.email, r.shortname as rolename
    FROM {user} u
    JOIN {role_assignments} ra ON ra.userid = u.id
    JOIN {role} r ON r.id = ra.roleid
    WHERE r.shortname IN ('editingteacher', 'teacher', 'manager') AND u.deleted = 0
");

mtrace("Total Teachers/Managers in Moodle: " . count($users));
foreach ($users as $u) {
    mtrace("ID: {$u->id} | Role: {$u->rolename} | Name: {$u->firstname} {$u->lastname} | Email: {$u->email} | Username: {$u->username}");
}
