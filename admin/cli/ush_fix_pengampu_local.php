<?php
/**
 * Sesuaikan dosen pengampu LMS 2026/2027 Ganjil dengan SIAKAD.
 * Sumber: Dosen Pengajar (class_room_lecture) + Dosen Penanggung Jawab.
 * Satu kelas bisa lebih dari satu pengajar (contoh ERP: Nimas + Dwi).
 *
 *   php admin/cli/ush_fix_pengampu_local.php
 *   php admin/cli/ush_fix_pengampu_local.php --confirm
 *   php admin/cli/ush_fix_pengampu_local.php --export=pengampu_target_20262027Ganjil.json
 */
define('CLI_SCRIPT', true);
$ushstartcwd = getcwd();
require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/enrollib.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once(__DIR__ . '/ush_course_owner.php');

if (strpos($CFG->wwwroot, 'localhost') === false && strpos($CFG->wwwroot, '127.0.0.1') === false) {
    mtrace('Dibatalkan: bukan LMS lokal. wwwroot=' . $CFG->wwwroot);
    exit(1);
}

$confirm = false;
$export = '';
foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--confirm') {
        $confirm = true;
    } else if (str_starts_with($arg, '--export=')) {
        $export = substr($arg, 9);
    }
}
$admin = get_admin();
\core\session\manager::set_user($admin);

$SUFFIX = '20262027Ganjil';
$SKIPRE = '/(kkn|skripsi|kerja praktik|praktik kerja|kuliah kerja|seminar proposal)/i';
$PRODI = [
    24 => 'SBD', 25 => 'SGZ', 26 => 'SIF', 31 => 'MBI', 32 => 'HKM',
    33 => 'TPN', 34 => 'BKI', 35 => 'PAR', 36 => 'ABD',
];

function ush_pengampu_norm(string $s): string {
    $s = html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $s = strtoupper(trim(preg_replace('/\s+/', ' ', strip_tags($s)) ?? $s));
    $s = str_replace([',', '.', ';', "'", '’'], ' ', $s);
    $s = trim(preg_replace('/\s+/', ' ', $s) ?? $s);
    foreach ([
        ' S TP', ' STP', ' S T', ' ST', ' M SC', ' MSC', ' S E', ' SE', ' M M', ' MM',
        ' S H', ' SH', ' M H', ' MH', ' S KOM', ' SKOM', ' M KOM', ' MKOM', ' M GZ', ' MGZ',
        ' S PD', ' SPD', ' M PD', ' MPD', ' M HUM', ' MHUM', ' M BIOTECH', ' DR', ' IR ',
        ' S PAR', ' SPAR', ' S ST', ' SST', ' M KES', ' MKES', ' MBA', ' M B A',
    ] as $g) {
        $s = str_replace($g, ' ', ' ' . $s . ' ');
    }
    return trim(preg_replace('/\s+/', ' ', $s) ?? $s);
}

function ush_pengampu_split(string $full): array {
    $full = trim(preg_replace('/\s+/', ' ', $full) ?? $full);
    $parts = $full === '' ? [] : explode(' ', $full);
    $firstname = array_shift($parts) ?: 'Dosen';
    $lastname = trim(implode(' ', $parts));
    return [$firstname, $lastname === '' ? 'USH' : $lastname];
}

function ush_pengampu_unenrol(int $courseid, int $userid): void {
    foreach (enrol_get_instances($courseid, false) as $instance) {
        $plugin = enrol_get_plugin($instance->enrol);
        if ($plugin) {
            $plugin->unenrol_user($instance, $userid);
        }
    }
}

function ush_pengampu_shorts(array $byexact, string $code, string $kelas, int $idprodi, array $prodi, string $suffix): array {
    $code = strtoupper($code);
    $found = [];
    if (stripos($kelas, 'A2') !== false) {
        $sn = strtoupper($code . 'A2_' . $suffix);
        if (!empty($byexact[$sn])) {
            $found[] = $byexact[$sn]->shortname;
            return $found;
        }
    }
    $pk = $prodi[$idprodi] ?? '';
    if ($pk !== '') {
        $sn = strtoupper($code . '_' . $pk . '_' . $suffix);
        if (!empty($byexact[$sn])) {
            $found[] = $byexact[$sn]->shortname;
        }
        $sn23 = strtoupper($code . '_' . $pk . '23_' . $suffix);
        if (!empty($byexact[$sn23])) {
            $found[] = $byexact[$sn23]->shortname;
        }
    }
    if ($found) {
        return $found;
    }
    $sn = strtoupper($code . '_' . $suffix);
    if (!empty($byexact[$sn])) {
        $found[] = $byexact[$sn]->shortname;
    }
    return $found;
}

$from = $CFG->dirroot . '/peserta_20262027Ganjil.json';
if (!is_readable($from) && $ushstartcwd && is_readable($ushstartcwd . '/peserta_20262027Ganjil.json')) {
    $from = $ushstartcwd . '/peserta_20262027Ganjil.json';
}
if (!is_readable($from)) {
    mtrace('File peserta_20262027Ganjil.json tidak terbaca.');
    exit(1);
}
$peserta = json_decode((string) file_get_contents($from), true);
if (!is_array($peserta)) {
    mtrace('File peserta tidak valid.');
    exit(1);
}

$courses = $DB->get_records_sql(
    "SELECT id, shortname, fullname FROM {course}
      WHERE id > 1 AND " . $DB->sql_like('shortname', ':p'),
    ['p' => '%' . $SUFFIX]
);
$byexact = [];
foreach ($courses as $c) {
    $byexact[strtoupper($c->shortname)] = $c;
}

$users = $DB->get_records_sql(
    "SELECT id, username, firstname, lastname
       FROM {user}
      WHERE deleted = 0 AND " . $DB->sql_like('username', ':p'),
    ['p' => 'dosen_%']
);
$byuname = [];
$bynorm = [];
$mandarinusers = [];
foreach ($users as $u) {
    $byuname[$u->username] = $u;
    $norm = ush_pengampu_norm($u->firstname . ' ' . $u->lastname);
    if ($norm !== '') {
        $bynorm[$norm] = $u;
    }
    if (stripos($u->firstname . ' ' . $u->lastname, 'mandarin') !== false) {
        $mandarinusers[] = $u;
    }
}

function ush_pengampu_find_user(string $dosen, int $lecid, array $bynorm, array $byuname, array $mandarinusers): ?stdClass {
    $norm = ush_pengampu_norm($dosen);
    if ($norm !== '' && !empty($bynorm[$norm])) {
        return $bynorm[$norm];
    }
    if ($norm !== '') {
        foreach ($bynorm as $unorm => $u) {
            if ($unorm === '' || $unorm === 'USH' || $unorm === 'DOSEN') {
                continue;
            }
            if (str_contains($norm, $unorm) || str_contains($unorm, $norm)) {
                return $u;
            }
        }
    }
    if (stripos($dosen, 'mandarin') !== false && $mandarinusers) {
        return $mandarinusers[0];
    }
    if ($lecid > 0 && !empty($byuname['dosen_' . $lecid])) {
        $u = $byuname['dosen_' . $lecid];
        $unorm = ush_pengampu_norm($u->firstname . ' ' . $u->lastname);
        if ($unorm === $norm || $norm === '' || str_contains($unorm, 'MANDARIN') || str_contains($unorm, 'YOSEPHINE')) {
            return $u;
        }
        $first = strtok($norm, ' ');
        if ($first && $first !== 'M' && stripos($unorm, $first) !== false) {
            return $u;
        }
    }
    return null;
}

$teacherrole = (int) $DB->get_field('role', 'id', ['shortname' => 'editingteacher'], MUST_EXIST);
$enrolplugin = enrol_get_plugin('manual');
$mnethostid = (int) $CFG->mnet_localhost_id;

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$siakad = new mysqli('127.0.0.1', 'root', '', 'acc_tes_backup');
$siakad->set_charset('utf8mb4');
$pengajarbyclass = [];
$res = $siakad->query("
    SELECT id_class_room, id_lecture
      FROM class_room_lecture
     WHERE id_batch_year = 13 AND id_sub_batch_year = 20
");
while ($row = $res->fetch_assoc()) {
    $pengajarbyclass[(int) $row['id_class_room']][(int) $row['id_lecture']] = true;
}
$pjbyclass = [];
$res = $siakad->query("
    SELECT id, id_lecture
      FROM class_room
     WHERE id_batch_year = 13 AND id_sub_batch_year = 20
");
while ($row = $res->fetch_assoc()) {
    $pjbyclass[(int) $row['id']] = (int) $row['id_lecture'];
}
$siakad->close();

$want = [];
$unresolved = [];
$skipped = 0;
$multiteacher = 0;

foreach ($peserta['classes'] ?? [] as $k) {
    $code = strtoupper(trim((string) ($k['code'] ?? '')));
    $lname = (string) ($k['lesson_name'] ?? '');
    $kelas = (string) ($k['class_name'] ?? '');
    $dosen = trim(strip_tags((string) ($k['dosen'] ?? '')));
    $dosen = trim(preg_replace('/\s+/', ' ', $dosen) ?? $dosen);
    $lecid = (int) ($k['id_lecture'] ?? 0);
    $pid = (int) ($k['id_prodi'] ?? 0);
    $cid = (int) ($k['id_class'] ?? 0);
    $n = count($k['students'] ?? []);
    if ($code === '') {
        continue;
    }
    if ($n === 0 && (int) ($k['jml_siakad'] ?? 0) === 0) {
        continue;
    }
    if (preg_match($SKIPRE, $lname . ' ' . $code . ' ' . $kelas)) {
        $skipped++;
        continue;
    }
    $shorts = ush_pengampu_shorts($byexact, $code, $kelas, $pid, $PRODI, $SUFFIX);
    if (!$shorts) {
        continue;
    }

    $lecids = [];
    if (!empty($pengajarbyclass[$cid])) {
        $lecids = array_keys($pengajarbyclass[$cid]);
    }
    if (!empty($pjbyclass[$cid])) {
        $lecids[] = $pjbyclass[$cid];
    }
    $lecids = array_values(array_unique(array_filter($lecids)));

    $ismandarin = stripos($lname, 'mandarin') !== false || preg_match('/^IUM000[79]/', $code);
    $usersforclass = [];
    foreach ($lecids as $id) {
        if (empty($byuname['dosen_' . $id])) {
            continue;
        }
        $u = $byuname['dosen_' . $id];
        if ($ismandarin && stripos($u->firstname . ' ' . $u->lastname, 'mandarin') === false) {
            continue;
        }
        $usersforclass[] = $u;
    }
    if ($ismandarin && $mandarinusers) {
        $usersforclass[] = $mandarinusers[0];
    }
    if (!$usersforclass) {
        $user = ush_pengampu_find_user($dosen, $lecid, $bynorm, $byuname, $mandarinusers);
        if ($user) {
            $usersforclass[] = $user;
        }
    }
    if (!$usersforclass) {
        $unresolved[] = ($dosen !== '' ? $dosen : 'id=' . implode(',', $lecids)) . ' | ' . $code;
        continue;
    }
    if (count($usersforclass) > 1) {
        $multiteacher++;
    }
    foreach ($shorts as $short) {
        foreach ($usersforclass as $user) {
            $want[$short][$user->id] = [
                'username' => $user->username,
                'name' => trim($user->firstname . ' ' . $user->lastname),
                'code' => $code,
            ];
        }
    }
}

if ($export !== '') {
    if ($ushstartcwd && !preg_match('#^([a-zA-Z]:[\\\\/]|[\\\\/])#', $export)) {
        $export = $ushstartcwd . DIRECTORY_SEPARATOR . $export;
    }
    $coursesout = [];
    foreach ($want as $short => $teachers) {
        $row = ['shortname' => $short, 'teachers' => []];
        foreach ($teachers as $meta) {
            $row['teachers'][] = [
                'username' => $meta['username'],
                'name' => $meta['name'],
            ];
        }
        $coursesout[] = $row;
    }
    $payload = [
        'generated' => date('c'),
        'semester' => $SUFFIX,
        'source' => 'class_room_lecture + id_lecture',
        'courses' => $coursesout,
    ];
    file_put_contents($export, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL);
    mtrace('Diekspor ' . count($coursesout) . ' kelas ke ' . $export);
}

mtrace('=== Sesuaikan pengampu 2026/2027 Ganjil ===');
mtrace('Site: ' . $CFG->wwwroot);
mtrace('Mode: ' . ($confirm ? 'LIVE' : 'DRY-RUN'));
mtrace('Sumber: SIAKAD class_room_lecture + penanggung jawab');
mtrace('Kelas dengan pengampu target: ' . count($want));
mtrace('Kelas SIAKAD lebih dari 1 pengajar: ' . $multiteacher);
mtrace('');

$enroln = 0;
$unenroln = 0;
$alreadyn = 0;
$shown = 0;

foreach ($want as $short => $teachers) {
    $course = $DB->get_record('course', ['shortname' => $short]);
    if (!$course) {
        continue;
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

    $wantids = array_map('intval', array_keys($teachers));
    foreach ($teachers as $uid => $meta) {
        $user = $DB->get_record('user', ['id' => $uid, 'deleted' => 0]);
        if (!$user) {
            continue;
        }
        $has = !empty($have[$uid]);
        $enrolled = is_enrolled($ctx, $user, '', true);
        if ($has && $enrolled) {
            $alreadyn++;
            continue;
        }
        $enroln++;
        if ($shown < 40) {
            mtrace('ENROL  ' . $user->username . ' (' . $meta['name'] . ') → ' . $short);
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
            ush_pengampu_unenrol((int) $course->id, (int) $uid);
        }
    }
}

mtrace('');
mtrace('=== RINGKASAN ===');
mtrace('  Sudah benar     : ' . $alreadyn);
mtrace('  Enrol pengampu  : ' . $enroln);
mtrace('  Lepas yang salah: ' . $unenroln);
mtrace('  KKN/Skripsi skip: ' . $skipped);
mtrace('  Nama tanpa akun : ' . count($unresolved));
if ($unresolved) {
    foreach (array_unique($unresolved) as $line) {
        mtrace('    ' . $line);
    }
}
if (!$confirm) {
    mtrace('DRY-RUN. Ulangi dengan --confirm untuk menulis.');
}
