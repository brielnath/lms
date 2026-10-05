<?php
defined('MOODLE_INTERNAL') || die();

function xmldb_block_ush_matkul_dosen_upgrade($oldversion) {
    global $CFG, $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026100501) {
        require_once($CFG->dirroot . '/blocks/ush_matkul_dosen/lib.php');
        block_ush_matkul_dosen_setup_dashboard();
        upgrade_plugin_savepoint(true, 2026100501, 'block', 'ush_matkul_dosen');
    }

    if ($oldversion < 2026100502) {
        $table = new xmldb_table('block_ush_matkul_dosen');
        $field = new xmldb_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'periode');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_plugin_savepoint(true, 2026100502, 'block', 'ush_matkul_dosen');
    }

    return true;
}
