<?php
/**
 * Generate kategori + cangkang kelas LMS untuk 2026/2027 Ganjil.
 * Tidak mengunci kelas semester lama.
 */
define('CLI_SCRIPT', true);
@error_reporting(E_ALL & ~E_NOTICE);
require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->libdir . '/enrollib.php');
require_once(__DIR__ . '/ush_course_owner.php');

$NEW_SEMESTER_LABEL = '2026/2027 - Ganjil';
$NEW_TAHUN          = '2026/2027';
$SHORT_SUFFIX       = '20262027Ganjil';
$START              = mktime(0, 0, 0, 9, 1, 2026);
$END                = mktime(0, 0, 0, 2, 28, 2027);

$prodi_list = ush_prodi_labels();

function siakad_login() {
    $ch = curl_init('https://siakad.sugenghartono.ac.id/api/login');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode([
            'email' => 'akademik@sugenghartono.ac.id',
            'password' => '321',
        ]),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 20,
    ]);
    $raw = curl_exec($ch);
    curl_close($ch);
    $json = json_decode($raw, true);
    return $json['token'] ?? $json['data']['token'] ?? $json['access_token'] ?? $json['data']['access_token'] ?? null;
}

function siakad_get($url, $token) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token, 'Accept: application/json'],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 40,
    ]);
    $raw = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$http, json_decode($raw, true)];
}

function ush_prodi_code($code) {
    return ush_prodi_from_code($code);
}

mtrace('=== GENERATE LMS 2026/2027 Ganjil ===');

// Categories
$idnumber_parent = 'TA_' . str_replace(['/', ' ', '-'], '_', $NEW_SEMESTER_LABEL);
$parent = $DB->get_record('course_categories', ['idnumber' => $idnumber_parent]);
if (!$parent) {
    $parent = $DB->get_record('course_categories', ['name' => 'TA ' . $NEW_SEMESTER_LABEL]);
}
if (!$parent) {
    $parent = core_course_category::create((object) [
        'name' => 'TA ' . $NEW_SEMESTER_LABEL,
        'idnumber' => $idnumber_parent,
        'parent' => 0,
        'description' => 'Tahun akademik ' . $NEW_SEMESTER_LABEL,
        'descriptionformat' => FORMAT_PLAIN,
    ]);
    mtrace('Kategori induk dibuat: TA ' . $NEW_SEMESTER_LABEL);
} else {
    mtrace('Kategori induk sudah ada: ' . $parent->name);
}
$parent_id = (int) $parent->id;

$cat_ids = [];
foreach ($prodi_list as $kode => $nama) {
    $cat_name = "$nama ($kode) - $NEW_SEMESTER_LABEL";
    $idn = "CAT_{$kode}_" . str_replace(['/', ' ', '-'], '_', $NEW_SEMESTER_LABEL);
    $existing = $DB->get_record('course_categories', ['idnumber' => $idn]);
    if (!$existing) {
        $existing = $DB->get_record('course_categories', ['name' => $cat_name]);
    }
    if (!$existing) {
        $createdcat = core_course_category::create((object) [
            'name' => $cat_name,
            'idnumber' => $idn,
            'parent' => $parent_id,
            'descriptionformat' => FORMAT_PLAIN,
        ]);
        $cat_ids[$kode] = (int) $createdcat->id;
        mtrace("  Sub: $cat_name");
    } else {
        $cat_ids[$kode] = (int) $existing->id;
        mtrace("  Sub sudah ada: $cat_name");
    }
}

$token = siakad_login();
if (!$token) {
    mtrace('Gagal login SIAKAD');
    exit(1);
}
mtrace('Login SIAKAD OK');

$lessons = [];
$page = 1;
while ($page <= 80) {
    [$http, $res] = siakad_get('https://siakad.sugenghartono.ac.id/api/all-lessons?per_page=100&page=' . $page, $token);
    if ($http !== 200) {
        mtrace("all-lessons page $page HTTP $http");
        break;
    }
    $items = $res['data'] ?? [];
    foreach ($items as $lesson) {
        $code = strtoupper(trim($lesson['code'] ?? ''));
        if ($code !== '' && !isset($lessons[$code])) {
            $lessons[$code] = $lesson;
        }
    }
    $last = (int) ($res['meta']['last_page'] ?? $res['last_page'] ?? 0);
    mtrace("  Katalog page $page: " . count($items) . ' item, unique ' . count($lessons));
    if (empty($items) || ($last > 0 && $page >= $last)) {
        break;
    }
    $page++;
}
mtrace('Total MK katalog: ' . count($lessons));

$created = 0;
$skipped = 0;
$ignored_even = 0;
$enrol_plugin = enrol_get_plugin('manual');

foreach ($lessons as $code => $lesson) {
    $sem = (int) ($lesson['semester'] ?? 0);
    if ($sem > 0 && $sem % 2 === 0) {
        $ignored_even++;
        continue;
    }
    $name = trim($lesson['name'] ?? '');
    if ($name === '') {
        continue;
    }
    $shortname = $code . '_' . $SHORT_SUFFIX;
    if ($DB->record_exists('course', ['shortname' => $shortname])) {
        $skipped++;
        continue;
    }
    $prodi = ush_prodi_code($code);
    $sks = (int) ($lesson['sks_total'] ?? 0);
    $newcourse = new stdClass();
    $newcourse->fullname = "$name — $NEW_SEMESTER_LABEL";
    $newcourse->shortname = $shortname;
    $newcourse->idnumber = $shortname;
    $newcourse->category = $cat_ids[$prodi] ?? $cat_ids['MKU'];
    $newcourse->visible = 1;
    $newcourse->format = 'topics';
    $newcourse->numsections = 16;
    $newcourse->startdate = $START;
    $newcourse->enddate = $END;
    $newcourse->summary = '<p><strong>' . s($name) . '</strong> — ' . s($NEW_SEMESTER_LABEL)
        . '</p><p>Kode: ' . s($code) . ($sks ? ' | SKS: ' . $sks : '') . '</p>';
    $newcourse->summaryformat = FORMAT_HTML;
    $newcourse->enablecompletion = 1;
    try {
        $course = create_course($newcourse);
        if ($enrol_plugin && !$DB->record_exists('enrol', ['courseid' => $course->id, 'enrol' => 'manual'])) {
            $enrol_plugin->add_instance($course);
        }
        $created++;
        if ($created % 40 === 0) {
            mtrace("  ... $created kelas");
        }
    } catch (Exception $e) {
        $skipped++;
        mtrace('  Skip ' . $shortname . ': ' . $e->getMessage());
    }
}

fix_course_sortorder();
rebuild_course_cache(0, true);

mtrace('');
mtrace("Selesai 2026/2027 Ganjil");
mtrace("  Kelas baru : $created");
mtrace("  Sudah ada  : $skipped");
mtrace("  MK genap tidak dibuat : $ignored_even");
foreach ($prodi_list as $kode => $nama) {
    $n = $DB->count_records('course', ['category' => $cat_ids[$kode]]);
    mtrace("  $kode : $n MK");
}
