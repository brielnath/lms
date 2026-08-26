<?php
/**
 * Script Sinkronisasi SIAKAD → LMS USH
 * File: local/siakad_sync/sync.php
 * 
 * CARA PAKAI:
 * 1. Export data mahasiswa dari SIAKAD sebagai CSV
 * 2. Upload CSV ke: /home/sugengha/siakad_sync/data_mahasiswa.csv
 * 3. Jalankan: php /home/sugengha/lms.ush.ac.id/local/siakad_sync/sync.php
 * 
 * CRON JOB (dijalankan otomatis jam 01.00 setiap hari):
 * 0 1 * * * php /home/sugengha/lms.ush.ac.id/local/siakad_sync/sync.php >> /home/sugengha/siakad_sync/logs/cron.log 2>&1
 */

// ============================================================
// BOOTSTRAP MOODLE
// ============================================================
define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->libdir . '/enrollib.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->dirroot . '/cohort/lib.php');
require_once(__DIR__ . '/config.php');

// ============================================================
// INISIALISASI LOG
// ============================================================
$timestamp   = date('Y-m-d_H-i-s');
$logFile     = LOG_PATH . 'sync_' . $timestamp . '.log';

if (!is_dir(LOG_PATH)) {
    mkdir(LOG_PATH, 0755, true);
}

$stats = [
    'total'      => 0,
    'created'    => 0,
    'updated'    => 0,
    'suspended'  => 0,
    'cohort_add' => 0,
    'skipped'    => 0,
    'errors'     => [],
];

function writeLog($msg, $level = 'INFO') {
    global $logFile;
    $line = '[' . date('Y-m-d H:i:s') . '] [' . $level . '] ' . $msg . PHP_EOL;
    file_put_contents($logFile, $line, FILE_APPEND);
    echo $line;
}

writeLog('========================================');
writeLog('MULAI SINKRONISASI SIAKAD → LMS USH');
writeLog('Waktu: ' . date('d/m/Y H:i:s'));
writeLog('========================================');

// ============================================================
// BACA FILE (EXCEL ATAU CSV)
// ============================================================
if (!file_exists(CSV_INPUT_PATH)) {
    writeLog('ERROR: File tidak ditemukan di: ' . CSV_INPUT_PATH, 'ERROR');
    writeLog('Pastikan file sudah diupload ke folder yang benar.', 'ERROR');
    exit(1);
}

writeLog('File ditemukan: ' . CSV_INPUT_PATH);
$rows = [];

if (USE_EXCEL) {
    // ---- Baca file Excel (.xlsx) menggunakan PhpSpreadsheet ----
    // PhpSpreadsheet sudah tersedia di Moodle 4.x
    $spreadsheetPaths = [
        $CFG->dirroot . '/lib/phpspreadsheet/vendor/autoload.php',
        $CFG->dirroot . '/vendor/phpoffice/phpspreadsheet/src/PhpSpreadsheet/IOFactory.php',
    ];
    $loaded = false;
    foreach ($spreadsheetPaths as $path) {
        if (file_exists($path)) {
            require_once($path);
            $loaded = true;
            break;
        }
    }

    if (!$loaded) {
        // Fallback: gunakan PhpSpreadsheet via composer jika tersedia
        if (file_exists($CFG->dirroot . '/vendor/autoload.php')) {
            require_once($CFG->dirroot . '/vendor/autoload.php');
            $loaded = true;
        }
    }

    if (!$loaded) {
        writeLog('ERROR: PhpSpreadsheet tidak ditemukan. Konversi file ke CSV dan set USE_EXCEL = false.', 'ERROR');
        exit(1);
    }

    try {
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load(CSV_INPUT_PATH);
        $sheet       = $spreadsheet->getSheet(EXCEL_SHEET);
        $allRows     = $sheet->toArray(null, true, true, false);

        // Skip baris header
        $rows = array_slice($allRows, CSV_SKIP_ROWS);
        writeLog('Excel berhasil dibaca. Total baris data: ' . count($rows));
    } catch (\Exception $e) {
        writeLog('ERROR membaca Excel: ' . $e->getMessage(), 'ERROR');
        exit(1);
    }

} else {
    // ---- Baca file CSV biasa ----
    $handle = fopen(CSV_INPUT_PATH, 'r');
    if (!$handle) {
        writeLog('ERROR: Tidak bisa membuka file CSV.', 'ERROR');
        exit(1);
    }
    // Skip header
    for ($i = 0; $i < CSV_SKIP_ROWS; $i++) {
        fgetcsv($handle, 0, CSV_SEPARATOR);
    }
    while (($row = fgetcsv($handle, 0, CSV_SEPARATOR)) !== false) {
        $rows[] = $row;
    }
    fclose($handle);
    writeLog('CSV berhasil dibaca. Total baris data: ' . count($rows));
}

$prodiMap = json_decode(PRODI_COHORT_MAP, true);
writeLog('Memproses ' . count($rows) . ' data mahasiswa...');


// ============================================================
// PROSES SETIAP BARIS MAHASISWA
// ============================================================
foreach ($rows as $row) {
    
    // Skip baris kosong
    if (empty(array_filter($row))) continue;
    if (!is_array($row)) continue;

    $stats['total']++;

    // Ambil data dari kolom CSV
    $nim    = isset($row[COL_NIM])    ? trim($row[COL_NIM])    : '';
    $nama   = isset($row[COL_NAMA])   ? trim($row[COL_NAMA])   : '';
    $prodi  = isset($row[COL_PRODI])  ? trim($row[COL_PRODI])  : '';
    $kelas  = isset($row[COL_KELAS])  ? trim($row[COL_KELAS])  : '';
    $status = isset($row[COL_STATUS]) ? strtolower(trim($row[COL_STATUS])) : 'aktif';
    $email  = isset($row[COL_EMAIL])  ? trim($row[COL_EMAIL])  : '';
    $tahun  = isset($row[COL_TAHUN])  ? trim($row[COL_TAHUN])  : date('Y');

    // Validasi data wajib
    if (empty($nim) || empty($nama)) {
        writeLog("SKIP: Baris {$stats['total']} - NIM atau Nama kosong", 'WARN');
        $stats['skipped']++;
        continue;
    }

    // Gunakan email default jika kosong atau tidak valid
    if (empty($email) || strpos($email, '@') === false) {
        // Gunakan NIM sebagai basis email agar selalu unik
        $email = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $nim)) . '@mhs.ush.ac.id';
    }
    // Pastikan email unik — cek di database
    $emailExists = $DB->get_record('user', ['email' => $email, 'deleted' => 0]);
    if ($emailExists && $emailExists->username !== strtolower($nim)) {
        // Jika email sudah dipakai akun lain, tambahkan suffix NIM
        $email = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $nim)) . '_' . time() . '@mhs.ush.ac.id';
    }

    // Pecah nama menjadi firstname & lastname
    $namaParts = explode(' ', $nama, 2);
    $firstname = $namaParts[0];
    $lastname  = isset($namaParts[1]) ? $namaParts[1] : '-';

    // Tentukan username dari NIM
    $username  = strtolower($nim);

    // Tentukan status suspend
    $suspended = (strpos($status, 'aktif') !== false && strpos($status, 'non') === false) ? 0 : 1;

    // --------------------------------------------------------
    // PROSES USER — dibungkus try-catch agar 1 error tidak stop semua
    // --------------------------------------------------------
    try {

    // CEK APAKAH USER SUDAH ADA DI MOODLE
    $existingUser = $DB->get_record('user', ['username' => $username, 'deleted' => 0]);

    if (!$existingUser) {
        // ---- BUAT AKUN BARU ----
        $newUser = new stdClass();
        $newUser->username    = $username;
        $newUser->password    = hash_internal_user_password(DEFAULT_PASSWORD);
        $newUser->firstname   = $firstname;
        $newUser->lastname    = $lastname;
        $newUser->email       = $email;
        $newUser->city        = DEFAULT_CITY;
        $newUser->country     = DEFAULT_COUNTRY;
        $newUser->lang        = DEFAULT_LANG;
        $newUser->confirmed   = 1;
        $newUser->suspended   = $suspended;
        $newUser->timecreated = time();
        $newUser->timemodified = time();
        $newUser->mnethostid  = $CFG->mnet_localhost_id;
        $newUser->auth        = 'manual';

        if (FORCE_PWD_CHANGE) {
            $newUser->preference_auth_forcepasswordchange = 1;
        }

        try {
            $newUser->id = $DB->insert_record('user', $newUser);
            writeLog("BARU: [{$nim}] {$nama} — Akun berhasil dibuat");
            $stats['created']++;
            $existingUser = $newUser;
        } catch (Exception $e) {
            writeLog("ERROR: Gagal buat akun [{$nim}] {$nama}: " . $e->getMessage(), 'ERROR');
            $stats['errors'][] = "Gagal buat akun [{$nim}] {$nama}";
            continue;
        }
    } else {
        // ---- UPDATE AKUN YANG SUDAH ADA ----
        $needUpdate = false;

        if ($existingUser->suspended != $suspended) {
            $existingUser->suspended = $suspended;
            $needUpdate = true;
            if ($suspended) {
                writeLog("SUSPEND: [{$nim}] {$nama} — Status Non-Aktif dari SIAKAD");
                $stats['suspended']++;
            }
        }

        if ($existingUser->email !== $email && !empty($email)) {
            $existingUser->email = $email;
            $needUpdate = true;
        }

        if ($needUpdate) {
            $existingUser->timemodified = time();
            $DB->update_record('user', $existingUser);
            $stats['updated']++;
        }
    }

    // --------------------------------------------------------
    // TAMBAHKAN KE COHORT (Berdasarkan Prodi + Tahun)
    // --------------------------------------------------------
    // Tentukan ID cohort: misal SIF2023, SBD2024
    $prodiCode   = '';
    foreach ($prodiMap as $prodiNama => $code) {
        if (stripos($prodi, $prodiNama) !== false || stripos($prodi, $code) !== false) {
            $prodiCode = $code;
            break;
        }
    }

    // Jika prodi tidak ditemukan di map, gunakan 4 huruf pertama prodi
    if (empty($prodiCode)) {
        $prodiCode = strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $prodi), 0, 4));
    }

    $cohortIdCode = $prodiCode . $tahun; // Contoh: SIF2023

    // Cari atau buat cohort
    $cohort = $DB->get_record('cohort', ['idnumber' => $cohortIdCode]);

    if (!$cohort) {
        // Buat cohort baru otomatis
        $newCohort            = new stdClass();
        $newCohort->name      = $prodi . ' Angkatan ' . $tahun;
        $newCohort->idnumber  = $cohortIdCode;
        $newCohort->contextid = context_system::instance()->id;
        $newCohort->timecreated  = time();
        $newCohort->timemodified = time();
        $newCohort->component    = '';
        $newCohort->id = $DB->insert_record('cohort', $newCohort);
        $cohort = $newCohort;
        writeLog("COHORT BARU: {$cohortIdCode} — {$prodi} Angkatan {$tahun}");
    }

    // Cek apakah mahasiswa sudah ada di cohort
    $inCohort = $DB->record_exists('cohort_members', [
        'cohortid' => $cohort->id,
        'userid'   => $existingUser->id,
    ]);

    if (!$inCohort) {
        cohort_add_member($cohort->id, $existingUser->id);
        writeLog("COHORT: [{$nim}] {$nama} → ditambahkan ke {$cohortIdCode}");
        $stats['cohort_add']++;
    }
}

fclose($handle);

// ============================================================
// LAPORAN AKHIR
// ============================================================
writeLog('');
writeLog('========================================');
writeLog('HASIL SINKRONISASI SELESAI');
writeLog('========================================');
writeLog("Total data dibaca   : {$stats['total']}");
writeLog("Akun baru dibuat    : {$stats['created']}");
writeLog("Akun diperbarui     : {$stats['updated']}");
writeLog("Akun disuspend      : {$stats['suspended']}");
writeLog("Ditambah ke Cohort  : {$stats['cohort_add']}");
writeLog("Dilewati (skip)     : {$stats['skipped']}");
writeLog("Error               : " . count($stats['errors']));
if (!empty($stats['errors'])) {
    foreach ($stats['errors'] as $err) {
        writeLog("  → " . $err, 'ERROR');
    }
}
writeLog('Log tersimpan di: ' . $logFile);
writeLog('========================================');

// ============================================================
// KIRIM EMAIL LAPORAN KE ADMIN
// ============================================================
if (SEND_EMAIL_REPORT) {
    $subject = '[LMS USH] Laporan Sync SIAKAD — ' . date('d/m/Y H:i');
    $body    = "Sinkronisasi SIAKAD → LMS USH selesai.\n\n";
    $body   .= "Tanggal    : " . date('d/m/Y H:i:s') . "\n";
    $body   .= "Total Data : {$stats['total']}\n";
    $body   .= "Akun Baru  : {$stats['created']}\n";
    $body   .= "Diperbarui : {$stats['updated']}\n";
    $body   .= "Disuspend  : {$stats['suspended']}\n";
    $body   .= "Cohort Add : {$stats['cohort_add']}\n";
    $body   .= "Error      : " . count($stats['errors']) . "\n";

    if (!empty($stats['errors'])) {
        $body .= "\nDETAIL ERROR:\n";
        foreach ($stats['errors'] as $err) {
            $body .= "- " . $err . "\n";
        }
    }

    $body .= "\nLog lengkap tersimpan di server: " . $logFile;

    $adminUser        = new stdClass();
    $adminUser->email = ADMIN_EMAIL;
    $adminUser->name  = 'Admin LMS USH';

    $supportUser        = new stdClass();
    $supportUser->email = $CFG->supportemail ?? ADMIN_EMAIL;
    $supportUser->name  = 'LMS USH System';

    email_to_user($adminUser, $supportUser, $subject, $body);
    writeLog('Email laporan terkirim ke: ' . ADMIN_EMAIL);
}

writeLog('Sinkronisasi selesai pada: ' . date('d/m/Y H:i:s'));
exit(0);
