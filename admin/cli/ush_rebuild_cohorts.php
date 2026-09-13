<?php
/**
 * Rapikan cohort angkatan dari NIM.
 *
 * NIM USH: 06 + YY + PP + nomor (062401037 → SIF 2024).
 * Anggota salah dikeluarkan; yang kurang dimasukkan.
 *
 * LMS belum dipakai resmi: boleh dijalankan ulang kapan saja.
 *
 *   php admin/cli/ush_rebuild_cohorts.php
 *   php admin/cli/ush_rebuild_cohorts.php --confirm
 */
define('CLI_SCRIPT', true);

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/cohort/lib.php');
require_once($CFG->dirroot . '/local/siakad_sync/locallib.php');

$CONFIRM = false;
foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--confirm') {
        $CONFIRM = true;
    } else if ($arg === '--prune-synced') {
        mtrace('Catatan: --prune-synced tidak perlu. Anggota salah selalu dikeluarkan.');
    } else {
        mtrace('Argumen tidak dikenal: ' . $arg);
        exit(1);
    }
}

mtrace('=== Rapikan cohort dari NIM ===');
mtrace('Site : ' . $CFG->wwwroot);
mtrace('Mode : ' . ($CONFIRM ? 'LIVE' : 'DRY-RUN'));
mtrace('');

$synced = [];
$enrols = $DB->get_records_sql("
    SELECT e.id, e.courseid, e.customint1 AS cohortid, e.status, c.shortname
      FROM {enrol} e
      JOIN {course} c ON c.id = e.courseid
     WHERE e.enrol = 'cohort'
");
foreach ($enrols as $e) {
    $cid = (int) $e->cohortid;
    if (!isset($synced[$cid])) {
        $synced[$cid] = [];
    }
    $synced[$cid][] = $e->shortname . ($e->status ? ' (nonaktif)' : '');
}
if ($synced) {
    mtrace('Ada Cohort sync di kelas (LMS belum resmi: anggota salah tetap dikeluarkan):');
    foreach ($synced as $cid => $names) {
        $idn = $DB->get_field('cohort', 'idnumber', ['id' => $cid]) ?: ('id=' . $cid);
        mtrace('  ' . $idn . ' → ' . implode(', ', array_slice($names, 0, 8)));
    }
    mtrace('');
}

$angkatanids = [];
$cohorts = $DB->get_records('cohort');
foreach ($cohorts as $c) {
    if (preg_match('/^[A-Z0-9]+20\d{2}$/', (string) $c->idnumber)) {
        $angkatanids[(int) $c->id] = (string) $c->idnumber;
    }
}

$users = $DB->get_records_sql("
    SELECT id, username FROM {user}
     WHERE deleted = 0 AND suspended = 0
       AND username REGEXP '^06[0-9]{7,}'
");

$byuser = [];
if ($angkatanids) {
    list($insql, $params) = $DB->get_in_or_equal(array_keys($angkatanids), SQL_PARAMS_NAMED, 'c');
    $rows = $DB->get_records_sql(
        "SELECT id, userid, cohortid FROM {cohort_members} WHERE cohortid {$insql}",
        $params
    );
    foreach ($rows as $row) {
        $cid = (int) $row->cohortid;
        $uid = (int) $row->userid;
        if (!isset($angkatanids[$cid])) {
            continue;
        }
        if (!isset($byuser[$uid])) {
            $byuser[$uid] = [];
        }
        $byuser[$uid][$cid] = $angkatanids[$cid];
    }
}

$add = [];
$remove = [];
$unknown = 0;
$ok = 0;
$created = 0;

foreach ($users as $u) {
    $info = siakad_nim_cohort($u->username);
    if ($info === null) {
        $unknown++;
        continue;
    }

    $wantaliases = siakad_cohort_idnumber_aliases($info['idnumber']);
    $current = $byuser[(int) $u->id] ?? [];
    $already = false;
    foreach ($current as $cid => $idn) {
        if (in_array($idn, $wantaliases, true)) {
            $already = true;
        } else {
            $remove[] = [
                'userid' => (int) $u->id,
                'username' => $u->username,
                'from' => $idn,
                'to' => $info['idnumber'],
                'cohortid' => $cid,
            ];
        }
    }
    if ($already) {
        $ok++;
        continue;
    }
    $add[] = [
        'userid' => (int) $u->id,
        'username' => $u->username,
        'to' => $info['idnumber'],
        'code' => $info['code'],
        'label' => $info['label'],
        'year' => $info['year'],
    ];
}

$addby = [];
foreach ($add as $row) {
    $addby[$row['to']] = ($addby[$row['to']] ?? 0) + 1;
}
$rmby = [];
foreach ($remove as $row) {
    $key = $row['from'] . ' → ' . $row['to'];
    $rmby[$key] = ($rmby[$key] ?? 0) + 1;
}

mtrace('Mahasiswa NIM dikenali : ' . (count($users) - $unknown));
mtrace('Sudah di cohort benar  : ' . $ok);
mtrace('Akan ditambah          : ' . count($add));
ksort($addby);
foreach ($addby as $idn => $n) {
    mtrace(sprintf('    +%-10s %3d', $idn, $n));
}
mtrace('Akan dikeluarkan       : ' . count($remove));
ksort($rmby);
foreach ($rmby as $key => $n) {
    mtrace(sprintf('    %s  (%d)', $key, $n));
}
if ($unknown) {
    mtrace('NIM tidak dipetakan    : ' . $unknown);
}
mtrace('');
$shown = 0;
foreach (array_merge(
    array_map(static fn($r) => '  + ' . $r['username'] . ' → ' . $r['to'], array_slice($add, 0, 8)),
    array_map(static fn($r) => '  - ' . $r['username'] . ' keluar ' . $r['from'] . ' (seharusnya ' . $r['to'] . ')', array_slice($remove, 0, 12))
) as $line) {
    mtrace($line);
    $shown++;
}
if (count($add) + count($remove) > $shown) {
    mtrace('  ...');
}

if (!$CONFIRM) {
    mtrace('');
    mtrace('DRY-RUN. Jalankan lagi dengan --confirm untuk menulis.');
    exit(0);
}

foreach ($add as $row) {
    list($cohort, $isnew) = siakad_find_or_create_cohort($row['code'], $row['label'], $row['year']);
    if ($isnew) {
        $created++;
        mtrace('  COHORT BARU: ' . $cohort->idnumber);
    }
    if (!$DB->record_exists('cohort_members', ['cohortid' => $cohort->id, 'userid' => $row['userid']])) {
        cohort_add_member($cohort->id, $row['userid']);
    }
}
foreach ($remove as $row) {
    cohort_remove_member($row['cohortid'], $row['userid']);
}

mtrace('');
mtrace('Selesai. Cohort baru: ' . $created . ' | tambah: ' . count($add) . ' | keluar: ' . count($remove));
