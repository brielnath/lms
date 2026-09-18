<?php
/**
 * Cek apakah pengampu + kaprodi 2026/2027 Ganjil sudah sama dengan SIAKAD.
 * Hanya baca. Acuan: nama dosen di file peserta (bukan hanya id_lecture).
 *
 *   php admin/cli/ush_verify_pengampu_local.php
 */
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/ush_course_owner.php');

$SUFFIX = '20262027Ganjil';
$skipre = '/(kkn|skripsi|kerja praktik|praktik kerja|kuliah kerja|seminar proposal)/i';
$prodi = [
    24 => 'SBD', 25 => 'SGZ', 26 => 'SIF', 31 => 'MBI', 32 => 'HKM',
    33 => 'TPN', 34 => 'BKI', 35 => 'PAR', 36 => 'ABD',
];
$kaprodi = [
    'dosen_64' => 'SIF',
    'dosen_839' => 'SBD',
    'dosen_60' => 'SGZ',
    'dosen_8412' => 'MBI',
    'dosen_8420' => 'HKM',
    'dosen_8414' => 'TPN',
    'dosen_8442' => 'BKI',
    'dosen_8438' => 'PAR',
    'dosen_70' => 'ABD',
];

$d = json_decode(file_get_contents($CFG->dirroot . '/peserta_20262027Ganjil.json'), true);
if (!is_array($d)) {
    mtrace('File peserta_20262027Ganjil.json tidak terbaca.');
    exit(1);
}

function ush_norm_name(string $s): string {
    $s = html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $s = strtoupper(trim(preg_replace('/\s+/', ' ', strip_tags($s)) ?? $s));
    $s = str_replace([',', '.', ';'], ' ', $s);
    $s = trim(preg_replace('/\s+/', ' ', $s) ?? $s);
    foreach ([' S TP', ' STP', ' S T', ' ST', ' M SC', ' MSC', ' S E', ' SE', ' M M', ' MM',
        ' S H', ' SH', ' M H', ' MH', ' S KOM', ' SKOM', ' M KOM', ' MKOM', ' M GZ', ' MGZ',
        ' S PD', ' SPD', ' M PD', ' MPD', ' M HUM', ' MHUM', ' M BIOTECH', ' DR', ' IR ',
        ' S PAR', ' SPAR'] as $g) {
        $s = str_replace($g, ' ', ' ' . $s . ' ');
    }
    return trim(preg_replace('/\s+/', ' ', $s) ?? $s);
}

function ush_base(string $short): string {
    return strtoupper(preg_replace('/_20262027Ganjil$/i', '', $short) ?? $short);
}

$courses = $DB->get_records_sql(
    "SELECT c.id, c.shortname, ctx.id AS ctxid
       FROM {course} c
       JOIN {context} ctx ON ctx.instanceid = c.id AND ctx.contextlevel = 50
      WHERE c.id > 1 AND " . $DB->sql_like('c.shortname', ':p'),
    ['p' => '%' . $SUFFIX]
);
$bybase = [];
foreach ($courses as $c) {
    $bybase[ush_base($c->shortname)] = $c;
}

function ush_target_short(array $bybase, string $code, string $pk): ?string {
    foreach ([$code . '_' . $pk, $code] as $base) {
        if (!empty($bybase[$base])) {
            return $bybase[$base]->shortname;
        }
    }
    return null;
}

$people = [];
$idmismatch = [];
$skippedold = [];
$skippedkkn = [];

foreach ($d['classes'] ?? [] as $k) {
    $code = strtoupper(trim((string) ($k['code'] ?? '')));
    $pid = (int) ($k['id_prodi'] ?? 0);
    $lname = (string) ($k['lesson_name'] ?? '');
    $dosen = trim(strip_tags((string) ($k['dosen'] ?? '')));
    $lecid = (int) ($k['id_lecture'] ?? 0);
    $n = count($k['students'] ?? []);
    if ($code === '' || $dosen === '' || !isset($prodi[$pid])) {
        continue;
    }
    if ($n === 0 && (int) ($k['jml_siakad'] ?? 0) === 0) {
        continue;
    }
    if (preg_match($skipre, $lname . ' ' . $code)) {
        $skippedkkn[$code] = true;
        continue;
    }
    if (!ush_is_official_scheme_code($code)) {
        $skippedold[$dosen][$code] = $lname;
        continue;
    }
    $pk = $prodi[$pid];
    $key = ush_norm_name($dosen);
    if ($key === '') {
        continue;
    }
    if (!isset($people[$key])) {
        $people[$key] = ['name' => $dosen, 'ids' => [], 'want' => []];
    }
    if ($lecid > 0) {
        $people[$key]['ids'][$lecid] = $lecid;
    }
    $short = ush_target_short($bybase, $code, $pk);
    $people[$key]['want'][$code . '|' . $pk] = [
        'code' => $code,
        'prodi' => $pk,
        'short' => $short,
        'lecid' => $lecid,
        'name' => $lname,
    ];
}

$users = $DB->get_records_sql(
    "SELECT id, username, firstname, lastname
       FROM {user}
      WHERE deleted = 0 AND " . $DB->sql_like('username', ':p'),
    ['p' => 'dosen_%']
);
$byuname = [];
$bynorm = [];
foreach ($users as $u) {
    $byuname[$u->username] = $u;
    $bynorm[ush_norm_name($u->firstname . ' ' . $u->lastname)] = $u;
}

$teacherrows = $DB->get_records_sql(
    "SELECT CONCAT(u.username, '|', c.id) AS k, u.username, c.shortname
       FROM {role_assignments} ra
       JOIN {role} r ON r.id = ra.roleid
       JOIN {context} ctx ON ctx.id = ra.contextid AND ctx.contextlevel = 50
       JOIN {course} c ON c.id = ctx.instanceid
       JOIN {user} u ON u.id = ra.userid AND u.deleted = 0
      WHERE r.shortname IN ('editingteacher', 'teacher')
        AND " . $DB->sql_like('c.shortname', ':p'),
    ['p' => '%' . $SUFFIX]
);
$lmsteach = [];
foreach ($teacherrows as $row) {
    $lmsteach[$row->username][$row->shortname] = true;
}

mtrace('=== Cek pengampu SIAKAD vs LMS 2026/2027 Ganjil ===');
mtrace('Sumber: peserta_20262027Ganjil.json (' . ($d['generated'] ?? '?') . ')');
mtrace('Acuan: NAMA dosen di absensi, lalu dicocokkan ke akun dosen_*');
mtrace('');

$ok = 0;
$problemdosen = 0;
$missingcourses = 0;
$extracount = 0;
$noshort = 0;

foreach ($people as $norm => $p) {
    $user = $bynorm[$norm] ?? null;
    if (!$user) {
        foreach ($p['ids'] as $id) {
            if (!empty($byuname['dosen_' . $id])) {
                $user = $byuname['dosen_' . $id];
                $unorm = ush_norm_name($user->firstname . ' ' . $user->lastname);
                if ($unorm !== $norm && stripos($norm, strtok($unorm, ' ')) === false) {
                    $idmismatch[] = sprintf(
                        '%s | id_lecture=%s akun=%s (%s %s)',
                        $p['name'],
                        implode(',', $p['ids']),
                        $user->username,
                        $user->firstname,
                        $user->lastname
                    );
                }
                break;
            }
        }
    }
    // id_lecture vs nama: flag if dosen_{id} fullname != this name
    foreach ($p['ids'] as $id) {
        $u2 = $byuname['dosen_' . $id] ?? null;
        if ($u2 && ush_norm_name($u2->firstname . ' ' . $u2->lastname) !== $norm) {
            $idmismatch[] = sprintf(
                'NAMA "%s" di MK, tapi id_lecture=%d = %s %s (%s)',
                $p['name'],
                $id,
                $u2->firstname,
                $u2->lastname,
                $u2->username
            );
        }
    }

    if (!$user) {
        $problemdosen++;
        mtrace('TIDAK ADA AKUN: ' . $p['name'] . ' ids=' . implode(',', $p['ids']));
        continue;
    }

    $wantshorts = [];
    $wantmissinglms = [];
    foreach ($p['want'] as $row) {
        if ($row['short']) {
            $wantshorts[$row['short']] = $row['code'] . ' ' . $row['prodi'];
        } else {
            $wantmissinglms[] = $row['code'] . ' ' . $row['prodi'] . ' ' . $row['name'];
            $noshort++;
        }
    }
    $have = $lmsteach[$user->username] ?? [];
    $kurang = array_diff_key($wantshorts, $have);
    $lebih = array_diff_key($have, $wantshorts);

    if (!$kurang && !$lebih && !$wantmissinglms) {
        $ok++;
        continue;
    }
    $problemdosen++;
    mtrace(sprintf('BEDA  %s (%s)  SIAKAD %d  LMS %d',
        $p['name'], $user->username, count($wantshorts), count($have)));
    foreach ($kurang as $sn => $label) {
        mtrace("    KURANG LMS  $sn ($label)");
        $missingcourses++;
    }
    foreach ($lebih as $sn => $_) {
        mtrace("    LEBIH LMS   $sn");
        $extracount++;
    }
    foreach ($wantmissinglms as $line) {
        mtrace("    TIDAK ADA KELAS  $line");
    }
}

mtrace('');
mtrace("Dosen cocok: $ok");
mtrace("Dosen beda / tanpa akun: $problemdosen");
mtrace("MK kurang di LMS: $missingcourses");
mtrace("MK lebih di LMS: $extracount");
mtrace("MK SIAKAD tanpa cangkang LMS (kurikulum lama/KKN sudah disaring): $noshort");

$idmismatch = array_unique($idmismatch);
if ($idmismatch) {
    mtrace('');
    mtrace('=== id_lecture tidak sama dengan nama pengampu (sumber salah ID) ===');
    foreach (array_slice($idmismatch, 0, 20) as $line) {
        mtrace('  ' . $line);
    }
    if (count($idmismatch) > 20) {
        mtrace('  … +' . (count($idmismatch) - 20));
    }
}

mtrace('');
mtrace('=== Kaprodi (Manager folder 2026/2027 Ganjil) ===');
$managerrole = (int) $DB->get_field('role', 'id', ['shortname' => 'manager']);
foreach ($kaprodi as $uname => $kode) {
    $user = $byuname[$uname] ?? $DB->get_record('user', ['username' => $uname, 'deleted' => 0]);
    $cat = $DB->get_record_sql(
        "SELECT id, name FROM {course_categories}
          WHERE " . $DB->sql_like('idnumber', ':idn') . " OR " . $DB->sql_like('name', ':nm'),
        ['idn' => 'CAT_' . $kode . '_2026_2027_Ganjil', 'nm' => '%(' . $kode . ')%2026/2027%Ganjil%']
    );
    if (!$user) {
        mtrace("  KURANG akun $uname ($kode)");
        continue;
    }
    if (!$cat) {
        mtrace("  KURANG folder $kode untuk {$user->firstname}");
        continue;
    }
    $ctx = context_coursecat::instance($cat->id);
    $okm = user_has_role_assignment($user->id, $managerrole, $ctx->id);
    mtrace('  ' . ($okm ? 'OK   ' : 'KURANG ') . $user->firstname . ' ' . $user->lastname . " → {$cat->name}");
}

mtrace('');
mtrace('KKN/Skripsi/KP di SIAKAD (sengaja tidak di LMS): ' . count($skippedkkn));
mtrace('Kode kurikulum lama berpengampu (sengaja tidak dibuat): ' . count($skippedold) . ' dosen punya MK lama');
mtrace('');
mtrace('Cara cek manual di SIAKAD: login dosen → kartu Matkul Di Ampu (jumlah + SKS).');
mtrace('Jangan pakai id_lecture saja. Pakai NAMA di dropdown/absensi.');
