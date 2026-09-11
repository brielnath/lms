<?php
/**
 * Buat akun mahasiswa (jika belum ada) dan enrol ke kelas semester,
 * dari file peserta SIAKAD.
 *
 * File dibuat di lokal (tanpa kredensial di server):
 *   php admin/cli/ush_export_peserta_siakad.php --pilot --out=peserta_IDM0629.json
 *
 * Lalu di LMS (dry-run dulu):
 *   php admin/cli/ush_enrol_mahasiswa_production.php --from-file=peserta_IDM0629.json
 *   php admin/cli/ush_enrol_mahasiswa_production.php --from-file=peserta_IDM0629.json --confirm
 *
 * Akun baru:
 *   username = NIM (huruf kecil)
 *   password = Ush@{NIM}
 *   email    = {nim}@sugenghartono.ac.id
 *
 * Hanya menambah enrol. Tidak menghapus mahasiswa yang sudah terdaftar.
 *
 * Opsi:
 *   --from-file=FILE          Wajib
 *   --semester=20262027Ganjil Suffix shortname kelas
 *   --only-kode=IDM0629       Batasi satu MK
 *   --confirm                 Benar-benar menulis
 *   --max-new-users=N         Batas akun baru (default 80)
 */
define('CLI_SCRIPT', true);

$ushstartcwd = getcwd();

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/enrollib.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->dirroot . '/cohort/lib.php');
require_once($CFG->dirroot . '/local/siakad_sync/locallib.php');

$FROMFILE = '';
$SUFFIX = '20262027Ganjil';
$ONLYKODE = '';
$CONFIRM = false;
$MAXNEWUSERS = 80;
$ALLOWINCOMPLETE = false;

foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--confirm') {
        $CONFIRM = true;
    } else if ($arg === '--allow-incomplete') {
        $ALLOWINCOMPLETE = true;
    } else if (str_starts_with($arg, '--from-file=')) {
        $FROMFILE = substr($arg, 12);
    } else if (str_starts_with($arg, '--semester=')) {
        $SUFFIX = substr($arg, 11);
    } else if (str_starts_with($arg, '--only-kode=')) {
        $ONLYKODE = strtoupper(trim(substr($arg, 12)));
    } else if (str_starts_with($arg, '--max-new-users=')) {
        $MAXNEWUSERS = max(0, (int) substr($arg, 16));
    } else {
        mtrace('Argumen tidak dikenal: ' . $arg);
        exit(1);
    }
}

if ($FROMFILE === '') {
    mtrace('Wajib pakai --from-file=peserta_xxx.json');
    mtrace('Buat dulu di lokal: php admin/cli/ush_export_peserta_siakad.php --pilot --out=peserta_IDM0629.json');
    exit(1);
}
if (!is_readable($FROMFILE) && $ushstartcwd && is_readable($ushstartcwd . '/' . $FROMFILE)) {
    $FROMFILE = $ushstartcwd . '/' . $FROMFILE;
}
if (!is_readable($FROMFILE)) {
    mtrace('File peserta tidak terbaca: ' . $FROMFILE);
    exit(1);
}

$payload = json_decode(file_get_contents($FROMFILE), true);
if (!is_array($payload) || empty($payload['classes']) || !is_array($payload['classes'])) {
    mtrace('Isi file tidak dikenali. Buat ulang dengan ush_export_peserta_siakad.php');
    exit(1);
}
if (empty($payload['complete'])) {
    mtrace('File ditandai BELUM LENGKAP (satu atau lebih kelas jumlahnya tidak cocok).');
    if (!$ALLOWINCOMPLETE) {
        mtrace('Jalankan ulang ekspor, atau lanjut dengan --allow-incomplete kalau selisihnya sudah Anda terima.');
        exit(1);
    }
    mtrace('Lanjut karena --allow-incomplete.');
}

function ush_mhs_norm_code(string $code): string {
    $c = strtoupper(trim($code));
    $c = str_replace(['*', ' '], '', $c);
    return preg_replace('/_20\d{6}(GANJIL|GENAP)$/i', '', $c) ?? $c;
}

function ush_mhs_split_name(string $full): array {
    $full = trim(preg_replace('/\s+/', ' ', $full) ?? $full);
    $parts = $full === '' ? [] : explode(' ', $full);
    $firstname = array_shift($parts) ?: 'Mahasiswa';
    $lastname = trim(implode(' ', $parts));
    if ($lastname === '') {
        $lastname = 'USH';
    }
    return [$firstname, $lastname];
}

mtrace('=== Enrol mahasiswa *_' . $SUFFIX . ' ===');
mtrace('Site   : ' . $CFG->wwwroot);
mtrace('Mode   : ' . ($CONFIRM ? 'LIVE (menulis data)' : 'DRY-RUN (tidak menulis)'));
mtrace('Sumber : ' . $FROMFILE . ' (dibuat ' . ($payload['generated'] ?? '?') . ')');
mtrace('Kelas di file : ' . count($payload['classes']) . ' | mhs unik : ' . ($payload['students_unique'] ?? '?'));
if ($ONLYKODE !== '') {
    mtrace('Batas MK : ' . $ONLYKODE);
}
mtrace('');

$courses = $DB->get_records_sql(
    "SELECT id, shortname, fullname FROM {course}
      WHERE id > 1 AND " . $DB->sql_like('shortname', ':p'),
    ['p' => '%' . $SUFFIX]
);
$index = [];
foreach ($courses as $course) {
    $index[ush_mhs_norm_code($course->shortname)] = $course;
}
mtrace('Kelas *_' . $SUFFIX . ' di LMS : ' . count($index));
if (!$index) {
    mtrace('Belum ada kelas untuk semester ini. Jalankan ush_prepare_semester_production.php dulu.');
    exit(1);
}

$roleid = (int) $DB->get_field('role', 'id', ['shortname' => 'student'], MUST_EXIST);
$enrolplugin = enrol_get_plugin('manual');
$mnethostid = (int) $CFG->mnet_localhost_id;

$needusers = [];
$matchedclasses = 0;
$unmatched = [];
$plannedenrol = 0;
$alreadyplan = 0;

foreach ($payload['classes'] as $kelas) {
    $code = ush_mhs_norm_code($kelas['code'] ?? '');
    if ($ONLYKODE !== '' && $code !== $ONLYKODE) {
        continue;
    }
    if ($code === '' || !isset($index[$code])) {
        if ($code !== '') {
            $unmatched[$code] = true;
        }
        continue;
    }
    $matchedclasses++;
    $course = $index[$code];
    foreach ($kelas['students'] ?? [] as $s) {
        $nim = preg_replace('/\D+/', '', (string) ($s['nim'] ?? '')) ?? '';
        if ($nim === '') {
            continue;
        }
        $username = strtolower($nim);
        $user = $DB->get_record('user', [
            'username' => $username,
            'mnethostid' => $mnethostid,
            'deleted' => 0,
        ]);
        if (!$user) {
            $needusers[$nim] = trim($s['name'] ?? $nim);
        }
        if ($user) {
            $ctx = context_course::instance($course->id);
            if (is_enrolled($ctx, $user, '', true)) {
                $alreadyplan++;
                continue;
            }
        }
        $plannedenrol++;
    }
}

mtrace('Kelas file yang cocok dengan LMS : ' . $matchedclasses);
mtrace('Kode MK tanpa kelas di LMS       : ' . count($unmatched));
mtrace('Akun mahasiswa yang perlu dibuat : ' . count($needusers));
foreach (array_slice($needusers, 0, 8, true) as $nim => $name) {
    mtrace("    $nim — $name");
}
if (count($needusers) > 8) {
    mtrace('    ... (+' . (count($needusers) - 8) . ' lagi)');
}
mtrace('Enrol yang akan ditambah         : ' . $plannedenrol);
mtrace('Sudah terdaftar (perkiraan)      : ' . $alreadyplan);
mtrace('');

if (count($needusers) > $MAXNEWUSERS) {
    mtrace('BERHENTI: akun baru (' . count($needusers) . ') melebihi batas aman ' . $MAXNEWUSERS . '.');
    mtrace('Untuk uji satu kelas, --max-new-users=40 biasanya cukup.');
    mtrace('Untuk satu angkatan penuh, ulangi dengan --max-new-users=' . (count($needusers) + 10));
    exit(1);
}

$enrolled = 0;
$already = 0;
$createdusers = 0;
$failed = 0;
$planned = 0;
$shown = 0;

foreach ($payload['classes'] as $kelas) {
    $code = ush_mhs_norm_code($kelas['code'] ?? '');
    if ($ONLYKODE !== '' && $code !== $ONLYKODE) {
        continue;
    }
    if ($code === '' || !isset($index[$code])) {
        continue;
    }
    $course = $index[$code];

    if ($CONFIRM && $enrolplugin && !$DB->record_exists('enrol', ['courseid' => $course->id, 'enrol' => 'manual'])) {
        $enrolplugin->add_instance($course);
    }

    foreach ($kelas['students'] ?? [] as $s) {
        $nim = preg_replace('/\D+/', '', (string) ($s['nim'] ?? '')) ?? '';
        $nama = trim($s['name'] ?? '');
        if ($nim === '' || $nama === '') {
            continue;
        }
        $username = strtolower($nim);

        $user = $DB->get_record('user', [
            'username' => $username,
            'mnethostid' => $mnethostid,
            'deleted' => 0,
        ]);

        if ($user) {
            $ctx = context_course::instance($course->id);
            if (is_enrolled($ctx, $user, '', true)) {
                if ($CONFIRM) {
                    siakad_ensure_user_in_nim_cohort((int) $user->id, $username);
                }
                $already++;
                continue;
            }
        }

        if (!$CONFIRM) {
            $planned++;
            if ($shown < 12) {
                mtrace("  [DRY] $username ($nama) → {$course->shortname}");
                $shown++;
            }
            continue;
        }

        if (!$user) {
            [$firstname, $lastname] = ush_mhs_split_name($nama);
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
                $failed++;
                mtrace("  Gagal buat akun $username: " . $e->getMessage());
                continue;
            }
        }

        siakad_ensure_user_in_nim_cohort((int) $user->id, $username);

        if (!enrol_try_internal_enrol($course->id, $user->id, $roleid)) {
            $failed++;
            mtrace("  Gagal enrol $username → {$course->shortname}");
            continue;
        }
        $enrolled++;
        if ($enrolled <= 12 || $enrolled % 40 === 0) {
            mtrace("  $username → {$course->shortname}");
        }
    }
}

mtrace('');
mtrace('=== RINGKASAN ===');
if ($CONFIRM) {
    mtrace('  Enrol baru           : ' . $enrolled);
    mtrace('  Akun mahasiswa dibuat: ' . $createdusers);
} else {
    mtrace('  Akan di-enrol        : ' . $planned);
    mtrace('  Akun akan dibuat     : ' . count($needusers));
}
mtrace('  Sudah terdaftar      : ' . $already);
mtrace('  Gagal                : ' . $failed);
if ($unmatched) {
    mtrace('  Kode tanpa kelas LMS : ' . implode(', ', array_slice(array_keys($unmatched), 0, 15)));
}
mtrace('');
if (!$CONFIRM) {
    mtrace('Ini DRY-RUN. Tambahkan --confirm untuk benar-benar menjalankan.');
}
