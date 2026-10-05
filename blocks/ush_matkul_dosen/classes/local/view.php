<?php
namespace block_ush_matkul_dosen\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Greyed-out list under Course overview: next semester's courses from SIAKAD,
 * or last year's same-period courses while SIAKAD has no data for the next semester yet.
 */
class view {

    public function export(int $userid): array {
        global $DB, $OUTPUT;

        $records = $DB->get_records('block_ush_matkul_dosen', ['userid' => $userid], 'lessonname, code', 'id, code, lessonname, tahun, periode, courseid');
        if (!$records) {
            return ['show' => false];
        }

        $first = reset($records);
        $label = $first->tahun . ' ' . $first->periode;

        $courses = [];
        foreach ($records as $r) {
            $courses[$r->code] = [
                'name' => format_string($r->lessonname),
                'code' => $r->code,
                'semester' => $label,
                'image' => $OUTPUT->get_generated_image_for_id(crc32($r->code) & 0x7fffffff),
                'url' => $r->courseid ? (new \moodle_url('/course/view.php', ['id' => $r->courseid]))->out(false) : '',
            ];
        }

        $next = semester::next(semester::active());
        $isnext = $first->tahun === $next['tahun'] && $first->periode === $next['periode'];
        $kind = $isnext ? 'next' : 'previous';

        return [
            'show' => true,
            'title' => get_string('section_' . $kind, 'block_ush_matkul_dosen', $label),
            'note' => get_string('note_' . $kind, 'block_ush_matkul_dosen'),
            'badge' => get_string('badge_' . $kind, 'block_ush_matkul_dosen'),
            'courses' => array_values($courses),
        ];
    }
}
