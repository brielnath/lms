<?php
/**
 * Enrol dosen pengampu ke kelas satu semester, dari file pemetaan SIAKAD.
 * Aman di production: default DRY-RUN, menulis hanya dengan --confirm.
 *
 * File pemetaan dibuat di lokal:
 *   php admin/cli/ush_export_pengampu_siakad.php --out=pengampu_20262027Ganjil.json
 *
 * Lalu di production:
 *   php admin/cli/ush_enrol_dosen_production.php --from-file=pengampu_20262027Ganjil.json
 *   php admin/cli/ush_enrol_dosen_production.php --from-file=pengampu_20262027Ganjil.json --confirm
 *
 * Opsi:
 *   --from-file=FILE       Wajib. JSON pengampu ATAU JSON peserta (ush_export_peserta_siakad.php)
 *   --semester=20262027Ganjil  Suffix shortname kelas (default 20262027Ganjil)
 *   --confirm              Benar-benar membuat akun & enrol
 *   --max-new-users=N      Batas akun dosen baru yang boleh dibuat (default 60)
 */
define('CLI_SCRIPT', true);

// Moodle memindah working directory, jadi catat dulu supaya --from-file relatif tetap ketemu.
$ushstartcwd = getcwd();

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/enrollib.php');
require_once($CFG->dirroot . '/user/lib.php');

$FROMFILE = '';
$SUFFIX = '20262027Ganjil';
$CONFIRM = false;
$MAXNEWUSERS = 60;
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
    } else if (str_starts_with($arg, '--max-new-users=')) {
        $MAXNEWUSERS = max(0, (int) substr($arg, 16));
    } else {
        mtrace('Argumen tidak dikenal: ' . $arg);
        exit(1);
    }
}

if ($FROMFILE === '') {
    mtrace('Wajib pakai --from-file=pengampu_xxx.json');
    mtrace('Buat dulu di lokal: php admin/cli/ush_export_pengampu_siakad.php --out=pengampu_' . $SUFFIX . '.json');
    exit(1);
}
if (!is_readable($FROMFILE) && $ushstartcwd && is_readable($ushstartcwd . '/' . $FROMFILE)) {
    $FROMFILE = $ushstartcwd . '/' . $FROMFILE;
}
if (!is_readable($FROMFILE)) {
    mtrace('File pemetaan tidak terbaca: ' . $FROMFILE);
    exit(1);
}

$payload = json_decode(file_get_contents($FROMFILE), true);
if (!is_array($payload)) {
    mtrace('Isi file tidak dikenali. Buat ulang dengan ush_export_pengampu_siakad.php atau ush_export_peserta_siakad.php');
    exit(1);
}
// File peserta (--full/--pilot) juga memuat id_lecture + nama dosen per kelas.
if (empty($payload['mappings']) && !empty($payload['classes']) && is_array($payload['classes'])) {
    $bycode = [];
    foreach ($payload['classes'] as $kelas) {
        $code = strtoupper(trim((string) ($kelas['code'] ?? '')));
        $lecid = (int) ($kelas['id_lecture'] ?? 0);
        $lecname = trim(strip_tags((string) ($kelas['dosen'] ?? '')));
        $lecname = trim(preg_replace('/\s+/', ' ', $lecname) ?? $lecname);
        if ($code === '' || $lecid <= 0 || $lecname === '') {
            continue;
        }
        if (!isset($bycode[$code])) {
            $bycode[$code] = ['code' => $code, 'lecturers' => []];
        }
        $bycode[$code]['lecturers'][$lecid] = ['id' => $lecid, 'name' => $lecname];
    }
    foreach ($bycode as &$row) {
        $row['lecturers'] = array_values($row['lecturers']);
    }
    unset($row);
    $payload['mappings'] = array_values($bycode);
    mtrace('Sumber dikenali sebagai file peserta (' . count($payload['classes']) . ' kelas → ' . count($payload['mappings']) . ' MK berpengampu).');
}
if (empty($payload['mappings']) || !is_array($payload['mappings'])) {
    mtrace('Isi file tidak dikenali. Buat ulang dengan ush_export_pengampu_siakad.php atau ush_export_peserta_siakad.php');
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

mtrace('=== Enrol dosen pengampu *_' . $SUFFIX . ' ===');
mtrace('Site   : ' . $CFG->wwwroot);
mtrace('Mode   : ' . ($CONFIRM ? 'LIVE (menulis data)' : 'DRY-RUN (tidak menulis)'));
mtrace('Sumber : ' . $FROMFILE . ' (dibuat ' . ($payload['generated'] ?? '?') . ')');
mtrace('Kode MK di file : ' . count($payload['mappings']));
mtrace('');

function ush_dosen_norm_code(string $code): string {
    $c = strtoupper(trim($code));
    $c = str_replace(['*', ' '], '', $c);
    return preg_replace('/_20\d{6}(GANJIL|GENAP)$/i', '', $c) ?? $c;
}

function ush_dosen_split_name(string $full): array {
    $full = trim(preg_replace('/\s+/', ' ', $full) ?? $full);
    $parts = explode(' ', $full);
    $firstname = array_shift($parts) ?: 'Dosen';
    $lastname = trim(implode(' ', $parts));
    if ($lastname === '') {
        $lastname = 'USH';
    }
    return [$firstname, $lastname];
}

// --- Indeks kelas semester ini ---
$courses = $DB->get_records_sql(
    "SELECT id, shortname, fullname FROM {course}
      WHERE id > 1 AND " . $DB->sql_like('shortname', ':p'),
    ['p' => '%' . $SUFFIX]
);
$index = [];
foreach ($courses as $course) {
    $index[ush_dosen_norm_code($course->shortname)] = $course;
}
mtrace('Kelas *_' . $SUFFIX . ' di LMS : ' . count($index));
if (!$index) {
    mtrace('Belum ada kelas untuk semester ini. Jalankan ush_prepare_semester_production.php dulu.');
    exit(1);
}

$roleid = (int) $DB->get_field('role', 'id', ['shortname' => 'editingteacher'], MUST_EXIST);
$enrolplugin = enrol_get_plugin('manual');

// --- Pemeriksaan awal: akun dosen yang belum ada ---
$needusers = [];
$matchedcodes = 0;
$unmatched = [];
foreach ($payload['mappings'] as $row) {
    $code = ush_dosen_norm_code($row['code'] ?? '');
    if ($code === '' || !isset($index[$code])) {
        if ($code !== '') {
            $unmatched[$code] = true;
        }
        continue;
    }
    $matchedcodes++;
    foreach ($row['lecturers'] ?? [] as $lec) {
        $lecid = (int) ($lec['id'] ?? 0);
        if ($lecid <= 0) {
            continue;
        }
        $exists = $DB->record_exists('user', [
            'username' => 'dosen_' . $lecid,
            'mnethostid' => $CFG->mnet_localhost_id,
            'deleted' => 0,
        ]);
        if (!$exists) {
            $needusers[$lecid] = trim($lec['name'] ?? '');
        }
    }
}

mtrace('MK yang cocok dengan kelas   : ' . $matchedcodes);
mtrace('MK di file tanpa kelas di LMS: ' . count($unmatched));
mtrace('Akun dosen yang perlu dibuat : ' . count($needusers));
foreach (array_slice($needusers, 0, 10, true) as $lecid => $name) {
    mtrace("    dosen_$lecid — $name");
}
if (count($needusers) > 10) {
    mtrace('    ... (+' . (count($needusers) - 10) . ' lagi)');
}
mtrace('');

if (count($needusers) > $MAXNEWUSERS) {
    mtrace('BERHENTI: akun baru (' . count($needusers) . ') melebihi batas aman ' . $MAXNEWUSERS . '.');
    mtrace('Angka sebesar ini biasanya tanda data SIAKAD tidak wajar. Periksa dulu daftar di atas.');
    mtrace('Kalau memang benar, ulangi dengan --max-new-users=' . (count($needusers) + 5));
    exit(1);
}

// --- Proses ---
$enrolled = 0;
$already = 0;
$createdusers = 0;
$failed = 0;
$planned = 0;

foreach ($payload['mappings'] as $row) {
    $code = ush_dosen_norm_code($row['code'] ?? '');
    if ($code === '' || !isset($index[$code])) {
        continue;
    }
    $course = $index[$code];

    if ($CONFIRM && $enrolplugin && !$DB->record_exists('enrol', ['courseid' => $course->id, 'enrol' => 'manual'])) {
        $enrolplugin->add_instance($course);
    }

    foreach ($row['lecturers'] ?? [] as $lec) {
        $lecid = (int) ($lec['id'] ?? 0);
        $lecname = trim($lec['name'] ?? '');
        if ($lecid <= 0 || $lecname === '') {
            continue;
        }

        $user = $DB->get_record('user', [
            'username' => 'dosen_' . $lecid,
            'mnethostid' => $CFG->mnet_localhost_id,
            'deleted' => 0,
        ]);

        if ($user) {
            $ctx = context_course::instance($course->id);
            $hasrole = $DB->record_exists('role_assignments', [
                'roleid' => $roleid,
                'contextid' => $ctx->id,
                'userid' => $user->id,
            ]);
            if ($hasrole && is_enrolled($ctx, $user, '', true)) {
                $already++;
                continue;
            }
        }

        if (!$CONFIRM) {
            $planned++;
            if ($planned <= 15) {
                mtrace("  [DRY] $lecname → {$course->shortname}");
            }
            continue;
        }

        if (!$user) {
            [$firstname, $lastname] = ush_dosen_split_name($lecname);
            try {
                $newid = user_create_user((object) [
                    'username' => 'dosen_' . $lecid,
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
                $user = $DB->get_record('user', ['id' => $newid], '*', MUST_EXIST);
                $createdusers++;
            } catch (Throwable $e) {
                $failed++;
                mtrace("  Gagal buat akun dosen_$lecid: " . $e->getMessage());
                continue;
            }
        }

        if (!enrol_try_internal_enrol($course->id, $user->id, $roleid)) {
            $failed++;
            mtrace("  Gagal enrol {$user->username} → {$course->shortname}");
            continue;
        }
        $enrolled++;
        if ($enrolled <= 15 || $enrolled % 40 === 0) {
            mtrace("  $lecname → {$course->shortname}");
        }
    }
}

// --- Ringkasan ---
$withteacher = (int) $DB->count_records_sql(
    "SELECT COUNT(DISTINCT c.id)
       FROM {course} c
       JOIN {context} ctx ON ctx.instanceid = c.id AND ctx.contextlevel = " . CONTEXT_COURSE . "
       JOIN {role_assignments} ra ON ra.contextid = ctx.id AND ra.roleid = :r
      WHERE " . $DB->sql_like('c.shortname', ':p'),
    ['r' => $roleid, 'p' => '%' . $SUFFIX]
);

mtrace('');
mtrace('=== RINGKASAN ===');
if ($CONFIRM) {
    mtrace('  Enrol baru        : ' . $enrolled);
    mtrace('  Akun dosen dibuat : ' . $createdusers);
} else {
    mtrace('  Akan di-enrol     : ' . $planned);
    mtrace('  Akun akan dibuat  : ' . count($needusers));
}
mtrace('  Sudah terdaftar   : ' . $already);
mtrace('  Gagal             : ' . $failed);
mtrace('  Kelas punya pengampu : ' . $withteacher . ' / ' . count($index));
if ($unmatched) {
    mtrace('  Contoh kode SIAKAD tanpa kelas: ' . implode(', ', array_slice(array_keys($unmatched), 0, 15)));
}
mtrace('');
if (!$CONFIRM) {
    mtrace('Ini DRY-RUN. Tambahkan --confirm untuk benar-benar menjalankan.');
}
