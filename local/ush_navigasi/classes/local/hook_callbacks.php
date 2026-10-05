<?php
namespace local_ush_navigasi\local;

defined('MOODLE_INTERNAL') || die();

use core\hook\output\before_standard_top_of_body_html_generation;
use html_writer;
use moodle_url;

class hook_callbacks {
    /** Layouts without normal course chrome (popups, embedded frames, quiz safe-exam mode, etc.). */
    private const SKIP_LAYOUTS = ['popup', 'embedded', 'frametop', 'print', 'redirect', 'maintenance', 'secure', 'login'];

    public static function before_standard_top_of_body_html_generation(
        before_standard_top_of_body_html_generation $hook
    ): void {
        global $PAGE, $OUTPUT;

        if (during_initial_install() || !isloggedin() || isguestuser()) {
            return;
        }
        $cm = $PAGE->cm;
        $contextlevel = $PAGE->context->contextlevel;
        $inactivity = $cm && $contextlevel == CONTEXT_MODULE;
        // Course-level pages (participants, grades, reports, settings...), but not the course home itself.
        // Matched by URL: participants also uses a course-view-* pagetype.
        $incoursepage = $contextlevel == CONTEXT_COURSE && $PAGE->has_set_url()
            && !str_ends_with($PAGE->url->get_path(), '/course/view.php');
        if (!$inactivity && !$incoursepage) {
            return;
        }
        if (in_array($PAGE->pagelayout, self::SKIP_LAYOUTS, true)) {
            return;
        }
        $course = $PAGE->course;
        if (empty($course->id) || $course->id == SITEID) {
            return;
        }

        $url = new moodle_url('/course/view.php', ['id' => $course->id]);
        if ($inactivity) {
            $url->set_anchor('section-' . $cm->sectionnum);
        }

        $label = $OUTPUT->pix_icon('i/previous', '') . ' ' . get_string('backtocourse', 'local_ush_navigasi');
        $button = html_writer::link($url, $label, [
            'class' => 'btn btn-secondary btn-sm',
            'title' => format_string($course->fullname),
        ]);

        // Rendered hidden at the top of <body>, then moved into the main content region once the DOM is ready.
        $html = html_writer::div($button, 'local-ush-navigasi-back d-print-none mb-3', [
            'id' => 'local-ush-navigasi-back',
            'hidden' => 'hidden',
        ]);
        $html .= html_writer::script("document.addEventListener('DOMContentLoaded', function() {
            var el = document.getElementById('local-ush-navigasi-back');
            var main = document.getElementById('region-main');
            if (!el || !main) { return; }
            main.insertBefore(el, main.firstChild);
            el.hidden = false;
        });");

        $hook->add_html($html);
    }
}
