<?php
/**
 * Lengkapi MK Aufar (dosen_65) agar sama SIAKAD Absensi, minus KKN.
 * - Buat SBD703 Simulasi Bisnis Digital (kurikulum lama, ada 55 mhs)
 * - Enrol mahasiswa A+B + pengampu Aufar
 * - Lepas IDM0534 dan IUM0012 (tidak ada di Absensi beliau)
 *
 *   php admin/cli/ush_fix_aufar_sbd703.php
 *   php admin/cli/ush_fix_aufar_sbd703.php --confirm
 */
define('CLI_SCRIPT', true);
$ushstartcwd = getcwd();
require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->libdir . '/enrollib.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once(__DIR__ . '/ush_course_owner.php');

$CONFIRM = in_array('--confirm', array_slice($argv, 1), true);
$SUFFIX = '20262027Ganjil';
$LABEL = '2026/2027 - Ganjil';
$START = mktime(0, 0, 0, 9, 1, 2026);
$END = mktime(0, 0, 0, 2, 28, 2027);

$from = $CFG->dirroot . '/peserta_20262027Ganjil.json';
if (!is_readable($from) && $ushstartcwd && is_readable($ushstartcwd . '/peserta_20262027Ganjil.json')) {
    $from = $ushstartcwd . '/peserta_20262027Ganjil.json';
}
$d = json_decode(file_get_contents($from), true);
if (!is_array($d)) {
    mtrace('File peserta tidak terbaca.');
    exit(1);
}

$aufar = $DB->get_record('user', ['username' => 'dosen_65', 'deleted' => 0], '*', MUST_EXIST);
$studentrole = (int) $DB->get_field('role', 'id', ['shortname' => 'student'], MUST_EXIST);
$teacherrole = (int) $DB->get_field('role', 'id', ['shortname' => 'editingteacher'], MUST_EXIST);
$enrolplugin = enrol_get_plugin('manual');
$mnethostid = (int) $CFG->mnet_localhost_id;

$nims = [];
$kelas = [];
foreach ($d['classes'] ?? [] as $k) {
    if ((int) ($k['id_lecture'] ?? 0) !== 65) {
        continue;
    }
    $code = strtoupper(trim((string) ($k['code'] ?? '')));
    $kelas[] = [
        'code' => $code,
        'name' => (string) ($k['lesson_name'] ?? ''),
        'class' => (string) ($k['class_name'] ?? ''),
        'n' => count($k['students'] ?? []),
    ];
    if ($code !== 'SBD703') {
        continue;
    }
    foreach ($k['students'] ?? [] as $s) {
        $nim = preg_replace('/\D+/', '', (string) ($s['nim'] ?? '')) ?? '';
        if ($nim !== '') {
            $nims[$nim] = trim((string) ($s['name'] ?? $nim));
        }
    }
}

mtrace('=== Lengkapi MK Aufar ===');
mtrace('Site: ' . $CFG->wwwroot);
mtrace('Mode: ' . ($CONFIRM ? 'LIVE' : 'DRY-RUN'));
mtrace('Akun: ' . $aufar->username . ' — ' . fullname($aufar));
mtrace('');
mtrace('Absensi SIAKAD id_lecture=65:');
foreach ($kelas as $row) {
    mtrace(sprintf('  %s | %s | %s | %d mhs', $row['code'], $row['class'], $row['name'], $row['n']));
}
mtrace('SBD703 NIM unik: ' . count($nims));
mtrace('');

$cat = $DB->get_record('course_categories', ['idnumber' => 'CAT_SBD_2026_2027_Ganjil']);
if (!$cat) {
    $cat = $DB->get_record_sql(
        "SELECT * FROM {course_categories} WHERE " . $DB->sql_like('name', ':n'),
        ['n' => '%Bisnis Digital%2026/2027%Ganjil%']
    );
}
if (!$cat) {
    mtrace('Folder SBD 2026/2027 Ganjil tidak ada.');
    exit(1);
}

$short = 'SBD703_' . $SUFFIX;
$course = $DB->get_record('course', ['shortname' => $short]);
if (!$course) {
    mtrace('Akan buat kelas ' . $short);
    if ($CONFIRM) {
        $new = (object) [
            'fullname' => 'Simulasi Bisnis Digital — ' . $LABEL,
            'shortname' => $short,
            'idnumber' => $short,
            'category' => (int) $cat->id,
            'visible' => 1,
            'format' => 'topics',
            'numsections' => 16,
            'startdate' => $START,
            'enddate' => $END,
            'summary' => '<p><strong>Simulasi Bisnis Digital</strong> — ' . s($LABEL)
                . '</p><p>Kode: SBD703 | SKS: 3 | Pengampu: Ahmad Aufar Ribhi</p>',
            'summaryformat' => FORMAT_HTML,
            'enablecompletion' => 1,
        ];
        $course = create_course($new);
        if ($enrolplugin && !$DB->record_exists('enrol', ['courseid' => $course->id, 'enrol' => 'manual'])) {
            $enrolplugin->add_instance($course);
        }
        mtrace('  dibuat id=' . $course->id);
    }
} else {
    mtrace('Kelas sudah ada: ' . $short);
}

$enrolmhs = 0;
$enrolteacher = 0;
if (!$course && !$CONFIRM) {
    mtrace('Akan enrol pengampu Aufar → ' . $short);
    $enrolteacher = 1;
    $enrolmhs = count($nims);
    mtrace('Akan enrol ' . $enrolmhs . ' mahasiswa → ' . $short);
} else if ($course) {
    $ctx = context_course::instance($course->id);
    if (!is_enrolled($ctx, $aufar, '', true) || !$DB->record_exists('role_assignments', [
        'roleid' => $teacherrole, 'contextid' => $ctx->id, 'userid' => $aufar->id,
    ])) {
        mtrace('Akan enrol pengampu Aufar → ' . $short);
        if ($CONFIRM && enrol_try_internal_enrol($course->id, $aufar->id, $teacherrole)) {
            $enrolteacher++;
        } else if (!$CONFIRM) {
            $enrolteacher++;
        }
    }
    foreach ($nims as $nim => $nama) {
        $user = $DB->get_record('user', [
            'username' => strtolower($nim),
            'mnethostid' => $mnethostid,
            'deleted' => 0,
        ]);
        if (!$user) {
            mtrace('  akun mhs belum ada: ' . $nim . ' ' . $nama);
            continue;
        }
        if (is_enrolled($ctx, $user, '', true)) {
            continue;
        }
        if ($CONFIRM) {
            if (enrol_try_internal_enrol($course->id, $user->id, $studentrole)) {
                $enrolmhs++;
            }
        } else {
            $enrolmhs++;
        }
    }
}

$extras = ['IDM0534_' . $SUFFIX, 'IUM0012_' . $SUFFIX];
$unenrol = 0;
foreach ($extras as $sn) {
    $extra = $DB->get_record('course', ['shortname' => $sn]);
    if (!$extra) {
        continue;
    }
    $ctx = context_course::instance($extra->id);
    if (!is_enrolled($ctx, $aufar, '', true)) {
        continue;
    }
    mtrace('Akan lepas Aufar dari ' . $sn . ' (bukan MK Absensi beliau)');
    if ($CONFIRM) {
        $instances = enrol_get_instances($extra->id, true);
        foreach ($instances as $instance) {
            $plugin = enrol_get_plugin($instance->enrol);
            if ($plugin) {
                $plugin->unenrol_user($instance, $aufar->id);
            }
        }
        $unenrol++;
    } else {
        $unenrol++;
    }
}

if ($CONFIRM) {
    fix_course_sortorder();
    rebuild_course_cache(0, true);
}

mtrace('');
mtrace('=== RINGKASAN ===');
mtrace('  Enrol mahasiswa SBD703 : ' . $enrolmhs);
mtrace('  Enrol pengampu         : ' . $enrolteacher);
mtrace('  Lepas MK terselip      : ' . $unenrol);
mtrace('  KKN SBD704             : tidak dibuat');
if (!$CONFIRM) {
    mtrace('DRY-RUN. Ulangi dengan --confirm untuk menulis.');
}
