<?php
define('CLI_SCRIPT', true);
@error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);
require(__DIR__ . '/../../config.php');

mtrace("=== COMPRESS MOODLE FOLDER VIA PHP ZIPARCHIVE ===");

$moodle_code_zip = "C:\\wamp64\\www\\moodle_code_lms_ush.zip";
$moodledata_zip  = "C:\\wamp64\\www\\moodledata_lms_ush.zip";

if (!class_exists('ZipArchive')) {
    mtrace("ZipArchive extension not loaded, enabling fallback...");
}

// Function to zip folder recursively using PHP ZipArchive
function zip_folder($source, $destination, $exclude_dirs = []) {
    if (!extension_loaded('zip') || !file_exists($source)) {
        return false;
    }

    $zip = new ZipArchive();
    if (!$zip->open($destination, ZIPARCHIVE::CREATE | ZIPARCHIVE::OVERWRITE)) {
        return false;
    }

    $source = str_replace('\\', '/', realpath($source));

    if (is_dir($source) === true) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($files as $file) {
            $file_path = str_replace('\\', '/', $file);
            
            // Check exclusion
            $skip = false;
            foreach ($exclude_dirs as $ex) {
                if (str_contains($file_path, $ex)) {
                    $skip = true;
                    break;
                }
            }
            if ($skip) continue;

            if (is_dir($file) === true) {
                $zip->addEmptyDir(str_replace($source . '/', '', $file_path . '/'));
            } else if (is_file($file) === true) {
                // Try reading file safely
                $rel = str_replace($source . '/', '', $file_path);
                @$zip->addFile($file_path, $rel);
            }
        }
    } else if (is_file($source) === true) {
        $zip->addFile($source, basename($source));
    }

    return $zip->close();
}

mtrace("1. Mengompresi Code Moodle...");
$res1 = zip_folder("C:\\wamp64\\www\\moodle", $moodle_code_zip, ['cache', 'localcache']);
if (file_exists($moodle_code_zip)) {
    $size1 = round(filesize($moodle_code_zip) / (1024 * 1024), 2);
    mtrace("   ✅ Code Moodle ZIP Selesai: {$moodle_code_zip} ({$size1} MB)");
}

mtrace("\n2. Mengompresi Moodledata (filedir & lang)...");
$res2 = zip_folder("C:\\wamp64\\moodledata", $moodledata_zip, ['cache', 'localcache', 'temp', 'trashdir', 'sessions']);
if (file_exists($moodledata_zip)) {
    $size2 = round(filesize($moodledata_zip) / (1024 * 1024), 2);
    mtrace("   ✅ Moodledata ZIP Selesai: {$moodledata_zip} ({$size2} MB)");
}

mtrace("\n🎉 DUA FILE ZIP UTAMA SUDAH SIAP DI C:\\wamp64\\www\\ !");
