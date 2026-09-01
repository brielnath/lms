<?php
defined('MOODLE_INTERNAL') || die();

function xmldb_block_ush_kaprodi_dosen_install() {
    global $CFG;
    require_once($CFG->dirroot . '/blocks/ush_kaprodi_dosen/lib.php');
    block_ush_kaprodi_dosen_setup_dashboard();
}
