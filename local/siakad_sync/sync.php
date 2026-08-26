<?php
/**
 * Script Sinkronisasi SIAKAD → LMS USH
 * File: local/siakad_sync/sync.php
 *
 * CARA PAKAI:
 * 1. Upload file Excel/CSV dari SIAKAD ke /home/sugengha/siakad_sync/
 * 2. php /home/sugengha/lms.ush.ac.id/local/siakad_sync/sync.php
 *
 * CRON JOB (otomatis setiap hari jam 01.00):
 * 0 1 * * * php /home/sugengha/lms.ush.ac.id/local/siakad_sync/sync.php >> /home/sugengha/siakad_sync/logs/cron.log 2>&1
 */

// Bootstrap Moodle
define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->libdir . '/enrollib.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->dirroot . '/cohort/lib.php');
require_once(__DIR__ . '/config.php');

// ============================================================
// SETUP LOG
// ============================================================
if (!is_dir(LOG_PATH)) {
    mkdir(LOG_PATH, 0755, true);
}
$logFile  = LOG_PATH . 'sync_' . date('Y-m-d_H-i-s') . '.log';
$stats    = ['total'=>0,'created'=>0,'updated'=>0,'suspended'=>0,'cohort_add'=>0,'skipped'=>0,'errors'=>[]];

function wlog($msg, $lvl = 'INFO') {
    global $logFile;
    $line = '[' . date('Y-m-d H:i:s') . '] [' . $lvl . '] ' . $msg . PHP_EOL;
    file_put_contents($logFile, $line, FILE_APPEND);
    echo $line;
}

wlog('========================================');
wlog('MULAI SINKRONISASI SIAKAD -> LMS USH');
wlog('Waktu: ' . date('d/m/Y H:i:s'));
wlog('========================================');

// ============================================================
// CEK FILE INPUT
// ============================================================
if (!file_exists(CSV_INPUT_PATH)) {
    wlog('ERROR: File tidak ditemukan: ' . CSV_INPUT_PATH, 'ERROR');
    wlog('Pastikan file Excel/CSV sudah diupload ke server.', 'ERROR');
    exit(1);
}
wlog('File ditemukan: ' . CSV_INPUT_PATH);

// ============================================================
// BACA DATA (EXCEL ATAU CSV)
// ============================================================
$rows = [];

if (USE_EXCEL) {
    // Cari PhpSpreadsheet di Moodle
    $autoloadPaths = [
        $CFG->dirroot . '/lib/phpspreadsheet/vendor/autoload.php',
        $CFG->dirroot . '/vendor/autoload.php',
    ];
    $loaded = false;
    foreach ($autoloadPaths as $ap) {
        if (file_exists($ap)) {
            require_once($ap);
            $loaded = true;
            break;
        }
    }
    if (!$loaded) {
        wlog('ERROR: PhpSpreadsheet tidak ditemukan. Ganti USE_EXCEL = false dan upload CSV.', 'ERROR');
        exit(1);
    }
    try {
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load(CSV_INPUT_PATH);
        $sheet       = $spreadsheet->getSheet(EXCEL_SHEET);
        $allRows     = $sheet->toArray(null, true, true, false);
        $rows        = array_slice($allRows, CSV_SKIP_ROWS);
        wlog('Excel berhasil dibaca. Total baris data: ' . count($rows));
    } catch (\Exception $e) {
        wlog('ERROR membaca Excel: ' . $e->getMessage(), 'ERROR');
        exit(1);
    }
} else {
    $handle = fopen(CSV_INPUT_PATH, 'r');
    if (!$handle) {
        wlog('ERROR: Tidak bisa membuka CSV.', 'ERROR');
        exit(1);
    }
    for ($i = 0; $i < CSV_SKIP_ROWS; $i++) {
        fgetcsv($handle, 0, CSV_SEPARATOR);
    }
    while (($csvRow = fgetcsv($handle, 0, CSV_SEPARATOR)) !== false) {
        $rows[] = $csvRow;
    }
    fclose($handle);
    wlog('CSV berhasil dibaca. Total baris data: ' . count($rows));
}

$prodiMap = json_decode(PRODI_COHORT_MAP, true);
wlog('Memproses ' . count($rows) . ' baris data...');

// ============================================================
// PROSES SETIAP MAHASISWA
// ============================================================
foreach ($rows as $row) {

    // Skip baris kosong
    if (!is_array($row) || empty(array_filter($row))) {
        continue;
    }

    // Ambil kolom
    $nim    = isset($row[COL_NIM])    ? trim((string)$row[COL_NIM])    : '';
    $nama   = isset($row[COL_NAMA])   ? trim((string)$row[COL_NAMA])   : '';
    $prodi  = isset($row[COL_PRODI])  ? trim((string)$row[COL_PRODI])  : '';
    $status = isset($row[COL_STATUS]) ? strtolower(trim((string)$row[COL_STATUS])) : 'aktif';
    $email  = isset($row[COL_EMAIL])  ? trim((string)$row[COL_EMAIL])  : '';
    $tahun  = isset($row[COL_TAHUN])  ? trim((string)$row[COL_TAHUN])  : date('Y');

    // Validasi wajib
    if (empty($nim) || empty($nama) || !is_numeric(substr($nim, 0, 4))) {
        wlog("SKIP: NIM '{$nim}' atau Nama '{$nama}' tidak valid", 'WARN');
        $stats['skipped']++;
        continue;
    }

    $stats['total']++;
    $username  = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $nim));
    $suspended = (strpos($status, 'non') !== false || strpos($status, 'keluar') !== false ||
                  strpos($status, 'cuti') !== false) ? 1 : 0;

    // Generate email unik dari NIM jika kosong/tidak valid
    if (empty($email) || strpos($email, '@') === false) {
        $email = $username . '@mhs.ush.ac.id';
    }

    // Tahun angkatan: ambil 4 digit angka dari kolom tahun
    preg_match('/\d{4}/', $tahun, $tahunMatch);
    $tahunAngkatan = isset($tahunMatch[0]) ? $tahunMatch[0] : date('Y');

    // Pecah nama
    $namaParts = explode(' ', $nama, 2);
    $firstname = $namaParts[0];
    $lastname  = isset($namaParts[1]) ? $namaParts[1] : '-';

    // ---- PROSES SATU BARIS — dibungkus try-catch ----
    try {

        // Cek apakah email sudah dipakai akun lain
        $emailOwner = $DB->get_record('user', ['email' => $email, 'deleted' => 0]);
        if ($emailOwner && $emailOwner->username !== $username) {
            $email = $username . '_' . substr($tahunAngkatan, -2) . '@mhs.ush.ac.id';
        }

        // Cek user existing
        $existingUser = $DB->get_record('user', ['username' => $username, 'deleted' => 0]);

        if (!$existingUser) {
            // ---- BUAT AKUN BARU ----
            $newUser                  = new stdClass();
            $newUser->username        = $username;
            $newUser->password        = hash_internal_user_password(DEFAULT_PASSWORD);
            $newUser->firstname       = $firstname;
            $newUser->lastname        = $lastname;
            $newUser->email           = $email;
            $newUser->city            = DEFAULT_CITY;
            $newUser->country         = DEFAULT_COUNTRY;
            $newUser->lang            = DEFAULT_LANG;
            $newUser->confirmed       = 1;
            $newUser->suspended       = $suspended;
            $newUser->timecreated     = time();
            $newUser->timemodified    = time();
            $newUser->mnethostid      = $CFG->mnet_localhost_id;
            $newUser->auth            = 'manual';
            $newUser->id = $DB->insert_record('user', $newUser);

            if (FORCE_PWD_CHANGE) {
                set_user_preference('auth_forcepasswordchange', 1, $newUser->id);
            }

            wlog("BARU: [{$nim}] {$nama}");
            $stats['created']++;
            $existingUser = $newUser;

        } else {
            // ---- UPDATE JIKA ADA PERUBAHAN ----
            $needUpdate = false;
            if ($existingUser->suspended != $suspended) {
                $existingUser->suspended = $suspended;
                $needUpdate = true;
                if ($suspended) {
                    wlog("SUSPEND: [{$nim}] {$nama}");
                    $stats['suspended']++;
                }
            }
            if (!empty($email) && $existingUser->email !== $email) {
                $existingUser->email = $email;
                $needUpdate = true;
            }
            if ($needUpdate) {
                $existingUser->timemodified = time();
                $DB->update_record('user', $existingUser);
                $stats['updated']++;
            }
        }

        // ---- COHORT ----
        $prodiCode = '';
        foreach ($prodiMap as $prodiNama => $code) {
            if (stripos($prodi, $prodiNama) !== false || stripos($prodi, $code) !== false) {
                $prodiCode = $code;
                break;
            }
        }
        if (empty($prodiCode)) {
            $prodiCode = strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $prodi), 0, 4));
        }

        $cohortIdCode = $prodiCode . $tahunAngkatan;
        $cohort = $DB->get_record('cohort', ['idnumber' => $cohortIdCode]);
        if (!$cohort) {
            $newCohort              = new stdClass();
            $newCohort->name        = trim($prodi) . ' Angkatan ' . $tahunAngkatan;
            $newCohort->idnumber    = $cohortIdCode;
            $newCohort->contextid   = context_system::instance()->id;
            $newCohort->timecreated = time();
            $newCohort->timemodified = time();
            $newCohort->component   = '';
            $newCohort->id = $DB->insert_record('cohort', $newCohort);
            $cohort = $newCohort;
            wlog("COHORT BARU: {$cohortIdCode}");
        }

        $inCohort = $DB->record_exists('cohort_members', [
            'cohortid' => $cohort->id,
            'userid'   => $existingUser->id,
        ]);
        if (!$inCohort) {
            cohort_add_member($cohort->id, $existingUser->id);
            $stats['cohort_add']++;
        }

    } catch (\Exception $e) {
        $errMsg = "ERROR [{$nim}] {$nama}: " . $e->getMessage();
        wlog($errMsg, 'ERROR');
        $stats['errors'][] = $errMsg;
    }

} // akhir foreach

// ============================================================
// LAPORAN AKHIR
// ============================================================
wlog('');
wlog('========================================');
wlog('SELESAI — HASIL SINKRONISASI');
wlog('========================================');
wlog("Total diproses      : {$stats['total']}");
wlog("Akun baru dibuat    : {$stats['created']}");
wlog("Akun diperbarui     : {$stats['updated']}");
wlog("Akun disuspend      : {$stats['suspended']}");
wlog("Ditambah ke Cohort  : {$stats['cohort_add']}");
wlog("Dilewati (skip)     : {$stats['skipped']}");
wlog("Error               : " . count($stats['errors']));
foreach ($stats['errors'] as $err) {
    wlog("  -> " . $err, 'ERROR');
}
wlog('Log tersimpan di: ' . $logFile);
wlog('========================================');

// Kirim email laporan ke admin
if (SEND_EMAIL_REPORT && !empty(ADMIN_EMAIL)) {
    $subject = '[LMS USH] Laporan Sync SIAKAD ' . date('d/m/Y H:i');
    $body    = "Sinkronisasi SIAKAD -> LMS USH\n\n";
    $body   .= "Tanggal    : " . date('d/m/Y H:i:s') . "\n";
    $body   .= "Total      : {$stats['total']}\n";
    $body   .= "Baru       : {$stats['created']}\n";
    $body   .= "Diperbarui : {$stats['updated']}\n";
    $body   .= "Disuspend  : {$stats['suspended']}\n";
    $body   .= "Cohort     : {$stats['cohort_add']}\n";
    $body   .= "Error      : " . count($stats['errors']) . "\n";
    if (!empty($stats['errors'])) {
        $body .= "\nDETAIL ERROR:\n";
        foreach ($stats['errors'] as $err) { $body .= "- $err\n"; }
    }
    $admin = core_user::get_support_user();
    $adminDest = new stdClass();
    $adminDest->email = ADMIN_EMAIL;
    $adminDest->name  = 'Admin LMS USH';
    email_to_user($adminDest, $admin, $subject, $body);
    wlog('Email laporan terkirim ke: ' . ADMIN_EMAIL);
}

exit(0);
