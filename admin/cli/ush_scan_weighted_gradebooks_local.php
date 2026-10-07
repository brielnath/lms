<?php
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/gradelib.php');
require_once($CFG->libdir . '/grade/constants.php');
require_once($CFG->libdir . '/grade/grade_category.php');
require_once($CFG->libdir . '/grade/grade_item.php');

// Cari kelas yang punya kategori Presensi + Tugas (setup USH / manual).
$rows = $DB->get_records_sql(
    "SELECT c.id, c.shortname, c.fullname,
            COUNT(DISTINCT gc.id) AS cats
       FROM {course} c
       JOIN {grade_categories} gc ON gc.courseid = c.id
      WHERE gc.fullname IN ('Presensi','Tugas','Kuis','Quiz','UTS','UAS')
        AND c.id > 1
   GROUP BY c.id, c.shortname, c.fullname
     HAVING cats >= 3
   ORDER BY c.id DESC
      LIMIT 30"
);
foreach ($rows as $r) {
    mtrace("---- {$r->shortname} (id={$r->id}) cats={$r->cats}");
    $coursecat = grade_category::fetch_course_category($r->id);
    $agg = (int)$coursecat->aggregation;
    $aggname = $agg === GRADE_AGGREGATE_WEIGHTED_MEAN ? 'Weighted' :
        ($agg === GRADE_AGGREGATE_SUM ? 'Natural' : "agg=$agg");
    mtrace("  course agg=$aggname");
    $cats = grade_category::fetch_all(['courseid' => $r->id]);
    foreach ($cats ?: [] as $cat) {
        if ((int)$cat->id === (int)$coursecat->id) {
            continue;
        }
        $ci = $cat->load_grade_item();
        mtrace("  [{$cat->fullname}] weight={$ci->aggregationcoef} hidden={$ci->hidden} needsupdate={$ci->needsupdate}");
    }
    $courseitem = grade_item::fetch(['courseid' => $r->id, 'itemtype' => 'course']);
    $sample = $DB->get_records_sql(
        "SELECT finalgrade FROM {grade_grades} WHERE itemid = ? AND finalgrade IS NOT NULL ORDER BY finalgrade DESC LIMIT 3",
        [$courseitem->id]
    );
    foreach ($sample as $g) {
        mtrace("  sample total={$g->finalgrade}");
    }
}
