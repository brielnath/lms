<?php
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/gradelib.php');
require_once($CFG->libdir . '/grade/constants.php');
require_once($CFG->libdir . '/grade/grade_category.php');
require_once($CFG->libdir . '/grade/grade_item.php');

$shortname = $argv[1] ?? 'IDM05202602_20262027Ganjil';
$course = $DB->get_record('course', ['shortname' => $shortname], '*', MUST_EXIST);
mtrace("Course {$course->shortname} id={$course->id}");

$coursecat = grade_category::fetch_course_category($course->id);
mtrace("agg={$coursecat->aggregation} onlygraded={$coursecat->aggregateonlygraded}");

$courseitem = grade_item::fetch(['courseid' => $course->id, 'itemtype' => 'course']);
mtrace("course item id={$courseitem->id} max={$courseitem->grademax} needsupdate={$courseitem->needsupdate}");

// All grade items with weights.
$items = grade_item::fetch_all(['courseid' => $course->id]);
foreach ($items ?: [] as $item) {
    if ($item->itemtype === 'course') {
        continue;
    }
    mtrace(sprintf(
        "  %-8s %-35s cat=%s coef=%.2f coef2=%.2f max=%.1f hidden=%d",
        $item->itemtype,
        mb_substr($item->get_name(), 0, 35),
        $item->categoryid,
        (float)$item->aggregationcoef,
        (float)$item->aggregationcoef2,
        (float)$item->grademax,
        (int)$item->hidden
    ));
}

// Category totals for one user who has grades.
$userids = $DB->get_fieldset_sql(
    "SELECT DISTINCT gg.userid
       FROM {grade_grades} gg
       JOIN {grade_items} gi ON gi.id = gg.itemid
      WHERE gi.courseid = ?
        AND gi.itemtype = 'mod'
        AND gg.finalgrade IS NOT NULL
      LIMIT 5",
    [$course->id]
);

foreach ($userids as $userid) {
    mtrace("User $userid:");
    foreach ($items ?: [] as $item) {
        if ($item->itemtype !== 'category' && $item->itemtype !== 'course') {
            continue;
        }
        $g = $DB->get_record('grade_grades', ['itemid' => $item->id, 'userid' => $userid]);
        $fg = $g ? $g->finalgrade : 'null';
        mtrace("  {$item->itemtype}:{$item->get_name()} = {$fg}");
    }
}
