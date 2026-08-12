<?php
define('CLI_SCRIPT', true);
@error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);
require(__DIR__ . '/../../config.php');

mtrace("==================================================");
mtrace("🔍 INSPEKSI TAMPILAN DASHBOARD MOODLE (/my/)");
mtrace("==================================================");

// 1. Check default dashboard blocks setup
$systemcontext = context_system::instance();
$blocks = $DB->get_records_select('block_instances', "parentcontextid = ?", [$systemcontext->id], 'id ASC');
mtrace("📌 System Level Blocks: " . count($blocks));

// 2. Check blocks on /my/ (pagetype 'my-index')
$my_blocks = $DB->get_records_select('block_instances', "pagetypepattern = 'my-index'", null, 'id ASC');
mtrace("📌 Dashboard (/my/) Blocks: " . count($my_blocks));
foreach ($my_blocks as $b) {
    mtrace("  • Block: {$b->blockname} | Subpage: {$b->subpagepattern} | Region: {$b->defaultregion} | Visible: {$b->visible}");
}

// 3. Check Theme Academi Dashboard specific settings
mtrace("\n🎨 Theme Academi Dashboard Settings:");
$theme_settings = $DB->get_records_select('config_plugins', "plugin = 'theme_academi'");
foreach ($theme_settings as $ts) {
    if (strpos($ts->name, 'dash') !== false || strpos($ts->name, 'my') !== false || strpos($ts->name, 'block') !== false) {
        mtrace("  • {$ts->name} => {$ts->value}");
    }
}

// 4. Check user dashboard access
$admin = $DB->get_record('user', ['username' => 'admin']);
mtrace("\n👤 Admin User Dashboard Check:");
mtrace("  Username: {$admin->username} | Email: {$admin->email}");

mtrace("==================================================");
