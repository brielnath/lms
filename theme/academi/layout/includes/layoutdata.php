<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Columns 2 Layout
 * @package    theme_academi
 * @copyright  2015 onwards LMSACE Dev Team (http://www.lmsace.com)
 * @author    LMSACE Dev Team
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/behat/lib.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once(dirname(__FILE__) . '/themedata.php');

$preset = optional_param('preset', 0, PARAM_TEXT);
if (!empty($preset) && isset($preset)) {
    set_config('preset', $preset, 'theme_academi');
    // Purge the theme cache to show the old icons in the GUI.
    theme_reset_all_caches();
}

// Add block button in editing mode.
$addblockbutton = $OUTPUT->addblockbutton();

if (isloggedin()) {
    $courseindexopen = (get_user_preferences('drawer-open-index', true) == true);
    $blockdraweropen = (get_user_preferences('drawer-open-block') == true);
} else {
    $courseindexopen = false;
    $blockdraweropen = false;
}

// Otomatis buka sidebar (course index drawer) di sebelah kiri saat membuka halaman course
if ($PAGE->pagelayout === 'course' || $PAGE->pagelayout === 'incourse') {
    $courseindexopen = true;
}

if (defined('BEHAT_SITE_RUNNING')) {
    $blockdraweropen = true;
}

$extraclasses = ['uses-drawers'];
if ($courseindexopen) {
    $extraclasses[] = 'drawer-open-index';
}

$blockshtml = $OUTPUT->blocks('side-pre');
$hasblocks = (strpos($blockshtml, 'data-block=') !== false || !empty($addblockbutton));
if (!$hasblocks) {
    $blockdraweropen = false;
}
$courseindex = core_course_drawer();
if (!$courseindex) {
    $courseindexopen = false;
}

$themestyleheader = theme_academi_get_setting('themestyleheader');
$extraclasses[] = ($themestyleheader) ? 'theme-based-header' : 'moodle-based-header';

$forceblockdraweropen = $OUTPUT->firstview_fakeblocks();

$secondarynavigation = false;
$overflow = '';
if ($PAGE->has_secondary_navigation()) {
    $tablistnav = $PAGE->has_tablist_secondary_navigation();
    $moremenu = new \core\navigation\output\more_menu($PAGE->secondarynav, 'nav-tabs', true, $tablistnav);
    $secondarynavigation = $moremenu->export_for_template($OUTPUT);
    $overflowdata = $PAGE->secondarynav->get_overflow_menu_data();
    if (!is_null($overflowdata)) {
        $overflow = $overflowdata->export_for_template($OUTPUT);
    }
}

$primary = new core\navigation\output\primary($PAGE);
$renderer = $PAGE->get_renderer('core');
$primarymenu = $primary->export_for_template($renderer);
$buildregionmainsettings = !$PAGE->include_region_main_settings_in_header_actions() && !$PAGE->has_secondary_navigation();
// If the settings menu will be included in the header then don't add it here.
$regionmainsettingsmenu = $buildregionmainsettings ? $OUTPUT->region_main_settings_menu() : false;

$header = $PAGE->activityheader;
$headercontent = $header->export_for_template($renderer);

$coursefullname = ($PAGE->course?->fullname) ? format_string(
    $PAGE->course->fullname,
    true,
    ['context' => context_course::instance($PAGE->course->id), 'escape' => false],
) : '';
$courseurl = $PAGE->course ? new \core\url('/course/view.php', ['id' => $PAGE->course->id]) : null;

// --- USH DOSEN & KAPRODI SERVER-SIDE BANNER ENGINE ---
$ush_banner_html = '';
$ush_is_dosen = false;

if (isloggedin() && !isguestuser()) {
    global $DB;
    $uname = strtolower($USER->username ?? '');
    $fname = trim(($USER->firstname ?? '') . ' ' . ($USER->lastname ?? ''));
    
    // Check if user is Kaprodi (has Manager role in any course category)
    $is_kaprodi = false;
    try {
        if ($DB) {
            $manager_role_id = $DB->get_field('role', 'id', ['shortname' => 'manager']);
            if ($manager_role_id) {
                $is_kaprodi = $DB->record_exists_sql("
                    SELECT 1 FROM {role_assignments} ra
                    JOIN {context} ctx ON ctx.id = ra.contextid
                    WHERE ra.userid = ? AND ra.roleid = ? AND ctx.contextlevel = ?
                ", [$USER->id, $manager_role_id, CONTEXT_COURSECAT]);
            }
        }
    } catch (Exception $e) {
        $is_kaprodi = false;
    }

    if ($is_kaprodi || 
        str_starts_with($uname, 'dosen_') || 
        str_contains(strtolower($fname), 'dosen') || 
        str_contains(strtolower($fname), 'yulaikha') || 
        str_contains(strtolower($fname), 'dwi utari') || 
        str_contains(strtolower($fname), 'graceilla') || 
        str_contains(strtolower($fname), 'yuniars') || 
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

        if ($is_kaprodi) {
            // --- BANNER KHUSUS KAPRODI ---
            if ($PAGE->pagetype === 'my-index') {
                $ush_banner_html = '
                <div class="ush-kaprodi-hero-server" style="position: relative; z-index: 999; background: linear-gradient(135deg, #1e1b4b 0%, #312e81 50%, #4338ca 100%); border-radius: 16px; padding: 26px 30px; color: #ffffff; margin: 15px 0 25px 0; box-shadow: 0 14px 40px rgba(49, 46, 129, 0.35); border-left: 6px solid #818cf8; font-family: sans-serif;">
                    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                        <span style="background: #4f46e5; color: #ffffff; font-size: 11px; font-weight: 800; padding: 5px 16px; border-radius: 20px; text-transform: uppercase; letter-spacing: 0.8px; display: inline-block;">🎓 PORTAL KEPALA PROGRAM STUDI (KAPRODI) USH</span>
                        <span style="background: rgba(255,255,255,0.15); font-size: 11px; padding: 4px 12px; border-radius: 12px; color: #c7d2fe;">Akses Manajerial & Pengajaran</span>
                    </div>
                    <h2 style="font-size: 24px; font-weight: 800; color: #ffffff; margin: 12px 0 8px 0;">Selamat Datang, ' . htmlspecialchars($dosen_fullname) . '! 👋</h2>
                    <p style="font-size: 13.5px; color: #c7d2fe; margin: 0 0 20px 0; line-height: 1.6;">Dashboard Manajerial Kaprodi Universitas Sugeng Hartono. Selain mengampu mata kuliah perkuliahan, Anda memiliki wewenang untuk memantau kelengkapan RPS, supervisi dosen pengampu, dan pengelolaan kelas prodi.</p>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 12px; margin-bottom: 20px;">
                        <div style="background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); border-radius: 12px; padding: 12px 14px; display: flex; align-items: center; gap: 12px;">
                            <span style="font-size: 22px; background: rgba(129, 140, 248, 0.25); width: 42px; height: 42px; border-radius: 10px; display: flex; align-items: center; justify-content: center; color: #a5b4fc;">🏛️</span>
                            <div><div style="font-size: 14px; font-weight: 700; color: #ffffff;">Monitoring Prodi</div><div style="font-size: 10px; color: #a5b4fc; text-transform: uppercase;">Pengawasan Kelas</div></div>
                        </div>
                        <div style="background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); border-radius: 12px; padding: 12px 14px; display: flex; align-items: center; gap: 12px;">
                            <span style="font-size: 22px; background: rgba(129, 140, 248, 0.25); width: 42px; height: 42px; border-radius: 10px; display: flex; align-items: center; justify-content: center; color: #a5b4fc;">👨‍🏫</span>
                            <div><div style="font-size: 14px; font-weight: 700; color: #ffffff;">Dosen Pengampu</div><div style="font-size: 10px; color: #a5b4fc; text-transform: uppercase;">Supervisi Mengajar</div></div>
                        </div>
                        <div style="background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); border-radius: 12px; padding: 12px 14px; display: flex; align-items: center; gap: 12px;">
                            <span style="font-size: 22px; background: rgba(129, 140, 248, 0.25); width: 42px; height: 42px; border-radius: 10px; display: flex; align-items: center; justify-content: center; color: #a5b4fc;">📄</span>
                            <div><div style="font-size: 14px; font-weight: 700; color: #ffffff;">Audit RPS Digital</div><div style="font-size: 10px; color: #a5b4fc; text-transform: uppercase;">100% Terverifikasi</div></div>
                        </div>
                        <div style="background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); border-radius: 12px; padding: 12px 14px; display: flex; align-items: center; gap: 12px;">
                            <span style="font-size: 22px; background: rgba(129, 140, 248, 0.25); width: 42px; height: 42px; border-radius: 10px; display: flex; align-items: center; justify-content: center; color: #a5b4fc;">📚</span>
                            <div><div style="font-size: 14px; font-weight: 700; color: #ffffff;">Mengajar Mengampu</div><div style="font-size: 10px; color: #a5b4fc; text-transform: uppercase;">Kelas Sendiri</div></div>
                        </div>
                    </div>
                    <div style="display: flex; flex-wrap: wrap; gap: 10px;">
                        <a href="' . $dash_url . 'courses.php" style="background: #ffffff; border: 1px solid #cbd5e1; padding: 9px 16px; border-radius: 30px; color: #1e1b4b; font-weight: 700; font-size: 12.5px; text-decoration: none;">📚 Kelas Yang Saya Ampu</a>
                        <a href="' . $CFG->wwwroot . '/course/management.php" style="background: #818cf8; border: 1px solid #6366f1; padding: 9px 16px; border-radius: 30px; color: #ffffff; font-weight: 700; font-size: 12.5px; text-decoration: none;">🏛️ Kelola Seluruh Kelas Prodi</a>
                        <a href="https://siakad.sugenghartono.ac.id" target="_blank" style="background: #ffffff; border: 1px solid #cbd5e1; padding: 9px 16px; border-radius: 30px; color: #1e1b4b; font-weight: 700; font-size: 12.5px; text-decoration: none;">🏛️ Portal SIAKAD</a>
                    </div>
                </div>';
            }
        } else {
            // --- BANNER DOSEN PENGAMPU REGULER ---
            if ($PAGE->pagetype === 'my-index') {
                $ush_banner_html = '
                <div class="ush-dosen-hero-server" style="position: relative; z-index: 999; background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #0284c7 100%); border-radius: 16px; padding: 26px 30px; color: #ffffff; margin: 15px 0 25px 0; box-shadow: 0 12px 35px rgba(15, 23, 42, 0.28); border-left: 6px solid #38bdf8; font-family: sans-serif;">
                    <span style="background: #0284c7; color: #ffffff; font-size: 11px; font-weight: 800; padding: 4px 14px; border-radius: 20px; text-transform: uppercase; letter-spacing: 0.8px; display: inline-block; margin-bottom: 12px;">👨‍🏫 PORTAL DOSEN PENGAMPU USH</span>
                    <h2 style="font-size: 24px; font-weight: 800; color: #ffffff; margin: 0 0 8px 0;">Selamat Datang, ' . htmlspecialchars($dosen_fullname) . '! 👋</h2>
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
                    </div>
                </div>';
            }
        }
    }
}

$templatecontext += [
    'sitename' => format_string($SITE->fullname, true, ['context' => context_course::instance(SITEID), "escape" => false]),
    'coursefullname' => $coursefullname,
    'courseurl' => $courseurl ? $courseurl->out(false) : null,
    'output' => $OUTPUT,
    'sidepreblocks' => $blockshtml,
    'hasblocks' => $hasblocks,
    'courseindexopen' => $courseindexopen,
    'blockdraweropen' => $blockdraweropen,
    'courseindex' => $courseindex,
    'primarymoremenu' => $primarymenu['moremenu'],
    'secondarymoremenu' => $secondarynavigation ?: false,
    'mobileprimarynav' => $primarymenu['mobileprimarynav'],
    'usermenu' => $primarymenu['user'],
    'langmenu' => $primarymenu['lang'],
    'forceblockdraweropen' => $forceblockdraweropen,
    'regionmainsettingsmenu' => $regionmainsettingsmenu,
    'hasregionmainsettingsmenu' => !empty($regionmainsettingsmenu),
    'overflow' => $overflow,
    'headercontent' => $headercontent,
    'addblockbutton' => $addblockbutton,
    'ush_banner_html' => $ush_banner_html,
    'ush_is_dosen' => $ush_is_dosen,
];
