<?php
/**
 * Susun struktur nilai akhir USH di satu kelas (lokal):
 * Presensi 10%, Tugas 20%, Kuis 10%, UTS 30%, UAS 30%.
 *
 * Pakai: php admin/cli/setup_ush_gradebook_local.php SHORTNAME
 */
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/gradelib.php');
require_once($CFG->libdir . '/grade/constants.php');
require_once($CFG->libdir . '/grade/grade_category.php');
require_once($CFG->libdir . '/grade/grade_item.php');

if (strpos($CFG->wwwroot, 'localhost') === false && strpos($CFG->wwwroot, '127.0.0.1') === false) {
    mtrace('Dibatalkan: bukan LMS lokal. wwwroot=' . $CFG->wwwroot);
    exit(1);
}

$shortname = $argv[1] ?? 'IDM0629_20262027Ganjil';
$course = $DB->get_record('course', ['shortname' => $shortname]);
if (!$course) {
    mtrace("Kelas $shortname tidak ada.");
    exit(1);
}
mtrace("Kelas: {$course->shortname} — {$course->fullname}");

// Bobot resmi USH.
$SCHEME = [
    'Presensi' => 0.10,
    'Tugas' => 0.20,
    'Kuis' => 0.10,
    'UTS' => 0.30,
    'UAS' => 0.30,
];

// Pastikan aktivitas Attendance punya kolom nilai.
$attmodid = $DB->get_field('modules', 'id', ['name' => 'attendance']);
if ($attmodid) {
    $cms = $DB->get_records('course_modules', [
        'course' => $course->id,
        'module' => $attmodid,
        'deletioninprogress' => 0,
    ]);
    foreach ($cms as $cm) {
        $att = $DB->get_record('attendance', ['id' => $cm->instance]);
        if ($att && (int) $att->grade === 0) {
            $DB->set_field('attendance', 'grade', 100, ['id' => $att->id]);
            $att->grade = 100;
            mtrace('  Presensi diberi nilai maksimum 100');
        }
        if ($att) {
            $att->cmidnumber = $cm->idnumber ?? '';
            $att->coursemodule = $cm->id;
            if (function_exists('attendance_grade_item_update')) {
                attendance_grade_item_update($att);
            }
        }
    }
}

// Weighted mean di tingkat kelas supaya nilai akhir selalu 0–100,
// tidak ikut berubah saat dosen menambah aktivitas.
$coursecat = grade_category::fetch_course_category($course->id);
if ((int) $coursecat->aggregation !== GRADE_AGGREGATE_WEIGHTED_MEAN) {
    $coursecat->aggregation = GRADE_AGGREGATE_WEIGHTED_MEAN;
    $coursecat->update();
    mtrace('  Agregasi kelas diset ke Weighted mean of grades');
}

// Buat / ambil 5 kategori nilai.
$catids = [];
foreach (array_keys($SCHEME) as $name) {
    $existing = grade_category::fetch([
        'courseid' => $course->id,
        'fullname' => $name,
        'parent' => $coursecat->id,
    ]);
    if (!$existing) {
        $cat = new grade_category([
            'courseid' => $course->id,
            'fullname' => $name,
            'parent' => $coursecat->id,
            // Di dalam kategori: rata-rata, jadi 4 tugas berbobot sama.
            'aggregation' => GRADE_AGGREGATE_MEAN,
        ], false);
        $cat->insert();
        $existing = grade_category::fetch(['id' => $cat->id]);
        mtrace("  Kategori dibuat: $name");
    } else {
        if ((int) $existing->aggregation !== GRADE_AGGREGATE_MEAN) {
            $existing->aggregation = GRADE_AGGREGATE_MEAN;
            $existing->update();
        }
        mtrace("  Kategori sudah ada: $name");
    }
    $catids[$name] = (int) $existing->id;
}

/**
 * Tentukan kategori tujuan dari jenis aktivitas dan namanya.
 */
function ush_target_category(string $modname, string $itemname): ?string {
    $n = mb_strtoupper($itemname);
    if (preg_match('/\bUAS\b|AKHIR SEMESTER|FINAL EXAM/u', $n)) {
        return 'UAS';
    }
    if (preg_match('/\bUTS\b|TENGAH SEMESTER|MIDTERM|MID-TERM/u', $n)) {
        return 'UTS';
    }
    if ($modname === 'attendance') {
        return 'Presensi';
    }
    if ($modname === 'quiz') {
        return 'Kuis';
    }
    if ($modname === 'assign' || $modname === 'workshop') {
        return 'Tugas';
    }
    return null;
}

$items = grade_item::fetch_all(['courseid' => $course->id, 'itemtype' => 'mod']);
$moved = 0;
foreach ($items ?: [] as $item) {
    $target = ush_target_category($item->itemmodule, (string) $item->itemname);
    if (!$target) {
        mtrace("  Dilewati (bukan komponen nilai): {$item->itemname}");
        continue;
    }
    if ((int) $item->categoryid === $catids[$target]) {
        mtrace("  Sudah di $target: {$item->itemname}");
        continue;
    }
    $item->set_parent($catids[$target]);
    $moved++;
    mtrace("  {$item->itemname} → $target");
}

// Pasang bobot pada tiap kategori.
foreach ($SCHEME as $name => $weight) {
    $cat = grade_category::fetch(['id' => $catids[$name]]);
    $catitem = $cat->load_grade_item();
    $catitem->aggregationcoef = $weight * 100;
    $catitem->aggregationcoef2 = 0;
    $catitem->weightoverride = 0;
    $catitem->grademax = 100;
    $catitem->grademin = 0;
    $catitem->update();
    mtrace('  Bobot ' . $name . ' = ' . round($weight * 100) . '%');
}

// Nilai akhir selalu skala 0–100.
// Penting: setelah ganti ke Weighted mean, Moodle kadang menyet grademax=0
// sehingga Course total tampil 0.00 meski kategori sudah terisi.
$courseitem = grade_item::fetch(['courseid' => $course->id, 'itemtype' => 'course']);
$courseitem->grademax = 100;
$courseitem->grademin = 0;
$courseitem->update();
mtrace('  Course total diset skala 0–100 (max sekarang ' . $courseitem->grademax . ')');

grade_regrade_final_grades($course->id);

mtrace('');
mtrace('Struktur nilai akhir sekarang:');
foreach ($SCHEME as $name => $weight) {
    $cat = grade_category::fetch(['id' => $catids[$name]]);
    $catitem = $cat->load_grade_item();
    mtrace('  ' . str_pad($name, 10) . round($catitem->aggregationcoef) . '%');
    $children = grade_item::fetch_all(['courseid' => $course->id, 'categoryid' => $catids[$name]]);
    foreach ($children ?: [] as $child) {
        if ($child->itemtype === 'category') {
            continue;
        }
        mtrace('      - ' . $child->get_name() . ' (' . $child->itemmodule . ', maks ' . (int) $child->grademax . ')');
    }
    if (!$children) {
        mtrace('      (belum ada aktivitas)');
    }
}
$courseitem = grade_item::fetch(['courseid' => $course->id, 'itemtype' => 'course']);
mtrace('  Course total maks: ' . (int) $courseitem->grademax);
mtrace('');
mtrace('Selesai: ' . $moved . ' item dipindahkan.');
mtrace('Lihat: ' . $CFG->wwwroot . '/grade/edit/tree/index.php?id=' . $course->id);
