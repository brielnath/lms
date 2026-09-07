<?php
/**
 * Salin peran Manager Kaprodi ke folder prodi 2026/2027 Ganjil (lokal).
 */
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/accesslib.php');

if (strpos($CFG->wwwroot, 'localhost') === false && strpos($CFG->wwwroot, '127.0.0.1') === false) {
    mtrace('Dibatalkan: bukan LMS lokal.');
    exit(1);
}

$roleid = $DB->get_field('role', 'id', ['shortname' => 'manager'], MUST_EXIST);
$map = [
    'Sistem Informasi' => 'Sistem Informasi (SIF) - 2026/2027 - Ganjil',
    'Bisnis Digital' => 'Bisnis Digital (SBD) - 2026/2027 - Ganjil',
    'Ilmu Gizi' => 'Ilmu Gizi (SGZ) - 2026/2027 - Ganjil',
    'Hukum' => 'Hukum Bisnis (HKM) - 2026/2027 - Ganjil',
];

$kaps = $DB->get_records_sql(
    "SELECT ra.userid, u.firstname, u.lastname, cc.name AS srcname
       FROM {role_assignments} ra
       JOIN {context} ctx ON ctx.id = ra.contextid AND ctx.contextlevel = 40
       JOIN {course_categories} cc ON cc.id = ctx.instanceid
       JOIN {user} u ON u.id = ra.userid
      WHERE ra.roleid = :roleid AND u.deleted = 0
      ORDER BY cc.name",
    ['roleid' => $roleid]
);

$assigned = 0;
foreach ($kaps as $k) {
    $destname = null;
    foreach ($map as $needle => $dest) {
        if (stripos($k->srcname, $needle) !== false) {
            $destname = $dest;
            break;
        }
    }
    if (!$destname) {
        mtrace("Lewati {$k->firstname}: kategori sumber {$k->srcname}");
        continue;
    }
    $dest = $DB->get_record('course_categories', ['name' => $destname]);
    if (!$dest) {
        mtrace("Folder tujuan tidak ada: $destname");
        continue;
    }
    $ctx = context_coursecat::instance($dest->id);
    if (user_has_role_assignment($k->userid, $roleid, $ctx->id)) {
        mtrace("Sudah ada: {$k->firstname} → $destname");
        continue;
    }
    role_assign($roleid, $k->userid, $ctx->id);
    $assigned++;
    mtrace("Assigned: {$k->firstname} {$k->lastname} → $destname");
}
mtrace("Selesai. Baru: $assigned");
