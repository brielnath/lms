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
 * Upgrade steps for the academic calendar block.
 *
 * @package   block_ush_calendar
 * @copyright 2026 Universitas Sugeng Hartono
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrade the academic calendar block.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_block_ush_calendar_upgrade($oldversion) {
    global $CFG;

    if ($oldversion < 2026083102) {
        require_once($CFG->dirroot . '/blocks/ush_calendar/lib.php');
        block_ush_calendar_setup_dashboard();
        upgrade_plugin_savepoint(true, 2026083102, 'block', 'ush_calendar');
    }

    return true;
}
