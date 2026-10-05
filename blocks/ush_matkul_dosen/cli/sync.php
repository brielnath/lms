<?php
/**
 * Jalankan sinkronisasi mata kuliah dosen secara manual (sama dengan tugas terjadwal).
 *
 *   php blocks/ush_matkul_dosen/cli/sync.php
 *   php blocks/ush_matkul_dosen/cli/sync.php --hide-only   (hanya sembunyikan kursus non-aktif)
 *   php blocks/ush_matkul_dosen/cli/sync.php --link-only   (tautkan kartu & daftarkan dosen dari data tersimpan)
 */
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');

$args = array_slice($argv, 1);

$sync = new \block_ush_matkul_dosen\local\sync();
if (in_array('--link-only', $args, true)) {
    $sync->link_courses();
    $sync->hide_inactive_courses();
    exit(0);
}
if (!in_array('--hide-only', $args, true)) {
    $sync->sync_upcoming();
}
$sync->hide_inactive_courses();
