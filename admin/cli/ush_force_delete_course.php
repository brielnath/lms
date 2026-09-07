<?php
/**
 * Force-hapus satu kursus di LMS lokal (CM rusak / deletioninprogress).
 */
defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->libdir . '/modinfolib.php');

function ush_repair_section_sequence(int $courseid): void {
    global $DB;
    $sections = $DB->get_records('course_sections', ['course' => $courseid]);
    foreach ($sections as $sec) {
        if ($sec->sequence === '' || $sec->sequence === null) {
            continue;
        }
        $ok = [];
        foreach (explode(',', $sec->sequence) as $cmid) {
            $cmid = (int) $cmid;
            if ($cmid && $DB->record_exists('course_modules', ['id' => $cmid, 'course' => $courseid])) {
                $ok[] = $cmid;
            }
        }
        $newseq = implode(',', $ok);
        if ($newseq !== (string) $sec->sequence) {
            $DB->set_field('course_sections', 'sequence', $newseq, ['id' => $sec->id]);
        }
    }
}

function ush_delete_leftover_mod_instances(int $courseid): void {
    global $DB;
    $modules = $DB->get_records('modules', null, '', 'id, name');
    $dbman = $DB->get_manager();
    foreach ($modules as $mod) {
        if (!$dbman->table_exists($mod->name) || !$dbman->field_exists($mod->name, 'course')) {
            continue;
        }
        $DB->delete_records($mod->name, ['course' => $courseid]);
    }
}

function ush_force_delete_course(stdClass $course): void {
    global $DB;

    $courseid = (int) $course->id;
    $DB->set_field('course_modules', 'deletioninprogress', 0, ['course' => $courseid]);
    ush_repair_section_sequence($courseid);
    rebuild_course_cache($courseid, true);

    $cms = $DB->get_records('course_modules', ['course' => $courseid]);
    foreach ($cms as $cm) {
        try {
            course_delete_module($cm->id, false);
        } catch (Throwable $e) {
            try {
                context_helper::delete_instance(CONTEXT_MODULE, $cm->id);
            } catch (Throwable $e2) {
                // Context mungkin sudah hilang.
            }
            $DB->delete_records('course_modules_completion', ['coursemoduleid' => $cm->id]);
            if ($DB->get_manager()->table_exists('course_modules_viewed')) {
                $DB->delete_records('course_modules_viewed', ['coursemoduleid' => $cm->id]);
            }
            $DB->delete_records('course_modules', ['id' => $cm->id]);
        }
    }

    ush_delete_leftover_mod_instances($courseid);
    ush_repair_section_sequence($courseid);
    rebuild_course_cache($courseid, true);

    delete_course($courseid, false);
}
