<?php
/**
 * Rapikan 2026/2027 Ganjil agar SIAKAD ≈ LMS:
 * - pecah MKU/IFM per prodi
 * - buat DUM diploma yang belum ada, pindah DDM/DUM ke folder ABD
 * - enrol yang kurang, unenrol yang lebih
 * - enrol pengampu sesuai id_lecture
 *
 * Tidak membuat KKN/Skripsi/KP dan kode kurikulum lama (SIF101, SBD701, …).
 * Default DRY-RUN; tulis dengan --confirm.
 *   php admin/cli/ush_finish_kaprodi_sync_20262027_production.php --from-file=peserta_20262027Ganjil.json
 *   php admin/cli/ush_finish_kaprodi_sync_20262027_production.php --from-file=peserta_20262027Ganjil.json --confirm
 */
define('CLI_SCRIPT', true);
$ushstartcwd = getcwd();
require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->libdir . '/enrollib.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->dirroot . '/local/siakad_sync/locallib.php');
require_once(__DIR__ . '/ush_course_owner.php');

$CONFIRM = false;
$FROMFILE = $CFG->dirroot . '/peserta_20262027Ganjil.json';
foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--confirm') {
        $CONFIRM = true;
    } else if (str_starts_with($arg, '--from-file=')) {
        $FROMFILE = substr($arg, 12);
    }
}
if (!is_readable($FROMFILE) && $ushstartcwd && is_readable($ushstartcwd . '/' . $FROMFILE)) {
    $FROMFILE = $ushstartcwd . '/' . $FROMFILE;
}
$LABEL = '2026/2027 - Ganjil';
$SUFFIX = '20262027Ganjil';
$START = mktime(0, 0, 0, 9, 1, 2026);
$END = mktime(0, 0, 0, 2, 28, 2027);
$skipre = '/(kkn|skripsi|kerja praktik|praktik kerja|kuliah kerja|seminar proposal)/i';

$prodiinfo = [
    24 => ['kode' => 'SBD', 'nama' => 'Bisnis Digital', 'label' => 'BisDig'],
    25 => ['kode' => 'SGZ', 'nama' => 'Ilmu Gizi', 'label' => 'Gizi'],
    26 => ['kode' => 'SIF', 'nama' => 'Informatika', 'label' => 'Informatika'],
    31 => ['kode' => 'MBI', 'nama' => 'MBI', 'label' => 'MBI'],
    32 => ['kode' => 'HKM', 'nama' => 'Hukum Bisnis', 'label' => 'Hubis'],
    33 => ['kode' => 'TPN', 'nama' => 'Teknologi Pangan', 'label' => 'Tekpang'],
    34 => ['kode' => 'BKI', 'nama' => 'BKI', 'label' => 'BKI'],
    35 => ['kode' => 'PAR', 'nama' => 'Pariwisata', 'label' => 'Pariwisata'],
    36 => ['kode' => 'ABD', 'nama' => 'Akuntansi Bisnis Digital', 'label' => 'Diploma'],
];

$studentrole = (int) $DB->get_field('role', 'id', ['shortname' => 'student'], MUST_EXIST);
$teacherrole = (int) $DB->get_field('role', 'id', ['shortname' => 'editingteacher'], MUST_EXIST);
$enrolplugin = enrol_get_plugin('manual');
$mnethostid = (int) $CFG->mnet_localhost_id;

if (!is_readable($FROMFILE)) {
    mtrace('File peserta tidak terbaca: ' . $FROMFILE);
    exit(1);
}
$d = json_decode(file_get_contents($FROMFILE), true);
if (!is_array($d)) {
    mtrace('Isi file peserta tidak dikenali: ' . $FROMFILE);
    exit(1);
}

$cats = [];
foreach (ush_prodi_labels() as $kode => $nama) {
    $idn = 'CAT_' . $kode . '_' . str_replace(['/', ' ', '-'], '_', $LABEL);
    $row = $DB->get_record('course_categories', ['idnumber' => $idn]);
    if (!$row) {
        $row = $DB->get_record('course_categories', ['name' => "$nama ($kode) - $LABEL"]);
    }
    if ($row) {
        $cats[$kode] = (int) $row->id;
    }
}
if (empty($cats['MKU']) || empty($cats['ABD'])) {
    mtrace('Folder MKU atau ABD 2026/2027 Ganjil tidak ada.');
    exit(1);
}

$lms = $DB->get_records_sql(
    "SELECT id, shortname, fullname, category, visible FROM {course}
      WHERE id > 1 AND " . $DB->sql_like('shortname', ':p'),
    ['p' => '%' . $SUFFIX]
);
$bybase = [];
foreach ($lms as $c) {
    $base = strtoupper(preg_replace('/_20262027Ganjil$/i', '', $c->shortname) ?? $c->shortname);
    $bybase[$base][] = $c;
}

function ush_base_of(string $short): string {
    return strtoupper(preg_replace('/_20262027Ganjil$/i', '', $short) ?? $short);
}

function ush_clean_lesson(string $name): string {
    $name = html_entity_decode($name, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $name = trim(preg_replace('/\s+/', ' ', strip_tags($name)) ?? $name);
    $name = preg_replace('/\s*\(PERTEMUAN\s*\d+\)/i', '', $name) ?? $name;
    $name = preg_replace('/\s*\/\s*/', ' / ', $name) ?? $name;
    return trim($name);
}

function ush_split_name(string $full): array {
    $full = trim(preg_replace('/\s+/', ' ', $full) ?? $full);
    $parts = $full === '' ? [] : explode(' ', $full);
    $firstname = array_shift($parts) ?: 'Mahasiswa';
    $lastname = trim(implode(' ', $parts));
    return [$firstname, $lastname === '' ? 'USH' : $lastname];
}

function ush_unenrol_user(int $courseid, int $userid): void {
    foreach (enrol_get_instances($courseid, false) as $instance) {
        $plugin = enrol_get_plugin($instance->enrol);
        if ($plugin) {
            $plugin->unenrol_user($instance, $userid);
        }
    }
}

function ush_student_nims(int $courseid, int $studentrole): array {
    global $DB;
    $ctx = context_course::instance($courseid);
    $rows = $DB->get_fieldset_sql(
        "SELECT DISTINCT LOWER(u.username)
           FROM {role_assignments} ra
           JOIN {user} u ON u.id = ra.userid AND u.deleted = 0 AND u.suspended = 0
          WHERE ra.contextid = :ctx AND ra.roleid = :r",
        ['ctx' => $ctx->id, 'r' => $studentrole]
    );
    $out = [];
    foreach ($rows as $u) {
        if (preg_match('/^\d+$/', $u)) {
            $out[$u] = true;
        }
    }
    return $out;
}

function ush_folder_for_code(string $code): string {
    if (str_starts_with($code, 'DUM') || str_starts_with($code, 'DDM')) {
        return 'ABD';
    }
    return ush_prodi_from_code($code);
}

function ush_skip_create(string $code, string $name, string $skipre): bool {
    if (preg_match($skipre, $name . ' ' . $code)) {
        return true;
    }
    return !ush_is_official_scheme_code($code);
}

function ush_ensure_student(string $nim, string $nama, bool $confirm, int $mnethostid): ?stdClass {
    global $DB, $CFG;
    $username = strtolower($nim);
    $user = $DB->get_record('user', [
        'username' => $username,
        'mnethostid' => $mnethostid,
        'deleted' => 0,
    ]);
    if ($user || !$confirm) {
        return $user ?: null;
    }
    [$firstname, $lastname] = ush_split_name($nama);
    $email = $username . '@sugenghartono.ac.id';
    if ($DB->record_exists('user', ['email' => $email, 'deleted' => 0])) {
        $email = $username . '.' . substr(sha1($nim), 0, 6) . '@sugenghartono.ac.id';
    }
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
    return $DB->get_record('user', ['id' => $newid], '*', MUST_EXIST);
}

function ush_ensure_dosen(int $lecid, string $nama, bool $confirm): ?stdClass {
    global $DB, $CFG;
    if ($lecid <= 0) {
        return null;
    }
    $username = 'dosen_' . $lecid;
    $user = $DB->get_record('user', [
        'username' => $username,
        'mnethostid' => $CFG->mnet_localhost_id,
        'deleted' => 0,
    ]);
    if ($user || !$confirm) {
        return $user ?: null;
    }
    [$firstname, $lastname] = ush_split_name($nama !== '' ? $nama : 'Dosen USH');
    $newid = user_create_user((object) [
        'username' => $username,
        'auth' => 'manual',
        'password' => 'DosenUSH2026!',
        'firstname' => $firstname,
        'lastname' => $lastname,
        'email' => 'dosen.' . $lecid . '@sugenghartono.ac.id',
        'confirmed' => 1,
        'mnethostid' => $CFG->mnet_localhost_id,
        'lang' => 'id',
        'calendartype' => $CFG->calendartype ?? 'gregorian',
        'mailformat' => 1,
        'maildisplay' => 2,
    ], true, false);
    return $DB->get_record('user', ['id' => $newid], '*', MUST_EXIST);
}

@set_time_limit(0);
mtrace('=== Rapikan kaprodi 2026/2027 Ganjil ===');
mtrace('Site  : ' . $CFG->wwwroot);
mtrace('Mode  : ' . ($CONFIRM ? 'LIVE' : 'DRY-RUN'));
mtrace('Sumber: ' . $FROMFILE . ' (' . ($d['generated'] ?? '?') . ', complete=' . (!empty($d['complete']) ? 'ya' : 'tidak') . ')');
mtrace('');

$groups = [];
foreach ($d['classes'] ?? [] as $k) {
    $code = strtoupper(trim((string) ($k['code'] ?? '')));
    $lname = ush_clean_lesson((string) ($k['lesson_name'] ?? ''));
    $pid = (int) ($k['id_prodi'] ?? 0);
    if ($code === '' || !isset($prodiinfo[$pid])) {
        continue;
    }
    if (preg_match($skipre, $lname . ' ' . $code)) {
        continue;
    }
    if (!isset($groups[$code])) {
        $groups[$code] = ['name' => $lname, 'prodi' => []];
    }
    if ($groups[$code]['name'] === '' && $lname !== '') {
        $groups[$code]['name'] = $lname;
    }
    if (!isset($groups[$code]['prodi'][$pid])) {
        $groups[$code]['prodi'][$pid] = ['nims' => [], 'lecture' => 0, 'dosen' => ''];
    }
    $lec = (int) ($k['id_lecture'] ?? 0);
    if ($lec > 0 && $groups[$code]['prodi'][$pid]['lecture'] === 0) {
        $groups[$code]['prodi'][$pid]['lecture'] = $lec;
        $groups[$code]['prodi'][$pid]['dosen'] = trim((string) ($k['dosen'] ?? ''));
    }
    foreach ($k['students'] ?? [] as $s) {
        $nim = strtolower(preg_replace('/\D+/', '', (string) ($s['nim'] ?? '')) ?? '');
        if ($nim !== '') {
            $groups[$code]['prodi'][$pid]['nims'][$nim] = trim((string) ($s['name'] ?? $nim));
        }
    }
}

ksort($groups);

$stats = [
    'created' => 0,
    'renamed' => 0,
    'movedcat' => 0,
    'enrol' => 0,
    'unenrol' => 0,
    'dosen' => 0,
    'newusers' => 0,
    'missingakun' => 0,
];

foreach ($groups as $code => $info) {
    $active = [];
    foreach ($info['prodi'] as $pid => $row) {
        if ($row['nims']) {
            $active[$pid] = $row;
        }
    }
    if (!$active) {
        continue;
    }

    $multi = count($active) > 1 || (bool) preg_match('/^(IUM|DUM|IFM)/', $code);
    if (count($active) === 1 && !preg_match('/^(IUM|DUM|IFM)/', $code) && ush_skip_create($code, $info['name'], $skipre)) {
        // Kurikulum lama berpeserta: jangan buat cangkang baru.
        $onlypid = (int) array_key_first($active);
        $wantshort = $code . '_' . $SUFFIX;
        if (!$DB->get_record('course', ['shortname' => $wantshort])) {
            mtrace("LEWATI (kurikulum lama, tidak dibuat): $code {$info['name']} SIAKAD=" . count($active[$onlypid]['nims']));
            continue;
        }
    }

    uasort($active, static function ($a, $b) {
        return count($b['nims']) <=> count($a['nims']);
    });

    $existing = [];
    foreach ($lms as $c) {
        $base = ush_base_of($c->shortname);
        if ($base === $code || str_starts_with($base, $code . '_')) {
            $existing[] = $c;
        }
    }

    $plain = null;
    foreach ($existing as $c) {
        if (ush_base_of($c->shortname) === $code && (int) $c->visible === 1) {
            $plain = $c;
            break;
        }
    }

    $assigned = [];
    $largest = (int) array_key_first($active);
    foreach ($active as $pid => $row) {
        $pk = $prodiinfo[$pid]['kode'];
        $splitshort = $code . '_' . $pk . '_' . $SUFFIX;
        $plainshort = $code . '_' . $SUFFIX;
        $found = $DB->get_record('course', ['shortname' => $splitshort]);
        if ($found) {
            $assigned[$pid] = $found;
            continue;
        }
        $usedids = [];
        foreach ($assigned as $c) {
            if ($c && !empty($c->id)) {
                $usedids[(int) $c->id] = true;
            }
        }
        if ($multi && $plain && $pid === $largest && empty($usedids[(int) $plain->id])) {
            $assigned[$pid] = $plain;
            $plain = null;
            continue;
        }
        if (!$multi) {
            $foundplain = $DB->get_record('course', ['shortname' => $plainshort]);
            if ($foundplain) {
                $assigned[$pid] = $foundplain;
                continue;
            }
        }
        $assigned[$pid] = null;
    }

    foreach ($assigned as $pid => $course) {
        $pk = $prodiinfo[$pid]['kode'];
        $folder = ($pk === 'ABD') ? 'ABD' : ush_folder_for_code($code);
        $catid = $cats[$folder] ?? $cats['MKU'];
        $label = $prodiinfo[$pid]['label'];
        $nims = $active[$pid]['nims'];
        $title = $info['name'] !== '' ? $info['name'] : $code;
        if ($multi) {
            $title .= ' ' . $label;
        }
        $short = $course ? $course->shortname : ($multi ? $code . '_' . $pk . '_' . $SUFFIX : $code . '_' . $SUFFIX);

        if (!$course) {
            if (ush_skip_create($code, $info['name'], $skipre) && !preg_match('/^(DUM|DDM)/', $code)) {
                mtrace("  LEWATI buat $short");
                continue;
            }
            mtrace(sprintf('BUAT  %s | %s | %d mhs | %s', $short, $title, count($nims), $active[$pid]['dosen']));
            if ($CONFIRM) {
                $new = new stdClass();
                $new->fullname = $title . ' — ' . $LABEL;
                $new->shortname = $short;
                $new->idnumber = $short;
                $new->category = $catid;
                $new->visible = 1;
                $new->format = 'topics';
                $new->numsections = 16;
                $new->startdate = $START;
                $new->enddate = $END;
                $new->summary = '<p><strong>' . s($title) . '</strong> — ' . s($LABEL)
                    . '</p><p>Kode: ' . s($code) . ' | Prodi: ' . s($prodiinfo[$pid]['nama']) . '</p>';
                $new->summaryformat = FORMAT_HTML;
                $new->enablecompletion = 1;
                $course = create_course($new);
                $stats['created']++;
                $lms[$course->id] = $course;
            } else {
                continue;
            }
        } else {
            $wantname = $title . ' — ' . $LABEL;
            if ($multi && $course->fullname !== $wantname) {
                mtrace("NAMA  {$course->shortname} → $wantname");
                if ($CONFIRM) {
                    $course->fullname = $wantname;
                    update_course($course);
                    $stats['renamed']++;
                }
            }
            if ($CONFIRM && in_array($folder, ['ABD'], true) && (int) $course->category !== $catid) {
                mtrace("PINDAH folder {$course->shortname} → ABD");
                $course->category = $catid;
                update_course($course);
                $stats['movedcat']++;
            }
        }

        if (!$CONFIRM || !$course) {
            $have = $course ? count(ush_student_nims((int) $course->id, $studentrole)) : 0;
            mtrace(sprintf('SYNC  %s | SIAKAD %d | LMS %d | %s', $short, count($nims), $have, $title));
            continue;
        }

        if ($enrolplugin && !$DB->record_exists('enrol', ['courseid' => $course->id, 'enrol' => 'manual'])) {
            $enrolplugin->add_instance($course);
        }

        $dosen = ush_ensure_dosen((int) $active[$pid]['lecture'], $active[$pid]['dosen'], true);
        $ctx = context_course::instance($course->id);
        if ($dosen) {
            $has = $DB->record_exists('role_assignments', [
                'roleid' => $teacherrole,
                'contextid' => $ctx->id,
                'userid' => $dosen->id,
            ]);
            if (!$has || !is_enrolled($ctx, $dosen, '', true)) {
                enrol_try_internal_enrol($course->id, $dosen->id, $teacherrole);
                $stats['dosen']++;
            }
        }
        if ($multi) {
            $teachers = $DB->get_records_sql(
                "SELECT u.id, u.username
                   FROM {role_assignments} ra
                   JOIN {user} u ON u.id = ra.userid AND u.deleted = 0
                  WHERE ra.contextid = :ctx AND ra.roleid = :r",
                ['ctx' => $ctx->id, 'r' => $teacherrole]
            );
            $keepdosen = $dosen ? (int) $dosen->id : 0;
            foreach ($teachers as $t) {
                if ($keepdosen && (int) $t->id === $keepdosen) {
                    continue;
                }
                if (str_starts_with($t->username, 'dosen_')) {
                    ush_unenrol_user((int) $course->id, (int) $t->id);
                    mtrace('    lepas pengampu ' . $t->username . ' dari ' . $course->shortname);
                }
            }
        }

        $wantnims = $nims;
        foreach ($wantnims as $nim => $nama) {
            $before = $DB->record_exists('user', [
                'username' => strtolower($nim),
                'mnethostid' => $mnethostid,
                'deleted' => 0,
            ]);
            $user = ush_ensure_student($nim, $nama, true, $mnethostid);
            if (!$user) {
                $stats['missingakun']++;
                mtrace("    tanpa akun $nim $nama");
                continue;
            }
            if (!$before) {
                $stats['newusers']++;
            }
            $ctx = context_course::instance($course->id);
            if (!is_enrolled($ctx, $user, '', true)) {
                if (enrol_try_internal_enrol($course->id, $user->id, $studentrole)) {
                    $stats['enrol']++;
                    siakad_ensure_user_in_nim_cohort((int) $user->id, $user->username);
                }
            } else {
                siakad_ensure_user_in_nim_cohort((int) $user->id, $user->username);
            }
        }

        // Pindahkan / cabut dari kelas lain dengan kode yang sama.
        foreach ($existing as $other) {
            if ((int) $other->id === (int) $course->id) {
                continue;
            }
            foreach (array_keys($wantnims) as $nim) {
                $user = $DB->get_record('user', [
                    'username' => $nim,
                    'mnethostid' => $mnethostid,
                    'deleted' => 0,
                ]);
                if (!$user) {
                    continue;
                }
                $octx = context_course::instance($other->id);
                if (is_enrolled($octx, $user, '', true)) {
                    ush_unenrol_user((int) $other->id, (int) $user->id);
                    $stats['unenrol']++;
                }
            }
        }

        $have = ush_student_nims((int) $course->id, $studentrole);
        foreach ($have as $nim => $_) {
            if (!isset($wantnims[$nim])) {
                $user = $DB->get_record('user', [
                    'username' => $nim,
                    'mnethostid' => $mnethostid,
                    'deleted' => 0,
                ]);
                if ($user) {
                    ush_unenrol_user((int) $course->id, (int) $user->id);
                    $stats['unenrol']++;
                }
            }
        }

        $after = count(ush_student_nims((int) $course->id, $studentrole));
        mtrace(sprintf('OK    %s | SIAKAD %d | LMS %d', $course->shortname, count($nims), $after));
    }
}

// Sembunyikan cangkang campur yang sudah dikosongkan (IUM/IFM tanpa suffix prodi).
if ($CONFIRM) {
    foreach ($lms as $c) {
        $base = ush_base_of($c->shortname);
        if (!preg_match('/^(IUM|DUM|IFM)/', $base) || str_contains($base, '_')) {
            continue;
        }
        $left = count(ush_student_nims((int) $c->id, $studentrole));
        if ($left === 0 && (int) $c->visible === 1) {
            $used = false;
            foreach ($groups[$base]['prodi'] ?? [] as $row) {
                if ($row['nims']) {
                    $used = true;
                    break;
                }
            }
            // Kalau masih dipakai sebagai kelas reuse, jangan sembunyikan.
            $stillnamed = false;
            foreach ($prodiinfo as $info) {
                if (stripos($c->fullname, $info['label']) !== false) {
                    $stillnamed = true;
                    break;
                }
            }
            if ($used && $stillnamed) {
                continue;
            }
            if (!$used || !$stillnamed) {
                // Hanya sembunyikan jika tidak ada mahasiswa dan nama belum berisi label prodi.
                if (!$stillnamed) {
                    $DB->set_field('course', 'visible', 0, ['id' => $c->id]);
                    mtrace('SEMBUNYIKAN ' . $c->shortname . ' (kosong)');
                }
            }
        }
    }

    // Veronica: lepas MK yang tidak ada di SIAKAD beliau (kode lama IUM0004/IUM0005).
    $veronica = $DB->get_record('user', ['username' => 'dosen_8420', 'deleted' => 0]);
    if ($veronica) {
        $keep = [];
        foreach ($groups as $code => $info) {
            foreach ($info['prodi'] as $pid => $row) {
                if ((int) $row['lecture'] === 8420 && $row['nims']) {
                    $keep[$code] = true;
                }
            }
        }
        $taught = $DB->get_records_sql(
            "SELECT c.id, c.shortname
               FROM {course} c
               JOIN {context} ctx ON ctx.instanceid = c.id AND ctx.contextlevel = 50
               JOIN {role_assignments} ra ON ra.contextid = ctx.id AND ra.userid = :u
               JOIN {role} r ON r.id = ra.roleid AND r.shortname IN ('editingteacher', 'teacher')
              WHERE " . $DB->sql_like('c.shortname', ':p'),
            ['u' => $veronica->id, 'p' => '%' . $SUFFIX]
        );
        foreach ($taught as $c) {
            $base = ush_base_of($c->shortname);
            $root = preg_replace('/_(SBD|SGZ|SIF|MBI|HKM|TPN|BKI|PAR|ABD)$/', '', $base) ?? $base;
            if (!isset($keep[$root]) && !isset($keep[$base])) {
                ush_unenrol_user((int) $c->id, (int) $veronica->id);
                mtrace('LEPAS Veronica dari ' . $c->shortname);
            }
        }
    }

    fix_course_sortorder();
    rebuild_course_cache(0, true);
}

mtrace('');
mtrace('=== RINGKASAN ===');
foreach ($stats as $k => $v) {
    mtrace("  $k: $v");
}
if (!$CONFIRM) {
    mtrace('');
    mtrace('DRY-RUN. Ulangi dengan --confirm untuk menulis.');
}
