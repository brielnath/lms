<?php
/**
 * Kelas dummy Etika Profesi. Hanya LMS lokal.
 * Mengenrol 3 mahasiswa dummy yang sama dengan kelas latihan presentasi.
 *
 *   php admin/cli/ush_demo_etika_profesi_local.php --confirm
 */
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->libdir . '/enrollib.php');

if (strpos($CFG->wwwroot, 'localhost') === false && strpos($CFG->wwwroot, '127.0.0.1') === false) {
    mtrace('Dibatalkan: bukan LMS lokal.');
    exit(1);
}

$confirm = in_array('--confirm', array_slice($argv, 1), true);
$short = 'DEMO002_20262027Ganjil';
$dosenlogin = 'dosen.demo@sugenghartono.ac.id';

mtrace('=== Kelas dummy kosong: Etika Profesi ===');
mtrace('Mode: ' . ($confirm ? 'LIVE' : 'DRY-RUN'));
mtrace('Kelas: ' . $short);
mtrace('Mahasiswa dummy: 069999001, 069999002, 069999003');
if (!$confirm) {
    mtrace('DRY-RUN. Ulangi dengan --confirm untuk menulis.');
    exit(0);
}

$admin = get_admin();
\core\session\manager::set_user($admin);

$tz = new DateTimeZone('Asia/Jakarta');
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
        'fullname' => 'Etika Profesi (Dummy) — 2026/2027 - Ganjil',
        'shortname' => $short,
        'idnumber' => $short,
        'category' => (int) $cat->id,
        'visible' => 1,
        'format' => 'topics',
        'numsections' => 15,
        'startdate' => (new DateTime('2026-09-01 00:00:00', $tz))->getTimestamp(),
        'summary' => '<p>Kelas fiktif kosong untuk latihan presentasi. Bukan kelas mahasiswa sungguhan.</p>',
        'summaryformat' => FORMAT_HTML,
        'enablecompletion' => 1,
    ]);
}

$opens = [
    1 => '2026-09-09 08:00:00',
    2 => '2026-09-16 08:00:00',
    3 => '2026-09-23 08:00:00',
    4 => '2026-10-07 08:00:00',
    5 => '2026-10-14 08:00:00',
    6 => '2026-10-21 08:00:00',
    7 => '2026-10-28 08:00:00',
    8 => '2026-11-04 08:00:00',
    9 => '2026-11-11 08:00:00',
    10 => '2026-11-18 08:00:00',
];

course_create_sections_if_missing($course, range(0, 15));
course_get_format($course)->update_course_format_options(['numsections' => 15]);
$sections = $DB->get_records('course_sections', ['course' => $course->id], 'section ASC');
foreach ($sections as $section) {
    $n = (int) $section->section;
    if ($n < 1) {
        continue;
    }
    if ($n > 15 || !isset($opens[$n])) {
        $section->name = $n <= 15 ? 'Pertemuan ' . $n : '';
        $section->visible = 0;
        $section->availability = null;
        $DB->update_record('course_sections', $section);
        continue;
    }
    $ts = (new DateTime($opens[$n], $tz))->getTimestamp();
    $section->name = 'Pertemuan ' . $n;
    $section->summary = '';
    $section->visible = 1;
    $section->availability = json_encode([
        'op' => '&',
        'c' => [['type' => 'date', 'd' => '>=', 't' => $ts]],
        'showc' => [true],
    ]);
    $DB->update_record('course_sections', $section);
}

$enrol = enrol_get_plugin('manual');
if ($enrol && !$DB->record_exists('enrol', ['courseid' => $course->id, 'enrol' => 'manual'])) {
    $enrol->add_instance($course);
}

$dosen = $DB->get_record('user', [
    'username' => $dosenlogin,
    'mnethostid' => $CFG->mnet_localhost_id,
    'deleted' => 0,
]);
if ($dosen) {
    $teacherrole = (int) $DB->get_field('role', 'id', ['shortname' => 'editingteacher'], MUST_EXIST);
    enrol_try_internal_enrol($course->id, $dosen->id, $teacherrole);
}

$studentrole = (int) $DB->get_field('role', 'id', ['shortname' => 'student'], MUST_EXIST);
$dummystudents = [
    ['username' => '069999001', 'firstname' => 'Demo', 'lastname' => 'Mahasiswa Satu'],
    ['username' => '069999002', 'firstname' => 'Demo', 'lastname' => 'Mahasiswa Dua'],
    ['username' => '069999003', 'firstname' => 'Demo', 'lastname' => 'Mahasiswa Tiga'],
];
foreach ($dummystudents as $row) {
    $user = $DB->get_record('user', [
        'username' => $row['username'],
        'mnethostid' => $CFG->mnet_localhost_id,
        'deleted' => 0,
    ]);
    if (!$user) {
        $id = user_create_user((object) [
            'username' => $row['username'],
            'auth' => 'manual',
            'password' => 'Ush@' . $row['username'],
            'firstname' => $row['firstname'],
            'lastname' => $row['lastname'],
            'email' => $row['username'] . '@demo.sugenghartono.ac.id',
            'idnumber' => 'DEMO_' . $row['username'],
            'confirmed' => 1,
            'mnethostid' => $CFG->mnet_localhost_id,
            'lang' => 'id',
            'calendartype' => $CFG->calendartype ?? 'gregorian',
            'mailformat' => 1,
            'maildisplay' => 0,
        ], true, false);
        $user = $DB->get_record('user', ['id' => $id], '*', MUST_EXIST);
    }
    enrol_try_internal_enrol($course->id, $user->id, $studentrole);
}

rebuild_course_cache($course->id, true);
$modules = $DB->count_records('course_modules', ['course' => $course->id]);
$students = $DB->count_records_sql(
    "SELECT COUNT(1)
       FROM {user_enrolments} ue
       JOIN {enrol} e ON e.id = ue.enrolid
       JOIN {role_assignments} ra ON ra.userid = ue.userid
       JOIN {context} ctx ON ctx.id = ra.contextid AND ctx.contextlevel = :level AND ctx.instanceid = e.courseid
       JOIN {role} r ON r.id = ra.roleid
      WHERE e.courseid = :courseid AND r.shortname = 'student' AND ue.status = 0",
    ['level' => CONTEXT_COURSE, 'courseid' => $course->id]
);
$now = new DateTime('now', $tz);
foreach ($opens as $n => $when) {
    $open = new DateTime($when, $tz);
    mtrace('Pertemuan ' . $n . ' kunci sampai ' . $open->format('Y-m-d H:i') . ($open > $now ? ' (masih terkunci)' : ' (tanggal sudah lewat)'));
}
mtrace('Selesai. course id ' . $course->id . ' kategori ' . $cat->name);
mtrace('Aktivitas: ' . $modules . '  Mahasiswa: ' . $students);
