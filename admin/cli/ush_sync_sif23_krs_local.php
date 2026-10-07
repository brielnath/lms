<?php
/**
 * Sinkron peserta Informatika angkatan 2023 dari KRS SIAKAD lokal.
 *
 * Smart City dan AR/VR diambil dari KRS batch 13 / sub-batch 20.
 * Mandarin 0 SKS tidak tercatat di KRS; pesertanya disamakan dengan
 * dua MK tersebut berdasarkan keputusan akademik.
 *
 * Hanya menambah enrol yang belum ada; tidak melepas enrol manual.
 *
 * php admin/cli/ush_sync_sif23_krs_local.php
 * php admin/cli/ush_sync_sif23_krs_local.php --confirm
 */
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/enrollib.php');

if (strpos($CFG->wwwroot, 'localhost') === false && strpos($CFG->wwwroot, '127.0.0.1') === false) {
    mtrace('Dibatalkan: bukan LMS lokal. wwwroot=' . $CFG->wwwroot);
    exit(1);
}

$confirm = in_array('--confirm', array_slice($argv, 1), true);
$studentrole = (int) $DB->get_field('role', 'id', ['shortname' => 'student'], MUST_EXIST);
$mnethostid = (int) $CFG->mnet_localhost_id;

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$siakad = new mysqli('127.0.0.1', 'root', '', 'acc_tes_backup');
$siakad->set_charset('utf8mb4');

$source = [
    'SIF1001_20262027Ganjil' => ['lesson' => 876, 'label' => 'Smart City'],
    'SIF1002_20262027Ganjil' => ['lesson' => 332, 'label' => 'AR/VR'],
];
$rosters = [];

foreach ($source as $shortname => $meta) {
    $stmt = $siakad->prepare(
        'SELECT DISTINCT m.nim
           FROM mahasiswa m
           JOIN krs k ON k.id_mahasiswa = m.id
           JOIN krs_detail kd ON kd.id_krs = k.id AND kd.id_mahasiswa = m.id
          WHERE m.id_prodi = 26
            AND m.nim LIKE "062301%"
            AND k.id_batch_year = 13
            AND k.id_sub_batch_year = 20
            AND kd.id_batch_year = 13
            AND kd.id_sub_batch_year = 20
            AND kd.id_lesson = ?
          ORDER BY m.nim'
    );
    $stmt->bind_param('i', $meta['lesson']);
    $stmt->execute();
    $rows = $stmt->get_result();
    $rosters[$shortname] = [];
    while ($row = $rows->fetch_assoc()) {
        $rosters[$shortname][strtolower(trim($row['nim']))] = true;
    }
    $stmt->close();
}
$siakad->close();

// Mandarin 0 SKS tidak tercatat dalam KRS; pakai gabungan peserta KRS dua MK SIF semester 7.
$mandarin = $rosters['SIF1001_20262027Ganjil'] + $rosters['SIF1002_20262027Ganjil'];
$rosters['IUM0009_SIF23_20262027Ganjil'] = $mandarin;

mtrace('=== Sinkron KRS Informatika 2023 ===');
mtrace('Site: ' . $CFG->wwwroot);
mtrace('Mode: ' . ($confirm ? 'LIVE' : 'DRY-RUN'));
mtrace('Sumber: KRS SIAKAD batch 13, sub-batch 20');
mtrace('Mandarin 0 SKS: peserta disamakan dengan Smart City/AR/VR.');
mtrace('');

$added = 0;
$missingaccounts = 0;
foreach ($rosters as $shortname => $nims) {
    $course = $DB->get_record('course', ['shortname' => $shortname]);
    if (!$course) {
        mtrace('Kelas LMS tidak ada: ' . $shortname);
        continue;
    }
    $context = context_course::instance($course->id);
    $new = 0;
    foreach (array_keys($nims) as $nim) {
        $user = $DB->get_record('user', [
            'username' => $nim,
            'mnethostid' => $mnethostid,
            'deleted' => 0,
        ]);
        if (!$user) {
            mtrace('  Akun tidak ada: ' . $nim);
            $missingaccounts++;
            continue;
        }
        if (is_enrolled($context, $user, '', true)) {
            continue;
        }
        mtrace('  Tambah ' . $nim . ' → ' . $shortname);
        if ($confirm && enrol_try_internal_enrol($course->id, $user->id, $studentrole)) {
            $added++;
            $new++;
        } else if (!$confirm) {
            $added++;
            $new++;
        }
    }
    mtrace(sprintf('%s: %d peserta KRS, %d enrol baru', $shortname, count($nims), $new));
}

if ($confirm) {
    rebuild_course_cache(0, true);
}

mtrace('');
mtrace('=== RINGKASAN ===');
mtrace('Enrol baru: ' . $added);
mtrace('Akun tidak ada: ' . $missingaccounts);
if (!$confirm) {
    mtrace('DRY-RUN. Ulangi dengan --confirm untuk menulis.');
}
