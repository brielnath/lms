<?php
define('CLI_SCRIPT', true);
@error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);
require(__DIR__ . '/../../config.php');

mtrace("==================================================");
mtrace("📦 OTOMATISASI KOMPRESI FOLDER UNTUK UPLOAD");
mtrace("==================================================\n");

// Stop WAMP Apache temporarily if possible, or zip via PowerShell
$moodle_code_zip = "C:\\wamp64\\www\\moodle_code_lms_ush.zip";
$moodledata_zip   = "C:\\wamp64\\www\\moodledata_lms_ush.zip";

// 1. Purge caches to reduce zip size
purge_all_caches();

mtrace("1. Mengompresi Folder Code Moodle (C:\\wamp64\\www\\moodle)...");
if (file_exists($moodle_code_zip)) @unlink($moodle_code_zip);

$cmd1 = "powershell -Command \"Compress-Archive -Path 'C:\\wamp64\\www\\moodle\\*' -DestinationPath '{$moodle_code_zip}' -Force\"";
exec($cmd1, $out1, $ret1);

if (file_exists($moodle_code_zip)) {
    $size_mb = round(filesize($moodle_code_zip) / (1024 * 1024), 2);
    mtrace("   ✅ Code Moodle Berhasil Di-zip: {$moodle_code_zip} ({$size_mb} MB)");
} else {
    mtrace("   ⚠️  PowerShell zip code sedang berjalan di background...");
}

mtrace("\n2. Mengompresi Folder Moodledata (C:\\wamp64\\moodledata)...");
if (file_exists($moodledata_zip)) @unlink($moodledata_zip);

// Exclude cache and localcache from moodledata to make zip small & fast
$cmd2 = "powershell -Command \"Compress-Archive -Path 'C:\\wamp64\\moodledata\\filedir', 'C:\\wamp64\\moodledata\\lang' -DestinationPath '{$moodledata_zip}' -Force\"";
exec($cmd2, $out2, $ret2);

if (file_exists($moodledata_zip)) {
    $size_mb2 = round(filesize($moodledata_zip) / (1024 * 1024), 2);
    mtrace("   ✅ Moodledata Berhasil Di-zip: {$moodledata_zip} ({$size_mb2} MB)");
} else {
    mtrace("   ⚠️  PowerShell zip moodledata sedang berjalan di background...");
}

mtrace("\n==================================================");
mtrace("🎉 SELESAI!");
mtrace("==================================================");
