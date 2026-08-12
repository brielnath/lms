<?php
define('CLI_SCRIPT', true);
@error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);
require(__DIR__ . '/../../config.php');

mtrace("==================================================");
mtrace("🚀 MEMASANG SERVER-SIDE DOSEN BANNER DI THEME ACADEMI");
mtrace("==================================================");

// 1. Update layoutdata.php
$layoutdata_path = $CFG->dirroot . '/theme/academi/layout/includes/layoutdata.php';
$layoutdata_content = file_get_contents($layoutdata_path);

$php_code_to_add = <<<PHP

// --- USH DOSEN SERVER-SIDE BANNER ENGINE ---
$ush_banner_html = '';
$ush_is_dosen = false;

if (isloggedin() && !isguestuser()) {
    $uname = strtolower($USER->username ?? '');
    $fname = trim(($USER->firstname ?? '') . ' ' . ($USER->lastname ?? ''));
    
    if (str_starts_with($uname, 'dosen_') || 
        str_contains(strtolower($fname), 'dosen') || 
        str_contains(strtolower($fname), 'yulaikha') || 
        str_contains(strtolower($fname), 'dr.') || 
        str_contains(strtolower($fname), 'm.kom') || 
        str_contains(strtolower($fname), 's.e') || 
        str_contains(strtolower($fname), 'm.m') || 
        str_contains(strtolower($fname), 'm.sc') || 
        str_contains(strtolower($fname), 'm.gz') || 
        str_contains(strtolower($fname), 'm.h') || 
        str_contains(strtolower($fname), 'ph.d')) {
        
        $ush_is_dosen = true;
        $dosen_fullname = $fname;
        $dash_url = $CFG->wwwroot . '/my/';

        if ($PAGE->pagetype === 'my-index') {
            $ush_banner_html = '
            <div class="ush-dosen-hero-server" style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #0284c7 100%); border-radius: 16px; padding: 26px 30px; color: #ffffff; margin: 15px 0 25px 0; box-shadow: 0 12px 35px rgba(15, 23, 42, 0.28); border-left: 6px solid #38bdf8; font-family: sans-serif;">
                <span style="background: #0284c7; color: #ffffff; font-size: 11px; font-weight: 800; padding: 4px 14px; border-radius: 20px; text-transform: uppercase; letter-spacing: 0.8px; display: inline-block; margin-bottom: 12px;">👨‍🏫 PORTAL DOSEN PENGAMPU USH</span>
                <h2 style="font-size: 24px; font-weight: 800; color: #ffffff; margin: 0 0 8px 0;">Selamat Datang, Ibu ' . htmlspecialchars($dosen_fullname) . '! 👋</h2>
                <p style="font-size: 13.5px; color: #cbd5e1; margin: 0 0 20px 0; line-height: 1.6;">Workspace Pengajaran Digital Universitas Sugeng Hartono. Kelola jadwal perkuliahan, evaluasi tugas mahasiswa, serta rekapitulasi presensi terintegrasi SIAKAD.</p>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 12px; margin-bottom: 20px;">
                    <div style="background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.18); border-radius: 12px; padding: 12px 14px; display: flex; align-items: center; gap: 12px;">
                        <span style="font-size: 22px; background: rgba(56, 189, 248, 0.2); width: 42px; height: 42px; border-radius: 10px; display: flex; align-items: center; justify-content: center; color: #38bdf8;">📚</span>
                        <div><div style="font-size: 15px; font-weight: 700; color: #ffffff;">Mengampu Kelas</div><div style="font-size: 10.5px; color: #94a3b8; text-transform: uppercase;">Terverifikasi SIAKAD</div></div>
                    </div>
                    <div style="background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.18); border-radius: 12px; padding: 12px 14px; display: flex; align-items: center; gap: 12px;">
                        <span style="font-size: 22px; background: rgba(56, 189, 248, 0.2); width: 42px; height: 42px; border-radius: 10px; display: flex; align-items: center; justify-content: center; color: #38bdf8;">📝</span>
                        <div><div style="font-size: 15px; font-weight: 700; color: #ffffff;">Input Nilai & Kuis</div><div style="font-size: 10.5px; color: #94a3b8; text-transform: uppercase;">Fitur Dosen</div></div>
                    </div>
                    <div style="background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.18); border-radius: 12px; padding: 12px 14px; display: flex; align-items: center; gap: 12px;">
                        <span style="font-size: 22px; background: rgba(56, 189, 248, 0.2); width: 42px; height: 42px; border-radius: 10px; display: flex; align-items: center; justify-content: center; color: #38bdf8;">📄</span>
                        <div><div style="font-size: 15px; font-weight: 700; color: #ffffff;">Dokumen RPS</div><div style="font-size: 10.5px; color: #94a3b8; text-transform: uppercase;">Resmi Kampus</div></div>
                    </div>
                    <div style="background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.18); border-radius: 12px; padding: 12px 14px; display: flex; align-items: center; gap: 12px;">
                        <span style="font-size: 22px; background: rgba(56, 189, 248, 0.2); width: 42px; height: 42px; border-radius: 10px; display: flex; align-items: center; justify-content: center; color: #38bdf8;">📊</span>
                        <div><div style="font-size: 15px; font-weight: 700; color: #ffffff;">Kurikulum OBE</div><div style="font-size: 10.5px; color: #94a3b8; text-transform: uppercase;">Standar USH</div></div>
                    </div>
                </div>
                <div style="display: flex; flex-wrap: wrap; gap: 10px;">
                    <a href="https://siakad.sugenghartono.ac.id" target="_blank" style="background: #ffffff; border: 1px solid #cbd5e1; padding: 9px 16px; border-radius: 30px; color: #0f172a; font-weight: 700; font-size: 12.5px; text-decoration: none;">🏛️ Input Nilai SIAKAD Dosen</a>
                    <a href="' . $dash_url . 'courses.php" style="background: #ffffff; border: 1px solid #cbd5e1; padding: 9px 16px; border-radius: 30px; color: #0f172a; font-weight: 700; font-size: 12.5px; text-decoration: none;">📚 Kelas Pengajaran Dosen</a>
                    <a href="https://library.sugenghartono.ac.id" target="_blank" style="background: #ffffff; border: 1px solid #cbd5e1; padding: 9px 16px; border-radius: 30px; color: #0f172a; font-weight: 700; font-size: 12.5px; text-decoration: none;">📖 E-Library & Jurnal Riset</a>
                    <a href="https://sugenghartono.ac.id/contact" target="_blank" style="background: #ffffff; border: 1px solid #cbd5e1; padding: 9px 16px; border-radius: 30px; color: #0f172a; font-weight: 700; font-size: 12.5px; text-decoration: none;">💬 Helpdesk Dosen</a>
                </div>
            </div>';
        } else if ($PAGE->pagetype === 'site-index') {
            $ush_banner_html = '
            <div class="ush-dosen-home-server" style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #0369a1 100%); border-radius: 18px; padding: 30px 35px; color: #ffffff; margin: 20px 0 30px 0; box-shadow: 0 15px 40px rgba(15, 23, 42, 0.3); border-left: 8px solid #38bdf8; font-family: sans-serif;">
                <span style="background: #0284c7; color: #ffffff; font-size: 11.5px; font-weight: 800; padding: 4px 14px; border-radius: 20px; text-transform: uppercase; letter-spacing: 0.8px; display: inline-block; margin-bottom: 12px;">🏛️ PORTAL AKADEMIK DOSEN USH</span>
                <h1 style="font-size: 26px; font-weight: 800; color: #ffffff; margin: 0 0 10px 0;">Portal Pengajaran & Riset Dosen Universitas Sugeng Hartono</h1>
                <p style="font-size: 14px; color: #cbd5e1; margin: 0 0 20px 0; line-height: 1.6;">Selamat datang Ibu ' . htmlspecialchars($dosen_fullname) . ' di Beranda Utama Pembelajaran Dosen. Akses panduan kurikulum OBE, portal input nilai SIAKAD, repositori RPS digital, serta jurnal riset internasional secara terpadu.</p>
                <div style="display: flex; flex-wrap: wrap; gap: 10px;">
                    <a href="' . $dash_url . '" style="background: #ffffff; border: 1px solid #cbd5e1; padding: 10px 20px; border-radius: 30px; color: #0f172a; font-weight: 700; font-size: 13px; text-decoration: none;">📊 Masuk ke Dashboard Dosen</a>
                    <a href="https://siakad.sugenghartono.ac.id" target="_blank" style="background: #ffffff; border: 1px solid #cbd5e1; padding: 10px 20px; border-radius: 30px; color: #0f172a; font-weight: 700; font-size: 13px; text-decoration: none;">🏛️ Portal SIAKAD Dosen</a>
                    <a href="https://library.sugenghartono.ac.id" target="_blank" style="background: #ffffff; border: 1px solid #cbd5e1; padding: 10px 20px; border-radius: 30px; color: #0f172a; font-weight: 700; font-size: 13px; text-decoration: none;">📖 Perpustakaan & E-Journal</a>
                </div>
            </div>';
        }
    }
}

$templatecontext += [
    'ush_banner_html' => $ush_banner_html,
    'ush_is_dosen' => $ush_is_dosen,
];
PHP;

if (strpos($layoutdata_content, 'ush_banner_html') === false) {
    // Add before $templatecontext += [
    $layoutdata_content = str_replace(
        '$templatecontext += [',
        $php_code_to_add . "\n\n\$templatecontext += [",
        $layoutdata_content
    );
    file_put_contents($layoutdata_path, $layoutdata_content);
    mtrace("  ✅ Updated theme_academi/layout/includes/layoutdata.php");
}

// 2. Update drawers.mustache to render {{{ ush_banner_html }}}
$drawers_path = $CFG->dirroot . '/theme/academi/templates/drawers.mustache';
$drawers_content = file_get_contents($drawers_path);

if (strpos($drawers_content, 'ush_banner_html') === false) {
    $drawers_content = str_replace(
        '<section id="region-main" aria-label="{{#str}}content{{/str}}">',
        "<section id=\"region-main\" aria-label=\"{{#str}}content{{/str}}\">\n                            {{{ ush_banner_html }}}",
        $drawers_content
    );
    file_put_contents($drawers_path, $drawers_content);
    mtrace("  ✅ Updated theme_academi/templates/drawers.mustache");
}

// 3. Update frontpage.mustache to render {{{ ush_banner_html }}}
$frontpage_path = $CFG->dirroot . '/theme/academi/templates/frontpage.mustache';
$frontpage_content = file_get_contents($frontpage_path);

if (strpos($frontpage_content, 'ush_banner_html') === false) {
    $frontpage_content = str_replace(
        '<section id="region-main" aria-label="{{#str}}content{{/str}}">',
        "<section id=\"region-main\" aria-label=\"{{#str}}content{{/str}}\">\n                            {{{ ush_banner_html }}}",
        $frontpage_content
    );
    file_put_contents($frontpage_path, $frontpage_content);
    mtrace("  ✅ Updated theme_academi/templates/frontpage.mustache");
}

rebuild_course_cache(0, true);
purge_all_caches();

mtrace("==================================================");
mtrace("🎉 SERVER-SIDE DOSEN BANNER ENGINE BERHASIL DIPASANG!");
mtrace("==================================================");
