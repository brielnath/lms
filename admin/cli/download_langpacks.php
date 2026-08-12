<?php
define('CLI_SCRIPT', true);
@error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);
require(__DIR__ . '/../../config.php');

mtrace("==================================================");
mtrace("🌐 UNDUH & PASANG LANGPACK INDONESIA & MANDARIN");
mtrace("==================================================");

$langdir = $CFG->dataroot . '/lang';
if (!is_dir($langdir)) {
    mkdir($langdir, 0777, true);
}

$pack_urls = [
    'id' => 'https://download.moodle.org/download.php/direct/langpack/4.5/id.zip',
    'zh_cn' => 'https://download.moodle.org/download.php/direct/langpack/4.5/zh_cn.zip'
];

foreach ($pack_urls as $code => $url) {
    mtrace("Mengunduh {$code} dari {$url}...");
    $zip_file = $langdir . "/{$code}.zip";
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Moodle/4.5 (Windows NT)');
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    $data = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http == 200 && strlen($data) > 1000) {
        file_put_contents($zip_file, $data);
        
        $zip = new ZipArchive();
        if ($zip->open($zip_file) === TRUE) {
            $zip->extractTo($langdir);
            $zip->close();
            @unlink($zip_file);
            mtrace("  ✅ Sukses mengekstrak paket bahasa: {$code}");
        } else {
            mtrace("  ⚠️ Gagal membuka zip: {$code}");
        }
    } else {
        mtrace("  ❌ Gagal unduh (HTTP {$http})");
    }
}

// Enable langmenu in config
set_config('langmenu', 1);

rebuild_course_cache(0, true);
purge_all_caches();

mtrace("\n==================================================");
mtrace("🎉 SELESAI MEMASANG DUKUNGAN BAHASA!");
mtrace("==================================================");
