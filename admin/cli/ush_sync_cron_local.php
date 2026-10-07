<?php
/**
 * Cron lokal: tarik SIAKAD → tambah yang belum ada di LMS.
 *
 * Hanya menambah (kelas, akun, enrol). Tidak menghapus mahasiswa/dosen.
 * Tidak membuat KKN/Skripsi/KP. Pengampu dicocokkan dari NAMA, bukan id_lecture.
 *
 *   php admin/cli/ush_sync_cron_local.php --dry-run
 *   php admin/cli/ush_sync_cron_local.php --live
 *
 * Windows Task Scheduler (setiap 3 jam) memanggil ush_sync_cron_local.bat
 */
define('CLI_SCRIPT', true);

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/enrollib.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/cohort/lib.php');
require_once($CFG->dirroot . '/local/siakad_sync/locallib.php');
require_once(__DIR__ . '/ush_course_owner.php');

if (strpos($CFG->wwwroot, 'localhost') === false && strpos($CFG->wwwroot, '127.0.0.1') === false) {
    mtrace('Dibatalkan: bukan LMS lokal. wwwroot=' . $CFG->wwwroot);
    exit(1);
}

$LIVE = false;
$SUFFIX = '20262027Ganjil';
$MAXPAGES = 120;
$MAXNEWCOURSES = 25;
$MAXNEWSTUDENTS = 80;
$MAXNEWDOSEN = 15;
$SKIPRE = '/(kkn|skripsi|kerja praktik|praktik kerja|kuliah kerja|seminar proposal)/i';
$PRODISUFFIX = ['SIF', 'SBD', 'SGZ', 'HKM', 'MBI', 'MNJ', 'TPN', 'TPG', 'BKI', 'PAR', 'ABD'];

foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--live') {
        $LIVE = true;
    } else if ($arg === '--dry-run') {
        $LIVE = false;
    } else if (str_starts_with($arg, '--semester=')) {
        $SUFFIX = substr($arg, 11);
    } else {
        mtrace('Argumen tidak dikenal: ' . $arg);
        exit(1);
    }
}

if (!preg_match('/^(20\d{2})(20\d{2})(Ganjil|Genap)$/i', $SUFFIX, $m)) {
    mtrace('Format semester salah. Contoh: 20262027Ganjil');
    exit(1);
}
$TAHUN = $m[1] . '/' . $m[2];
$PERIODE = ucfirst(strtolower($m[3]));
$LABEL = $TAHUN . ' - ' . $PERIODE;
$START = strcasecmp($PERIODE, 'Ganjil') === 0
    ? mktime(0, 0, 0, 9, 1, (int) $m[1])
    : mktime(0, 0, 0, 3, 1, (int) $m[2]);
$END = strcasecmp($PERIODE, 'Ganjil') === 0
    ? mktime(0, 0, 0, 2, 28, (int) $m[2])
    : mktime(0, 0, 0, 8, 31, (int) $m[2]);

$logdir = $CFG->dataroot . '/siakad_cron';
if (!is_dir($logdir) && !mkdir($logdir, 0777, true) && !is_dir($logdir)) {
    mtrace('Tidak bisa membuat folder log: ' . $logdir);
    exit(1);
}
$logfile = $logdir . '/sync_' . date('Ymd_His') . '.log';
$lockfile = $logdir . '/sync.lock';

function ush_cron_log(string $msg): void {
    global $logfile;
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg;
    mtrace($msg);
    file_put_contents($logfile, $line . PHP_EOL, FILE_APPEND);
}

$lock = fopen($lockfile, 'c+');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
    mtrace('Sync masih jalan. Lewati giliran ini.');
    exit(0);
}

ush_cron_log('=== Cron SIAKAD → LMS ' . $LABEL . ' ===');
ush_cron_log('Mode: ' . ($LIVE ? 'LIVE (menambah data)' : 'DRY-RUN (tidak menulis)'));
ush_cron_log('Log : ' . $logfile);

function ush_cron_login(): ?string {
    $ch = curl_init('https://siakad.sugenghartono.ac.id/api/login');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode([
            'email' => getenv('SIAKAD_EMAIL') ?: 'akademik@sugenghartono.ac.id',
            'password' => getenv('SIAKAD_PASSWORD') ?: '321',
        ]),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 25,
    ]);
    $raw = curl_exec($ch);
    curl_close($ch);
    $json = json_decode($raw, true);
    return $json['token'] ?? $json['data']['token'] ?? $json['access_token'] ?? $json['data']['access_token'] ?? null;
}

function ush_cron_get(string $url, string $token): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token, 'Accept: application/json'],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 60,
    ]);
    $raw = curl_exec($ch);
    $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$http, json_decode($raw, true)];
}

function ush_cron_norm_code(string $code): string {
    $c = strtoupper(trim($code));
    $c = str_replace(['*', ' '], '', $c);
    return preg_replace('/_20\d{6}(GANJIL|GENAP)$/i', '', $c) ?? $c;
}

function ush_cron_norm_name(string $s): string {
    $s = html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $s = strtoupper(trim(preg_replace('/\s+/', ' ', strip_tags($s)) ?? $s));
    $s = str_replace([',', '.', ';'], ' ', $s);
    return trim(preg_replace('/\s+/', ' ', $s) ?? $s);
}

function ush_cron_split_name(string $full): array {
    $full = trim(preg_replace('/\s+/', ' ', $full) ?? $full);
    $parts = $full === '' ? [] : explode(' ', $full);
    $firstname = array_shift($parts) ?: 'User';
    $lastname = trim(implode(' ', $parts));
    return [$firstname, $lastname === '' ? 'USH' : $lastname];
}

function ush_cron_tahun_ok(string $ta, string $want): bool {
    $norm = static fn(string $s): string => preg_replace('/[^0-9]/', '', $s) ?? '';
    return $norm($ta) !== '' && $norm($ta) === $norm($want);
}

function ush_cron_nim_prodi(string $nim): ?string {
    $info = siakad_nim_cohort(strtolower(trim($nim)));
    if (!$info) {
        return null;
    }
    $map = ['MNJ' => 'MBI', 'TPG' => 'TPN'];
    return $map[$info['code']] ?? $info['code'];
}

function ush_cron_parse_short(string $short, string $suffix, array $prodisuffix): array {
    $base = ush_cron_norm_code($short);
    $prodi = null;
    foreach ($prodisuffix as $pk) {
        if (preg_match('/^(.+)_' . preg_quote($pk, '/') . '$/i', $base, $m)) {
            $base = strtoupper($m[1]);
            $prodi = strtoupper($pk);
            if ($prodi === 'MNJ') {
                $prodi = 'MBI';
            } else if ($prodi === 'TPG') {
                $prodi = 'TPN';
            }
            break;
        }
    }
    return [$base, $prodi];
}

function ush_cron_folder(string $code): string {
    if (str_starts_with($code, 'DUM') || str_starts_with($code, 'DDM')) {
        return 'ABD';
    }
    return ush_prodi_from_code($code);
}

$token = ush_cron_login();
if (!$token) {
    ush_cron_log('Gagal login SIAKAD');
    flock($lock, LOCK_UN);
    fclose($lock);
    exit(1);
}
ush_cron_log('Login SIAKAD: OK');

$students = [];
$lessonnama = [];
$page = 1;
$http429 = 0;
while ($page <= $MAXPAGES) {
    [$http, $res] = ush_cron_get(
        'https://siakad.sugenghartono.ac.id/api/grades-per-course?per_page=100&page=' . $page,
        $token
    );
    if ($http === 429) {
        $http429++;
        if ($http429 > 5) {
            ush_cron_log("HTTP 429 berulang di page $page — stop.");
            break;
        }
        ush_cron_log("HTTP 429 page $page — tunggu 5 detik");
        sleep(5);
        continue;
    }
    $http429 = 0;
    if ($http !== 200 || !is_array($res)) {
        ush_cron_log("grades-per-course page $page HTTP $http — stop");
        break;
    }
    $items = $res['data'] ?? [];
    if (!$items) {
        break;
    }
    foreach ($items as $mhs) {
        $nim = trim((string) ($mhs['nim'] ?? $mhs['student_nim'] ?? ''));
        if ($nim === '') {
            continue;
        }
        $status = strtoupper(trim((string) ($mhs['status'] ?? 'AKTIF')));
        if (in_array($status, ['NONAKTIF', 'NON-AKTIF', 'CUTI', 'DO', 'LULUS', 'KELUAR'], true)) {
            continue;
        }
        $key = strtolower($nim);
        if (!isset($students[$key])) {
            $students[$key] = [
                'nim' => $nim,
                'name' => trim((string) ($mhs['name'] ?? $mhs['nama'] ?? $mhs['student_name'] ?? 'Mahasiswa')),
                'rows' => [],
            ];
        }
        foreach ($mhs['grade'] ?? [] as $g) {
            if (!ush_cron_tahun_ok((string) ($g['tahun_akademik'] ?? ''), $TAHUN)) {
                continue;
            }
            if (strcasecmp(trim((string) ($g['periode'] ?? '')), $PERIODE) !== 0) {
                continue;
            }
            $code = ush_cron_norm_code((string) ($g['lesson_code'] ?? ''));
            $lname = trim((string) ($g['lesson_name'] ?? $g['name'] ?? ''));
            $dosen = trim(strip_tags((string) ($g['lecture_name'] ?? $g['dosen'] ?? '')));
            $lecid = (int) ($g['id_lecture'] ?? 0);
            if ($code === '') {
                continue;
            }
            if (preg_match($SKIPRE, $lname . ' ' . $code)) {
                continue;
            }
            if ($lname !== '') {
                $lessonnama[$code] = $lname;
            }
            $students[$key]['rows'][] = [
                'code' => $code,
                'dosen' => $dosen,
                'lecid' => $lecid,
            ];
        }
    }
    $last = (int) ($res['meta']['last_page'] ?? $res['last_page'] ?? 0);
    if ($page === 1 || $page % 15 === 0) {
        ush_cron_log("  scan page $page" . ($last ? " / $last" : '') . ' — mhs ' . count($students));
    }
    if ($last > 0 && $page >= $last) {
        break;
    }
    usleep(200000);
    $page++;
}
ush_cron_log('Mahasiswa unik SIAKAD (semua tahun, lalu difilter KRS): ' . count($students));

$krs = [];
foreach ($students as $key => $row) {
    if (empty($row['rows'])) {
        unset($students[$key]);
        continue;
    }
    foreach ($row['rows'] as $r) {
        $krs[$r['code']] = true;
    }
}
ush_cron_log('Mahasiswa dengan KRS ' . $LABEL . ': ' . count($students));
ush_cron_log('Kode MK KRS: ' . count($krs));

$prodilabels = ush_prodi_labels();
$parentidn = 'TA_' . str_replace(['/', ' ', '-'], '_', $LABEL);
$parent = $DB->get_record('course_categories', ['idnumber' => $parentidn])
    ?: $DB->get_record('course_categories', ['name' => 'TA ' . $LABEL]);
$catids = [];
foreach ($prodilabels as $kode => $nama) {
    $idn = 'CAT_' . $kode . '_' . str_replace(['/', ' ', '-'], '_', $LABEL);
    $catname = "$nama ($kode) - $LABEL";
    $existing = $DB->get_record('course_categories', ['idnumber' => $idn])
        ?: $DB->get_record('course_categories', ['name' => $catname]);
    if ($existing) {
        $catids[$kode] = (int) $existing->id;
    }
}

$courses = $DB->get_records_sql(
    "SELECT id, shortname, fullname, category FROM {course}
      WHERE id > 1 AND " . $DB->sql_like('shortname', ':p'),
    ['p' => '%' . $SUFFIX]
);
$bybase = [];
foreach ($courses as $c) {
    [$base, $prodi] = ush_cron_parse_short($c->shortname, $SUFFIX, $PRODISUFFIX);
    $bybase[$base][] = ['course' => $c, 'prodi' => $prodi];
}

function ush_cron_pick_course(array $bybase, string $code, ?string $prodi): ?stdClass {
    $list = $bybase[$code] ?? [];
    if (!$list) {
        return null;
    }
    if ($prodi) {
        foreach ($list as $row) {
            if ($row['prodi'] === $prodi) {
                return $row['course'];
            }
        }
    }
    $unsplit = [];
    foreach ($list as $row) {
        if ($row['prodi'] === null) {
            $unsplit[] = $row['course'];
        }
    }
    if (count($unsplit) === 1) {
        return $unsplit[0];
    }
    if (count($list) === 1) {
        return $list[0]['course'];
    }
    return null;
}

$enrolplugin = enrol_get_plugin('manual');
$studentrole = (int) $DB->get_field('role', 'id', ['shortname' => 'student'], MUST_EXIST);
$teacherrole = (int) $DB->get_field('role', 'id', ['shortname' => 'editingteacher'], MUST_EXIST);
$mnethostid = (int) $CFG->mnet_localhost_id;

$needcourses = [];
foreach (array_keys($krs) as $code) {
    if (!ush_is_official_scheme_code($code)) {
        continue;
    }
    if (!empty($bybase[$code])) {
        continue;
    }
    $needcourses[$code] = $lessonnama[$code] ?? $code;
}

$stats = [
    'kelas_baru' => 0,
    'akun_mhs' => 0,
    'enrol_mhs' => 0,
    'akun_dosen' => 0,
    'enrol_dosen' => 0,
    'skip_mku' => 0,
    'gagal' => 0,
];
$laporan = [];

if (count($needcourses) > $MAXNEWCOURSES) {
    ush_cron_log('BERHENTI kelas baru: ' . count($needcourses) . ' > batas ' . $MAXNEWCOURSES);
    foreach (array_slice($needcourses, 0, 20, true) as $code => $name) {
        ush_cron_log("  $code — $name");
    }
} else {
    foreach ($needcourses as $code => $name) {
        $shortname = $code . '_' . $SUFFIX;
        $folder = ush_cron_folder($code);
        $catid = $catids[$folder] ?? $catids['MKU'] ?? 0;
        $laporan[] = "KELAS $shortname — $name ($folder)";
        if (!$LIVE) {
            $stats['kelas_baru']++;
            continue;
        }
        if (!$catid) {
            ush_cron_log("  Lewati $shortname: folder $folder tidak ada");
            $stats['gagal']++;
            continue;
        }
        try {
            $newcourse = (object) [
                'fullname' => "$name — $LABEL",
                'shortname' => $shortname,
                'idnumber' => $shortname,
                'category' => $catid,
                'visible' => 1,
                'format' => 'topics',
                'numsections' => 16,
                'startdate' => $START,
                'enddate' => $END,
                'summary' => '<p><strong>' . s($name) . '</strong> — ' . s($LABEL)
                    . '</p><p>Kode: ' . s($code) . '</p>',
                'summaryformat' => FORMAT_HTML,
                'enablecompletion' => 1,
            ];
            $course = create_course($newcourse);
            if ($enrolplugin && !$DB->record_exists('enrol', ['courseid' => $course->id, 'enrol' => 'manual'])) {
                $enrolplugin->add_instance($course);
            }
            $bybase[$code][] = ['course' => $course, 'prodi' => null];
            $stats['kelas_baru']++;
            ush_cron_log("  BUAT $shortname");
        } catch (Throwable $e) {
            $stats['gagal']++;
            ush_cron_log('  Gagal kelas ' . $shortname . ': ' . $e->getMessage());
        }
    }
}

$newstudents = 0;
foreach ($students as $row) {
    $username = strtolower($row['nim']);
    if (!$DB->record_exists('user', ['username' => $username, 'mnethostid' => $mnethostid, 'deleted' => 0])) {
        $newstudents++;
    }
}
if ($newstudents > $MAXNEWSTUDENTS) {
    ush_cron_log("BERHENTI akun mahasiswa: $newstudents > batas $MAXNEWSTUDENTS");
}

$dosenusers = $DB->get_records_select('user', "deleted = 0 AND username LIKE 'dosen_%'", [], '', 'id, username, firstname, lastname');
$dosenbynorm = [];
foreach ($dosenusers as $u) {
    $n = ush_cron_norm_name($u->firstname . ' ' . $u->lastname);
    if ($n === '') {
        continue;
    }
    $dosenbynorm[$n][] = $u;
}

function ush_cron_resolve_dosen(array $dosenusers, array $dosenbynorm, int $lecid, string $nama): ?stdClass {
    $norm = ush_cron_norm_name($nama);
    if ($norm !== '' && !empty($dosenbynorm[$norm]) && count($dosenbynorm[$norm]) === 1) {
        return $dosenbynorm[$norm][0];
    }
    if ($lecid > 0) {
        $uname = 'dosen_' . $lecid;
        foreach ($dosenusers as $u) {
            if ($u->username === $uname) {
                return $u;
            }
        }
    }
    return null;
}

$needdosen = [];
foreach ($students as $row) {
    foreach ($row['rows'] as $r) {
        if ($r['dosen'] === '' || $r['lecid'] <= 0) {
            continue;
        }
        if (ush_cron_resolve_dosen($dosenusers, $dosenbynorm, $r['lecid'], $r['dosen'])) {
            continue;
        }
        $needdosen[$r['lecid']] = $r['dosen'];
    }
}
if (count($needdosen) > $MAXNEWDOSEN) {
    ush_cron_log('BERHENTI akun dosen: ' . count($needdosen) . ' > batas ' . $MAXNEWDOSEN);
    $needdosen = [];
}

if ($LIVE && $needdosen) {
    foreach ($needdosen as $lecid => $nama) {
        [$firstname, $lastname] = ush_cron_split_name($nama);
        try {
            $newid = user_create_user((object) [
                'username' => 'dosen_' . $lecid,
                'auth' => 'manual',
                'password' => 'DosenUSH2026!',
                'firstname' => $firstname,
                'lastname' => $lastname,
                'email' => 'dosen.' . $lecid . '@sugenghartono.ac.id',
                'confirmed' => 1,
                'mnethostid' => $mnethostid,
                'lang' => 'en',
                'calendartype' => $CFG->calendartype ?? 'gregorian',
                'mailformat' => 1,
                'maildisplay' => 2,
            ], true, false);
            $u = $DB->get_record('user', ['id' => $newid], '*', MUST_EXIST);
            $dosenusers[$u->id] = $u;
            $dosenbynorm[ush_cron_norm_name($nama)][] = $u;
            $stats['akun_dosen']++;
            ush_cron_log("  BUAT dosen_$lecid — $nama | DosenUSH2026!");
        } catch (Throwable $e) {
            $stats['gagal']++;
            ush_cron_log("  Gagal dosen_$lecid: " . $e->getMessage());
        }
    }
} else {
    $stats['akun_dosen'] = count($needdosen);
    foreach ($needdosen as $lecid => $nama) {
        $laporan[] = "DOSEN dosen_$lecid — $nama";
    }
}

$enrolmhsok = $newstudents <= $MAXNEWSTUDENTS;
foreach ($students as $row) {
    $username = strtolower($row['nim']);
    $user = $DB->get_record('user', [
        'username' => $username,
        'mnethostid' => $mnethostid,
        'deleted' => 0,
    ]);
    if (!$user) {
        if (!$enrolmhsok) {
            continue;
        }
        if (!$LIVE) {
            $stats['akun_mhs']++;
            $laporan[] = 'MHS ' . $row['nim'] . ' — ' . $row['name'];
        } else {
            [$firstname, $lastname] = ush_cron_split_name($row['name']);
            $email = $username . '@sugenghartono.ac.id';
            if ($DB->record_exists('user', ['email' => $email, 'deleted' => 0])) {
                $email = $username . '.' . substr(sha1($row['nim']), 0, 6) . '@sugenghartono.ac.id';
            }
            try {
                $uid = user_create_user((object) [
                    'username' => $username,
                    'auth' => 'manual',
                    'password' => 'Ush@' . $row['nim'],
                    'firstname' => $firstname,
                    'lastname' => $lastname,
                    'email' => $email,
                    'city' => 'Sukoharjo',
                    'country' => 'ID',
                    'lang' => 'en',
                    'confirmed' => 1,
                    'mnethostid' => $mnethostid,
                    'calendartype' => $CFG->calendartype ?? 'gregorian',
                    'mailformat' => 1,
                    'maildisplay' => 0,
                    'institution' => 'Universitas Sugeng Hartono',
                    'department' => 'Mahasiswa',
                ], true, false);
                $user = $DB->get_record('user', ['id' => $uid], '*', MUST_EXIST);
                $stats['akun_mhs']++;
                ush_cron_log('  BUAT ' . $username . ' | Ush@' . $row['nim']);
            } catch (Throwable $e) {
                $stats['gagal']++;
                ush_cron_log('  Gagal mhs ' . $username . ': ' . $e->getMessage());
                continue;
            }
        }
    }
    if ($LIVE && $user) {
        siakad_ensure_user_in_nim_cohort((int) $user->id, $username);
    }

    $prodi = ush_cron_nim_prodi($row['nim']);
    foreach ($row['rows'] as $r) {
        $course = ush_cron_pick_course($bybase, $r['code'], $prodi);
        if (!$course) {
            if (!empty($bybase[$r['code']])) {
                $stats['skip_mku']++;
            }
            continue;
        }
        $dosenuser = ush_cron_resolve_dosen($dosenusers, $dosenbynorm, $r['lecid'], $r['dosen']);

        if ($user && $LIVE) {
            $ctx = context_course::instance($course->id);
            if (!is_enrolled($ctx, $user, '', true)) {
                if (enrol_try_internal_enrol($course->id, $user->id, $studentrole)) {
                    $stats['enrol_mhs']++;
                }
            }
        } else if ($user && $LIVE === false) {
            $ctx = context_course::instance($course->id);
            if (!is_enrolled($ctx, $user, '', true)) {
                $stats['enrol_mhs']++;
            }
        } else if (!$LIVE && !$user) {
            $stats['enrol_mhs']++;
        }

        if ($dosenuser) {
            $ctx = context_course::instance($course->id);
            $has = $DB->record_exists('role_assignments', [
                'roleid' => $teacherrole,
                'contextid' => $ctx->id,
                'userid' => $dosenuser->id,
            ]);
            if (!$has || !is_enrolled($ctx, $dosenuser, '', true)) {
                if ($LIVE) {
                    if (enrol_try_internal_enrol($course->id, $dosenuser->id, $teacherrole)) {
                        $stats['enrol_dosen']++;
                    }
                } else {
                    $stats['enrol_dosen']++;
                }
            }
        }
    }
}

if ($LIVE && $stats['kelas_baru'] > 0) {
    fix_course_sortorder();
    rebuild_course_cache(0, true);
}

ush_cron_log('');
ush_cron_log('=== RINGKASAN ===');
ush_cron_log('  Kelas baru     : ' . $stats['kelas_baru']);
ush_cron_log('  Akun mahasiswa : ' . $stats['akun_mhs']);
ush_cron_log('  Enrol mahasiswa: ' . $stats['enrol_mhs']);
ush_cron_log('  Akun dosen     : ' . $stats['akun_dosen']);
ush_cron_log('  Enrol dosen    : ' . $stats['enrol_dosen']);
ush_cron_log('  MKU pecah skip : ' . $stats['skip_mku'] . ' (tidak nebak kelas prodi)');
ush_cron_log('  Gagal          : ' . $stats['gagal']);
if (!$LIVE && $laporan) {
    ush_cron_log('Contoh yang akan ditambah:');
    foreach (array_slice($laporan, 0, 25) as $line) {
        ush_cron_log('  ' . $line);
    }
}
ush_cron_log('Selesai. Tidak ada penghapusan.');

file_put_contents($logdir . '/latest.json', json_encode([
    'time' => date('c'),
    'mode' => $LIVE ? 'live' : 'dry-run',
    'semester' => $SUFFIX,
    'stats' => $stats,
    'log' => $logfile,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

flock($lock, LOCK_UN);
fclose($lock);
exit(0);
