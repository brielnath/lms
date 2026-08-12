<?php
define('CLI_SCRIPT', true);
@error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);
require(__DIR__ . '/../../config.php');

mtrace("=== CHECK THEME REMUI CONFIG ===");
$configs = $DB->get_records_select('config_plugins', "plugin LIKE '%remui%' OR plugin LIKE '%theme%'");
foreach ($configs as $c) {
    if (strpos(strtolower($c->value), 'monash') !== false || strpos(strtolower($c->name), 'frontpage') !== false || strpos(strtolower($c->name), 'slide') !== false) {
        mtrace("Plugin: {$c->plugin} | Name: {$c->name} | Value: " . substr($c->value, 0, 100));
    }
}

// Check site summary / frontpage description
mtrace("\n=== SITE SUMMARY ===");
$site = $DB->get_record('course', ['id' => 1]);
mtrace("Site Fullname: " . $site->fullname);
mtrace("Site Summary: " . $site->summary);
