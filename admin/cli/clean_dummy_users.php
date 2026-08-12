<?php
define('CLI_SCRIPT', true);

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/user/lib.php');

mtrace("==================================================");
mtrace("🚀 MEMBERSIHKAN AKUN DUMMY UJI COBA DI MOODLE");
mtrace("==================================================");

// Hapus akun dummy uji coba (username diawali 2401...)
$dummy_users = $DB->get_records_sql("SELECT id, username, firstname, lastname FROM {user} WHERE username LIKE '2401%' AND deleted = 0");

$count = 0;
foreach ($dummy_users as $du) {
    delete_user($du);
    mtrace("     🗑️ Akun Dummy Dihapus: {$du->username} ({$du->firstname} {$du->lastname})");
    $count++;
}

rebuild_course_cache(0, true);
purge_all_caches();

mtrace("==================================================");
mtrace("🎉 TOTAL {$count} AKUN DUMMY BERHASIL DIHAPUS!");
mtrace("==================================================");
