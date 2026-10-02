<?php
defined('MOODLE_INTERNAL') || die();

function xmldb_local_ush_pimpinan_upgrade($oldversion) {
    if ($oldversion < 2026092901) {
        require_once(__DIR__ . '/../lib.php');
        local_ush_pimpinan_ensure_roles();
        upgrade_plugin_savepoint(true, 2026092901, 'local', 'ush_pimpinan');
    }
    return true;
}
