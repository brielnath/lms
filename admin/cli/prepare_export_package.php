<?php
define('CLI_SCRIPT', true);
@error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);
require(__DIR__ . '/../../config.php');

mtrace("==================================================");
mtrace("📦 MEMPERSIAPKAN PAKET EXPORT LOKAL UNTUK PRODUCTION");
mtrace("   Domain Target: https://lms.ush.ac.id");
mtrace("==================================================\n");

$dump_file = "C:\\wamp64\\www\\moodle_dump_lms_ush.sql";
$mysqldump_bin = "C:\\wamp64\\bin\\mysql\\mysql8.0.31\\bin\\mysqldump.exe";

if (!file_exists($mysqldump_bin)) {
    // Search for mysqldump in WAMP dir
    $found = glob("C:\\wamp64\\bin\\mysql\\mysql*\\bin\\mysqldump.exe");
    if (!empty($found)) {
        $mysqldump_bin = reset($found);
    }
}

mtrace("1. Melakukan Export Database SQL Dump...");
$cmd = "\"{$mysqldump_bin}\" -u root moodle > \"{$dump_file}\" 2>&1";
exec($cmd, $out, $ret);

if (file_exists($dump_file) && filesize($dump_file) > 1000) {
    $size_mb = round(filesize($dump_file) / (1024 * 1024), 2);
    mtrace("   ✅ Database berhasil di-export!");
    mtrace("   📄 File SQL: {$dump_file} ({$size_mb} MB)");
} else {
    mtrace("   ⚠️  Gagal export otomatis via mysqldump. Anda dapat export manual via phpMyAdmin lokal.");
}

// 2. Generate config.production.php template
$config_prod_file = "C:\\wamp64\\www\\moodle\\config.production.php";
$config_template = '<?php  // Moodle configuration file - Production https://lms.ush.ac.id

unset($CFG);
global $CFG;
$CFG = new stdClass();

// 1. Pengaturan Database Server Produksi
$CFG->dbtype    = \'mysqli\';
$CFG->dblibrary = \'native\';
$CFG->dbhost    = \'localhost\';             // Ganti jika beda host DB
$CFG->dbname    = \'NAMA_DB_PRODUKSI\';       // Ganti dengan Nama Database Produksi
$CFG->dbuser    = \'USERNAME_DB_PRODUKSI\';   // Ganti dengan Username Database Produksi
$CFG->dbpass    = \'PASSWORD_DB_PRODUKSI\';   // Ganti dengan Password Database Produksi
$CFG->prefix    = \'mdl_\';
$CFG->dboptions = array (
  \'dbpersist\' => 0,
  \'dbport\' => \'\',
  \'dbsocket\' => \'\',
  \'dbcollation\' => \'utf8mb4_unicode_ci\',
);

// 2. URL Domain Resmi & Lokasi Folder Server
$CFG->wwwroot   = \'https://lms.ush.ac.id\';
$CFG->dataroot  = \'/path/to/moodledata\';     // Ganti dengan lokasi folder moodledata di server
$CFG->admin     = \'admin\';

$CFG->directorypermissions = 0777;

require_once(__DIR__ . \'/lib/setup.php\');
';

file_put_contents($config_prod_file, $config_template);
mtrace("\n2. Membuka Templat config.production.php...");
mtrace("   ✅ File Dibuat: {$config_prod_file}");

mtrace("\n==================================================");
mtrace("🎉 PERSIAPAN BACKUP LOKAL SELESAI!");
mtrace("==================================================");
