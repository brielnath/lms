<?php
/**
 * Script Sinkronisasi SIAKAD → LMS USH (Menggunakan API Resmi Moodle user_create_user)
 * File: local/siakad_sync/sync.php
 */

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->libdir . '/moodlelib.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->dirroot . '/cohort/lib.php');
require_once(__DIR__ . '/config.php');

// Setup Log
if (!is_dir(LOG_PATH)) {
    mkdir(LOG_PATH, 0755, true);
}
$logFile = LOG_PATH . 'sync_' . date('Y-m-d_H-i-s') . '.log';
$stats   = ['total'=>0, 'created'=>0, 'updated'=>0, 'suspended'=>0, 'cohort_add'=>0, 'skipped'=>0, 'errors'=>[]];

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

if (!file_exists(CSV_INPUT_PATH)) {
    wlog('ERROR: File tidak ditemukan: ' . CSV_INPUT_PATH, 'ERROR');
    exit(1);
}
wlog('File ditemukan: ' . CSV_INPUT_PATH);

// Baca Data (Excel / CSV)
$rows = [];
if (USE_EXCEL) {
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
        wlog('ERROR: PhpSpreadsheet tidak ditemukan.', 'ERROR');
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
}

$prodiMap = json_decode(PRODI_COHORT_MAP, true);
wlog('Memproses ' . count($rows) . ' baris data...');

foreach ($rows as $index => $row) {
    if (!is_array($row) || empty(array_filter($row))) {
        continue;
    }

    $nim    = isset($row[COL_NIM])    ? trim((string)$row[COL_NIM])    : '';
    $nama   = isset($row[COL_NAMA])   ? trim((string)$row[COL_NAMA])   : '';
    $prodi  = isset($row[COL_PRODI])  ? trim((string)$row[COL_PRODI])  : '';
    $status = isset($row[COL_STATUS]) ? strtolower(trim((string)$row[COL_STATUS])) : 'aktif';
    $email  = isset($row[COL_EMAIL])  ? trim((string)$row[COL_EMAIL])  : '';
    $tahun  = isset($row[COL_TAHUN])  ? trim((string)$row[COL_TAHUN])  : date('Y');

    // Abaikan baris jika NIM bukan angka atau Nama kosong
    if (empty($nim) || empty($nama) || !is_numeric(substr($nim, 0, 3))) {
        $stats['skipped']++;
        continue;
    }

    $stats['total']++;
    $username  = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $nim));
    $suspended = (strpos($status, 'non') !== false || strpos($status, 'keluar') !== false || strpos($status, 'cuti') !== false) ? 1 : 0;

    // Pastikan Email Valid & Unik
    if (empty($email) || strpos($email, '@') === false) {
        $email = $username . '@mhs.ush.ac.id';
    }

    preg_match('/\d{4}/', $tahun, $tahunMatch);
    $tahunAngkatan = isset($tahunMatch[0]) ? $tahunMatch[0] : date('Y');

    $namaParts = explode(' ', $nama, 2);
    $firstname = $namaParts[0];
    $lastname  = isset($namaParts[1]) && trim($namaParts[1]) !== '' ? $namaParts[1] : '.';

    try {
        // Cek apakah user sudah ada berdasarkan username
        $existingUser = $DB->get_record('user', ['username' => $username, 'deleted' => 0]);

        if (!$existingUser) {
            // Cek apakah email sudah dipakai user lain
            $emailOwner = $DB->get_record('user', ['email' => $email, 'deleted' => 0]);
            if ($emailOwner) {
                $email = $username . '_' . substr($tahunAngkatan, -2) . '@mhs.ush.ac.id';
            }

            // GUNAAN API RESMI MOODLE (user_create_user) UNTUK MENCEGAH ERROR DB
            $userObj = new stdClass();
            $userObj->username   = $username;
            $userObj->password   = DEFAULT_PASSWORD;
            $userObj->firstname  = $firstname;
            $userObj->lastname   = $lastname;
            $userObj->email      = $email;
            $userObj->auth       = 'manual';
            $userObj->city       = DEFAULT_CITY;
            $userObj->country    = DEFAULT_COUNTRY;
            $userObj->lang       = DEFAULT_LANG;
            $userObj->suspended  = $suspended;

            $newUserId = user_create_user($userObj, false, false);
            set_user_preference('auth_forcepasswordchange', 1, $newUserId);

            wlog("BARU: [{$nim}] {$nama}");
            $stats['created']++;

            $existingUser = $DB->get_record('user', ['id' => $newUserId]);

        } else {
            // Update jika ada perubahan status
            if ($existingUser->suspended != $suspended) {
                $existingUser->suspended = $suspended;
                $DB->update_record('user', $existingUser);
                if ($suspended) {
                    wlog("SUSPEND: [{$nim}] {$nama}");
                    $stats['suspended']++;
                } else {
                    $stats['updated']++;
                }
            }
        }

        // COHORT MANAGEMENT
        if ($existingUser) {
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
                $newCohort->id          = $DB->insert_record('cohort', $newCohort);
                $cohort                 = $newCohort;
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
        }

    } catch (\Exception $e) {
        $errMsg = "ERROR [{$nim}] {$nama}: " . $e->getMessage();
        wlog($errMsg, 'ERROR');
        $stats['errors'][] = $errMsg;
    }
}

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
wlog('Log tersimpan di    : ' . $logFile);
wlog('========================================');

exit(0);
