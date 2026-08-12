<?php
define('CLI_SCRIPT', true);
@error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);
require(__DIR__ . '/../../config.php');

mtrace("=== MOODLE LANGUAGE CONFIG ===");
mtrace("Current lang: " . $CFG->lang);
mtrace("Lang menu enabled: " . get_config('core', 'langmenu'));
mtrace("Custom menu: " . get_config('core', 'custommenuitems'));

// Check installed lang packs
$langdir = $CFG->dataroot . '/lang';
mtrace("\nInstalled langpacks in dataroot/lang:");
if (is_dir($langdir)) {
    $files = scandir($langdir);
    foreach ($files as $f) {
        if ($f !== '.' && $f !== '..') {
            mtrace(" - {$f}");
        }
    }
}
