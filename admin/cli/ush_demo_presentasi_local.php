<?php
/**
 * Akun latihan presentasi. Hanya LMS lokal. Tidak menyentuh kelas sungguhan.
 *
 *   php admin/cli/ush_demo_presentasi_local.php
 *   php admin/cli/ush_demo_presentasi_local.php --confirm
 */
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->libdir . '/enrollib.php');
require_once($CFG->libdir . '/accesslib.php');
require_once($CFG->dirroot . '/course/modlib.php');

if (strpos($CFG->wwwroot, 'localhost') === false && strpos($CFG->wwwroot, '127.0.0.1') === false) {
    mtrace('Dibatalkan: bukan LMS lokal.');
    exit(1);
}

$confirm = in_array('--confirm', array_slice($argv, 1), true);
$admin = get_admin();
\core\session\manager::set_user($admin);

$tz = new DateTimeZone('Asia/Jakarta');
$students = [
    ['username' => '069999001', 'firstname' => 'Demo', 'lastname' => 'Mahasiswa Satu', 'password' => 'Ush@069999001'],
    ['username' => '069999002', 'firstname' => 'Demo', 'lastname' => 'Mahasiswa Dua', 'password' => 'Ush@069999002'],
    ['username' => '069999003', 'firstname' => 'Demo', 'lastname' => 'Mahasiswa Tiga', 'password' => 'Ush@069999003'],
];
$dosenlogin = 'dosen.demo@sugenghartono.ac.id';
$kaprodilogin = 'kaprodi.demo@sugenghartono.ac.id';
$short = 'DEMO001_20262027Ganjil';

mtrace('=== Akun latihan presentasi ===');
mtrace('Mode: ' . ($confirm ? 'LIVE' : 'DRY-RUN'));
mtrace('Mahasiswa : 069999001 / Ush@069999001');
mtrace('Dosen     : ' . $dosenlogin . ' / DosenUSH2026!');
mtrace('Kaprodi   : ' . $kaprodilogin . ' / KaprodiUSH2026!');
mtrace('Kelas     : ' . $short);
if (!$confirm) {
    mtrace('DRY-RUN. Ulangi dengan --confirm untuk menulis.');
    exit(0);
}

function ush_demo_user(string $username, string $firstname, string $lastname, string $email, string $password, string $idnumber): stdClass {
    global $DB, $CFG;
    $user = $DB->get_record('user', [
        'username' => $username,
        'mnethostid' => $CFG->mnet_localhost_id,
        'deleted' => 0,
    ]);
    if ($user) {
        $user->firstname = $firstname;
        $user->lastname = $lastname;
        $user->email = $email;
        $user->idnumber = $idnumber;
        $user->password = $password;
        user_update_user($user, true, false);
        return $DB->get_record('user', ['id' => $user->id], '*', MUST_EXIST);
    }
    $id = user_create_user((object) [
        'username' => $username,
        'auth' => 'manual',
        'password' => $password,
        'firstname' => $firstname,
        'lastname' => $lastname,
        'email' => $email,
        'idnumber' => $idnumber,
        'confirmed' => 1,
        'mnethostid' => $CFG->mnet_localhost_id,
        'lang' => 'id',
        'calendartype' => $CFG->calendartype ?? 'gregorian',
        'mailformat' => 1,
        'maildisplay' => 0,
    ], true, false);
    return $DB->get_record('user', ['id' => $id], '*', MUST_EXIST);
}

$cat = $DB->get_record('course_categories', ['idnumber' => 'CAT_DEMO_PRESENTASI']);
if (!$cat) {
    $cat = core_course_category::create([
        'name' => 'Latihan Presentasi (DEMO)',
        'idnumber' => 'CAT_DEMO_PRESENTASI',
        'parent' => 0,
        'visible' => 1,
    ]);
    $cat = $DB->get_record('course_categories', ['id' => $cat->id], '*', MUST_EXIST);
}

$course = $DB->get_record('course', ['shortname' => $short]);
if (!$course) {
    $course = create_course((object) [
        'fullname' => 'Kelas Latihan Presentasi LMS — 2026/2027 - Ganjil',
        'shortname' => $short,
        'idnumber' => $short,
        'category' => (int) $cat->id,
        'visible' => 1,
        'format' => 'topics',
        'numsections' => 4,
        'startdate' => (new DateTime('2026-09-01 00:00:00', $tz))->getTimestamp(),
        'summary' => '<p>Kelas fiktif untuk latihan presentasi. Bukan kelas mahasiswa sungguhan.</p>',
        'summaryformat' => FORMAT_HTML,
    ]);
}

$opens = [
    1 => '2026-09-09 08:00:00',
    2 => '2026-09-16 08:00:00',
    3 => '2026-09-23 08:00:00',
    4 => '2026-10-07 08:00:00',
];
$sections = $DB->get_records('course_sections', ['course' => $course->id], 'section ASC');
foreach ($sections as $section) {
    $n = (int) $section->section;
    if ($n < 1 || $n > 4 || !isset($opens[$n])) {
        continue;
    }
    $section->name = 'Pertemuan ' . $n;
    $section->visible = 1;
    $ts = (new DateTime($opens[$n], $tz))->getTimestamp();
    $section->availability = json_encode([
        'op' => '&',
        'c' => [['type' => 'date', 'd' => '>=', 't' => $ts]],
        'showc' => [true],
    ]);
    $DB->update_record('course_sections', $section);
}

$studentrole = (int) $DB->get_field('role', 'id', ['shortname' => 'student'], MUST_EXIST);
$teacherrole = (int) $DB->get_field('role', 'id', ['shortname' => 'editingteacher'], MUST_EXIST);
$managerrole = (int) $DB->get_field('role', 'id', ['shortname' => 'manager'], MUST_EXIST);
$enrol = enrol_get_plugin('manual');
if ($enrol && !$DB->record_exists('enrol', ['courseid' => $course->id, 'enrol' => 'manual'])) {
    $enrol->add_instance($course);
}

$made = [];
foreach ($students as $row) {
    $made[] = ush_demo_user($row['username'], $row['firstname'], $row['lastname'], $row['username'] . '@demo.sugenghartono.ac.id', $row['password'], 'DEMO_' . $row['username']);
}
$dosen = ush_demo_user($dosenlogin, 'Dosen', 'Demo Presentasi', $dosenlogin, 'DosenUSH2026!', 'DEMO_DOSEN');
$kaprodi = ush_demo_user($kaprodilogin, 'Kaprodi', 'Demo Presentasi (Kaprodi)', $kaprodilogin, 'KaprodiUSH2026!', 'DEMO_KAPRODI');

foreach ($made as $user) {
    enrol_try_internal_enrol($course->id, $user->id, $studentrole);
}
enrol_try_internal_enrol($course->id, $dosen->id, $teacherrole);

$catctx = context_coursecat::instance($cat->id);
role_assign($managerrole, $kaprodi->id, $catctx->id);

$moduleid = (int) $DB->get_field('modules', 'id', ['name' => 'attendance']);
$hasatt = $DB->record_exists('course_modules', ['course' => $course->id, 'module' => $moduleid]);
if ($moduleid && !$hasatt) {
    $moduleinfo = (object) [
        'module' => $moduleid,
        'modulename' => 'attendance',
        'course' => $course->id,
        'section' => 0,
        'visible' => 1,
        'name' => 'Presensi Kelas Latihan',
        'intro' => '<p>Presensi latihan. Mahasiswa tidak absen sendiri.</p>',
        'introformat' => FORMAT_HTML,
        'grade' => 100,
    ];
    $added = add_moduleinfo($moduleinfo, $course);
    require_once($CFG->dirroot . '/mod/attendance/locallib.php');
    $cm = get_coursemodule_from_id('attendance', $added->coursemodule, 0, false, MUST_EXIST);
    $instance = $DB->get_record('attendance', ['id' => $cm->instance], '*', MUST_EXIST);
    $context = context_module::instance($cm->id);
    $structure = new mod_attendance_structure($instance, $cm, $course, $context);
    foreach ([1 => '2026-09-09 08:00:00', 2 => '2026-09-16 08:00:00', 3 => '2026-09-23 08:00:00'] as $n => $when) {
        $structure->add_session((object) [
            'sessdate' => (new DateTime($when, $tz))->getTimestamp(),
            'duration' => 100 * 60,
            'descriptionitemid' => 0,
            'description' => '<p>Pertemuan ' . $n . ' latihan.</p>',
            'descriptionformat' => FORMAT_HTML,
            'calendarevent' => 0,
            'timemodified' => time(),
            'studentscanmark' => 0,
            'allowupdatestatus' => 0,
            'studentsearlyopentime' => 0,
            'autoassignstatus' => 0,
            'studentpassword' => '',
            'subnet' => '',
            'automark' => 0,
            'absenteereport' => 1,
            'includeqrcode' => 0,
            'statusset' => 0,
            'groupid' => 0,
            'automarkcmid' => 0,
        ]);
    }
}

rebuild_course_cache($course->id, true);
purge_all_caches();
mtrace('Selesai. course id ' . $course->id . ' kategori ' . $cat->name);
