<?php
/**
 * Buat / isi semua cohort dari data mahasiswa yang sudah ada di LMS
 * (user preferences siakad_prodi + siakad_angkatan).
 *
 * php local/siakad_sync/cli/build_cohorts.php
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/cohort/lib.php');
require_once(__DIR__ . '/../locallib.php');

$syscontext = context_system::instance();

mtrace('========================================');
mtrace('Membangun cohort dari data mahasiswa');
mtrace('========================================');

$sql = "SELECT u.id AS userid, p.value AS prodi, a.value AS angkatan
          FROM {user} u
          JOIN {user_preferences} p ON p.userid = u.id AND p.name = 'siakad_prodi'
          JOIN {user_preferences} a ON a.userid = u.id AND a.name = 'siakad_angkatan'
         WHERE u.deleted = 0 AND u.suspended = 0";
$rows = $DB->get_records_sql($sql);

$created = 0;
$reused = 0;
$added = 0;
$skipped = 0;
$unmapped = [];
$bycohort = [];

foreach ($rows as $row) {
    $year = '';
    if (preg_match('/(20\d{2})/', (string) $row->angkatan, $m)) {
        $year = $m[1];
    }
    if ($year === '') {
        $skipped++;
        continue;
    }

    $mapped = siakad_map_prodi((string) $row->prodi);
    if ($mapped === null) {
        $key = (string) $row->prodi;
        $unmapped[$key] = ($unmapped[$key] ?? 0) + 1;
        $skipped++;
        continue;
    }

    list($cohort, $isnew) = siakad_find_or_create_cohort($mapped['code'], $mapped['label'], $year);
    if ($isnew) {
        $created++;
        mtrace('  COHORT BARU: ' . $cohort->idnumber . ' — ' . $cohort->name);
    } else {
        $reused++;
    }

    if (!$DB->record_exists('cohort_members', ['cohortid' => $cohort->id, 'userid' => $row->userid])) {
        cohort_add_member($cohort->id, $row->userid);
        $added++;
    }

    $idnumber = $cohort->idnumber;
    if (!isset($bycohort[$idnumber])) {
        $bycohort[$idnumber] = ['name' => $cohort->name, 'n' => 0];
    }
    $bycohort[$idnumber]['n']++;
}

ksort($bycohort);

mtrace('');
mtrace('Daftar cohort:');
foreach ($bycohort as $idnumber => $info) {
    $total = $DB->count_records('cohort_members', ['cohortid' => $DB->get_field('cohort', 'id', ['idnumber' => $idnumber])]);
    mtrace(sprintf('  %-10s  %4d anggota  %s', $idnumber, $total, $info['name']));
}

mtrace('');
mtrace('Mahasiswa diproses : ' . count($rows));
mtrace('Cohort baru        : ' . $created);
mtrace('Ditambah ke cohort : ' . $added);
mtrace('Dilewati           : ' . $skipped);
if ($unmapped) {
    mtrace('Prodi belum terpetakan:');
    foreach ($unmapped as $name => $n) {
        mtrace('  - ' . $name . ' (' . $n . ')');
    }
}
mtrace('Selesai.');
