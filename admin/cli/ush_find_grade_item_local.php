<?php
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../config.php');

$needle = $argv[1] ?? 'Tugas Pertemuan 4';
$rows = $DB->get_records_sql(
    "SELECT gi.id, gi.itemname, gi.courseid, c.shortname, c.fullname
       FROM {grade_items} gi
       JOIN {course} c ON c.id = gi.courseid
      WHERE gi.itemname LIKE ?
   ORDER BY gi.courseid DESC
      LIMIT 40",
    ['%' . $needle . '%']
);
if (!$rows) {
    mtrace("Tidak ketemu: $needle");
    exit(0);
}
foreach ($rows as $r) {
    mtrace("{$r->courseid} | {$r->shortname} | {$r->itemname}");
}
