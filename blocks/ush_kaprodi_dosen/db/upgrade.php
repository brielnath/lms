<?php
defined('MOODLE_INTERNAL') || die();

function xmldb_block_ush_kaprodi_dosen_upgrade($oldversion) {
    global $CFG;

    if ($oldversion < 2026090100) {
        require_once($CFG->dirroot . '/blocks/ush_kaprodi_dosen/lib.php');
        block_ush_kaprodi_dosen_setup_dashboard();
        upgrade_plugin_savepoint(true, 2026090100, 'block', 'ush_kaprodi_dosen');
    }

    return true;
}
