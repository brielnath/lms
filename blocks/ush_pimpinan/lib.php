<?php
// lib.php
defined('MOODLE_INTERNAL') || die();

/**
 * Pasang block monitoring pimpinan di dasbor default dan dasbor akun yang sudah ada.
 * Hanya akun pimpinan yang akan melihat isinya (block menyembunyikan diri sendiri
 * jika user bukan pimpinan), sehingga aman dipasang global.
 */
function block_ush_pimpinan_setup_dashboard(): void {
    global $DB, $CFG;

    require_once($CFG->libdir . '/blocklib.php');

    $DB->set_field('block', 'visible', 1, ['name' => 'ush_pimpinan']);

    $systemcontext   = context_system::instance();
    $now             = time();
    $subpagepattern  = null;

    if ($defaultmypage = $DB->get_record('my_pages',
            ['userid' => null, 'name' => '__default', 'private' => 1], '*', IGNORE_MULTIPLE)) {
        $subpagepattern = (string) $defaultmypage->id;
    }

    $exists = $DB->record_exists('block_instances', [
        'blockname'       => 'ush_pimpinan',
        'pagetypepattern' => 'my-index',
        'parentcontextid' => $systemcontext->id,
    ]);

    if (!$exists) {
        $page = new moodle_page();
        $page->set_context($systemcontext);
        $page->blocks->add_region('content');
        $page->blocks->add_block('ush_pimpinan', 'content', -5, false, 'my-index', $subpagepattern);
    } else {
        $DB->execute(
            "UPDATE {block_instances}
                SET defaultregion = :region, defaultweight = :weight, timemodified = :now
              WHERE blockname = :blockname
                AND pagetypepattern = :pagetype
                AND parentcontextid = :ctxid",
            [
                'region'    => 'content',
                'weight'    => -5,
                'now'       => $now,
                'blockname' => 'ush_pimpinan',
                'pagetype'  => 'my-index',
                'ctxid'     => $systemcontext->id,
            ]
        );
    }

    // Tambahkan ke dashboard user yang sudah punya myoverview tapi belum punya block ini.
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
        'sysctx'   => $systemcontext->id,
        'kblock'   => 'ush_pimpinan',
    ]);

    $blockinstances = [];
    foreach ($existing as $record) {
        $blockinstances[] = [
            'blockname'       => 'ush_pimpinan',
            'parentcontextid' => $record->parentcontextid,
            'showinsubcontexts' => 0,
            'pagetypepattern' => 'my-index',
            'subpagepattern'  => $record->subpagepattern,
            'defaultregion'   => 'content',
            'defaultweight'   => -5,
            'configdata'      => '',
            'timecreated'     => $now,
            'timemodified'    => $now,
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

    $newblocks = $DB->get_records('block_instances', ['blockname' => 'ush_pimpinan']);
    foreach ($newblocks as $instance) {
        context_block::instance($instance->id);
    }
}
