<?php
/**
 * Cek agregasi gradebook satu kelas (lokal).
 * Pakai: php admin/cli/ush_check_gradebook_local.php SHORTNAME
 */
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/gradelib.php');
require_once($CFG->libdir . '/grade/constants.php');
require_once($CFG->libdir . '/grade/grade_category.php');
require_once($CFG->libdir . '/grade/grade_item.php');

if (strpos($CFG->wwwroot, 'localhost') === false && strpos($CFG->wwwroot, '127.0.0.1') === false) {
    mtrace('Dibatalkan: bukan LMS lokal.');
    exit(1);
}

$shortname = $argv[1] ?? 'IDM0629_20262027Ganjil';
$course = $DB->get_record('course', ['shortname' => $shortname]);
if (!$course) {
    mtrace("Kelas $shortname tidak ada.");
    exit(1);
}

$aggmap = [
    GRADE_AGGREGATE_MEAN => 'Mean',
    GRADE_AGGREGATE_WEIGHTED_MEAN => 'Weighted mean',
    GRADE_AGGREGATE_WEIGHTED_MEAN2 => 'Weighted mean2',
    GRADE_AGGREGATE_MEDIAN => 'Median',
    GRADE_AGGREGATE_SUM => 'Natural (sum)',
    GRADE_AGGREGATE_MODE => 'Mode',
];
if (defined('GRADE_AGGREGATE_LOWEST')) {
    $aggmap[GRADE_AGGREGATE_LOWEST] = 'Lowest';
}
if (defined('GRADE_AGGREGATE_HIGHEST')) {
    $aggmap[GRADE_AGGREGATE_HIGHEST] = 'Highest';
}

mtrace("Kelas: {$course->shortname} (id={$course->id})");
$coursecat = grade_category::fetch_course_category($course->id);
$courseitem = grade_item::fetch(['courseid' => $course->id, 'itemtype' => 'course']);
mtrace('Course aggregation: ' . ($aggmap[(int)$coursecat->aggregation] ?? $coursecat->aggregation));
mtrace('Course total max: ' . $courseitem->grademax);

$cats = grade_category::fetch_all(['courseid' => $course->id]);
foreach ($cats ?: [] as $cat) {
    if ((int)$cat->id === (int)$coursecat->id) {
        continue;
    }
    $ci = $cat->load_grade_item();
    $agg = $aggmap[(int)$cat->aggregation] ?? $cat->aggregation;
    mtrace("  Cat [{$cat->fullname}] agg={$agg} weight={$ci->aggregationcoef} max={$ci->grademax}");
}

$items = grade_item::fetch_all(['courseid' => $course->id, 'itemtype' => 'mod']);
mtrace('Mod items: ' . count($items ?: []));
foreach ($items ?: [] as $item) {
    $parent = grade_category::fetch(['id' => $item->categoryid]);
    $pname = $parent ? $parent->get_name() : '?';
    mtrace("  - {$item->itemname} ({$item->itemmodule}) cat={$pname} max={$item->grademax}");
}

// Sample course totals (anonymous: userid + finalgrade only).
$grades = $DB->get_records_sql(
    "SELECT userid, finalgrade, rawgrademax
       FROM {grade_grades}
      WHERE itemid = ?
        AND finalgrade IS NOT NULL
   ORDER BY finalgrade DESC
      LIMIT 5",
    [$courseitem->id]
);
mtrace('Sample course totals:');
foreach ($grades as $g) {
    mtrace("  userid={$g->userid} final={$g->finalgrade} max={$g->rawgrademax}");
}
