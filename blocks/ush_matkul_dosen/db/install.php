<?php
defined('MOODLE_INTERNAL') || die();

function xmldb_block_ush_matkul_dosen_install() {
    global $CFG;
    require_once($CFG->dirroot . '/blocks/ush_matkul_dosen/lib.php');
    block_ush_matkul_dosen_setup_dashboard();
}
