<?php
define('CLI_SCRIPT', true);
@error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);
require(__DIR__ . '/../../config.php');

mtrace("==================================================");
mtrace("🛡️ MEMATIKAN DEBUG DISPLAY UNTUK TAMPILAN BERSIH");
mtrace("==================================================");

set_config('debug', 0);
set_config('debugdisplay', 0);
set_config('debugdeveloper', 0);

rebuild_course_cache(0, true);
purge_all_caches();

mtrace("✅ Debug display berhasil dimatikan. Tampilan Moodle 100% bersih!");
mtrace("==================================================");
