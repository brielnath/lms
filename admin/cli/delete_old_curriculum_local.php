<?php
/**
 * Hapus semua kelas kurikulum lama di LMS lokal (SBD, SGZ, HKM, MKU, FIK, …).
 * Kode skema baru (IDM/IUM/IFM/IDE/GDM) tidak diubah. Production ditolak.
 */
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/ush_course_owner.php');
require_once(__DIR__ . '/ush_force_delete_course.php');

if (strpos($CFG->wwwroot, 'localhost') === false && strpos($CFG->wwwroot, '127.0.0.1') === false) {
    mtrace('Dibatalkan: bukan LMS lokal. wwwroot=' . $CFG->wwwroot);
    exit(1);
}

$all = $DB->get_records_sql("SELECT id, shortname FROM {course} WHERE id > 1 ORDER BY shortname");
$courses = [];
foreach ($all as $course) {
    if (!ush_is_official_scheme_code($course->shortname)) {
        $courses[] = $course;
    }
}

mtrace('Hapus ' . count($courses) . ' kelas kurikulum lama di ' . $CFG->wwwroot);
mtrace('Skema baru yang tetap: ' . (count($all) - count($courses)));

$deleted = 0;
$failed = 0;
foreach ($courses as $course) {
    try {
        ush_force_delete_course($course);
        $deleted++;
    } catch (Throwable $e) {
        $failed++;
        mtrace("  GAGAL {$course->shortname}: " . $e->getMessage());
    }
    if (($deleted + $failed) % 25 === 0) {
        mtrace('  ... ' . $deleted . ' hapus, ' . $failed . ' gagal');
    }
}

fix_course_sortorder();
rebuild_course_cache(0, true);

$left_old = 0;
$keep = 0;
$remain = $DB->get_records_sql("SELECT id, shortname FROM {course} WHERE id > 1");
$pfx = [];
foreach ($remain as $c) {
    if (ush_is_official_scheme_code($c->shortname)) {
        $keep++;
        $k = strtoupper(substr(preg_replace('/_20\d{6}(GANJIL|GENAP)$/i', '', $c->shortname), 0, 3));
        $pfx[$k] = ($pfx[$k] ?? 0) + 1;
    } else {
        $left_old++;
        mtrace('  masih ada: ' . $c->shortname);
    }
}
ksort($pfx);

mtrace("Selesai. Terhapus: $deleted | gagal: $failed | lama tersisa: $left_old | skema baru: $keep");
foreach ($pfx as $k => $n) {
    mtrace("  $k : $n");
}
