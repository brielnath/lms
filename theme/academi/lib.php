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
 * lib.php
 * @package    theme_academi
 * @copyright  2015 onwards LMSACE Dev Team (http://www.lmsace.com)
 * @author    LMSACE Dev Team
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('FRONTPAGEPROMOTEDCOURSE', 10);
define('FRONTPAGESITEFEATURES', 11);
define('FRONTPAGEMARKETINGSPOT', 12);
define('FRONTPAGEJUMBOTRON', 13);

define('THEMEDEFAULT', 16);
define('SMALL', 15);
define('MEDIUM', 17);
define('LARGE', 18);

define('MOODLEBASED', 0);
define('THEMEBASED', 1);

define('CAROUSEL', 1);

define('EXPAND', 0);
define('COLLAPSE', 1);

define('NO', 0);
define('YES', 1);

define('SAMEWINDOW', 0);
define('NEWWINDOW', 1);

define('LOGO', 0);
define('SITENAME', 1);
define('LOGOANDSITENAME', 2);

/**
 * Load the Jquery and migration files
 * @param moodle_page $page
 * @return void
 */
function theme_academi_page_init(moodle_page $page) {
    global $CFG;

    $page->requires->js_call_amd('theme_academi/theme', 'init');

    // Akun hanya dari SIAKAD/admin; nonaktifkan daftar mandiri.
    if (!empty($CFG->registerauth)) {
        set_config('registerauth', '');
        $CFG->registerauth = '';
    }

    // Pastikan Course overview punya layout kartu (dropdown Moodle sering tersembunyi).
    $layouts = get_config('block_myoverview', 'layouts');
    if ($layouts === false || $layouts === '' || strpos(',' . $layouts . ',', ',card,') === false) {
        set_config('layouts', 'card,list,summary', 'block_myoverview');
    }

    // User production masih mode list; pindahkan ke kartu sekali (tombol Daftar tetap bisa dipakai setelah itu).
    if (isloggedin() && !isguestuser()) {
        if (!get_user_preferences('theme_academi_myoverview_card_migrated')) {
            set_user_preference('block_myoverview_user_view_preference', 'card');
            set_user_preference('theme_academi_myoverview_card_migrated', 1);
        }
    }

    // Git pull tidak menghapus cache Moodle; bersihkan sekali setelah versi ini ter-deploy.
    $bust = '2026042015';
    if (get_config('theme_academi', 'ushcachebust') !== $bust) {
        theme_reset_all_caches();
        set_config('ushcachebust', $bust, 'theme_academi');
    }
}

/**
 * Loads the CSS Styles and replace the background images.
 * If background image not available in the settings take the default images.
 *
 * @param string $css
 * @param object $theme
 * @return string
 */
function theme_academi_process_css($css, $theme) {
    global $OUTPUT, $CFG;
    $css = theme_academi_pre_css_set_fontwww($css);
    // Set custom CSS.
    $customcss = $theme->settings->customcss;
    $css = theme_academi_set_customcss($css, $customcss);
    return $css;
}

/**
 * Adds any custom CSS to the CSS before it is cached.
 *
 * @param string $css The original CSS.
 * @param string $customcss The custom CSS to add.
 * @return string The CSS which now contains our custom CSS.
 * @return string $css
 */
function theme_academi_set_customcss($css, $customcss) {
    $tag = '[[setting:customcss]]';
    $replacement = $customcss;
    if (is_null($replacement)) {
        $replacement = '';
    }
    $css = str_replace($tag, $replacement, $css);
    return $css;
}

/**
 * Serves any files associated with the theme settings.
 *
 * @param stdClass $course
 * @param stdClass $cm
 * @param context $context
 * @param string $filearea
 * @param array $args
 * @param bool $forcedownload
 * @param array $options
 * @return bool
 */
function theme_academi_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    static $theme;
    $bgimgs = ['footerbgimg', 'loginbg', 'mspotmedia'];

    if (empty($theme)) {
        $theme = theme_config::load('academi');
    }
    if ($context->contextlevel == CONTEXT_SYSTEM) {
        if ($filearea === 'logo') {
            return $theme->setting_file_serve('logo', $args, $forcedownload, $options);
        } else if ($filearea === 'loginlogo') {
            return $theme->setting_file_serve('loginlogo', $args, $forcedownload, $options);
        } else if ($filearea === 'footerlogo') {
            return $theme->setting_file_serve('footerlogo', $args, $forcedownload, $options);
        } else if ($filearea === 'pagebackground') {
            return $theme->setting_file_serve('pagebackground', $args, $forcedownload, $options);
        } else if (preg_match("/slide[1-9][0-9]*image/", $filearea) !== false) {
            return $theme->setting_file_serve($filearea, $args, $forcedownload, $options);
        } else if (in_array($filearea, $bgimgs)) {
            return $theme->setting_file_serve($filearea, $args, $forcedownload, $options);
        } else {
            send_file_not_found();
        }
    } else {
        send_file_not_found();
    }
}

/**
 * Loads the CSS Styles and put the font path
 *
 * @param string $css
 * @return string
 */
function theme_academi_pre_css_set_fontwww($css) {
    global $CFG;
    if (empty($CFG->themewww)) {
        $themewww = $CFG->wwwroot . "/theme";
    } else {
        $themewww = $CFG->themewww;
    }
    $tag = '[[setting:fontwww]]';
    $css = str_replace($tag, $themewww . '/academi/fonts/', $css);
    return $css;
}

/**
 * Load the font folder path into the scss.
 * @return string
 */
function theme_academi_set_fontwww() {
    global $CFG;
    if (empty($CFG->themewww)) {
        $themewww = $CFG->wwwroot . "/theme";
    } else {
        $themewww = $CFG->themewww;
    }
    $fontwww = '$fontwww: "' . $themewww . '/academi/fonts/"' . ";\n";
    return $fontwww;
}


/**
 * Description
 *
 * @param string $type logo position type.
 * @return type|string
 */
function theme_academi_get_logo_url($type = 'header') {
    global $OUTPUT;
    static $theme;
    if (empty($theme)) {
        $theme = theme_config::load('academi');
    }
    if ($type == 'header') {
        $logo = $theme->setting_file_url('logo', 'logo');
        $logo = empty($logo) ? $OUTPUT->get_compact_logo_url() : $logo;
    } else if ($type == 'footer') {
        $logo = $theme->setting_file_url('footerlogo', 'footerlogo');
        $logo = empty($logo) ? '' : $logo;
    }
    return $logo;
}

/**
 * Logo khusus halaman login (tidak memakai logo navbar).
 *
 * @return string
 */
function theme_academi_get_login_logo_url() {
    $theme = theme_config::load('academi');
    $logo = $theme->setting_file_url('loginlogo', 'loginlogo');
    if (empty($logo)) {
        return '';
    }
    return is_object($logo) ? $logo->out(false) : (string) $logo;
}

/**
 *
 * Description
 * @param string $setting
 * @param bool $format
 * @return string
 */
function theme_academi_get_setting($setting, $format = true) {
    global $CFG, $PAGE;
    require_once($CFG->dirroot . '/lib/weblib.php');
    static $theme;
    if (empty($theme)) {
        $theme = theme_config::load('academi');
    }
    if (empty($theme->settings->$setting)) {
        return false;
    } else if (!$format) {
        $return = $theme->settings->$setting;
    } else if ($format === 'format_text') {
        $return = format_text($theme->settings->$setting, FORMAT_PLAIN);
    } else if ($format === 'format_html') {
        $return = format_text($theme->settings->$setting, FORMAT_HTML, ['trusted' => true, 'noclean' => true]);
    } else if ($format === 'file') {
        $return = $PAGE->theme->setting_file_url($setting, $setting);
    } else {
        $return = format_string($theme->settings->$setting);
    }
    return (isset($return)) ? theme_academi_lang($return) : '';
}

/**
 * Returns the language values from the given lang string or key.
 * @param string $key
 * @return string
 */
function theme_academi_lang($key = '') {
    $pos = strpos($key, 'lang:');
    if ($pos !== false) {
        [$l, $k] = explode(':', $key);
        if (get_string_manager()->string_exists($k, 'theme_academi')) {
            $v = get_string($k, 'theme_academi');
            return $v;
        } else {
            return $key;
        }
    } else {
        return $key;
    }
}

/**
 * Returns the main SCSS content.
 *
 * @param theme_config $theme The theme config object.
 * @return string
 */
function theme_academi_get_main_scss_content($theme) {
    global $CFG;

    $scss = '';
    $filename = (isset($theme->settings->preset) && !empty($theme->settings->preset)) ? $theme->settings->preset : null;
    $fs = get_file_storage();

    $context = \context_system::instance();
    if ($filename == 'default.scss') {
        $scss .= file_get_contents($CFG->dirroot . '/theme/academi/scss/preset/default.scss');
    } else if ($filename == 'eguru') {
        $scss .= file_get_contents($CFG->dirroot . '/theme/academi/scss/preset/eguru.scss');
    } else if ($filename == 'klass') {
        $scss .= file_get_contents($CFG->dirroot . '/theme/academi/scss/preset/klass.scss');
    } else if ($filename == 'enlightlite') {
        $scss .= file_get_contents($CFG->dirroot . '/theme/academi/scss/preset/enlightlite.scss');
    } else if ($filename && ($presetfile = $fs->get_file($context->id, 'theme_academi', 'preset', 0, '/', $filename))) {
        $scss .= $presetfile->get_content();
    } else {
        // Fallback to default.
        $scss .= file_get_contents($CFG->dirroot . '/theme/academi/scss/preset/default.scss');
    }
    return $scss;
}

/**
 * Get the configuration values into main scss variables.
 *
 * @param string $theme theme data.
 * @return string $scss return the scss values.
 */
function theme_academi_get_pre_scss($theme) {
    $scss = '';
    $helperobj = new theme_academi\helper();
    $scss .= $helperobj->load_bgimages($theme, $scss);
    $scss .= $helperobj->load_additional_scss_settings();
    return $scss;
}

/**
 * Inject additional SCSS.
 *
 * @param theme_config $theme The theme config object.
 * @return string
 */
function theme_academi_get_extra_scss($theme) {
    // Load the settings from the parent.
    $theme = theme_config::load('boost');
    // Call the parent themes get_extra_scss function.
    $extrascss = theme_boost_get_extra_scss($theme);

    // Remove Boost login background and watermark.
    $extrascss .= 'body.pagelayout-login #page .login-layout-left::after { display: none; }';
    $extrascss .= 'body.pagelayout-login .ush-login-panel__title {'
        . ' display: block !important; visibility: visible !important;'
        . ' color: #e9e62a !important; font-size: 2.25rem !important;'
        . ' font-weight: 800 !important; margin: 0 0 28px !important; }';

    // Add our custom login background logic.
    $loginbackgroundimageurl = $theme->setting_file_url('loginbackgroundimage', 'loginbackgroundimage');
    if (!empty($loginbackgroundimageurl)) {
        $customloginbg = 'body.pagelayout-login #page .login-layout-left { ';
        $customloginbg .= "background-image: url('$loginbackgroundimageurl'); ";
        $customloginbg .= "background-size: cover; background-position: center; position: relative;";
        $customloginbg .= ' }';
        $extrascss .= $customloginbg;
    } else {
        $emptyloginbg = 'body.pagelayout-login #page .login-layout-left { ';
        $emptyloginbg .= 'background: linear-gradient(135deg, #1e1b4b 0%, #1c0ccb 55%, #210acd 100%) !important; ';
        $emptyloginbg .= 'background-image: none; position: relative;';
        $emptyloginbg .= ' }';
        $extrascss .= $emptyloginbg;
    }

    return $extrascss;
}

/**
 * Username tampak akun mahasiswa USH (NIM), termasuk kalau diisi sebagai email.
 */
function theme_academi_ush_username_looks_mahasiswa(string $raw): bool {
    $u = strtolower(trim($raw));
    if (str_contains($u, '@')) {
        $u = explode('@', $u, 2)[0];
    }
    if (str_starts_with($u, 'dosen_') || str_starts_with($u, 'dosen.') || $u === 'admin' || $u === 'guest') {
        return false;
    }
    if (preg_match('/^06\d{8,}$/', $u)) {
        return true;
    }
    return ctype_digit($u) && strlen($u) >= 8 && strlen($u) <= 12;
}

/**
 * Tolak login kalau tab Dosen/Admin vs Mahasiswa tidak sesuai jenis akun.
 * Dipanggil sebelum password dicek, supaya NIM tidak lolos lewat form staf.
 *
 * @param stdClass $frm data form login
 * @return string pesan error, atau kosong jika boleh dilanjut
 */
function theme_academi_ush_login_precheck(stdClass $frm): string {
    $role = strtolower(trim((string) ($frm->ush_login_role ?? 'staff')));
    if ($role !== 'student' && $role !== 'staff') {
        $role = 'staff';
    }
    $username = (string) ($frm->username ?? '');
    $isnim = theme_academi_ush_username_looks_mahasiswa($username);
    if ($role === 'staff' && $isnim) {
        return 'Akun mahasiswa tidak bisa masuk lewat Dosen / Admin. Pilih tab Mahasiswa.';
    }
    if ($role === 'student' && !$isnim) {
        return 'Akun dosen atau admin tidak bisa masuk lewat tab Mahasiswa. Pilih Dosen / Admin.';
    }
    return '';
}

/**
 * Dosen di halaman kelas: Settings paling kiri, Course diganti tautan daftar kehadiran.
 * Bahasa situs id = Kehadiran, selain itu = Attendance.
 */
function theme_academi_ush_adjust_teacher_secondarynav(moodle_page $page): void {
    global $USER;

    if (!isloggedin() || isguestuser()) {
        return;
    }
    $course = $page->course ?? null;
    if (!$course || empty($course->id) || (int) $course->id <= 1) {
        return;
    }
    $level = (int) $page->context->contextlevel;
    if ($level !== CONTEXT_COURSE && $level !== CONTEXT_MODULE) {
        return;
    }
    $context = context_course::instance((int) $course->id);
    if (!has_capability('moodle/course:update', $context)
            && !has_capability('mod/attendance:takeattendances', $context)
            && !has_capability('mod/attendance:viewreports', $context)) {
        return;
    }

    $nav = $page->secondarynav;
    if (!$nav || empty($nav->children)) {
        return;
    }

    $reporturl = null;
    try {
        $modinfo = get_fast_modinfo($course, $USER->id);
        foreach ($modinfo->get_instances_of('attendance') as $cm) {
            if ($cm->uservisible) {
                $reporturl = new moodle_url('/mod/attendance/report.php', [
                    'id' => $cm->id,
                    'ushplain' => 1,
                ]);
                break;
            }
        }
    } catch (Throwable $e) {
        $reporturl = null;
    }

    if ($reporturl && !$nav->get('ushkehadiran')) {
        $lang = current_language();
        $label = ($lang === 'id' || str_starts_with($lang, 'id_')) ? 'Kehadiran' : 'Attendance';
        $node = navigation_node::create(
            $label,
            $reporturl,
            navigation_node::TYPE_CUSTOM,
            null,
            'ushkehadiran'
        );
        $node->showinflatnavigation = true;
        $plain = optional_param('ushplain', 0, PARAM_BOOL);
        if (str_starts_with((string) $page->pagetype, 'mod-attendance') && $plain) {
            $node->make_active();
        }
        $nav->add_node($node);
    }

    // Tab Course tidak dipakai dosen. Diganti Kehadiran kalau ada presensi.
    if ($nav->get('coursehome')) {
        $nav->children->remove('coursehome');
    }

    $settings = $nav->get('editsettings');
    if ($settings) {
        $settings->set_force_into_more_menu(true);
    }

    $kehadiran = $nav->get('ushkehadiran');
    if ($kehadiran) {
        $nav->children->remove('ushkehadiran');
        $keys = $nav->children->get_key_list();
        $nav->add_node($kehadiran, $keys[0] ?? null);
    }
}

/**
 * Halaman daftar kehadiran dosen: hanya tabel mahasiswa, tanpa tab kelas.
 */
function theme_academi_ush_attendance_plain_view(): bool {
    global $PAGE;

    if (!isloggedin() || isguestuser()) {
        return false;
    }
    if (!str_starts_with((string) $PAGE->pagetype, 'mod-attendance-report')) {
        return false;
    }
    if (!optional_param('ushplain', 0, PARAM_BOOL)) {
        return false;
    }
    $context = context_course::instance((int) $PAGE->course->id);
    return has_capability('moodle/course:update', $context)
        || has_capability('mod/attendance:viewreports', $context)
        || has_capability('mod/attendance:takeattendances', $context);
}

/**
 * English label for USH menus and course titles when the active language is English.
 *
 * @param string $text Indonesian source label.
 * @return string
 */
function theme_academi_ush_en_label(string $text): string {
    $lang = current_language();
    if ($lang !== 'en' && !str_starts_with($lang, 'en')) {
        return $text;
    }
    $exact = [
        'Kategori' => 'Categories',
        'Semua kategori' => 'All categories',
        'Panduan' => 'Guide',
        'Masuk' => 'Log in',
        'Cari' => 'Search',
    ];
    if (isset($exact[$text])) {
        return $exact[$text];
    }
    if (class_exists(\filter_ushlabels\text_filter::class)) {
        return \filter_ushlabels\text_filter::translate($text);
    }
    return $text;
}

/**
 * Active semester category shown on "Kursusku".
 *
 * Uses theme_academi/ush_active_semester (top category idnumber) when set,
 * otherwise the latest top-level TA_YYYY_YYYY___Ganjil|Genap category.
 *
 * @return stdClass|null Category record with id, idnumber, name.
 */
function theme_academi_ush_active_semester(): ?stdClass {
    global $DB;
    static $cached = false;
    if ($cached !== false) {
        return $cached;
    }
    $cats = $DB->get_records_select('course_categories', "parent = 0 AND idnumber LIKE 'TA\\_%'", null, '', 'id, idnumber, name');
    $configured = trim((string) get_config('theme_academi', 'ush_active_semester'));
    $active = null;
    foreach ($cats as $cat) {
        if ($configured !== '') {
            if ($cat->idnumber === $configured) {
                $active = $cat;
            }
        } else if (preg_match('/^TA_\d{4}_\d{4}___(Ganjil|Genap)$/', $cat->idnumber)
                && (!$active || strcmp($cat->idnumber, $active->idnumber) > 0)) {
            $active = $cat;
        }
    }
    $cached = $active;
    return $active;
}

/**
 * Keep the hidden course custom field "ush_semester" equal to the course's top category idnumber
 * (or TA_YYYY_YYYY___Ganjil|Genap from a shortname suffix like _20262027Ganjil outside TA categories).
 *
 * @param bool $force Skip the throttle.
 * @return string Field shortname.
 */
function theme_academi_ush_sync_semester_field(bool $force = false): string {
    global $DB;
    $shortname = 'ush_semester';
    $last = (int) get_config('theme_academi', 'ush_semester_synced');
    $fieldid = (int) $DB->get_field_sql(
        "SELECT f.id FROM {customfield_field} f
           JOIN {customfield_category} c ON c.id = f.categoryid
          WHERE f.shortname = ? AND c.component = 'core_course' AND c.area = 'course'",
        [$shortname]
    );
    if ($fieldid && !$force && $last > time() - 300) {
        return $shortname;
    }

    if (!$fieldid) {
        $handler = \core_course\customfield\course_handler::create();
        $categoryid = $handler->create_category('USH');
        $category = \core_customfield\category_controller::create($categoryid);
        $field = \core_customfield\field_controller::create(0, (object) [
            'type' => 'text',
            'shortname' => $shortname,
            'name' => 'Semester',
            'description' => 'Diisi otomatis dari kategori tahun akademik.',
            'descriptionformat' => FORMAT_HTML,
        ], $category);
        $handler->save_field_configuration($field, (object) [
            'shortname' => $shortname,
            'name' => 'Semester',
            'configdata' => [
                'required' => 0,
                'uniquevalues' => 0,
                'locked' => 1,
                'visibility' => \core_course\customfield\course_handler::NOTVISIBLE,
                'defaultvalue' => '',
                'displaysize' => 50,
                'maxlength' => 100,
                'ispassword' => 0,
                'link' => '',
            ],
        ]);
        $fieldid = (int) $field->get('id');
    }

    $rows = $DB->get_recordset_sql(
        "SELECT c.id, c.shortname, cc.path, ctx.id AS contextid, cd.id AS dataid, cd.value
           FROM {course} c
           JOIN {course_categories} cc ON cc.id = c.category
           JOIN {context} ctx ON ctx.instanceid = c.id AND ctx.contextlevel = :ctxlevel
      LEFT JOIN {customfield_data} cd ON cd.instanceid = c.id AND cd.fieldid = :fieldid
          WHERE c.id <> :siteid",
        ['ctxlevel' => CONTEXT_COURSE, 'fieldid' => $fieldid, 'siteid' => SITEID]
    );
    $topidnumbers = $DB->get_records_menu('course_categories', ['parent' => 0], '', 'id, idnumber');
    $now = time();
    foreach ($rows as $row) {
        $topid = (int) explode('/', trim($row->path, '/'))[0];
        $expected = (string) ($topidnumbers[$topid] ?? '');
        if (!str_starts_with($expected, 'TA_')
                && preg_match('/_(\d{4})(\d{4})(Ganjil|Genap)$/', $row->shortname, $m)) {
            $expected = "TA_{$m[1]}_{$m[2]}___{$m[3]}";
        }
        if ($row->dataid && (string) $row->value === $expected) {
            continue;
        }
        if ($row->dataid) {
            $DB->update_record('customfield_data', (object) [
                'id' => $row->dataid,
                'value' => $expected,
                'charvalue' => $expected,
                'timemodified' => $now,
            ]);
        } else {
            $DB->insert_record('customfield_data', (object) [
                'fieldid' => $fieldid,
                'instanceid' => $row->id,
                'value' => $expected,
                'charvalue' => $expected,
                'valueformat' => FORMAT_MOODLE,
                'valuetrust' => 0,
                'timecreated' => $now,
                'timemodified' => $now,
                'contextid' => $row->contextid,
            ]);
        }
    }
    $rows->close();
    set_config('ush_semester_synced', $now, 'theme_academi');
    return $shortname;
}
