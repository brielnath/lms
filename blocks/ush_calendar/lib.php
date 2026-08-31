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
 * Library for the academic calendar block.
 *
 * @package   block_ush_calendar
 * @copyright 2026 Universitas Sugeng Hartono
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Add the academic calendar block to the default dashboard and existing user dashboards.
 */
function block_ush_calendar_setup_dashboard(): void {
    global $DB, $CFG;

    require_once($CFG->libdir . '/blocklib.php');

    $DB->set_field('block', 'visible', 1, ['name' => 'ush_calendar']);

    $systemcontext = context_system::instance();
    $now = time();

    $subpagepattern = null;
    if ($defaultmypage = $DB->get_record('my_pages', ['userid' => null, 'name' => '__default', 'private' => 1], '*', IGNORE_MULTIPLE)) {
        $subpagepattern = (string) $defaultmypage->id;
    }

    $exists = $DB->record_exists('block_instances', [
        'blockname' => 'ush_calendar',
        'pagetypepattern' => 'my-index',
        'parentcontextid' => $systemcontext->id,
    ]);

    if (!$exists) {
        $page = new moodle_page();
        $page->set_context($systemcontext);
        $page->blocks->add_region('content');
        $page->blocks->add_block('ush_calendar', 'content', -5, false, 'my-index', $subpagepattern);
    } else {
        $DB->execute(
            "UPDATE {block_instances}
                SET defaultregion = :region, defaultweight = :weight, timemodified = :now
              WHERE blockname = :blockname
                AND pagetypepattern = :pagetype
                AND parentcontextid = :ctxid",
            [
                'region' => 'content',
                'weight' => -5,
                'now' => $now,
                'blockname' => 'ush_calendar',
                'pagetype' => 'my-index',
                'ctxid' => $systemcontext->id,
            ]
        );
    }

    // Add to dashboards that users already opened (cloned from default).
    $sql = "SELECT DISTINCT bi.parentcontextid, bi.subpagepattern
              FROM {block_instances} bi
             WHERE bi.pagetypepattern = :pagetype
               AND bi.blockname = :overview
               AND bi.parentcontextid <> :sysctx
               AND NOT EXISTS (
                    SELECT 1
                      FROM {block_instances} cal
                     WHERE cal.blockname = :calendar
                       AND cal.pagetypepattern = bi.pagetypepattern
                       AND cal.parentcontextid = bi.parentcontextid
               )";
    $existing = $DB->get_recordset_sql($sql, [
        'pagetype' => 'my-index',
        'overview' => 'myoverview',
        'sysctx' => $systemcontext->id,
        'calendar' => 'ush_calendar',
    ]);

    $blockinstances = [];
    foreach ($existing as $record) {
        $blockinstances[] = [
            'blockname' => 'ush_calendar',
            'parentcontextid' => $record->parentcontextid,
            'showinsubcontexts' => 0,
            'pagetypepattern' => 'my-index',
            'subpagepattern' => $record->subpagepattern,
            'defaultregion' => 'content',
            'defaultweight' => -5,
            'configdata' => '',
            'timecreated' => $now,
            'timemodified' => $now,
        ];
        if (count($blockinstances) >= 500) {
            $DB->insert_records('block_instances', $blockinstances);
            $blockinstances = [];
        }
    }
    $existing->close();

    if (!empty($blockinstances)) {
        $DB->insert_records('block_instances', $blockinstances);
    }

    // Ensure block contexts exist.
    $newblocks = $DB->get_records('block_instances', ['blockname' => 'ush_calendar']);
    foreach ($newblocks as $instance) {
        context_block::instance($instance->id);
    }
}
