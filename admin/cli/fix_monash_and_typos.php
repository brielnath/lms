<?php
define('CLI_SCRIPT', true);
@error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);
require(__DIR__ . '/../../config.php');

mtrace("==================================================");
mtrace("🔧 MEMPERBAIKI TEXT 'MONASH' & TYPO NAMA MATA KULIAH");
mtrace("==================================================");

// 1. Fix typos in course names
$c14 = $DB->get_record('course', ['id' => 14]);
if ($c14) {
    $c14->fullname = 'Internet of Things (IoT)';
    $c14->shortname = 'IoT';
    $DB->update_record('course', $c14);
    mtrace("✅ Typo fixed: Course 14 -> Internet of Things (IoT)");
}

$c11 = $DB->get_record('course', ['id' => 11]);
if ($c11) {
    $c11->fullname = 'Artificial Intelligence (AI)';
    $c11->shortname = 'AI';
    $DB->update_record('course', $c11);
    mtrace("✅ Typo fixed: Course 11 -> Artificial Intelligence (AI)");
}

// 2. Update theme_academi settings to remove Monash placeholder text
$updates = [
    'promotedtitle' => 'Program Studi & Mata Kuliah Unggulan',
    'promotedcoursedesc' => 'Selamat Datang di Portal Pembelajaran Digital Universitas Sugeng Hartono. Temukan mata kuliah unggulan berstandar internasional yang dirancang untuk mempersiapkan karir global Anda di bidang Bisnis Digital, Sistem Informasi, Ilmu Gizi, Hukum, dan Bahasa.',
    'slide2caption' => 'Kurikulum Berbasis OBE & Integrasi SIAKAD',
    'slide2desc' => 'LMS USH terintegrasi langsung dengan SIAKAD kampus untuk pendaftaran mata kuliah, presensi otomatis, dan materi RPS digital.',
    'slide3caption' => 'Kampus Berwawasan Global & Multibahasa',
    'slide3desc' => 'Mendukung pembelajaran multibahasa (Bahasa Indonesia, English, & 中文 Mandarin) untuk mendorong daya saing lulusan di tingkat internasional.',
];

foreach ($updates as $name => $value) {
    set_config($name, $value, 'theme_academi');
    mtrace("✅ Updated theme_academi setting: {$name}");
}

rebuild_course_cache(0, true);
purge_all_caches();

mtrace("\n==================================================");
mtrace("🎉 SEMUA TEKS BERHASIL DIPERBAIKI SANGAT RAPI!");
mtrace("==================================================");
