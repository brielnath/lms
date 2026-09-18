<?php
/**
 * Pisah Business Intelligence IDM0515 A1/A2.
 * LMS sempat gabung 81 mhs. SIAKAD: A1 Dwi Utari 40 mhs, A2 Nimas 41 mhs.
 *
 *   php admin/cli/ush_fix_dwi_idm0515.php
 *   php admin/cli/ush_fix_dwi_idm0515.php --confirm
 */
define('CLI_SCRIPT', true);
$ushstartcwd = getcwd();
require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->libdir . '/enrollib.php');
require_once($CFG->dirroot . '/user/lib.php');

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

$a1 = [];
$a2 = [];
foreach ($d['classes'] ?? [] as $k) {
    $code = strtoupper(trim((string) ($k['code'] ?? '')));
    $name = (string) ($k['lesson_name'] ?? '');
    if ($code !== 'IDM0515' || stripos($name, 'Business Intelligence') === false) {
        continue;
    }
    $class = (string) ($k['class_name'] ?? '');
    $dosen = (string) ($k['dosen'] ?? '');
    $bucket = null;
    if (stripos($class, 'A1') !== false || stripos($dosen, 'Dwi Utari') !== false) {
        $bucket = 'a1';
    }
    if (stripos($class, 'A2') !== false || (stripos($dosen, 'Nimas') !== false && stripos($class, 'A1') === false)) {
        $bucket = 'a2';
    }
    if ($bucket === null) {
        continue;
    }
    foreach ($k['students'] ?? [] as $s) {
        $nim = preg_replace('/\D+/', '', (string) ($s['nim'] ?? '')) ?? '';
        if ($nim === '') {
            continue;
        }
        if ($bucket === 'a1') {
            $a1[$nim] = trim((string) ($s['name'] ?? $nim));
        } else {
            $a2[$nim] = trim((string) ($s['name'] ?? $nim));
        }
    }
}

$both = array_intersect_key($a1, $a2);
mtrace('=== Pisah Business Intelligence IDM0515 ===');
mtrace('Site: ' . $CFG->wwwroot);
mtrace('Mode: ' . ($CONFIRM ? 'LIVE' : 'DRY-RUN'));
mtrace('A1 Dwi Utari : ' . count($a1) . ' mhs');
mtrace('A2 Nimas     : ' . count($a2) . ' mhs');
mtrace('NIM di A1+A2 : ' . count($both));
mtrace('');

if (count($a1) < 30 || count($a2) < 30) {
    mtrace('Jumlah A1/A2 tidak wajar. Berhenti.');
    exit(1);
}

$dwi = $DB->get_record('user', ['username' => 'dosen_64', 'deleted' => 0], '*', MUST_EXIST);
$nimas = $DB->get_record('user', ['username' => 'dosen_70', 'deleted' => 0], '*', MUST_EXIST);
$studentrole = (int) $DB->get_field('role', 'id', ['shortname' => 'student'], MUST_EXIST);
$teacherrole = (int) $DB->get_field('role', 'id', ['shortname' => 'editingteacher'], MUST_EXIST);
$enrolplugin = enrol_get_plugin('manual');
$mnethostid = (int) $CFG->mnet_localhost_id;

function ush_bi_unenrol(int $courseid, int $userid): void {
    foreach (enrol_get_instances($courseid, true) as $instance) {
        $plugin = enrol_get_plugin($instance->enrol);
        if ($plugin) {
            $plugin->unenrol_user($instance, $userid);
        }
    }
}

$cat = $DB->get_record('course_categories', ['idnumber' => 'CAT_SBD_2026_2027_Ganjil']);
if (!$cat) {
    $cat = $DB->get_record_sql(
        "SELECT * FROM {course_categories} WHERE " . $DB->sql_like('name', ':n'),
        ['n' => '%Bisnis Digital (SBD) - 2026/2027%Ganjil%']
    );
}
if (!$cat) {
    mtrace('Folder Bisnis Digital 2026/2027 Ganjil tidak ada.');
    exit(1);
}

$shorta1 = 'IDM0515_' . $SUFFIX;
$shorta2 = 'IDM0515A2_' . $SUFFIX;
$coursea1 = $DB->get_record('course', ['shortname' => $shorta1]);
if (!$coursea1) {
    mtrace('Kelas A1 belum ada: ' . $shorta1);
    exit(1);
}

if ($CONFIRM && $coursea1->fullname !== 'Business Intelligence A1 — ' . $LABEL) {
    $coursea1->fullname = 'Business Intelligence A1 — ' . $LABEL;
    update_course($coursea1);
    mtrace('Nama A1 disesuaikan: ' . $coursea1->fullname);
} else if ($coursea1->fullname !== 'Business Intelligence A1 — ' . $LABEL) {
    mtrace('Akan rename A1 → Business Intelligence A1 — ' . $LABEL);
}

$coursea2 = $DB->get_record('course', ['shortname' => $shorta2]);
$createda2 = 0;
if (!$coursea2) {
    mtrace('Akan buat kelas A2 ' . $shorta2);
    if ($CONFIRM) {
        $coursea2 = create_course((object) [
            'fullname' => 'Business Intelligence A2 — ' . $LABEL,
            'shortname' => $shorta2,
            'idnumber' => $shorta2,
            'category' => (int) $cat->id,
            'visible' => 1,
            'format' => 'topics',
            'numsections' => 16,
            'startdate' => $START,
            'enddate' => $END,
            'summary' => '<p><strong>Business Intelligence A2</strong> — ' . s($LABEL)
                . '</p><p>Kode: IDM0515 | SKS: 3 | Pengampu: Nimas Ratna Sari</p>',
            'summaryformat' => FORMAT_HTML,
            'enablecompletion' => 1,
        ]);
        if ($enrolplugin && !$DB->record_exists('enrol', ['courseid' => $coursea2->id, 'enrol' => 'manual'])) {
            $enrolplugin->add_instance($coursea2);
        }
        $createda2 = 1;
        mtrace('  dibuat id=' . $coursea2->id);
    }
} else {
    mtrace('Kelas A2 sudah ada: ' . $shorta2);
}

$unenrolnimas = 0;
$unenrolmhs = 0;
$enrola1dwi = 0;
$enrola2nimas = 0;
$enrola2mhs = 0;
$missing = 0;

$ctxa1 = context_course::instance($coursea1->id);

if (is_enrolled($ctxa1, $nimas, '', true)) {
    mtrace('Akan lepas Nimas dari A1 ' . $shorta1);
    if ($CONFIRM) {
        ush_bi_unenrol((int) $coursea1->id, (int) $nimas->id);
        $unenrolnimas = 1;
    } else {
        $unenrolnimas = 1;
    }
}

if (!is_enrolled($ctxa1, $dwi, '', true) || !$DB->record_exists('role_assignments', [
    'roleid' => $teacherrole, 'contextid' => $ctxa1->id, 'userid' => $dwi->id,
])) {
    mtrace('Akan enrol Dwi Utari ke A1');
    if ($CONFIRM && enrol_try_internal_enrol($coursea1->id, $dwi->id, $teacherrole)) {
        $enrola1dwi = 1;
    } else if (!$CONFIRM) {
        $enrola1dwi = 1;
    }
}

$studentsa1 = $DB->get_records_sql(
    "SELECT DISTINCT u.id, u.username
       FROM {user_enrolments} ue
       JOIN {enrol} e ON e.id = ue.enrolid
       JOIN {user} u ON u.id = ue.userid AND u.deleted = 0
       JOIN {role_assignments} ra ON ra.userid = u.id
       JOIN {context} ctx ON ctx.id = ra.contextid AND ctx.instanceid = e.courseid AND ctx.contextlevel = 50
       JOIN {role} r ON r.id = ra.roleid AND r.shortname = 'student'
      WHERE e.courseid = :cid AND ue.status = 0",
    ['cid' => $coursea1->id]
);
foreach ($studentsa1 as $u) {
    $nim = preg_replace('/\D+/', '', $u->username) ?? '';
    if ($nim !== '' && isset($a1[$nim])) {
        continue;
    }
    mtrace('  lepas dari A1: ' . $u->username);
    if ($CONFIRM) {
        ush_bi_unenrol((int) $coursea1->id, (int) $u->id);
    }
    $unenrolmhs++;
}

if ($coursea2) {
    $ctxa2 = context_course::instance($coursea2->id);
    if (!is_enrolled($ctxa2, $nimas, '', true) || !$DB->record_exists('role_assignments', [
        'roleid' => $teacherrole, 'contextid' => $ctxa2->id, 'userid' => $nimas->id,
    ])) {
        mtrace('Akan enrol Nimas ke A2 ' . $shorta2);
        if ($CONFIRM && enrol_try_internal_enrol($coursea2->id, $nimas->id, $teacherrole)) {
            $enrola2nimas = 1;
        } else if (!$CONFIRM) {
            $enrola2nimas = 1;
        }
    }
    foreach ($a2 as $nim => $nama) {
        $user = $DB->get_record('user', [
            'username' => strtolower($nim),
            'mnethostid' => $mnethostid,
            'deleted' => 0,
        ]);
        if (!$user) {
            mtrace('  akun belum ada: ' . $nim . ' ' . $nama);
            $missing++;
            continue;
        }
        if (is_enrolled($ctxa2, $user, '', true)) {
            continue;
        }
        if ($CONFIRM) {
            if (enrol_try_internal_enrol($coursea2->id, $user->id, $studentrole)) {
                $enrola2mhs++;
            }
        } else {
            $enrola2mhs++;
        }
    }
} else if (!$CONFIRM) {
    $enrola2nimas = 1;
    $enrola2mhs = count($a2);
}

if ($CONFIRM) {
    fix_course_sortorder();
    rebuild_course_cache(0, true);
}

mtrace('');
mtrace('=== RINGKASAN ===');
mtrace('  Kelas A2 baru        : ' . $createda2);
mtrace('  Lepas Nimas dari A1  : ' . $unenrolnimas);
mtrace('  Lepas mhs bukan A1   : ' . $unenrolmhs);
mtrace('  Enrol Dwi ke A1      : ' . $enrola1dwi);
mtrace('  Enrol Nimas ke A2    : ' . $enrola2nimas);
mtrace('  Enrol mhs ke A2      : ' . $enrola2mhs);
mtrace('  Akun A2 belum ada    : ' . $missing);
if (!$CONFIRM) {
    mtrace('DRY-RUN. Ulangi dengan --confirm untuk menulis.');
}
