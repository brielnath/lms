<?php
/**
 * Pasang batasan tanggal pertemuan 2026/2027 Ganjil.
 * Sumber: Excel jadwal (hari+jam) + kalender_akademik SIAKAD (Wn O).
 *
 *   php admin/cli/ush_apply_jadwal_pertemuan_local.php
 *   php admin/cli/ush_apply_jadwal_pertemuan_local.php --confirm
 */
define('CLI_SCRIPT', true);
$ushstartcwd = getcwd();
require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->libdir . '/phpspreadsheet/vendor/autoload.php');

use PhpOffice\PhpSpreadsheet\IOFactory;

if (strpos($CFG->wwwroot, 'localhost') === false && strpos($CFG->wwwroot, '127.0.0.1') === false) {
    mtrace('Dibatalkan: bukan LMS lokal.');
    exit(1);
}

$CONFIRM = in_array('--confirm', array_slice($argv, 1), true);
$SUFFIX = '20262027Ganjil';
$SKIPRE = '/(kkn|skripsi|kerja praktik|praktik kerja|kuliah kerja|seminar proposal)/i';
$TZ = new DateTimeZone('Asia/Jakarta');

$xlsx = $CFG->dirroot . '/Jadwal_Kuliah_SIAKAD_2026_2027.xlsx';
if (!is_readable($xlsx) && $ushstartcwd && is_readable($ushstartcwd . '/Jadwal_Kuliah_SIAKAD_2026_2027.xlsx')) {
    $xlsx = $ushstartcwd . '/Jadwal_Kuliah_SIAKAD_2026_2027.xlsx';
}
if (!is_readable($xlsx)) {
    mtrace('File Excel tidak terbaca: Jadwal_Kuliah_SIAKAD_2026_2027.xlsx');
    exit(1);
}

$excelprodi = [
    'INFORMATIKA' => 'SIF',
    'BISNIS DIGITAL' => 'SBD',
    'GIZI' => 'SGZ',
    'ILMU GIZI' => 'SGZ',
    'HUKUM BISNIS' => 'HKM',
    'MANAJEMEN BISNIS INTERNASIONAL' => 'MBI',
    'TEKNOLOGI PANGAN' => 'TPN',
    'BAHASA DAN KEBUDAYAAN INGGRIS' => 'BKI',
    'PROGRAM STUDI PARIWISATA' => 'PAR',
    'PARIWISATA' => 'PAR',
    'PROGRAM STUDI AKUNTANSI BISNIS DIGITAL' => 'ABD',
    'AKUNTANSI BISNIS DIGITAL' => 'ABD',
];

$harimap = [
    'SENIN' => 1, 'SELASA' => 2, 'RABU' => 3, 'KAMIS' => 4,
    "JUM'AT" => 5, 'JUMAT' => 5, 'JUMAT' => 5, 'SABTU' => 6, 'MINGGU' => 7,
];

function ush_norm_code(string $code): string {
    $c = strtoupper(trim($code));
    return preg_replace('/\s+/', '', $c) ?? $c;
}

function ush_parse_jam(string $waktu): string {
    if (preg_match('/(\d{1,2}:\d{2})/', $waktu, $m)) {
        return $m[1] . ':00';
    }
    return '00:00:00';
}

mtrace('=== Batasan pertemuan dari Excel + kalender SIAKAD ===');
mtrace('Site: ' . $CFG->wwwroot);
mtrace('Mode: ' . ($CONFIRM ? 'LIVE' : 'DRY-RUN'));
mtrace('Excel: ' . $xlsx);
mtrace('');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$siakad = new mysqli('127.0.0.1', 'root', '', 'acc_tes_backup');
$siakad->set_charset('utf8mb4');
$kr = $siakad->query("
    SELECT tanggal, status
      FROM kalender_akademik
     WHERE id_batch_year = 13
       AND tanggal BETWEEN '2026-08-01' AND '2027-01-31'
     ORDER BY tanggal
");
$weekdates = [];
while ($row = $kr->fetch_assoc()) {
    if (preg_match('/^W(\d+)\s+O$/i', trim($row['status']), $m)) {
        $weekdates[(int) $m[1]][] = $row['tanggal'];
    }
}

$spreadsheet = IOFactory::load($xlsx);
$sheet = $spreadsheet->getSheetByName('Semua Jadwal');
if (!$sheet) {
    $sheet = $spreadsheet->getActiveSheet();
}

$excelrows = [];
$max = (int) $sheet->getHighestDataRow();
for ($r = 5; $r <= $max; $r++) {
    $kode = ush_norm_code((string) $sheet->getCell('D' . $r)->getValue());
    if ($kode === '' || $kode === 'KODEMATKUL') {
        continue;
    }
    $nama = trim((string) $sheet->getCell('E' . $r)->getValue());
    if (preg_match($SKIPRE, $nama . ' ' . $kode)) {
        continue;
    }
    $hari = strtoupper(trim((string) $sheet->getCell('B' . $r)->getValue()));
    $hari = str_replace(['`', '’'], "'", $hari);
    $dow = $harimap[$hari] ?? 0;
    if ($dow < 1) {
        continue;
    }
    $excelrows[] = [
        'kode' => $kode,
        'nama' => $nama,
        'kelas' => trim((string) $sheet->getCell('G' . $r)->getValue()),
        'dosen' => trim((string) $sheet->getCell('I' . $r)->getValue()),
        'prodi' => strtoupper(trim((string) $sheet->getCell('J' . $r)->getValue())),
        'dow' => $dow,
        'jam' => ush_parse_jam((string) $sheet->getCell('C' . $r)->getValue()),
        'hari' => $hari,
    ];
}

$groups = [];
foreach ($excelrows as $row) {
    $key = $row['kode'] . '|' . $row['kelas'] . '|' . $row['prodi'];
    $groups[$key][] = $row;
}

$lms = $DB->get_records_sql(
    "SELECT id, shortname, fullname FROM {course}
      WHERE id > 1 AND " . $DB->sql_like('shortname', ':p'),
    ['p' => '%' . $SUFFIX]
);
$byexact = [];
foreach ($lms as $c) {
    $byexact[strtoupper($c->shortname)] = $c;
}

function ush_find_course(array $byexact, array $first, string $suffix): ?stdClass {
    global $excelprodi;
    $code = $first['kode'];
    $kelas = $first['kelas'];
    $pk = $excelprodi[$first['prodi']] ?? '';
    $cands = [];
    if (stripos($kelas, 'A2') !== false) {
        $cands[] = $code . 'A2_' . $suffix;
    }
    if ($pk !== '') {
        $cands[] = $code . '_' . $pk . '_' . $suffix;
    }
    $cands[] = $code . '_' . $suffix;
    foreach ($cands as $sn) {
        $k = strtoupper($sn);
        if (!empty($byexact[$k])) {
            return $byexact[$k];
        }
    }
    return null;
}

function ush_tanggal_hari(array $dates, int $dow): ?string {
    foreach ($dates as $d) {
        $n = (int) date('N', strtotime($d . ' 12:00:00'));
        if ($n === $dow) {
            return $d;
        }
    }
    return null;
}

function ush_section_for_pertemuan(array $sections, int $p): ?stdClass {
    foreach ($sections as $s) {
        $name = trim((string) $s->name);
        if ($name !== '' && preg_match('/pertemuan\s*0*' . $p . '\b/i', $name)) {
            if ((int) $s->section === 0) {
                return null;
            }
            return $s;
        }
    }
    foreach ($sections as $s) {
        if ((int) $s->section !== $p) {
            continue;
        }
        $name = trim((string) $s->name);
        if (preg_match('/(uts|uas|perbaikan)/i', $name)) {
            return null;
        }
        return $s;
    }
    return null;
}

set_config('enableavailability', 1);

$matched = 0;
$nomatch = 0;
$updated = 0;
$shown = 0;
$unmatched = [];

foreach ($groups as $key => $slots) {
    $first = $slots[0];
    $course = ush_find_course($byexact, $first, $SUFFIX);
    if (!$course) {
        $nomatch++;
        if (count($unmatched) < 25) {
            $unmatched[] = $first['kode'] . ' | ' . $first['prodi'] . ' | ' . $first['nama'];
        }
        continue;
    }
    $matched++;
    $sections = $DB->get_records('course_sections', ['course' => $course->id], 'section ASC');
    $slotline = [];
    foreach ($slots as $sl) {
        $slotline[] = $sl['hari'] . ' ' . substr($sl['jam'], 0, 5);
    }

    $opens = [];
    $lockts = (new DateTime('2099-01-01 00:00:00', $TZ))->getTimestamp();
    foreach ([1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15] as $p) {
        $best = null;
        $bestts = null;
        if (!empty($weekdates[$p])) {
            foreach ($slots as $sl) {
                $tgl = ush_tanggal_hari($weekdates[$p], (int) $sl['dow']);
                if (!$tgl) {
                    continue;
                }
                $dt = new DateTime($tgl . ' ' . $sl['jam'], $TZ);
                $ts = $dt->getTimestamp();
                if ($bestts === null || $ts < $bestts) {
                    $bestts = $ts;
                    $best = $dt;
                }
            }
        }
        $opens[$p] = $best;
        $sec = ush_section_for_pertemuan($sections, $p);
        if (!$sec) {
            continue;
        }
        $ts = $bestts ?? $lockts;
        $json = json_encode([
            'op' => '&',
            'c' => [['type' => 'date', 'd' => '>=', 't' => $ts]],
            'showc' => [true],
        ]);
        if ($CONFIRM) {
            $DB->set_field('course_sections', 'availability', $json, ['id' => $sec->id]);
            $updated++;
        } else {
            $updated++;
        }
    }

    if ($shown < 12) {
        $bits = [];
        foreach ($opens as $p => $dt) {
            $bits[] = 'P' . $p . '=' . ($dt ? $dt->format('Y-m-d H:i') : '-');
        }
        mtrace($course->shortname . ' | ' . implode(', ', $slotline));
        mtrace('  ' . implode('  ', $bits));
        $shown++;
    }
}

if ($CONFIRM) {
    rebuild_course_cache(0, true);
    purge_all_caches();
}

mtrace('');
mtrace('=== RINGKASAN ===');
mtrace('  Baris Excel (minus KKN/Skripsi) : ' . count($excelrows));
mtrace('  Kelas unik di Excel             : ' . count($groups));
mtrace('  Cocok ke LMS                    : ' . $matched);
mtrace('  Tidak ada cangkang LMS          : ' . $nomatch);
mtrace('  Seksi yang ' . ($CONFIRM ? 'diubah' : 'akan diubah') . '        : ' . $updated);
mtrace('  UTS/UAS                         : tidak diubah');
mtrace('  Pertemuan 6 dan 8               : dikosongkan (tidak ada W6/W8)');
if ($unmatched) {
    mtrace('  Contoh tanpa LMS:');
    foreach ($unmatched as $line) {
        mtrace('    ' . $line);
    }
}
if (!$CONFIRM) {
    mtrace('DRY-RUN. Ulangi dengan --confirm untuk menulis.');
}
