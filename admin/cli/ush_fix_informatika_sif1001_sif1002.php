<?php
/**
 * Buat Smart City (SIF1001) dan AR/VR (SIF1002) untuk Informatika angkatan 2023.
 * Sumber: Absensi SIAKAD (peserta_20262027Ganjil.json). Kelas kosong "Reguler B" dilewati.
 * KKN / Seminar Proposal tidak dibuat. Mandarin 2023 tidak ada di Absensi — hanya dilaporkan.
 *
 *   php admin/cli/ush_fix_informatika_sif1001_sif1002.php
 *   php admin/cli/ush_fix_informatika_sif1001_sif1002.php --confirm
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
    mtrace('File peserta_20262027Ganjil.json tidak terbaca.');
    exit(1);
}
$d = json_decode(file_get_contents($from), true);
if (!is_array($d)) {
    mtrace('File peserta tidak valid.');
    exit(1);
}

$want = [
    'SIF1001' => [
        'title' => 'Smart City',
        'sks' => 4,
        'teacheruser' => 'dosen_8431',
        'teachername' => 'Ardy Wicaksono',
    ],
    'SIF1002' => [
        'title' => 'AR/VR',
        'sks' => 4,
        'teacheruser' => 'dosen_8429',
        'teachername' => 'Suyahman',
    ],
];

$bycode = [];
$mandarin23 = [];
foreach ($d['classes'] ?? [] as $k) {
    $code = strtoupper(trim((string) ($k['code'] ?? '')));
    $lname = (string) ($k['lesson_name'] ?? '');
    $class = (string) ($k['class_name'] ?? '');
    $dosen = (string) ($k['dosen'] ?? '');
    $students = $k['students'] ?? [];
    if (isset($want[$code])) {
        $nims = [];
        foreach ($students as $s) {
            $nim = preg_replace('/\D+/', '', (string) ($s['nim'] ?? '')) ?? '';
            if ($nim !== '') {
                $nims[$nim] = trim((string) ($s['name'] ?? $nim));
            }
        }
        $bycode[$code][] = [
            'class' => $class,
            'dosen' => $dosen,
            'nims' => $nims,
        ];
    }
    if (stripos($lname, 'mandarin') !== false) {
        foreach ($students as $s) {
            $nim = preg_replace('/\D+/', '', (string) ($s['nim'] ?? '')) ?? '';
            if (str_starts_with($nim, '0623')) {
                $mandarin23[] = $code . ' | ' . $lname . ' | ' . $class . ' | ' . $nim;
            }
        }
    }
}

$studentrole = (int) $DB->get_field('role', 'id', ['shortname' => 'student'], MUST_EXIST);
$teacherrole = (int) $DB->get_field('role', 'id', ['shortname' => 'editingteacher'], MUST_EXIST);
$enrolplugin = enrol_get_plugin('manual');
$mnethostid = (int) $CFG->mnet_localhost_id;

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

function ush_sif23_split_name(string $full): array {
    $full = trim(preg_replace('/\s+/', ' ', $full) ?? $full);
    $parts = $full === '' ? [] : explode(' ', $full);
    $firstname = array_shift($parts) ?: 'Mahasiswa';
    $lastname = trim(implode(' ', $parts));
    if ($lastname === '') {
        $lastname = 'USH';
    }
    return [$firstname, $lastname];
}

function ush_sif23_ensure_teacher(string $username, string $displayname, bool $confirm, int $mnethostid): ?stdClass {
    global $DB, $CFG;
    $teacher = $DB->get_record('user', [
        'username' => $username,
        'mnethostid' => $mnethostid,
        'deleted' => 0,
    ]);
    if ($teacher) {
        return $teacher;
    }
    if (!$confirm) {
        mtrace('  Akan buat akun pengampu ' . $username . ' — ' . $displayname);
        return null;
    }
    [$firstname, $lastname] = ush_sif23_split_name($displayname);
    $idnum = preg_replace('/\D+/', '', $username) ?? '';
    $newid = user_create_user((object) [
        'username' => $username,
        'auth' => 'manual',
        'password' => 'DosenUSH2026!',
        'firstname' => $firstname,
        'lastname' => $lastname,
        'email' => 'dosen.' . $idnum . '@sugenghartono.ac.id',
        'confirmed' => 1,
        'mnethostid' => $mnethostid,
        'lang' => 'id',
        'calendartype' => $CFG->calendartype ?? 'gregorian',
        'mailformat' => 1,
        'maildisplay' => 2,
    ], true, false);
    return $DB->get_record('user', ['id' => $newid], '*', MUST_EXIST);
}

mtrace('=== Smart City + AR/VR Informatika 2023 ===');
mtrace('Site: ' . $CFG->wwwroot);
mtrace('Mode: ' . ($CONFIRM ? 'LIVE' : 'DRY-RUN'));
mtrace('Folder: ' . $cat->name);
mtrace('');

$created = 0;
$enrolmhs = 0;
$enrolteacher = 0;
$createdusers = 0;

foreach ($want as $code => $meta) {
    $rows = $bycode[$code] ?? [];
    $nims = [];
    $dosenabsen = $meta['teachername'];
    foreach ($rows as $row) {
        if (!$row['nims']) {
            mtrace(sprintf('  Lewati %s kelas kosong: %s', $code, $row['class']));
            continue;
        }
        if ($row['dosen'] !== '') {
            $dosenabsen = $row['dosen'];
        }
        foreach ($row['nims'] as $nim => $nama) {
            $nims[$nim] = $nama;
        }
        mtrace(sprintf('  Absensi %s | %s | %s | %d mhs', $code, $row['class'], $row['dosen'], count($row['nims'])));
    }
    if (!$nims) {
        mtrace('  ' . $code . ' tidak ada mahasiswa di Absensi. Lewati.');
        continue;
    }

    $teacher = ush_sif23_ensure_teacher($meta['teacheruser'], $meta['teachername'], $CONFIRM, $mnethostid);
    $short = $code . '_' . $SUFFIX;
    $course = $DB->get_record('course', ['shortname' => $short]);
    if (!$course) {
        mtrace('  Akan buat kelas ' . $short . ' — ' . $meta['title']);
        $created++;
        if ($CONFIRM) {
            $course = create_course((object) [
                'fullname' => $meta['title'] . ' — ' . $LABEL,
                'shortname' => $short,
                'idnumber' => $short,
                'category' => (int) $cat->id,
                'visible' => 1,
                'format' => 'topics',
                'numsections' => 16,
                'startdate' => $START,
                'enddate' => $END,
                'summary' => '<p><strong>' . s($meta['title']) . '</strong> — ' . s($LABEL)
                    . '</p><p>Kode: ' . s($code) . ' | SKS: ' . (int) $meta['sks']
                    . ' | Prodi: Informatika | Pengampu: ' . s($dosenabsen) . '</p>',
                'summaryformat' => FORMAT_HTML,
                'enablecompletion' => 1,
            ]);
            if ($enrolplugin && !$DB->record_exists('enrol', ['courseid' => $course->id, 'enrol' => 'manual'])) {
                $enrolplugin->add_instance($course);
            }
            mtrace('    dibuat id=' . $course->id);
        }
    } else {
        mtrace('  Kelas sudah ada: ' . $short . ' id=' . $course->id);
        if ((int) $course->category !== (int) $cat->id && $CONFIRM) {
            $course->category = (int) $cat->id;
            update_course($course);
            mtrace('    dipindah ke ' . $cat->name);
        }
        if ((int) $course->visible !== 1 && $CONFIRM) {
            $course->visible = 1;
            update_course($course);
            mtrace('    ditampilkan');
        }
    }

    if (!$course) {
        $enrolteacher += $teacher ? 1 : 0;
        $enrolmhs += count($nims);
        continue;
    }

    $ctx = context_course::instance($course->id);
    if ($teacher && (!is_enrolled($ctx, $teacher, '', true) || !$DB->record_exists('role_assignments', [
        'roleid' => $teacherrole, 'contextid' => $ctx->id, 'userid' => $teacher->id,
    ]))) {
        mtrace('  Akan enrol pengampu ' . $meta['teacheruser'] . ' → ' . $short);
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
                mtrace('    akan buat akun ' . $nim . ' ' . $nama);
            }
            continue;
        }
        if (!$user) {
            [$firstname, $lastname] = ush_sif23_split_name($nama);
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
                mtrace('    gagal akun ' . $nim . ': ' . $e->getMessage());
                continue;
            }
        }
        if (enrol_try_internal_enrol($course->id, $user->id, $studentrole)) {
            $enrolmhs++;
        }
    }
    mtrace('  Mahasiswa unik ' . $code . ': ' . count($nims));
    mtrace('');
}

if ($CONFIRM) {
    fix_course_sortorder();
    rebuild_course_cache(0, true);
}

mtrace('=== RINGKASAN ===');
mtrace('  Kelas baru            : ' . $created);
mtrace('  Enrol mahasiswa       : ' . $enrolmhs);
mtrace('  Akun mahasiswa dibuat : ' . $createdusers);
mtrace('  Enrol pengampu        : ' . $enrolteacher);
mtrace('  Mandarin angkatan 2023 di Absensi: ' . count($mandarin23));
if ($mandarin23) {
    foreach (array_slice($mandarin23, 0, 20) as $line) {
        mtrace('    ' . $line);
    }
} else {
    mtrace('  Tidak ada KRS Mandarin untuk NIM 0623* (I/III/V sudah untuk 2026/2025/2024).');
    mtrace('  Kelas Mandarin Informatika yang sudah ada: IUM002605_SIF, IUM0007_SIF, IUM0009_SIF.');
}
if (!$CONFIRM) {
    mtrace('DRY-RUN. Ulangi dengan --confirm untuk menulis.');
}
