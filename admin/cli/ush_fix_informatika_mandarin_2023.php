<?php
/**
 * Mandarin untuk Informatika angkatan 2023 (semester 7).
 * SIAKAD Absensi/KRS tidak memuat Mandarin (hanya Smart City, AR/VR, KKN, Sempro).
 * Mahasiswa bilang mereka punya Mandarin — cangkang terpisah dari Mandarin V 2024.
 *
 * Peserta = mahasiswa yang sudah di Smart City (SIF1001).
 *
 *   php admin/cli/ush_fix_informatika_mandarin_2023.php
 *   php admin/cli/ush_fix_informatika_mandarin_2023.php --confirm
 */
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->libdir . '/enrollib.php');

$islocal = strpos($CFG->wwwroot, 'localhost') !== false || strpos($CFG->wwwroot, '127.0.0.1') !== false;
$isprod = strpos($CFG->wwwroot, 'lms.ush.ac.id') !== false;
if (!$islocal && !$isprod) {
    mtrace('Dibatalkan: wwwroot bukan LMS lokal atau production. wwwroot=' . $CFG->wwwroot);
    exit(1);
}

$CONFIRM = in_array('--confirm', array_slice($argv, 1), true);
$SUFFIX = '20262027Ganjil';
$LABEL = '2026/2027 - Ganjil';
$START = mktime(0, 0, 0, 9, 1, 2026);
$END = mktime(0, 0, 0, 2, 28, 2027);
$SHORT = 'IUM0009_SIF23_' . $SUFFIX;
$TEACHERUSER = 'dosen_8426';
$SRCSHORT = 'SIF1001_' . $SUFFIX;
$AVSHORT = 'IUM0009_SIF_' . $SUFFIX;

$studentrole = (int) $DB->get_field('role', 'id', ['shortname' => 'student'], MUST_EXIST);
$teacherrole = (int) $DB->get_field('role', 'id', ['shortname' => 'editingteacher'], MUST_EXIST);
$enrolplugin = enrol_get_plugin('manual');
$mnethostid = (int) $CFG->mnet_localhost_id;

$src = $DB->get_record('course', ['shortname' => $SRCSHORT]);
if (!$src) {
    mtrace('Smart City ' . $SRCSHORT . ' belum ada. Buat itu dulu.');
    exit(1);
}

$nims = $DB->get_records_sql(
    "SELECT u.username, u.id, u.firstname, u.lastname
       FROM {user} u
       JOIN {role_assignments} ra ON ra.userid = u.id
       JOIN {context} ctx ON ctx.id = ra.contextid AND ctx.contextlevel = 50
       JOIN {role} r ON r.id = ra.roleid AND r.shortname = 'student'
      WHERE ctx.instanceid = :cid AND u.deleted = 0
      ORDER BY u.username",
    ['cid' => $src->id]
);

$teacher = $DB->get_record('user', [
    'username' => $TEACHERUSER,
    'mnethostid' => $mnethostid,
    'deleted' => 0,
], '*', MUST_EXIST);

$cat = $DB->get_record('course_categories', ['idnumber' => 'CAT_MKU_2026_2027_Ganjil']);
if (!$cat) {
    $cat = $DB->get_record_sql(
        "SELECT * FROM {course_categories} WHERE " . $DB->sql_like('name', ':n'),
        ['n' => '%Mata Kuliah Umum%2026/2027%Ganjil%']
    );
}
if (!$cat) {
    mtrace('Folder MKU 2026/2027 Ganjil tidak ada.');
    exit(1);
}

mtrace('=== Mandarin Informatika 2023 ===');
mtrace('Site: ' . $CFG->wwwroot);
mtrace('Mode: ' . ($CONFIRM ? 'LIVE' : 'DRY-RUN'));
mtrace('Peserta dari Smart City: ' . count($nims));
mtrace('Pengampu: ' . $teacher->username . ' — ' . fullname($teacher));
mtrace('');

$created = 0;
$enrolmhs = 0;
$enrolteacher = 0;
$copiedav = 0;

$course = $DB->get_record('course', ['shortname' => $SHORT]);
if (!$course) {
    mtrace('Akan buat ' . $SHORT);
    $created = 1;
    if ($CONFIRM) {
        $course = create_course((object) [
            'fullname' => 'Mandarin Informatika 2023 — ' . $LABEL,
            'shortname' => $SHORT,
            'idnumber' => $SHORT,
            'category' => (int) $cat->id,
            'visible' => 1,
            'format' => 'topics',
            'numsections' => 16,
            'startdate' => $START,
            'enddate' => $END,
            'summary' => '<p><strong>Mandarin Informatika 2023</strong> — ' . s($LABEL)
                . '</p><p>Kode: IUM0009 | SKS: 0 | MKU | Prodi: Informatika | Angkatan 2023 (semester 7).</p>'
                . '<p>Mandarin Informatika di SIAKAD 0 SKS (sama seperti Mandarin I/III/V). '
                . 'Kelas ini terpisah dari Mandarin V angkatan 2024. Peserta sama dengan Smart City / AR/VR.</p>',
            'summaryformat' => FORMAT_HTML,
            'enablecompletion' => 1,
        ]);
        if ($enrolplugin && !$DB->record_exists('enrol', ['courseid' => $course->id, 'enrol' => 'manual'])) {
            $enrolplugin->add_instance($course);
        }
        mtrace('  dibuat id=' . $course->id);
    }
} else {
    mtrace('Kelas sudah ada: ' . $SHORT . ' id=' . $course->id);
}

if (!$course && !$CONFIRM) {
    $enrolteacher = 1;
    $enrolmhs = count($nims);
    foreach ($nims as $u) {
        mtrace('  enrol ' . $u->username . ' ' . $u->firstname . ' ' . $u->lastname);
    }
}

if ($course) {
    $ctx = context_course::instance($course->id);
    if (!is_enrolled($ctx, $teacher, '', true) || !$DB->record_exists('role_assignments', [
        'roleid' => $teacherrole, 'contextid' => $ctx->id, 'userid' => $teacher->id,
    ])) {
        mtrace('Akan enrol pengampu ' . $TEACHERUSER);
        if ($CONFIRM && enrol_try_internal_enrol($course->id, $teacher->id, $teacherrole)) {
            $enrolteacher++;
        } else if (!$CONFIRM) {
            $enrolteacher++;
        }
    }
    foreach ($nims as $u) {
        if (is_enrolled($ctx, $u, '', true)) {
            continue;
        }
        mtrace('  enrol ' . $u->username . ' ' . $u->firstname . ' ' . $u->lastname);
        if ($CONFIRM && enrol_try_internal_enrol($course->id, $u->id, $studentrole)) {
            $enrolmhs++;
        } else if (!$CONFIRM) {
            $enrolmhs++;
        }
    }

    $srcav = $DB->get_record('course', ['shortname' => $AVSHORT]);
    if ($srcav && $CONFIRM) {
        $fromsecs = $DB->get_records('course_sections', ['course' => $srcav->id], 'section ASC');
        $tosecs = $DB->get_records('course_sections', ['course' => $course->id], 'section ASC');
        $bysec = [];
        foreach ($fromsecs as $s) {
            $bysec[(int) $s->section] = $s;
        }
        foreach ($tosecs as $s) {
            $sec = (int) $s->section;
            if ($sec < 1 || empty($bysec[$sec])) {
                continue;
            }
            $av = $bysec[$sec]->availability;
            if ($av && $av !== $s->availability) {
                $DB->set_field('course_sections', 'availability', $av, ['id' => $s->id]);
                $copiedav++;
            }
        }
    }
}

if ($CONFIRM) {
    fix_course_sortorder();
    rebuild_course_cache(0, true);
}

mtrace('');
mtrace('=== RINGKASAN ===');
mtrace('  Kelas baru     : ' . $created);
mtrace('  Enrol mhs      : ' . $enrolmhs);
mtrace('  Enrol pengampu : ' . $enrolteacher);
mtrace('  Salin jadwal   : ' . $copiedav);
if (!$CONFIRM) {
    mtrace('DRY-RUN. Ulangi dengan --confirm untuk menulis.');
}
