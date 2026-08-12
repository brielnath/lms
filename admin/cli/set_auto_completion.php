<?php
define('CLI_SCRIPT', true);

require(__DIR__ . '/../../config.php');

mtrace("==================================================");
mtrace("🚀 MENGUBAH COMPLETION TRACKING MENJADI OTOMATIS (ON VIEW)");
mtrace("==================================================");

// Ubah semua modul agar completion = 2 (Automatic) dan completionview = 1 (Tuntas saat dilihat)
$DB->execute("UPDATE {course_modules} SET completion = 2, completionview = 1 WHERE completion > 0");

rebuild_course_cache(0, true);

mtrace("✅ [SUKSES] Sistem completion diubah ke OTOMATIS!");
mtrace("   Modul akan OTOMATIS tercentang tuntas begitu mahasiswa MEMBUKA/MEMBACA materinya!");
mtrace("==================================================");
