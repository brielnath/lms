<?php
/**
 * Enrol dosen pengampu SIAKAD ke kelas 2026/2027 Ganjil (lokal).
 * Mencocokkan lesson_code dengan shortname *_20262027Ganjil.
 */
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/enrollib.php');
require_once($CFG->dirroot . '/user/lib.php');

if (strpos($CFG->wwwroot, 'localhost') === false && strpos($CFG->wwwroot, '127.0.0.1') === false) {
    mtrace('Dibatalkan: bukan LMS lokal. wwwroot=' . $CFG->wwwroot);
    exit(1);
}

$SHORT_SUFFIX = '20262027Ganjil';

function siakad_login() {
    $ch = curl_init('https://siakad.sugenghartono.ac.id/api/login');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode([
            'email' => 'akademik@sugenghartono.ac.id',
            'password' => '321',
        ]),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 20,
    ]);
    $raw = curl_exec($ch);
    curl_close($ch);
    $json = json_decode($raw, true);
    return $json['token'] ?? $json['data']['token'] ?? $json['access_token'] ?? $json['data']['access_token'] ?? null;
}

function siakad_get($url, $token) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token, 'Accept: application/json'],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 40,
    ]);
    $raw = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$http, json_decode($raw, true)];
}

function ush_norm_code(string $code): string {
    $c = strtoupper(trim($code));
    $c = str_replace(['*', ' '], '', $c);
    $c = preg_replace('/_20\d{6}(GANJIL|GENAP)$/i', '', $c) ?? $c;
    return $c;
}

function ush_split_name(string $full): array {
    $full = trim(preg_replace('/\s+/', ' ', $full) ?? $full);
    $parts = explode(' ', $full);
    $firstname = array_shift($parts) ?: 'Dosen';
    $lastname = trim(implode(' ', $parts));
    if ($lastname === '') {
        $lastname = 'USH';
    }
    return [$firstname, $lastname];
}

function ush_get_or_create_dosen(int $lecid, string $fullname): stdClass {
    global $DB, $CFG;

    $username = 'dosen_' . $lecid;
    $user = $DB->get_record('user', ['username' => $username, 'mnethostid' => $CFG->mnet_localhost_id, 'deleted' => 0]);
    if ($user) {
        return $user;
    }

    [$firstname, $lastname] = ush_split_name($fullname);
    $record = (object) [
        'username' => $username,
        'auth' => 'manual',
        'password' => generate_password(),
        'firstname' => $firstname,
        'lastname' => $lastname,
        'email' => 'dosen.' . $lecid . '@sugenghartono.ac.id',
        'confirmed' => 1,
        'mnethostid' => $CFG->mnet_localhost_id,
        'lang' => 'id',
        'calendartype' => $CFG->calendartype ?? 'gregorian',
        'mailformat' => 1,
        'maildisplay' => 2,
    ];
    $id = user_create_user($record, true, false);
    return $DB->get_record('user', ['id' => $id], '*', MUST_EXIST);
}

$token = siakad_login();
if (!$token) {
    mtrace('Gagal login SIAKAD');
    exit(1);
}
mtrace('Login SIAKAD OK');

$lecturers = [];
$mappings = [];
$page = 1;
while ($page <= 80) {
    [$http, $res] = siakad_get(
        'https://siakad.sugenghartono.ac.id/api/grades-per-course?per_page=100&page=' . $page,
        $token
    );
    if ($http !== 200) {
        mtrace("grades-per-course page $page HTTP $http");
        break;
    }
    $items = $res['data'] ?? [];
    foreach ($items as $mhs) {
        foreach ($mhs['grade'] ?? [] as $g) {
            $lecid = (int) ($g['id_lecture'] ?? 0);
            $lecname = trim($g['lecture_name'] ?? '');
            $code = ush_norm_code($g['lesson_code'] ?? '');
            if ($lecid <= 0 || $code === '' || $lecname === '' || strcasecmp($lecname, 'Unknown') === 0) {
                continue;
            }
            $lecturers[$lecid] = $lecname;
            $mappings[$code][$lecid] = $lecname;
        }
    }
    $last = (int) ($res['meta']['last_page'] ?? $res['last_page'] ?? 0);
    mtrace("  Nilai page $page: " . count($items) . ' mhs, MK+dosen ' . count($mappings));
    if (empty($items) || ($last > 0 && $page >= $last)) {
        break;
    }
    $page++;
}

mtrace('Dosen unik SIAKAD: ' . count($lecturers));
mtrace('MK SIAKAD yang punya pengampu: ' . count($mappings));

$courses = $DB->get_records_sql(
    "SELECT id, shortname, fullname FROM {course}
      WHERE id > 1 AND " . $DB->sql_like('shortname', ':p'),
    ['p' => '%' . $SHORT_SUFFIX]
);
$index = [];
foreach ($courses as $course) {
    $base = ush_norm_code($course->shortname);
    $index[$base] = $course;
}
mtrace('Kelas ' . $SHORT_SUFFIX . ': ' . count($index));

$roleid = (int) $DB->get_field('role', 'id', ['shortname' => 'editingteacher'], MUST_EXIST);
$enrolplugin = enrol_get_plugin('manual');

$matched = 0;
$unmatched = [];
$enrolled = 0;
$already = 0;
$createdusers = 0;
$failed = 0;

foreach ($mappings as $code => $lecs) {
    $targets = [];
    if (isset($index[$code])) {
        $targets[$index[$code]->id] = $index[$code];
    }
    if (!str_ends_with($code, 'P') && isset($index[$code . 'P'])) {
        $targets[$index[$code . 'P']->id] = $index[$code . 'P'];
    }
    if (!$targets) {
        $unmatched[$code] = true;
        continue;
    }
    $matched++;

    foreach ($targets as $course) {
        if ($enrolplugin && !$DB->record_exists('enrol', ['courseid' => $course->id, 'enrol' => 'manual'])) {
            $enrolplugin->add_instance($course);
        }
        foreach ($lecs as $lecid => $lecname) {
            $before = $DB->record_exists('user', [
                'username' => 'dosen_' . $lecid,
                'mnethostid' => $CFG->mnet_localhost_id,
                'deleted' => 0,
            ]);
            try {
                $user = ush_get_or_create_dosen((int) $lecid, $lecname);
            } catch (Throwable $e) {
                $failed++;
                mtrace("  Gagal akun dosen_{$lecid}: " . $e->getMessage());
                continue;
            }
            if (!$before) {
                $createdusers++;
            }

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
            if (!enrol_try_internal_enrol($course->id, $user->id, $roleid)) {
                $failed++;
                mtrace("  Gagal enrol {$user->username} → {$course->shortname}");
                continue;
            }
            $enrolled++;
            if ($enrolled <= 15 || $enrolled % 40 === 0) {
                mtrace("  {$lecname} → {$course->shortname}");
            }
        }
    }
}

$with = $DB->count_records_sql(
    "SELECT COUNT(DISTINCT c.id)
       FROM {course} c
       JOIN {context} ctx ON ctx.instanceid = c.id AND ctx.contextlevel = 50
       JOIN {role_assignments} ra ON ra.contextid = ctx.id AND ra.roleid = :r
      WHERE " . $DB->sql_like('c.shortname', ':p'),
    ['r' => $roleid, 'p' => '%' . $SHORT_SUFFIX]
);
$dosenuniq = $DB->count_records_sql(
    "SELECT COUNT(DISTINCT ra.userid)
       FROM {course} c
       JOIN {context} ctx ON ctx.instanceid = c.id AND ctx.contextlevel = 50
       JOIN {role_assignments} ra ON ra.contextid = ctx.id AND ra.roleid = :r
       JOIN {user} u ON u.id = ra.userid AND u.deleted = 0
      WHERE " . $DB->sql_like('c.shortname', ':p'),
    ['r' => $roleid, 'p' => '%' . $SHORT_SUFFIX]
);

mtrace('');
mtrace('Selesai enrol pengampu 2026/2027 Ganjil di ' . $CFG->wwwroot);
mtrace("  MK SIAKAD cocok ke kelas baru : $matched");
mtrace('  MK SIAKAD tidak ada di 2026/2027 : ' . count($unmatched));
mtrace("  Enrol baru : $enrolled");
mtrace("  Sudah terdaftar : $already");
mtrace("  Akun dosen baru : $createdusers");
mtrace("  Gagal : $failed");
mtrace("  Kelas 2026/2027 yang punya pengampu : $with / " . count($index));
mtrace("  Dosen unik di semester baru : $dosenuniq");
if ($unmatched) {
    $sample = array_slice(array_keys($unmatched), 0, 20);
    mtrace('  Contoh kode SIAKAD tanpa kelas 2026/2027: ' . implode(', ', $sample));
}
