<?php
define('CLI_SCRIPT', true);
@error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);
require(__DIR__ . '/../../config.php');

mtrace("==================================================");
mtrace("🔧 MEMPERBAIKI CLICKABILITY & Z-INDEX TOMBOL DOSEN");
mtrace("==================================================");

$layoutdata_path = $CFG->dirroot . '/theme/academi/layout/includes/layoutdata.php';
$content = file_get_contents($layoutdata_path);

// Ensure all links inside ush-dosen-hero-server have explicit z-index, cursor pointer and proper hrefs
$old_buttons = '<div style="display: flex; flex-wrap: wrap; gap: 10px;">
                    <a href="https://siakad.sugenghartono.ac.id" target="_blank" style="background: #ffffff; border: 1px solid #cbd5e1; padding: 9px 16px; border-radius: 30px; color: #0f172a; font-weight: 700; font-size: 12.5px; text-decoration: none;">🏛️ Input Nilai SIAKAD Dosen</a>
                    <a href="' . $dash_url . 'courses.php" style="background: #ffffff; border: 1px solid #cbd5e1; padding: 9px 16px; border-radius: 30px; color: #0f172a; font-weight: 700; font-size: 12.5px; text-decoration: none;">📚 Kelas Pengajaran Dosen</a>
                    <a href="https://library.sugenghartono.ac.id" target="_blank" style="background: #ffffff; border: 1px solid #cbd5e1; padding: 9px 16px; border-radius: 30px; color: #0f172a; font-weight: 700; font-size: 12.5px; text-decoration: none;">📖 E-Library & Jurnal Riset</a>
                    <a href="https://sugenghartono.ac.id/contact" target="_blank" style="background: #ffffff; border: 1px solid #cbd5e1; padding: 9px 16px; border-radius: 30px; color: #0f172a; font-weight: 700; font-size: 12.5px; text-decoration: none;">💬 Helpdesk Dosen</a>
                </div>';

// Replace with click-optimized buttons with z-index 99, hover transition, and cursor pointer
$new_buttons = '<div style="display: flex; flex-wrap: wrap; gap: 10px; position: relative; z-index: 9999;">
                    <a href="https://siakad.sugenghartono.ac.id" target="_blank" style="background: #ffffff; border: 1px solid #cbd5e1; padding: 9px 16px; border-radius: 30px; color: #0f172a !important; font-weight: 700; font-size: 12.5px; text-decoration: none !important; position: relative; z-index: 10000; cursor: pointer; display: inline-flex; align-items: center; gap: 6px;">🏛️ Input Nilai SIAKAD Dosen</a>
                    <a href="' . $CFG->wwwroot . '/my/courses.php" style="background: #ffffff; border: 1px solid #cbd5e1; padding: 9px 16px; border-radius: 30px; color: #0f172a !important; font-weight: 700; font-size: 12.5px; text-decoration: none !important; position: relative; z-index: 10000; cursor: pointer; display: inline-flex; align-items: center; gap: 6px;">📚 Kelas Pengajaran Dosen</a>
                    <a href="https://library.sugenghartono.ac.id" target="_blank" style="background: #ffffff; border: 1px solid #cbd5e1; padding: 9px 16px; border-radius: 30px; color: #0f172a !important; font-weight: 700; font-size: 12.5px; text-decoration: none !important; position: relative; z-index: 10000; cursor: pointer; display: inline-flex; align-items: center; gap: 6px;">📖 E-Library & Jurnal Riset</a>
                    <a href="https://sugenghartono.ac.id/contact" target="_blank" style="background: #ffffff; border: 1px solid #cbd5e1; padding: 9px 16px; border-radius: 30px; color: #0f172a !important; font-weight: 700; font-size: 12.5px; text-decoration: none !important; position: relative; z-index: 10000; cursor: pointer; display: inline-flex; align-items: center; gap: 6px;">💬 Helpdesk Dosen</a>
                </div>';

$content = str_replace($old_buttons, $new_buttons, $content);

// Also set container z-index
$content = str_replace(
    'class="ush-dosen-hero-server" style="',
    'class="ush-dosen-hero-server" style="position: relative; z-index: 999; ',
    $content
);

file_put_contents($layoutdata_path, $content);

rebuild_course_cache(0, true);
purge_all_caches();

mtrace("==================================================");
mtrace("🎉 SELESAI MEMPERBAIKI CLICKABILITY SEMUA TOMBOL!");
mtrace("==================================================");
