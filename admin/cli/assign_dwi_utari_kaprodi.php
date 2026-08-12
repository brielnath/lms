<?php
define('CLI_SCRIPT', true);
@error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);
require(__DIR__ . '/../../config.php');

mtrace("=== MENCARI & MEMBUAT AKUN KAPRODI INFORMATIKA (DWI UTARI) ===\n");

$manager_role = $DB->get_record('role', ['shortname' => 'manager']);

// Find Dwi Utari in DB
$user = $DB->get_record_sql("
    SELECT * FROM {user}
    WHERE deleted = 0 AND (
        firstname LIKE '%Dwi%' OR lastname LIKE '%Dwi%' OR
        firstname LIKE '%Utari%' OR lastname LIKE '%Utari%'
    )
");

if (!$user) {
    mtrace("Creating account for Dwi Utari Iswavigra...");
    $newu = new stdClass();
    $newu->username   = 'dosen_64';
    $newu->password   = hash_internal_user_password('Dosen@123');
    $newu->firstname  = 'Dwi Utari';
    $newu->lastname   = 'Iswavigra, S.T., M.Kom.';
    $newu->email      = 'dwi.utari@sugenghartono.ac.id';
    $newu->confirmed  = 1;
    $newu->mnethostid = $CFG->mnet_localhost_id;
    $newu->auth       = 'manual';
    $uid = $DB->insert_record('user', $newu);
    $user = $DB->get_record('user', ['id' => $uid]);
    mtrace("  ✅ Akun Dosen Dwi Utari Dibuat: username=dosen_64, id=$uid");
} else {
    mtrace("  ℹ️  Akun Dwi Utari Ditemukan: ID {$user->id} ({$user->firstname} {$user->lastname})");
}

// Assign to SIF category and sub-categories
$sif_cats = $DB->get_records_sql("
    SELECT id, name FROM {course_categories}
    WHERE name LIKE '%Sistem Informasi%' OR name LIKE '%Informatika%' OR name LIKE '%Angkatan%'
");

foreach ($sif_cats as $cat) {
    $ctx = context_coursecat::instance($cat->id);
    role_assign($manager_role->id, $user->id, $ctx->id);
    mtrace("  ✅ Role KAPRODI (Manager) Diberikan ke {$user->firstname} di Kategori: {$cat->name}");
}

// Summary of Kaprodi for all categories
mtrace("\n=== 🎓 DAFTAR RESMI KAPRODI UNIVERSITAS SUGENG HARTONO ===");
$prodi_cat_ids = [
    'Sistem Informasi (SIF)' => 'Dwi Utari Iswavigra, S.T., M.Kom.',
    'Bisnis Digital (SBD)'   => 'Graceilla Kristia Sheraphim Budiono, S.E, M.B.A',
    'Ilmu Gizi (SGZ)'        => 'Yuniars Renowening, M.Gz',
];

foreach ($prodi_cat_ids as $pname => $kname) {
    mtrace("  • $pname ──► 🎓 Kaprodi: $kname");
}

purge_all_caches();
