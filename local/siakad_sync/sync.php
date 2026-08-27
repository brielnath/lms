<?php
/**
 * Script Sinkronisasi SIAKAD → LMS USH
 * VERSI 3.0 — Menggunakan tool resmi Moodle (admin/tool/uploaduser)
 *
 * CARA PAKAI:
 * php /home/sugengha/lms.ush.ac.id/local/siakad_sync/sync.php
 */

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . '/cohort/lib.php');
require_once(__DIR__ . '/config.php');

// Tingkatkan memory dan waktu eksekusi
raise_memory_limit(MEMORY_HUGE);
core_php_time_limit::raise();

// ============================================================
// SETUP LOG
// ============================================================
if (!is_dir(LOG_PATH)) {
    mkdir(LOG_PATH, 0755, true);
}
$logFile = LOG_PATH . 'sync_' . date('Y-m-d_H-i-s') . '.log';

function wlog($msg, $lvl = 'INFO') {
    global $logFile;
    $line = '[' . date('Y-m-d H:i:s') . '] [' . $lvl . '] ' . $msg . PHP_EOL;
    file_put_contents($logFile, $line, FILE_APPEND);
    echo $line;
}

wlog('========================================');
wlog('MULAI SINKRONISASI SIAKAD -> LMS USH v3');
wlog('Waktu: ' . date('d/m/Y H:i:s'));
wlog('========================================');

// ============================================================
// CEK FILE INPUT
// ============================================================
if (!file_exists(CSV_INPUT_PATH)) {
    wlog('ERROR: File tidak ditemukan: ' . CSV_INPUT_PATH, 'ERROR');
    exit(1);
}
wlog('File SIAKAD ditemukan: ' . basename(CSV_INPUT_PATH));

// ============================================================
// BACA DATA EXCEL / CSV
// ============================================================
$rows = [];
if (USE_EXCEL) {
    $autoloadPaths = [
        $CFG->dirroot . '/lib/phpspreadsheet/vendor/autoload.php',
        $CFG->dirroot . '/vendor/autoload.php',
    ];
    $loaded = false;
    foreach ($autoloadPaths as $ap) {
        if (file_exists($ap)) { require_once($ap); $loaded = true; break; }
    }
    if (!$loaded) {
        wlog('ERROR: PhpSpreadsheet tidak ditemukan.', 'ERROR');
        exit(1);
    }
    try {
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load(CSV_INPUT_PATH);
        $sheet       = $spreadsheet->getSheet(EXCEL_SHEET);
        $allRows     = $sheet->toArray(null, true, true, false);
        $rows        = array_slice($allRows, CSV_SKIP_ROWS);
        wlog('Excel berhasil dibaca. Total baris: ' . count($rows));
    } catch (\Exception $e) {
        wlog('ERROR membaca Excel: ' . $e->getMessage(), 'ERROR');
        exit(1);
    }
} else {
    $handle = fopen(CSV_INPUT_PATH, 'r');
    if (!$handle) { wlog('ERROR: Tidak bisa membuka CSV.', 'ERROR'); exit(1); }
    for ($i = 0; $i < CSV_SKIP_ROWS; $i++) { fgetcsv($handle, 0, CSV_SEPARATOR); }
    while (($r = fgetcsv($handle, 0, CSV_SEPARATOR)) !== false) { $rows[] = $r; }
    fclose($handle);
}

// ============================================================
// KONVERSI KE FORMAT CSV STANDAR MOODLE
// ============================================================
$csvTempPath = LOG_PATH . 'moodle_upload_temp.csv';
$fp = fopen($csvTempPath, 'w');
// Header kolom CSV Moodle
fputcsv($fp, ['username','password','firstname','lastname','email','city','country','lang','idnumber','institution','department']);

$prodiMap     = json_decode(PRODI_COHORT_MAP, true);
$studentData  = []; // Simpan untuk proses cohort & DPA setelah upload
$skipped      = 0;
$csvRowsCount = 0;

foreach ($rows as $row) {
    if (!is_array($row) || empty(array_filter($row))) continue;

    $nim    = isset($row[COL_NIM])    ? trim((string)$row[COL_NIM])    : '';
    $nama   = isset($row[COL_NAMA])   ? trim((string)$row[COL_NAMA])   : '';
    $prodi  = isset($row[COL_PRODI])  ? trim((string)$row[COL_PRODI])  : '';
    $status = isset($row[COL_STATUS]) ? strtolower(trim((string)$row[COL_STATUS])) : 'aktif';
    $dpa    = isset($row[COL_DPA])    ? trim((string)$row[COL_DPA])    : '';
    $email  = isset($row[COL_EMAIL])  ? trim((string)$row[COL_EMAIL])  : '';
    $tahun  = isset($row[COL_TAHUN])  ? trim((string)$row[COL_TAHUN])  : date('Y');

    if (empty($nim) || empty($nama) || !is_numeric(substr($nim, 0, 3))) {
        $skipped++;
        continue;
    }

    $username      = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $nim));
    preg_match('/\d{4}/', $tahun, $tahunMatch);
    $tahunAngkatan = isset($tahunMatch[0]) ? $tahunMatch[0] : date('Y');

    if (empty($email) || strpos($email, '@') === false) {
        $email = $username . '@mhs.ush.ac.id';
    }

    $namaParts = explode(' ', $nama, 2);
    $firstname = $namaParts[0];
    $lastname  = isset($namaParts[1]) && trim($namaParts[1]) !== '' ? $namaParts[1] : '.';

    // Hanya tulis ke CSV jika user BELUM ada (hindari duplikasi)
    $existingUser = $DB->get_record('user', ['username' => $username, 'deleted' => 0]);
    if (!$existingUser) {
        // Cek email duplikat
        $emailOwner = $DB->get_record('user', ['email' => $email, 'deleted' => 0]);
        if ($emailOwner) {
            $email = $username . '_' . substr($tahunAngkatan, -2) . '@mhs.ush.ac.id';
        }
        fputcsv($fp, [
            $username,
            DEFAULT_PASSWORD,
            $firstname,
            $lastname,
            $email,
            DEFAULT_CITY,
            DEFAULT_COUNTRY,
            DEFAULT_LANG,
            $nim,            // idnumber = NIM
            'USH',           // institution
            $prodi,          // department
        ]);
        $csvRowsCount++;
    }

    // Simpan semua data untuk proses cohort & DPA
    $studentData[] = [
        'nim'      => $nim,
        'nama'     => $nama,
        'username' => $username,
        'prodi'    => $prodi,
        'dpa'      => $dpa,
        'tahun'    => $tahunAngkatan,
        'status'   => $status,
    ];
}
fclose($fp);

wlog("CSV Moodle berhasil dibuat: {$csvRowsCount} akun baru akan dibuat.");
wlog("Baris dilewati: {$skipped}");

// ============================================================
// STEP 2: JALANKAN TOOL RESMI MOODLE (uploaduser.php)
// ============================================================
if ($csvRowsCount > 0) {
    wlog('');
    wlog('--- Menjalankan tool resmi Moodle uploaduser ---');

    $uploadScript = $CFG->dirroot . '/admin/tool/uploaduser/cli/uploaduser.php';
    $cmd = PHP_BINARY . ' ' . escapeshellarg($uploadScript)
         . ' --file=' . escapeshellarg($csvTempPath)
         . ' --uutype=0'          // 0 = Add new only, skip existing
         . ' --uupassword=1'      // 1 = use password from file
         . ' --uuupdatetype=0'    // 0 = no update
         . ' --delimiter=comma'
         . ' --encoding=UTF-8'
         . ' --uunoemailduplicates=1' // skip duplicate emails
         . ' 2>&1';

    wlog('Perintah: ' . $cmd);
    passthru($cmd);
    wlog('--- Upload selesai ---');
    wlog('');
} else {
    wlog('Tidak ada akun baru yang perlu dibuat (semua sudah ada di LMS).');
}

// ============================================================
// STEP 3: UPDATE COHORT, DPA, & STATUS SUSPEND
// ============================================================
wlog('Memperbarui Cohort dan DPA untuk ' . count($studentData) . ' mahasiswa...');
$stats = ['cohort_add'=>0, 'dpa_set'=>0, 'suspended'=>0, 'errors'=>[]];

foreach ($studentData as $mhs) {
    try {
        $existingUser = $DB->get_record('user', ['username' => $mhs['username'], 'deleted' => 0]);
        if (!$existingUser) continue; // Masih belum ada, skip

        // Update status suspend
        $suspended = (strpos($mhs['status'], 'non') !== false || strpos($mhs['status'], 'keluar') !== false) ? 1 : 0;
        if ($existingUser->suspended != $suspended) {
            $DB->set_field('user', 'suspended', $suspended, ['id' => $existingUser->id]);
            if ($suspended) { $stats['suspended']++; }
        }

        // Simpan DPA ke preferences
        if (!empty($mhs['dpa']) && strtolower($mhs['dpa']) !== 'belum di set') {
            set_user_preference('siakad_dpa',      $mhs['dpa'],   $existingUser->id);
            set_user_preference('siakad_prodi',    $mhs['prodi'], $existingUser->id);
            set_user_preference('siakad_nim',      $mhs['nim'],   $existingUser->id);
            set_user_preference('siakad_angkatan', $mhs['tahun'], $existingUser->id);
            $stats['dpa_set']++;
        }

        // Cohort management
        $prodiCode = '';
        foreach ($prodiMap as $prodiNama => $code) {
            if (stripos($mhs['prodi'], $prodiNama) !== false || stripos($mhs['prodi'], $code) !== false) {
                $prodiCode = $code; break;
            }
        }
        if (empty($prodiCode)) {
            $prodiCode = strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $mhs['prodi']), 0, 4));
        }

        $cohortIdCode = $prodiCode . $mhs['tahun'];
        $cohort = $DB->get_record('cohort', ['idnumber' => $cohortIdCode]);
        if (!$cohort) {
            $newCohort               = new stdClass();
            $newCohort->name         = trim($mhs['prodi']) . ' Angkatan ' . $mhs['tahun'];
            $newCohort->idnumber     = $cohortIdCode;
            $newCohort->contextid    = context_system::instance()->id;
            $newCohort->timecreated  = time();
            $newCohort->timemodified = time();
            $newCohort->component    = '';
            $newCohort->id           = $DB->insert_record('cohort', $newCohort);
            $cohort                  = $newCohort;
            wlog("COHORT BARU: {$cohortIdCode}");
        }

        if (!$DB->record_exists('cohort_members', ['cohortid' => $cohort->id, 'userid' => $existingUser->id])) {
            cohort_add_member($cohort->id, $existingUser->id);
            $stats['cohort_add']++;
        }

    } catch (\Exception $e) {
        $errMsg = "ERROR [{$mhs['nim']}] {$mhs['nama']}: " . $e->getMessage();
        wlog($errMsg, 'ERROR');
        $stats['errors'][] = $errMsg;
    }
}

// Hapus file CSV temporer
if (file_exists($csvTempPath)) { unlink($csvTempPath); }

// ============================================================
// LAPORAN AKHIR
// ============================================================
wlog('');
wlog('========================================');
wlog('SELESAI — HASIL SINKRONISASI');
wlog('========================================');
wlog("CSV baru ke Moodle  : {$csvRowsCount}");
wlog("Ditambah ke Cohort  : {$stats['cohort_add']}");
wlog("Data DPA tersimpan  : {$stats['dpa_set']}");
wlog("Akun disuspend      : {$stats['suspended']}");
wlog("Error               : " . count($stats['errors']));
wlog('Log tersimpan di    : ' . $logFile);
wlog('========================================');
exit(0);
