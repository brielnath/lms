<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Add the kaprodi lecturers block to the default dashboard and existing user dashboards.
 */
function block_ush_kaprodi_dosen_setup_dashboard(): void {
    global $DB, $CFG;

    require_once($CFG->libdir . '/blocklib.php');

    $DB->set_field('block', 'visible', 1, ['name' => 'ush_kaprodi_dosen']);

    $systemcontext = context_system::instance();
    $now = time();

    $subpagepattern = null;
    if ($defaultmypage = $DB->get_record('my_pages', ['userid' => null, 'name' => '__default', 'private' => 1], '*', IGNORE_MULTIPLE)) {
        $subpagepattern = (string) $defaultmypage->id;
    }

    $exists = $DB->record_exists('block_instances', [
        'blockname' => 'ush_kaprodi_dosen',
        'pagetypepattern' => 'my-index',
        'parentcontextid' => $systemcontext->id,
    ]);

    if (!$exists) {
        $page = new moodle_page();
        $page->set_context($systemcontext);
        $page->blocks->add_region('content');
        $page->blocks->add_block('ush_kaprodi_dosen', 'content', -4, false, 'my-index', $subpagepattern);
    } else {
        $DB->execute(
            "UPDATE {block_instances}
                SET defaultregion = :region, defaultweight = :weight, timemodified = :now
              WHERE blockname = :blockname
                AND pagetypepattern = :pagetype
                AND parentcontextid = :ctxid",
            [
                'region' => 'content',
                'weight' => -4,
                'now' => $now,
                'blockname' => 'ush_kaprodi_dosen',
                'pagetype' => 'my-index',
                'ctxid' => $systemcontext->id,
            ]
        );
    }

    $sql = "SELECT DISTINCT bi.parentcontextid, bi.subpagepattern
              FROM {block_instances} bi
             WHERE bi.pagetypepattern = :pagetype
               AND bi.blockname = :overview
               AND bi.parentcontextid <> :sysctx
               AND NOT EXISTS (
                    SELECT 1
                      FROM {block_instances} k
                     WHERE k.blockname = :kblock
                       AND k.pagetypepattern = bi.pagetypepattern
                       AND k.parentcontextid = bi.parentcontextid
               )";
    $existing = $DB->get_recordset_sql($sql, [
        'pagetype' => 'my-index',
        'overview' => 'myoverview',
        'sysctx' => $systemcontext->id,
        'kblock' => 'ush_kaprodi_dosen',
    ]);

    $blockinstances = [];
    foreach ($existing as $record) {
        $blockinstances[] = [
            'blockname' => 'ush_kaprodi_dosen',
            'parentcontextid' => $record->parentcontextid,
            'showinsubcontexts' => 0,
            'pagetypepattern' => 'my-index',
            'subpagepattern' => $record->subpagepattern,
            'defaultregion' => 'content',
            'defaultweight' => -4,
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

    $newblocks = $DB->get_records('block_instances', ['blockname' => 'ush_kaprodi_dosen']);
    foreach ($newblocks as $instance) {
        context_block::instance($instance->id);
    }
}
