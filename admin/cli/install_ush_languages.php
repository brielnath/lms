<?php
define('CLI_SCRIPT', true);
@error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);
require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

mtrace("==================================================");
mtrace("🌐 MEMASANG LANGUAGE PACK: INDONESIA & MANDARIN (ZH_CN)");
mtrace("==================================================");

$installer = new \core\task\install_langpacks();
// Or use get_string_manager
$langman = get_string_manager();

$langs_to_install = ['id', 'zh_cn'];

foreach ($langs_to_install as $lang) {
    mtrace("Mengunduh & memasang paket bahasa: {$lang}...");
    try {
        $result = $langman->install_langpack($lang);
        if ($result) {
            mtrace("  ✅ Sukses terpasang: {$lang}");
        } else {
            mtrace("  ⚠️ Gagal atau tidak merespon: {$lang}");
        }
    } catch (Exception $e) {
        mtrace("  ❌ Error: " . $e->getMessage());
    }
}

// Ensure custom menu & lang menu options
set_config('langmenu', 1);

rebuild_course_cache(0, true);
purge_all_caches();

mtrace("==================================================");
mtrace("🎉 PAKET BAHASA BERHASIL DISIAPKAN!");
mtrace("==================================================");
