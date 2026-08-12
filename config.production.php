<?php  // Moodle configuration file - Production https://lms.ush.ac.id

unset($CFG);
global $CFG;
$CFG = new stdClass();

// 1. Pengaturan Database Server Produksi
$CFG->dbtype    = 'mysqli';
$CFG->dblibrary = 'native';
$CFG->dbhost    = 'localhost';             // Ganti jika beda host DB
$CFG->dbname    = 'NAMA_DB_PRODUKSI';       // Ganti dengan Nama Database Produksi
$CFG->dbuser    = 'USERNAME_DB_PRODUKSI';   // Ganti dengan Username Database Produksi
$CFG->dbpass    = 'PASSWORD_DB_PRODUKSI';   // Ganti dengan Password Database Produksi
$CFG->prefix    = 'mdl_';
$CFG->dboptions = array (
  'dbpersist' => 0,
  'dbport' => '',
  'dbsocket' => '',
  'dbcollation' => 'utf8mb4_unicode_ci',
);

// 2. URL Domain Resmi & Lokasi Folder Server
$CFG->wwwroot   = 'https://lms.ush.ac.id';
$CFG->dataroot  = '/path/to/moodledata';     // Ganti dengan lokasi folder moodledata di server
$CFG->admin     = 'admin';

$CFG->directorypermissions = 0777;

require_once(__DIR__ . '/lib/setup.php');
