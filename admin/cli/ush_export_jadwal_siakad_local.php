<?php
/**
 * Ekspor jadwal kuliah 2026/2027 Ganjil dari SIAKAD lokal (hari + jam)
 * digabung nama MK dari peserta JSON, plus shortname LMS jika ada.
 *
 *   php admin/cli/ush_export_jadwal_siakad_local.php
 */
define('CLI_SCRIPT', true);
$ushstartcwd = getcwd();
require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/ush_course_owner.php');

if (strpos($CFG->wwwroot, 'localhost') === false && strpos($CFG->wwwroot, '127.0.0.1') === false) {
    mtrace('Dibatalkan: bukan LMS lokal.');
    exit(1);
}

$SUFFIX = '20262027Ganjil';
$prodi = [
    24 => 'Bisnis Digital',
    25 => 'Ilmu Gizi',
    26 => 'Informatika',
    31 => 'MBI',
    32 => 'Hukum Bisnis',
    33 => 'Teknologi Pangan',
    34 => 'BKI',
    35 => 'Pariwisata',
    36 => 'ABD',
];

$from = $CFG->dirroot . '/peserta_20262027Ganjil.json';
if (!is_readable($from) && $ushstartcwd && is_readable($ushstartcwd . '/peserta_20262027Ganjil.json')) {
    $from = $ushstartcwd . '/peserta_20262027Ganjil.json';
}
$peserta = json_decode(file_get_contents($from), true);
$byclass = [];
foreach ($peserta['classes'] ?? [] as $k) {
    $cid = (int) ($k['id_class'] ?? 0);
    if ($cid) {
        $byclass[$cid] = $k;
    }
}

$lms = [];
$courses = $DB->get_records_sql(
    "SELECT id, shortname, fullname FROM {course} WHERE id > 1 AND " . $DB->sql_like('shortname', ':p'),
    ['p' => '%' . $SUFFIX]
);
foreach ($courses as $c) {
    $base = strtoupper(preg_replace('/_20262027Ganjil$/i', '', $c->shortname) ?? $c->shortname);
    $lms[$base][] = $c;
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$m = new mysqli('127.0.0.1', 'root', '', 'acc_tes_backup');
$m->set_charset('utf8mb4');

$sql = "
    SELECT sl.id_class_room, sl.id_days, sl.id_lesson, sl.id_lecture, sl.id_prodi,
           d.name AS hari, MIN(h.start) AS jam_mulai, MAX(h.end) AS jam_selesai,
           COUNT(*) AS slots
      FROM schedule_lesson sl
      JOIN days d ON d.id = sl.id_days
      JOIN hours h ON h.id = sl.id_hours
     WHERE sl.id_batch_year = 13
       AND sl.id_sub_batch_year = 20
       AND sl.status = 'Y'
       AND sl.is_deleted = 'N'
     GROUP BY sl.id_class_room, sl.id_days, sl.id_lesson, sl.id_lecture, sl.id_prodi, d.name
     ORDER BY d.id, jam_mulai, sl.id_prodi, sl.id_class_room
";
$res = $m->query($sql);

$out = $CFG->dirroot . '/jadwal_20262027Ganjil.csv';
$fp = fopen($out, 'w');
fwrite($fp, "\xEF\xBB\xBF");
fputcsv($fp, [
    'hari', 'jam_mulai', 'jam_selesai', 'sks_slot',
    'prodi', 'kode', 'nama_mk', 'kelas', 'dosen',
    'jml_mhs', 'id_class', 'lms_shortname', 'lms_url',
]);

$n = 0;
$nolms = 0;
while ($row = $res->fetch_assoc()) {
    $cid = (int) $row['id_class_room'];
    $meta = $byclass[$cid] ?? null;
    $code = strtoupper(trim((string) ($meta['code'] ?? '')));
    $nama = (string) ($meta['lesson_name'] ?? '');
    $kelas = (string) ($meta['class_name'] ?? '');
    $dosen = (string) ($meta['dosen'] ?? '');
    $jml = (int) ($meta['jml_diambil'] ?? $meta['jml_siakad'] ?? 0);
    $pid = (int) ($row['id_prodi'] ?: ($meta['id_prodi'] ?? 0));
    $prodiname = $prodi[$pid] ?? (string) $pid;

    $short = '';
    $url = '';
    $course = null;
    if ($code !== '' && stripos($kelas, 'A2') !== false && !empty($lms[$code . 'A2'])) {
        $course = $lms[$code . 'A2'][0];
    } else if ($code !== '' && !empty($lms[$code])) {
        $course = $lms[$code][0];
        if (count($lms[$code]) > 1) {
            $course = null;
        }
    }
    if ($course) {
        $short = $course->shortname;
        $url = $CFG->wwwroot . '/course/view.php?id=' . $course->id;
    } else if ($code !== '' && !empty($lms[$code]) && count($lms[$code]) > 1) {
        $shorts = [];
        foreach ($lms[$code] as $c) {
            $shorts[] = $c->shortname;
        }
        $short = implode(' | ', $shorts);
        $url = $CFG->wwwroot . '/course/view.php?id=' . $lms[$code][0]->id;
    } else if ($code !== '') {
        $nolms++;
    }

    fputcsv($fp, [
        $row['hari'],
        substr($row['jam_mulai'], 0, 5),
        substr($row['jam_selesai'], 0, 5),
        (int) $row['slots'],
        $prodiname,
        $code,
        $nama,
        $kelas,
        $dosen,
        $jml,
        $cid,
        $short,
        $url,
    ]);
    $n++;
}
fclose($fp);

$kal = $CFG->dirroot . '/kalender_pertemuan_20262027Ganjil.csv';
$fp2 = fopen($kal, 'w');
fwrite($fp2, "\xEF\xBB\xBF");
fputcsv($fp2, ['minggu_siakad', 'buka_dari', 'buka_sampai', 'untuk_seksi_lms', 'catatan']);

$map = [
    'W1 O' => ['Pertemuan 1', 'Kuliah minggu 1'],
    'W2 O' => ['Pertemuan 2', 'Kuliah minggu 2'],
    'W3 O' => ['Pertemuan 3', 'Kuliah minggu 3'],
    'W4 O' => ['Pertemuan 4', 'Kuliah minggu 4'],
    'W5 O' => ['Pertemuan 5', 'Di kalender SIAKAD dua pekan (5–16 Sep)'],
    'W7 O' => ['Pertemuan 7', 'Tidak ada label W6 di kalender SIAKAD'],
    'UTS' => ['UTS', 'Ujian tengah semester'],
    'W9 O' => ['Pertemuan 9', 'Kuliah setelah UTS'],
    'W10 O' => ['Pertemuan 10', ''],
    'W11 O' => ['Pertemuan 11', ''],
    'W12 O' => ['Pertemuan 12', ''],
    'W13 O' => ['Pertemuan 13', ''],
    'W14 O' => ['Pertemuan 14', ''],
    'W15 O' => ['Pertemuan 15', ''],
    'PTP15 O' => ['Pertemuan 15 (lanjutan)', 'Perkuliahan pengganti / tambahan'],
    'UAS' => ['UAS', 'Hanya tanggal Jan 2027 yang dipakai ganjil'],
];
$ranges = [];
$kr = $m->query("
    SELECT status, MIN(tanggal) min_t, MAX(tanggal) max_t
      FROM kalender_akademik
     WHERE id_batch_year = 13 AND tanggal BETWEEN '2026-08-01' AND '2027-01-31'
     GROUP BY status
     ORDER BY min_t
");
while ($row = $kr->fetch_assoc()) {
    $st = $row['status'];
    if (!isset($map[$st])) {
        continue;
    }
    $max = $row['max_t'];
    if ($st === 'UTS' && $max > '2026-10-31') {
        $max = '2026-10-06';
    }
    if ($st === 'UAS' && $row['min_t'] > '2026-12-31') {
        // keep
    }
    fputcsv($fp2, [
        $st,
        $row['min_t'],
        $max,
        $map[$st][0],
        $map[$st][1],
    ]);
}
fclose($fp2);

mtrace('Jadwal kelas : ' . $out);
mtrace('Baris        : ' . $n);
mtrace('Tanpa kelas LMS (kode tidak ketemu): ' . $nolms);
mtrace('Kalender     : ' . $kal);
mtrace('Sumber SIAKAD: acc_tes_backup.schedule_lesson batch 13 / sub 20');
