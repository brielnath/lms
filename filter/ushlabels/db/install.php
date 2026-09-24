<?php
defined('MOODLE_INTERNAL') || die();

function xmldb_filter_ushlabels_install() {
    global $CFG;
    require_once($CFG->libdir . '/filterlib.php');
    filter_set_global_state('ushlabels', TEXTFILTER_ON);
    filter_set_applies_to_strings('ushlabels', true);
}
