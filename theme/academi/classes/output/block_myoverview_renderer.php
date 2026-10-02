<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace theme_academi\output;

use block_myoverview\output\main;

/**
 * Course overview renderer: "Kursusku" only lists courses of the active semester,
 * while the Dashboard keeps every course.
 *
 * @package    theme_academi
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_myoverview_renderer extends \block_myoverview\output\renderer {

    /**
     * Render the course overview.
     *
     * @param main $main
     * @return string
     */
    public function render_main(main $main) {
        global $USER, $CFG;
        require_once($CFG->dirroot . '/theme/academi/lib.php');

        if ($this->page->pagelayout !== 'mycourses' || !count(enrol_get_all_users_courses($USER->id, true))) {
            return parent::render_main($main);
        }
        $semester = theme_academi_ush_active_semester();
        if (!$semester) {
            return parent::render_main($main);
        }

        $data = $main->export_for_template($this);
        $data['grouping'] = 'customfield';
        $data['customfieldname'] = theme_academi_ush_sync_semester_field();
        $data['customfieldvalue'] = $semester->idnumber;
        $data['displaygroupingselector'] = false;

        $notice = \html_writer::div(
            'Menampilkan mata kuliah semester aktif: <strong>' . s($semester->name) . '</strong>. '
                . 'Semua mata kuliah (semester lain) ada di '
                . \html_writer::link(new \moodle_url('/my/'), 'Dasbor') . '.',
            'alert alert-info py-2 mb-2 ush-mycourses-semester'
        );
        return $notice . $this->render_from_template('block_myoverview/main', $data);
    }
}
