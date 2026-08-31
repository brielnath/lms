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

/**
 * Academic calendar dashboard block.
 *
 * @package   block_ush_calendar
 * @copyright 2026 Universitas Sugeng Hartono
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

class block_ush_calendar extends block_base {

    public function init() {
        $this->title = get_string('pluginname', 'block_ush_calendar');
    }

    public function hide_header() {
        return true;
    }

    public function instance_allow_multiple() {
        return false;
    }

    public function applicable_formats() {
        return ['my' => true];
    }

    public function get_content() {
        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new stdClass();
        $this->content->footer = '';
        $view = new \block_ush_calendar\local\view();
        $this->content->text = $this->page->get_renderer('core')->render_from_template(
            'block_ush_calendar/main',
            $view->export()
        );
        return $this->content;
    }
}
