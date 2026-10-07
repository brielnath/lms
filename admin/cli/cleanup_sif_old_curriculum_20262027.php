<?php
/**
 * Bersihkan kurikulum SI lama di semester 2026/2027 Ganjil:
 * hapus cangkang SIF* yang kosong, pindahkan IDM06* ke kategori SIF.
 */
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/course/lib.php');

$sifcat = $DB->get_record('course_categories', [
    'idnumber' => 'CAT_SIF_2026_2027__Ganjil',
]);
if (!$sifcat) {
    $sifcat = $DB->get_record_sql(
        "SELECT * FROM {course_categories} WHERE " . $DB->sql_like('name', ':n'),
        ['n' => 'Sistem Informasi (SIF) - 2026/2027 - Ganjil']
    );
}
if (!$sifcat) {
    mtrace('Kategori SIF 2026/2027 tidak ditemukan.');
    exit(1);
}

$sifnew = $DB->get_records_sql(
    "SELECT c.id, c.shortname, c.fullname
       FROM {course} c
      WHERE c.id > 1
        AND " . $DB->sql_like('c.shortname', ':p1') . "
        AND " . $DB->sql_like('c.shortname', ':p2'),
    ['p1' => 'SIF%', 'p2' => '%20262027Ganjil']
);

$deleted = 0;
$kept = 0;
foreach ($sifnew as $course) {
    $enrols = $DB->count_records_sql(
        "SELECT COUNT(*) FROM {user_enrolments} ue
           JOIN {enrol} e ON e.id = ue.enrolid
          WHERE e.courseid = ?",
        [$course->id]
    );
    if ((int) $enrols > 0) {
        $kept++;
        mtrace("KEEP (ada mahasiswa) {$course->shortname}");
        continue;
    }
    delete_course($course->id, false);
    $deleted++;
    mtrace("HAPUS {$course->shortname}");
}

$idm06 = $DB->get_records_sql(
    "SELECT c.id, c.shortname, c.category
       FROM {course} c
      WHERE c.id > 1
        AND " . $DB->sql_like('c.shortname', ':p1') . "
        AND " . $DB->sql_like('c.shortname', ':p2'),
    ['p1' => 'IDM06%', 'p2' => '%20262027Ganjil']
);

$moved = 0;
foreach ($idm06 as $course) {
    if ((int) $course->category === (int) $sifcat->id) {
        continue;
    }
    $upd = new stdClass();
    $upd->id = $course->id;
    $upd->category = $sifcat->id;
    $DB->update_record('course', $upd);
    $moved++;
    mtrace("PINDAH {$course->shortname} -> SIF 2026/2027");
}

fix_course_sortorder();
rebuild_course_cache(0, true);

mtrace('');
mtrace("Selesai. Hapus SIF kosong: $deleted | SIF tetap (ada mhs): $kept | IDM06 dipindah ke SIF: $moved");
mtrace('Kelas SIF historis (ada mahasiswa) tidak dihapus.');
