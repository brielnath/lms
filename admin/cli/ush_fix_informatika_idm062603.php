<?php
/**
 * Lengkapi Digital Business Development Informatika (id_prodi 26).
 * SIAKAD dasbor dosen: milik Aufar (dosen_65), 13 mhs angkatan 2026.
 * id_lecture Absensi (Nimas / 70) tidak dipakai. Cangkang kosong IDM0603 disembunyikan.
 *
 *   php admin/cli/ush_fix_informatika_idm062603.php
 *   php admin/cli/ush_fix_informatika_idm062603.php --confirm
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
$CODE = 'IDM062603';
$EMPTYCODE = 'IDM0603';
$TEACHERUSER = 'dosen_65';
$WRONGTEACHER = 'dosen_70';

$from = $CFG->dirroot . '/peserta_20262027Ganjil.json';
if (!is_readable($from) && $ushstartcwd && is_readable($ushstartcwd . '/peserta_20262027Ganjil.json')) {
    $from = $ushstartcwd . '/peserta_20262027Ganjil.json';
}
if (!is_readable($from)) {
    mtrace('File peserta_20262027Ganjil.json tidak terbaca.');
    exit(1);
}
$d = json_decode(file_get_contents($from), true);
if (!is_array($d)) {
    mtrace('File peserta tidak valid.');
    exit(1);
}

$nims = [];
$dosenname = 'Ahmad Aufar Ribhi';
$found = false;
foreach ($d['classes'] ?? [] as $k) {
    $code = strtoupper(trim((string) ($k['code'] ?? '')));
    if ($code !== $CODE) {
        continue;
    }
    $found = true;
    foreach ($k['students'] ?? [] as $s) {
        $nim = preg_replace('/\D+/', '', (string) ($s['nim'] ?? '')) ?? '';
        if ($nim !== '') {
            $nims[$nim] = trim((string) ($s['name'] ?? $nim));
        }
    }
}
if (!$found) {
    mtrace('Kelas ' . $CODE . ' tidak ada di file peserta.');
    exit(1);
}

mtrace('=== Digital Business Development Informatika ===');
mtrace('Site: ' . $CFG->wwwroot);
mtrace('Mode: ' . ($CONFIRM ? 'LIVE' : 'DRY-RUN'));
mtrace('Kode: ' . $CODE . ' | mahasiswa: ' . count($nims) . ' | pengampu: ' . $dosenname);
mtrace('');

$studentrole = (int) $DB->get_field('role', 'id', ['shortname' => 'student'], MUST_EXIST);
$teacherrole = (int) $DB->get_field('role', 'id', ['shortname' => 'editingteacher'], MUST_EXIST);
$enrolplugin = enrol_get_plugin('manual');
$mnethostid = (int) $CFG->mnet_localhost_id;

$teacher = $DB->get_record('user', ['username' => $TEACHERUSER, 'mnethostid' => $mnethostid, 'deleted' => 0]);
if (!$teacher && $CONFIRM) {
    $parts = preg_split('/\s+/', $dosenname) ?: ['Ahmad'];
    $firstname = array_shift($parts) ?: 'Ahmad';
    $lastname = trim(implode(' ', $parts));
    if ($lastname === '') {
        $lastname = 'USH';
    }
    $newid = user_create_user((object) [
        'username' => $TEACHERUSER,
        'auth' => 'manual',
        'password' => 'DosenUSH2026!',
        'firstname' => $firstname,
        'lastname' => $lastname,
        'email' => 'dosen.65@sugenghartono.ac.id',
        'confirmed' => 1,
        'mnethostid' => $mnethostid,
        'lang' => 'id',
        'calendartype' => $CFG->calendartype ?? 'gregorian',
        'mailformat' => 1,
        'maildisplay' => 2,
    ], true, false);
    $teacher = $DB->get_record('user', ['id' => $newid], '*', MUST_EXIST);
    mtrace('Akun pengampu dibuat: ' . $TEACHERUSER);
} else if (!$teacher) {
    mtrace('Akan buat akun pengampu ' . $TEACHERUSER . ' — ' . $dosenname);
}

$cat = $DB->get_record('course_categories', ['idnumber' => 'CAT_SIF_2026_2027_Ganjil']);
if (!$cat) {
    $cat = $DB->get_record_sql(
        "SELECT * FROM {course_categories} WHERE " . $DB->sql_like('name', ':n'),
        ['n' => '%Sistem Informasi (SIF) - 2026/2027%Ganjil%']
    );
}
if (!$cat) {
    mtrace('Folder Sistem Informasi / Informatika 2026/2027 Ganjil tidak ada.');
    exit(1);
}

$short = $CODE . '_' . $SUFFIX;
$course = $DB->get_record('course', ['shortname' => $short]);
$created = 0;
if (!$course) {
    mtrace('Akan buat kelas ' . $short . ' di ' . $cat->name);
    if ($CONFIRM) {
        $course = create_course((object) [
            'fullname' => 'Digital Business Development — ' . $LABEL,
            'shortname' => $short,
            'idnumber' => $short,
            'category' => (int) $cat->id,
            'visible' => 1,
            'format' => 'topics',
            'numsections' => 16,
            'startdate' => $START,
            'enddate' => $END,
            'summary' => '<p><strong>Digital Business Development</strong> — ' . s($LABEL)
                . '</p><p>Kode: ' . s($CODE) . ' | SKS: 3 | Prodi: Informatika</p>',
            'summaryformat' => FORMAT_HTML,
            'enablecompletion' => 1,
        ]);
        if ($enrolplugin && !$DB->record_exists('enrol', ['courseid' => $course->id, 'enrol' => 'manual'])) {
            $enrolplugin->add_instance($course);
        }
        $created = 1;
        mtrace('  dibuat id=' . $course->id);
    }
} else {
    mtrace('Kelas sudah ada: ' . $short);
    if ((int) $course->category !== (int) $cat->id) {
        mtrace('Akan pindah ke folder ' . $cat->name);
        if ($CONFIRM) {
            $course->category = (int) $cat->id;
            update_course($course);
        }
    }
    if ((int) $course->visible !== 1) {
        mtrace('Akan tampilkan kelas (sekarang tersembunyi)');
        if ($CONFIRM) {
            $course->visible = 1;
            update_course($course);
        }
    }
}

function ush_dbd_split_name(string $full): array {
    $full = trim(preg_replace('/\s+/', ' ', $full) ?? $full);
    $parts = $full === '' ? [] : explode(' ', $full);
    $firstname = array_shift($parts) ?: 'Mahasiswa';
    $lastname = trim(implode(' ', $parts));
    if ($lastname === '') {
        $lastname = 'USH';
    }
    return [$firstname, $lastname];
}

$enrolmhs = 0;
$enrolteacher = 0;
$createdusers = 0;
$unenrolwrong = 0;
if ($course) {
    $ctx = context_course::instance($course->id);
    $wrong = $DB->get_record('user', [
        'username' => $WRONGTEACHER,
        'mnethostid' => $mnethostid,
        'deleted' => 0,
    ]);
    if ($wrong && is_enrolled($ctx, $wrong, '', true)) {
        mtrace('Akan lepas ' . $WRONGTEACHER . ' (bukan pengampu dasbor) dari ' . $short);
        if ($CONFIRM) {
            foreach (enrol_get_instances($course->id, true) as $instance) {
                $plugin = enrol_get_plugin($instance->enrol);
                if ($plugin) {
                    $plugin->unenrol_user($instance, $wrong->id);
                }
            }
            $unenrolwrong = 1;
        } else {
            $unenrolwrong = 1;
        }
    }
    if ($teacher && (!is_enrolled($ctx, $teacher, '', true) || !$DB->record_exists('role_assignments', [
        'roleid' => $teacherrole, 'contextid' => $ctx->id, 'userid' => $teacher->id,
    ]))) {
        mtrace('Akan enrol pengampu Aufar ' . $TEACHERUSER . ' → ' . $short);
        if ($CONFIRM && enrol_try_internal_enrol($course->id, $teacher->id, $teacherrole)) {
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
        if ($user && is_enrolled($ctx, $user, '', true)) {
            continue;
        }
        if (!$CONFIRM) {
            $enrolmhs++;
            if (!$user) {
                mtrace('  akan buat akun ' . $nim . ' ' . $nama);
            }
            continue;
        }
        if (!$user) {
            [$firstname, $lastname] = ush_dbd_split_name($nama);
            $username = strtolower($nim);
            $email = $username . '@sugenghartono.ac.id';
            if ($DB->record_exists('user', ['email' => $email, 'deleted' => 0])) {
                $email = $username . '.' . substr(sha1($nim), 0, 6) . '@sugenghartono.ac.id';
            }
            try {
                $newid = user_create_user((object) [
                    'username' => $username,
                    'auth' => 'manual',
                    'password' => 'Ush@' . $nim,
                    'firstname' => $firstname,
                    'lastname' => $lastname,
                    'email' => $email,
                    'idnumber' => $nim,
                    'city' => 'Sukoharjo',
                    'country' => 'ID',
                    'institution' => 'Universitas Sugeng Hartono',
                    'department' => 'Mahasiswa',
                    'confirmed' => 1,
                    'mnethostid' => $mnethostid,
                    'lang' => 'id',
                    'calendartype' => $CFG->calendartype ?? 'gregorian',
                    'mailformat' => 1,
                    'maildisplay' => 0,
                ], true, false);
                $user = $DB->get_record('user', ['id' => $newid], '*', MUST_EXIST);
                $createdusers++;
            } catch (Throwable $e) {
                mtrace('  gagal akun ' . $nim . ': ' . $e->getMessage());
                continue;
            }
        }
        if (enrol_try_internal_enrol($course->id, $user->id, $studentrole)) {
            $enrolmhs++;
        }
    }
} else if (!$CONFIRM) {
    $enrolteacher = $teacher ? 1 : 0;
    $enrolmhs = count($nims);
}

$empty = $DB->get_record('course', ['shortname' => $EMPTYCODE . '_' . $SUFFIX]);
$hidden = 0;
if ($empty && (int) $empty->visible === 1) {
    $nempty = $DB->count_records_sql(
        "SELECT COUNT(DISTINCT ue.userid)
           FROM {user_enrolments} ue
           JOIN {enrol} e ON e.id = ue.enrolid
           JOIN {role_assignments} ra ON ra.userid = ue.userid
           JOIN {context} ctx ON ctx.id = ra.contextid AND ctx.instanceid = e.courseid AND ctx.contextlevel = 50
           JOIN {role} r ON r.id = ra.roleid AND r.shortname = 'student'
          WHERE e.courseid = :cid AND ue.status = 0",
        ['cid' => $empty->id]
    );
    if ((int) $nempty === 0) {
        mtrace('Akan sembunyikan cangkang kosong ' . $empty->shortname . ' (0 mahasiswa)');
        if ($CONFIRM) {
            $empty->visible = 0;
            update_course($empty);
            $hidden = 1;
        } else {
            $hidden = 1;
        }
    }
}

if ($CONFIRM) {
    fix_course_sortorder();
    rebuild_course_cache(0, true);
}

mtrace('');
mtrace('=== RINGKASAN ===');
mtrace('  Kelas baru              : ' . $created);
mtrace('  Enrol mahasiswa         : ' . $enrolmhs);
mtrace('  Akun mahasiswa dibuat   : ' . $createdusers);
mtrace('  Enrol pengampu Aufar    : ' . $enrolteacher);
mtrace('  Lepas Nimas             : ' . $unenrolwrong);
mtrace('  Sembunyikan ' . $EMPTYCODE . ' kosong : ' . $hidden);
if (!$CONFIRM) {
    mtrace('DRY-RUN. Ulangi dengan --confirm untuk menulis.');
}
