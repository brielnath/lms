<?php
/**
 * Isi kekosongan KRS → LMS 2026/2027 Ganjil.
 * - Buat cangkang MK yang ada mahasiswanya di SIAKAD tapi belum ada di LMS
 *   (kecuali KKN / Skripsi / KP / Seminar Proposal).
 * - Enrol mahasiswa + pengampu yang belum masuk.
 * - Tidak pernah unenrol. Kelas yang sudah ada tetap.
 *
 *   php admin/cli/ush_fill_krs_gaps.php
 *   php admin/cli/ush_fill_krs_gaps.php --confirm
 */
define('CLI_SCRIPT', true);
$ushstartcwd = getcwd();
require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->libdir . '/enrollib.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once(__DIR__ . '/ush_course_owner.php');
require_once(__DIR__ . '/ush_dosen_account.php');

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
$SKIPRE = '/(kkn|skripsi|kerja praktik|praktik kerja|kuliah kerja|seminar proposal)/i';
$PRODIID = [
    24 => 'SBD', 25 => 'SGZ', 26 => 'SIF', 31 => 'MBI', 32 => 'HKM',
    33 => 'TPN', 34 => 'BKI', 35 => 'PAR', 36 => 'ABD',
];

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

function ush_gap_norm(string $code): string {
    $c = strtoupper(trim($code));
    $c = str_replace(['*', ' '], '', $c);
    return preg_replace('/_20\d{6}(GANJIL|GENAP)$/i', '', $c) ?? $c;
}

function ush_gap_parse_short(string $short, string $suffix): array {
    $base = strtoupper((string) preg_replace('/_' . preg_quote($suffix, '/') . '$/i', '', $short));
    $a2 = false;
    if (preg_match('/^(.+)A2$/', $base, $m)) {
        $a2 = true;
        $base = $m[1];
    }
    $known = 'SIF|SBD|SGZ|HKM|MBI|TPN|BKI|PAR|ABD|MKU|FITH|FTHB|S2SBD';
    if (preg_match('/^(.+)_(' . $known . ')(\d{2})$/', $base, $m)) {
        return ['code' => $m[1], 'prodi' => $m[2], 'a2' => $a2];
    }
    if (preg_match('/^(.+)_(' . $known . ')$/', $base, $m)) {
        return ['code' => $m[1], 'prodi' => $m[2], 'a2' => $a2];
    }
    return ['code' => $base, 'prodi' => '', 'a2' => $a2];
}

function ush_gap_find_cat(string $prodi): ?object {
    global $DB, $LABEL;
    $idn = 'CAT_' . $prodi . '_' . str_replace(['/', ' ', '-'], '_', $LABEL);
    $cat = $DB->get_record('course_categories', ['idnumber' => $idn]);
    if ($cat) {
        return $cat;
    }
    $cat = $DB->get_record('course_categories', ['idnumber' => 'CAT_' . $prodi . '_2026_2027_Ganjil']);
    if ($cat) {
        return $cat;
    }
    $cat = $DB->get_record('course_categories', ['idnumber' => $prodi . '_2027']);
    if ($cat) {
        return $cat;
    }
    $labels = ush_prodi_labels();
    $nama = $labels[$prodi] ?? $prodi;
    return $DB->get_record_sql(
        "SELECT * FROM {course_categories} WHERE " . $DB->sql_like('name', ':n'),
        ['n' => '%' . $nama . '%2026/2027%Ganjil%']
    ) ?: null;
}

function ush_gap_split_name(string $full): array {
    $full = trim(preg_replace('/\s+/', ' ', $full) ?? $full);
    $parts = $full === '' ? [] : explode(' ', $full);
    $firstname = array_shift($parts) ?: 'Mahasiswa';
    $lastname = trim(implode(' ', $parts));
    if ($lastname === '') {
        $lastname = 'USH';
    }
    return [$firstname, $lastname];
}

$katalog = [];
$katfile = $CFG->dirroot . '/katalog_20262027Ganjil.json';
if (is_readable($katfile)) {
    $kat = json_decode(file_get_contents($katfile), true);
    foreach (($kat['lessons'] ?? []) as $lesson) {
        $c = ush_gap_norm($lesson['code'] ?? '');
        if ($c !== '') {
            $katalog[$c] = $lesson;
        }
    }
}

$courses = $DB->get_records_sql(
    "SELECT id, shortname, fullname, category FROM {course}
      WHERE id > 1 AND " . $DB->sql_like('shortname', ':p'),
    ['p' => '%' . $SUFFIX]
);
$lms = [];
foreach ($courses as $c) {
    $p = ush_gap_parse_short($c->shortname, $SUFFIX);
    $lms[$p['code']][] = $c;
}

$want = [];
foreach ($d['classes'] as $k) {
    $code = ush_gap_norm($k['code'] ?? '');
    $name = trim((string) ($k['lesson_name'] ?? ''));
    if ($code === '' || preg_match($SKIPRE, $name . ' ' . $code)) {
        continue;
    }
    if (!isset($want[$code])) {
        $want[$code] = [
            'name' => $name !== '' ? $name : (string) ($katalog[$code]['name'] ?? $code),
            'sks' => (int) ($katalog[$code]['sks_total'] ?? 3),
            'students' => [],
            'teachers' => [],
            'prodi' => $PRODIID[(int) ($k['id_prodi'] ?? 0)] ?? ush_prodi_from_code($code),
        ];
    }
    $lec = (int) ($k['id_lecture'] ?? 0);
    if ($lec > 0 && count($k['students'] ?? []) > 0) {
        $want[$code]['teachers'][$lec] = true;
    }
    foreach ($k['students'] ?? [] as $s) {
        $nim = preg_replace('/\D+/', '', (string) ($s['nim'] ?? '')) ?? '';
        if ($nim !== '') {
            $want[$code]['students'][$nim] = [
                'name' => trim((string) ($s['name'] ?? $nim)),
                'prodi' => $PRODIID[(int) ($k['id_prodi'] ?? 0)] ?? '',
            ];
        }
    }
}

$studentrole = (int) $DB->get_field('role', 'id', ['shortname' => 'student'], MUST_EXIST);
$teacherrole = (int) $DB->get_field('role', 'id', ['shortname' => 'editingteacher'], MUST_EXIST);
$enrolplugin = enrol_get_plugin('manual');
$mnethostid = (int) $CFG->mnet_localhost_id;

mtrace('=== Isi kekosongan KRS → LMS ===');
mtrace('Site : ' . $CFG->wwwroot);
mtrace('Mode : ' . ($CONFIRM ? 'LIVE (hanya menambah)' : 'DRY-RUN'));
mtrace('Tidak unenrol. KKN/Skripsi/KP tidak dibuat.');
mtrace('');

$created = 0;
$enrolmhs = 0;
$enrolteach = 0;
$createdusers = 0;
$missinguser = 0;
$already = 0;
$skipempty = 0;

foreach ($want as $code => $info) {
    if (!$info['students']) {
        $skipempty++;
        continue;
    }
    $cands = $lms[$code] ?? [];
    $course = null;
    if (count($cands) === 1) {
        $course = reset($cands);
    } else if (count($cands) > 1) {
        foreach ($cands as $c) {
            if (strcasecmp($c->shortname, $code . '_' . $SUFFIX) === 0) {
                $course = $c;
                break;
            }
        }
    }

    if (!$course && !$cands) {
        $prodi = $info['prodi'] ?: ush_prodi_from_code($code);
        $cat = ush_gap_find_cat($prodi);
        if (!$cat) {
            mtrace("LEWAT $code — folder prodi $prodi tidak ada.");
            continue;
        }
        $short = $code . '_' . $SUFFIX;
        mtrace("BUAT $short | {$info['name']} | " . count($info['students']) . ' mhs | ' . $cat->name);
        if ($CONFIRM) {
            $teacherslabel = [];
            foreach (array_keys($info['teachers']) as $lec) {
                $du = ush_find_dosen_user((int) $lec);
                $teacherslabel[] = $du ? fullname($du) : ('dosen_' . $lec);
            }
            $new = (object) [
                'fullname' => $info['name'] . ' — ' . $LABEL,
                'shortname' => $short,
                'idnumber' => $short,
                'category' => (int) $cat->id,
                'visible' => 1,
                'format' => 'topics',
                'numsections' => 16,
                'startdate' => $START,
                'enddate' => $END,
                'summary' => '<p><strong>' . s($info['name']) . '</strong> — ' . s($LABEL)
                    . '</p><p>Kode: ' . s($code) . ' | SKS: ' . (int) $info['sks']
                    . ' | Pengampu: ' . s(implode(', ', $teacherslabel)) . '</p>',
                'summaryformat' => FORMAT_HTML,
                'enablecompletion' => 1,
            ];
            $course = create_course($new);
            if ($enrolplugin && !$DB->record_exists('enrol', ['courseid' => $course->id, 'enrol' => 'manual'])) {
                $enrolplugin->add_instance($course);
            }
            $lms[$code][] = $course;
            $created++;
        } else {
            $created++;
            $enrolmhs += count($info['students']);
            $enrolteach += count($info['teachers']);
            continue;
        }
    }

    $targets = $lms[$code] ?? [];
    if ($course) {
        $found = false;
        foreach ($targets as $c) {
            if ((int) $c->id === (int) $course->id) {
                $found = true;
                break;
            }
        }
        if (!$found) {
            $targets[] = $course;
        }
    }
    if (!$targets) {
        continue;
    }

    $single = count($targets) === 1 ? reset($targets) : null;
    if ($single && $CONFIRM && $enrolplugin
            && !$DB->record_exists('enrol', ['courseid' => $single->id, 'enrol' => 'manual'])) {
        $enrolplugin->add_instance($single);
    }

    if ($single) {
        $ctx = context_course::instance($single->id);
        foreach (array_keys($info['teachers']) as $lec) {
            $du = ush_find_dosen_user((int) $lec);
            if (!$du) {
                continue;
            }
            $has = is_enrolled($ctx, $du, '', true) && $DB->record_exists('role_assignments', [
                'roleid' => $teacherrole, 'contextid' => $ctx->id, 'userid' => $du->id,
            ]);
            if ($has) {
                continue;
            }
            if ($CONFIRM) {
                if (enrol_try_internal_enrol($single->id, $du->id, $teacherrole)) {
                    $enrolteach++;
                }
            } else {
                $enrolteach++;
            }
        }
    }

    foreach ($info['students'] as $nim => $row) {
        $username = strtolower($nim);
        $user = $DB->get_record('user', [
            'username' => $username,
            'mnethostid' => $mnethostid,
            'deleted' => 0,
        ]);
        if (!$user && $CONFIRM) {
            [$firstname, $lastname] = ush_gap_split_name($row['name']);
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
                mtrace("  gagal buat akun $username: " . $e->getMessage());
                $missinguser++;
                continue;
            }
        }
        if (!$user) {
            $missinguser++;
            continue;
        }

        $onany = false;
        foreach ($targets as $c) {
            $ctx = context_course::instance($c->id);
            if (is_enrolled($ctx, $user, '', true)) {
                $onany = true;
                break;
            }
        }
        if ($onany) {
            $already++;
            continue;
        }

        $target = $single;
        if (!$target) {
            $prodi = $row['prodi'] ?? '';
            foreach ($targets as $c) {
                $parsed = ush_gap_parse_short($c->shortname, $SUFFIX);
                if ($prodi !== '' && $parsed['prodi'] === $prodi) {
                    $target = $c;
                    break;
                }
            }
            if (!$target) {
                foreach ($targets as $c) {
                    if (strcasecmp($c->shortname, $code . '_' . $SUFFIX) === 0) {
                        $target = $c;
                        break;
                    }
                }
            }
        }
        if (!$target) {
            mtrace("  LEWAT $username $code — kelas pecah, tidak ditebak.");
            continue;
        }
        if ($CONFIRM) {
            if ($enrolplugin && !$DB->record_exists('enrol', ['courseid' => $target->id, 'enrol' => 'manual'])) {
                $enrolplugin->add_instance($target);
            }
            if (enrol_try_internal_enrol($target->id, $user->id, $studentrole)) {
                $enrolmhs++;
            }
        } else {
            $enrolmhs++;
        }
    }
}

if ($CONFIRM) {
    fix_course_sortorder();
    rebuild_course_cache(0, true);
}

mtrace('');
mtrace('=== RINGKASAN ===');
mtrace('  Cangkang baru            : ' . $created);
mtrace('  Enrol mahasiswa (tambah) : ' . $enrolmhs);
mtrace('  Enrol pengampu (tambah)  : ' . $enrolteach);
mtrace('  Akun mahasiswa baru      : ' . $createdusers);
mtrace('  Sudah terdaftar (skip)   : ' . $already);
mtrace('  Akun belum ada           : ' . $missinguser);
mtrace('  MK tanpa mahasiswa (skip): ' . $skipempty);
mtrace('  Unenrol                  : 0');
if (!$CONFIRM) {
    mtrace('DRY-RUN. Ulangi dengan --confirm untuk menulis.');
}
