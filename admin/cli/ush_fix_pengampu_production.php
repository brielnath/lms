<?php
/**
 * Sesuaikan dosen pengampu LMS production 2026/2027 Ganjil.
 * Sumber: JSON dari laptop (class_room_lecture + penanggung jawab).
 * Default DRY-RUN; tulis dengan --confirm.
 *
 *   php admin/cli/ush_fix_pengampu_production.php --from-file=pengampu_target_20262027Ganjil.json
 *   php admin/cli/ush_fix_pengampu_production.php --from-file=pengampu_target_20262027Ganjil.json --only=IDM0630
 *   php admin/cli/ush_fix_pengampu_production.php --from-file=pengampu_target_20262027Ganjil.json --only=IDM0630 --confirm
 */
define('CLI_SCRIPT', true);
$ushstartcwd = getcwd();
require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/enrollib.php');
require_once($CFG->dirroot . '/user/lib.php');

if (strpos($CFG->wwwroot, 'lms.ush.ac.id') === false) {
    mtrace('Dibatalkan: bukan LMS production. wwwroot=' . $CFG->wwwroot);
    exit(1);
}

$confirm = false;
$fromfile = '';
$only = '';
foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--confirm') {
        $confirm = true;
    } else if (str_starts_with($arg, '--from-file=')) {
        $fromfile = substr($arg, 12);
    } else if (str_starts_with($arg, '--only=')) {
        $only = strtoupper(trim(substr($arg, 7)));
    }
}

if ($fromfile === '') {
    mtrace('Wajib --from-file=pengampu_target_20262027Ganjil.json');
    exit(1);
}
if (!is_readable($fromfile) && $ushstartcwd && is_readable($ushstartcwd . '/' . $fromfile)) {
    $fromfile = $ushstartcwd . '/' . $fromfile;
}
if (!is_readable($fromfile) && is_readable($CFG->dirroot . '/' . basename($fromfile))) {
    $fromfile = $CFG->dirroot . '/' . basename($fromfile);
}
if (!is_readable($fromfile)) {
    mtrace('File pengampu tidak terbaca: ' . $fromfile);
    exit(1);
}

$payload = json_decode((string) file_get_contents($fromfile), true);
if (!is_array($payload) || empty($payload['courses']) || !is_array($payload['courses'])) {
    mtrace('Isi file pengampu tidak dikenali.');
    exit(1);
}
if ($only !== '') {
    $payload['courses'] = array_values(array_filter($payload['courses'], static function (array $row) use ($only): bool {
        $sn = strtoupper((string) ($row['shortname'] ?? ''));
        return str_contains($sn, $only);
    }));
    if (!$payload['courses']) {
        mtrace('Tidak ada kelas yang cocok dengan --only=' . $only);
        exit(1);
    }
}

$admin = get_admin();
\core\session\manager::set_user($admin);

$teacherrole = (int) $DB->get_field('role', 'id', ['shortname' => 'editingteacher'], MUST_EXIST);
$enrolplugin = enrol_get_plugin('manual');
$mnethostid = (int) $CFG->mnet_localhost_id;

function ush_pengampu_prod_split(string $full): array {
    $full = trim(preg_replace('/\s+/', ' ', $full) ?? $full);
    $parts = $full === '' ? [] : explode(' ', $full);
    $firstname = array_shift($parts) ?: 'Dosen';
    $lastname = trim(implode(' ', $parts));
    return [$firstname, $lastname === '' ? 'USH' : $lastname];
}

function ush_pengampu_prod_unenrol(int $courseid, int $userid): void {
    foreach (enrol_get_instances($courseid, false) as $instance) {
        $plugin = enrol_get_plugin($instance->enrol);
        if ($plugin) {
            $plugin->unenrol_user($instance, $userid);
        }
    }
}

function ush_pengampu_prod_ensure_user(string $username, string $name, bool $confirm, int $mnethostid): ?stdClass {
    global $DB, $CFG;
    $user = $DB->get_record('user', [
        'username' => $username,
        'mnethostid' => $mnethostid,
        'deleted' => 0,
    ]);
    if ($user) {
        return $user;
    }
    if (!$confirm) {
        return (object) ['id' => 0, 'username' => $username, 'firstname' => $name, 'lastname' => ''];
    }
    $idnum = preg_replace('/\D+/', '', $username) ?? '';
    [$firstname, $lastname] = ush_pengampu_prod_split($name);
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

mtrace('=== Sesuaikan pengampu production 2026/2027 Ganjil ===');
mtrace('Site: ' . $CFG->wwwroot);
mtrace('Mode: ' . ($confirm ? 'LIVE' : 'DRY-RUN'));
mtrace('Sumber: ' . $fromfile . ' (dibuat ' . ($payload['generated'] ?? '?') . ')');
if ($only !== '') {
    mtrace('Filter: --only=' . $only);
}
mtrace('Kelas di file: ' . count($payload['courses']));
mtrace('');

$enroln = 0;
$unenroln = 0;
$alreadyn = 0;
$createdusers = 0;
$missingcourse = 0;
$shown = 0;

foreach ($payload['courses'] as $row) {
    $short = trim((string) ($row['shortname'] ?? ''));
    $teachers = $row['teachers'] ?? [];
    if ($short === '' || !is_array($teachers) || !$teachers) {
        continue;
    }
    $course = $DB->get_record('course', ['shortname' => $short]);
    if (!$course) {
        $missingcourse++;
        if ($shown < 20) {
            mtrace('BELUM ADA KELAS  ' . $short);
            $shown++;
        }
        continue;
    }

    $wantusers = [];
    foreach ($teachers as $t) {
        $username = strtolower(trim((string) ($t['username'] ?? '')));
        $name = trim((string) ($t['name'] ?? ''));
        if ($username === '' || !str_starts_with($username, 'dosen_')) {
            continue;
        }
        $exists = $DB->record_exists('user', [
            'username' => $username,
            'mnethostid' => $mnethostid,
            'deleted' => 0,
        ]);
        if (!$exists) {
            $createdusers++;
        }
        $user = ush_pengampu_prod_ensure_user($username, $name, $confirm, $mnethostid);
        if ($user && (int) $user->id > 0) {
            $wantusers[(int) $user->id] = $user;
        } else if ($user) {
            $wantusers['u:' . $username] = $user;
        }
    }

    $ctx = context_course::instance($course->id);
    $have = $DB->get_records_sql(
        "SELECT u.id, u.username, u.firstname, u.lastname
           FROM {role_assignments} ra
           JOIN {role} r ON r.id = ra.roleid
           JOIN {user} u ON u.id = ra.userid AND u.deleted = 0
          WHERE ra.contextid = :ctx
            AND r.shortname IN ('editingteacher', 'teacher')
            AND " . $DB->sql_like('u.username', ':p'),
        ['ctx' => $ctx->id, 'p' => 'dosen_%']
    );

    $wantids = [];
    foreach ($wantusers as $user) {
        if ((int) $user->id <= 0) {
            $enroln++;
            if ($shown < 40) {
                mtrace('ENROL  ' . $user->username . ' → ' . $short);
                $shown++;
            }
            continue;
        }
        $wantids[] = (int) $user->id;
        $has = !empty($have[$user->id]);
        $enrolled = is_enrolled($ctx, $user, '', true);
        if ($has && $enrolled) {
            $alreadyn++;
            continue;
        }
        $enroln++;
        if ($shown < 40) {
            mtrace('ENROL  ' . $user->username . ' (' . trim($user->firstname . ' ' . $user->lastname) . ') → ' . $short);
            $shown++;
        }
        if ($confirm) {
            if ($enrolplugin && !$DB->record_exists('enrol', ['courseid' => $course->id, 'enrol' => 'manual'])) {
                $enrolplugin->add_instance($course);
            }
            enrol_try_internal_enrol($course->id, $user->id, $teacherrole);
        }
    }

    foreach ($have as $uid => $u) {
        if (in_array((int) $uid, $wantids, true)) {
            continue;
        }
        $unenroln++;
        if ($shown < 80) {
            mtrace('LEPAS  ' . $u->username . ' (' . $u->firstname . ' ' . $u->lastname . ') ← ' . $short);
            $shown++;
        }
        if ($confirm) {
            ush_pengampu_prod_unenrol((int) $course->id, (int) $uid);
        }
    }
}

mtrace('');
mtrace('=== RINGKASAN ===');
mtrace('  Sudah benar      : ' . $alreadyn);
mtrace('  Enrol pengampu   : ' . $enroln);
mtrace('  Lepas yang salah : ' . $unenroln);
mtrace('  Akun akan dibuat : ' . $createdusers);
mtrace('  Kelas belum ada  : ' . $missingcourse);
if (!$confirm) {
    mtrace('DRY-RUN. Ulangi dengan --confirm untuk menulis.');
}
