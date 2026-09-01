<?php
/**
 * Konfigurasi Sinkronisasi SIAKAD → LMS USH
 * File: local/siakad_sync/config.php
 * 
 * SESUAIKAN nilai di bawah dengan kondisi server Anda
 */

// ============================================================
// MAPPING KOLOM CSV SIAKAD (index mulai dari 0)
// Sesuaikan dengan urutan kolom di file export SIAKAD
// ============================================================
define('COL_NIM',       1);   // Kolom NIM mahasiswa
define('COL_NAMA',      2);   // Kolom Nama Lengkap
define('COL_PRODI',     3);   // Kolom Program Studi
define('COL_KELAS',     4);   // Kolom Kelas
define('COL_STATUS',    5);   // Kolom Status (Aktif/Non-Aktif)
define('COL_DPA',       6);   // Kolom Dosen Pembimbing Akademik (DPA)
define('COL_EMAIL',     8);   // Kolom Email mahasiswa
define('COL_TAHUN',     12);  // Kolom Tahun Angkatan

// ============================================================
// KONFIGURASI CSV
// ============================================================
define('CSV_SEPARATOR',  ',');    // Pemisah kolom: ',' atau ';'
define('CSV_ENCODING',   'UTF-8'); // Encoding file CSV
define('CSV_SKIP_ROWS',  3);      // SIAKAD Excel punya 3 baris header: judul, filter, nama kolom
define('EXCEL_SHEET',    0);      // Index sheet Excel yang dibaca (0 = sheet pertama)

// ============================================================
// MAPPING PRODI → COHORT ID DI MOODLE
// Sesuaikan dengan nama Cohort yang sudah dibuat di LMS
// ============================================================
define('PRODI_COHORT_MAP', json_encode([
    'Akuntansi'             => 'ABD',
    'Pariwisata'            => 'PAR',
    'Bahasa'                => 'BKI',
    'Teknologi Pangan'      => 'TPG',
    'Hukum'                 => 'HKM',
    'Manajemen'             => 'MNJ',
    'Gizi'                  => 'SGZ',
    'Bisnis Digital'        => 'SBD',
    'Sistem Informasi'      => 'SIF',
    'Informatika'           => 'SIF',
]));

// ============================================================
// PENGATURAN DEFAULT AKUN MAHASISWA BARU
// ============================================================
define('DEFAULT_PASSWORD',   'Mhs@USH2026!');  // Password default untuk akun baru
define('FORCE_PWD_CHANGE',   true);             // Paksa ganti password saat login pertama
define('DEFAULT_CITY',       'Sukoharjo');
define('DEFAULT_COUNTRY',    'ID');
define('DEFAULT_LANG',       'id');

// ============================================================
// PENGATURAN EMAIL NOTIFIKASI ADMIN
// ============================================================
define('ADMIN_EMAIL',        'admin@ush.ac.id');  // Email penerima laporan sync
define('SEND_EMAIL_REPORT',  true);                // true = kirim email laporan setiap sync

// ============================================================
// PATH FILE CSV
// Letakkan file CSV SIAKAD di folder ini sebelum sync dijalankan
// ============================================================
// Path file data mahasiswa dari SIAKAD
// Bisa berupa .csv ATAU .xlsx langsung dari SIAKAD
define('CSV_INPUT_PATH', '/home/sugengha/siakad_sync/Data mahasiswa Semua Prodi Tahun Masuk _ Semua Tahun.xlsx');
define('LOG_PATH',       '/home/sugengha/siakad_sync/logs/');

// Set ke true jika file berformat Excel (.xlsx/.xls)
// Set ke false jika file berformat CSV (.csv)
define('USE_EXCEL', true);
