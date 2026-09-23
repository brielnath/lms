<?php
/**
 * Buat kelas kurikulum lama SBD701 dan SBD702 (minus KKN SBD704),
 * lalu enrol mahasiswa + pengampu dari peserta SIAKAD.
 *
 * Dipakai angkatan 2023 Bisnis Digital. Cangkang semester baru
 * hanya kode IDM/IUM/… jadi dua MK ini sempat tidak ada di LMS.
 *
 *   php admin/cli/ush_fix_sbd701_sbd702.php
 *   php admin/cli/ush_fix_sbd701_sbd702.php --confirm
 */
define('CLI_SCRIPT', true);
$ushstartcwd = getcwd();
require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->libdir . '/enrollib.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once(__DIR__ . '/ush_course_owner.php');

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

$from = $CFG->dirroot . '/peserta_20262027Ganjil.json';
if (!is_readable($from) && $ushstartcwd && is_readable($ushstartcwd . '/peserta_20262027Ganjil.json')) {
    $from = $ushstartcwd . '/peserta_20262027Ganjil.json';
}
if (!is_readable($from)) {
    mtrace('File peserta_20262027Ganjil.json tidak ada di folder LMS.');
    exit(1);
}
$d = json_decode(file_get_contents($from), true);
if (!is_array($d) || empty($d['classes'])) {
    mtrace('File peserta tidak terbaca.');
    exit(1);
}

$want = [
    'SBD701' => ['name' => 'Perdagangan Elektronik', 'sks' => 3],
    'SBD702' => ['name' => 'Manajemen Media Sosial', 'sks' => 3],
];

$studentrole = (int) $DB->get_field('role', 'id', ['shortname' => 'student'], MUST_EXIST);
$teacherrole = (int) $DB->get_field('role', 'id', ['shortname' => 'editingteacher'], MUST_EXIST);
$enrolplugin = enrol_get_plugin('manual');
$mnethostid = (int) $CFG->mnet_localhost_id;

$cat = $DB->get_record('course_categories', ['idnumber' => 'CAT_SBD_2026_2027_Ganjil']);
if (!$cat) {
    $cat = $DB->get_record('course_categories', ['idnumber' => 'SBD_2027']);
}
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

$bycode = [];
foreach ($d['classes'] as $k) {
    $code = strtoupper(trim((string) ($k['code'] ?? '')));
    if (!isset($want[$code])) {
        continue;
    }
    if (!isset($bycode[$code])) {
        $bycode[$code] = ['students' => [], 'teachers' => [], 'classes' => []];
    }
    $bycode[$code]['classes'][] = [
        'class' => (string) ($k['class_name'] ?? ''),
        'dosen' => (string) ($k['dosen'] ?? ''),
        'id_lecture' => (int) ($k['id_lecture'] ?? 0),
        'n' => count($k['students'] ?? []),
    ];
    $lec = (int) ($k['id_lecture'] ?? 0);
    if ($lec > 0) {
        $bycode[$code]['teachers'][$lec] = true;
    }
    foreach ($k['students'] ?? [] as $s) {
        $nim = preg_replace('/\D+/', '', (string) ($s['nim'] ?? '')) ?? '';
        if ($nim !== '') {
            $bycode[$code]['students'][$nim] = trim((string) ($s['name'] ?? $nim));
        }
    }
}

mtrace('=== SBD701 / SBD702 kurikulum lama ===');
mtrace('Site : ' . $CFG->wwwroot);
mtrace('Mode : ' . ($CONFIRM ? 'LIVE' : 'DRY-RUN'));
mtrace('Folder: ' . $cat->name . ' id=' . $cat->id);
mtrace('KKN SBD704 tidak dibuat.');
mtrace('');

$created = 0;
$enrolmhs = 0;
$enrolteach = 0;
$missinguser = 0;

foreach ($want as $code => $meta) {
    $info = $bycode[$code] ?? ['students' => [], 'teachers' => [], 'classes' => []];
    mtrace($code . ' ' . $meta['name']);
    foreach ($info['classes'] as $row) {
        mtrace(sprintf('  SIAKAD %s | %s | %d mhs | lecture=%d',
            $row['class'], $row['dosen'], $row['n'], $row['id_lecture']));
    }
    mtrace('  NIM unik: ' . count($info['students']) . ' | pengampu: ' . count($info['teachers']));

    $short = $code . '_' . $SUFFIX;
    $course = $DB->get_record('course', ['shortname' => $short]);
    if (!$course) {
        mtrace('  Akan buat ' . $short);
        if ($CONFIRM) {
            $teacherslabel = [];
            foreach (array_keys($info['teachers']) as $lec) {
                $du = $DB->get_record('user', ['username' => 'dosen_' . $lec, 'deleted' => 0]);
                $teacherslabel[] = $du ? fullname($du) : ('dosen_' . $lec);
            }
            $new = (object) [
                'fullname' => $meta['name'] . ' — ' . $LABEL,
                'shortname' => $short,
                'idnumber' => $short,
                'category' => (int) $cat->id,
                'visible' => 1,
                'format' => 'topics',
                'numsections' => 16,
                'startdate' => $START,
                'enddate' => $END,
                'summary' => '<p><strong>' . s($meta['name']) . '</strong> — ' . s($LABEL)
                    . '</p><p>Kode: ' . s($code) . ' | SKS: ' . (int) $meta['sks']
                    . ' | Pengampu: ' . s(implode(', ', $teacherslabel)) . '</p>',
                'summaryformat' => FORMAT_HTML,
                'enablecompletion' => 1,
            ];
            $course = create_course($new);
            if ($enrolplugin && !$DB->record_exists('enrol', ['courseid' => $course->id, 'enrol' => 'manual'])) {
                $enrolplugin->add_instance($course);
            }
            mtrace('  dibuat id=' . $course->id);
            $created++;
        }
    } else {
        mtrace('  sudah ada id=' . $course->id);
    }

    if (!$course) {
        $enrolteach += count($info['teachers']);
        $enrolmhs += count($info['students']);
        mtrace('');
        continue;
    }

    $ctx = context_course::instance($course->id);
    if ($CONFIRM && $enrolplugin && !$DB->record_exists('enrol', ['courseid' => $course->id, 'enrol' => 'manual'])) {
        $enrolplugin->add_instance($course);
    }

    foreach (array_keys($info['teachers']) as $lec) {
        $du = $DB->get_record('user', [
            'username' => 'dosen_' . $lec,
            'mnethostid' => $mnethostid,
            'deleted' => 0,
        ]);
        if (!$du) {
            mtrace('  akun dosen belum ada: dosen_' . $lec);
            continue;
        }
        $has = is_enrolled($ctx, $du, '', true) && $DB->record_exists('role_assignments', [
            'roleid' => $teacherrole, 'contextid' => $ctx->id, 'userid' => $du->id,
        ]);
        if ($has) {
            continue;
        }
        mtrace('  pengampu ' . $du->username . ' → ' . $short);
        if ($CONFIRM) {
            if (enrol_try_internal_enrol($course->id, $du->id, $teacherrole)) {
                $enrolteach++;
            }
        } else {
            $enrolteach++;
        }
    }

    foreach ($info['students'] as $nim => $nama) {
        $user = $DB->get_record('user', [
            'username' => strtolower($nim),
            'mnethostid' => $mnethostid,
            'deleted' => 0,
        ]);
        if (!$user) {
            $missinguser++;
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
    mtrace('');
}

if ($CONFIRM) {
    fix_course_sortorder();
    rebuild_course_cache(0, true);
}

mtrace('=== RINGKASAN ===');
mtrace('  Kelas baru     : ' . $created);
mtrace('  Enrol mahasiswa: ' . $enrolmhs);
mtrace('  Enrol pengampu : ' . $enrolteach);
mtrace('  Akun mhs belum : ' . $missinguser);
mtrace('  KKN SBD704     : tidak dibuat');
if (!$CONFIRM) {
    mtrace('DRY-RUN. Ulangi dengan --confirm untuk menulis.');
}
