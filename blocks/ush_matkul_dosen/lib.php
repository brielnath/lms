<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Place the block directly under Course overview on every dashboard.
 *
 * One system-wide instance (like myoverview) with the same default weight but a later id,
 * plus a matching position wherever a user has moved Course overview.
 */
function block_ush_matkul_dosen_setup_dashboard(): void {
    global $DB, $CFG;

    require_once($CFG->libdir . '/blocklib.php');

    $blockname = 'ush_matkul_dosen';
    $DB->set_field('block', 'visible', 1, ['name' => $blockname]);

    $systemcontext = context_system::instance();
    $overview = $DB->get_record('block_instances', [
        'blockname' => 'myoverview',
        'pagetypepattern' => 'my-index',
        'parentcontextid' => $systemcontext->id,
    ], '*', IGNORE_MULTIPLE);
    $region = $overview->defaultregion ?? 'content';
    $weight = (int) ($overview->defaultweight ?? 1);

    $old = $DB->get_records('block_instances', ['blockname' => $blockname], '', 'id');
    foreach ($old as $instance) {
        blocks_delete_instance($instance);
    }

    $now = time();
    $instanceid = $DB->insert_record('block_instances', (object) [
        'blockname' => $blockname,
        'parentcontextid' => $systemcontext->id,
        'showinsubcontexts' => 0,
        'requiredbytheme' => 0,
        'pagetypepattern' => 'my-index',
        'subpagepattern' => null,
        'defaultregion' => $region,
        'defaultweight' => $weight,
        'configdata' => '',
        'timecreated' => $now,
        'timemodified' => $now,
    ]);
    context_block::instance($instanceid);

    if (!$overview) {
        return;
    }
    $positions = $DB->get_records('block_positions', ['blockinstanceid' => $overview->id]);
    foreach ($positions as $pos) {
        $DB->insert_record('block_positions', (object) [
            'blockinstanceid' => $instanceid,
            'contextid' => $pos->contextid,
            'pagetype' => $pos->pagetype,
            'subpage' => $pos->subpage,
            'visible' => 1,
            'region' => $pos->region,
            'weight' => $pos->weight,
        ]);
    }
}
