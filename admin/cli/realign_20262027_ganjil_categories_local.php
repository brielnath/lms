<?php
/**
 * Rapikan folder TA 2026/2027 Ganjil di LMS lokal sesuai skema kode MK resmi.
 * Termasuk pisah IDM08 ke MBI. Production ditolak.
 */
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once(__DIR__ . '/ush_course_owner.php');

if (strpos($CFG->wwwroot, 'localhost') === false && strpos($CFG->wwwroot, '127.0.0.1') === false) {
    mtrace('Dibatalkan: bukan LMS lokal. wwwroot=' . $CFG->wwwroot);
    exit(1);
}

$SEMESTER_LABEL = '2026/2027 - Ganjil';
$SHORT_SUFFIX = '20262027Ganjil';
$labels = ush_prodi_labels();

$parent = $DB->get_record('course_categories', ['idnumber' => 'TA_2026_2027___Ganjil']);
if (!$parent) {
    $parent = $DB->get_record_sql(
        "SELECT * FROM {course_categories} WHERE name = :n",
        ['n' => 'TA ' . $SEMESTER_LABEL]
    );
}
if (!$parent) {
    mtrace('Kategori induk TA 2026/2027 - Ganjil tidak ditemukan.');
    exit(1);
}

$courses = $DB->get_records_sql(
    "SELECT id, shortname, category FROM {course}
      WHERE id > 1 AND " . $DB->sql_like('shortname', ':p') . "
      ORDER BY shortname",
    ['p' => '%' . $SHORT_SUFFIX]
);

$needed = [];
foreach ($courses as $course) {
    $needed[ush_prodi_from_code($course->shortname)] = true;
}
$needed['MKU'] = true;

$cat_ids = [];
foreach ($labels as $kode => $nama) {
    $cat_name = "$nama ($kode) - $SEMESTER_LABEL";
    $idn = "CAT_{$kode}_" . str_replace(['/', ' ', '-'], '_', $SEMESTER_LABEL);
    $existing = $DB->get_record('course_categories', ['idnumber' => $idn]);
    if (!$existing) {
        $existing = $DB->get_record('course_categories', ['name' => $cat_name]);
    }
    if (!$existing && $kode === 'HKM') {
        $existing = $DB->get_record('course_categories', [
            'name' => "Hukum (HKM) - $SEMESTER_LABEL",
        ]);
        if ($existing) {
            $existing->name = $cat_name;
            $DB->update_record('course_categories', $existing);
            mtrace("Rename: Hukum → $cat_name");
        }
    }
    if (!$existing && empty($needed[$kode])) {
        continue;
    }
    if (!$existing) {
        $created = core_course_category::create((object) [
            'name' => $cat_name,
            'idnumber' => $idn,
            'parent' => (int) $parent->id,
            'descriptionformat' => FORMAT_PLAIN,
        ]);
        $cat_ids[$kode] = (int) $created->id;
        mtrace("Folder baru: $cat_name");
    } else {
        $cat_ids[$kode] = (int) $existing->id;
        if (empty($existing->idnumber)) {
            $existing->idnumber = $idn;
            $DB->update_record('course_categories', $existing);
        }
    }
}

$moved = 0;
$same = 0;
$buckets = [];
foreach ($courses as $course) {
    $prodi = ush_prodi_from_code($course->shortname);
    $dest = $cat_ids[$prodi] ?? $cat_ids['MKU'];
    if ((int) $course->category === $dest) {
        $same++;
        continue;
    }
    $buckets[$dest][] = (int) $course->id;
}
foreach ($buckets as $dest => $ids) {
    move_courses($ids, $dest);
    $moved += count($ids);
}

fix_course_sortorder();
rebuild_course_cache(0, true);
core_course_category::resort_categories_cleanup(true);

mtrace('');
mtrace("Selesai rapikan $SEMESTER_LABEL di " . $CFG->wwwroot);
mtrace("  Dipindah: $moved | sudah benar: $same | total: " . count($courses));
foreach ($labels as $kode => $nama) {
    if (empty($cat_ids[$kode])) {
        continue;
    }
    $n = $DB->count_records('course', ['category' => $cat_ids[$kode]]);
    mtrace("  $kode ($nama): $n");
}

$idm08 = $DB->get_records_sql(
    "SELECT c.shortname, cat.name AS catname
       FROM {course} c
       JOIN {course_categories} cat ON cat.id = c.category
      WHERE " . $DB->sql_like('c.shortname', ':p') . "
      ORDER BY c.shortname",
    ['p' => 'IDM08%20262027Ganjil']
);
mtrace('');
mtrace('Cek IDM08 2026/2027:');
foreach ($idm08 as $r) {
    mtrace("  {$r->shortname} → {$r->catname}");
}
