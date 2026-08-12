<?php
define('CLI_SCRIPT', true);
@error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);
require(__DIR__ . '/../../config.php');

$courses = $DB->get_records_sql("
    SELECT id, fullname, shortname
    FROM {course}
    WHERE shortname LIKE '%SIF%' AND shortname LIKE '%20252026Ganjil%'
    ORDER BY id ASC
    LIMIT 10
");

mtrace("=== DAFTAR KELAS SIF UNTUK DEMO SHOWCASE ===");
foreach ($courses as $c) {
    mtrace("  • ID: {$c->id} | Shortname: {$c->shortname} | Fullname: {$c->fullname}");
}
