<?php
define('CLI_SCRIPT', true);
@error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);
require(__DIR__ . '/../../config.php');

mtrace("=== SETTING KAPRODI MANUAL PADA KATEGORI PRODI ===\n");

// Get Manager Role ID in Moodle
$manager_role = $DB->get_record('role', ['shortname' => 'manager']);
if (!$manager_role) {
    mtrace("❌ Role manager tidak ditemukan!");
    exit(1);
}

// Target Kaprodi Mapping:
// Informatika (SIF) -> Dwi Utari Iswavigra
// Gizi (SGZ)        -> Yuniar / Yuniars
// Bisnis Digital (SBD) -> Grace / Graceilla Kristia

$kaprodi_targets = [
    'SIF' => ['search' => 'Dwi Utari', 'label' => 'Kaprodi Sistem Informasi / Informatika'],
    'SGZ' => ['search' => 'Yuniar', 'label' => 'Kaprodi Ilmu Gizi'],
    'SBD' => ['search' => 'Grace', 'label' => 'Kaprodi Bisnis Digital'],
];

// Find matching categories in Moodle
$categories = $DB->get_records_sql("
    SELECT id, name
    FROM {course_categories}
    WHERE name LIKE '%Sistem Informasi%' OR name LIKE '%Gizi%' OR name LIKE '%Bisnis Digital%'
");

mtrace("Kategori yang Ditemukan di Moodle:");
foreach ($categories as $cat) {
    mtrace("  • [ID: {$cat->id}] {$cat->name}");
}
mtrace("");

foreach ($kaprodi_targets as $code => $info) {
    $search = $info['search'];
    // Find user in Moodle
    $users = $DB->get_records_sql("
        SELECT id, username, firstname, lastname, email
        FROM {user}
        WHERE deleted = 0 AND (
            firstname LIKE ? OR lastname LIKE ? OR username LIKE ?
        )
    ", ["%$search%", "%$search%", "%$search%"]);

    if (empty($users)) {
        mtrace("⚠️  User '$search' tidak ditemukan di database Moodle. Mencari di SIAKAD lecturer list...");
        continue;
    }

    $user = reset($users);
    mtrace("👤 User {$info['label']}: {$user->firstname} {$user->lastname} (Username: {$user->username}, ID: {$user->id})");

    // Assign Manager Role to matching categories for this prodi
    foreach ($categories as $cat) {
        if (stripos($cat->name, $code) !== false || stripos($cat->name, $info['search']) !== false ||
            ($code == 'SIF' && str_contains($cat->name, 'Sistem Informasi')) ||
            ($code == 'SGZ' && str_contains($cat->name, 'Gizi')) ||
            ($code == 'SBD' && str_contains($cat->name, 'Bisnis Digital'))) {

            $context = context_coursecat::instance($cat->id);

            // Assign role
            role_assign($manager_role->id, $user->id, $context->id);
            mtrace("  ✅ Role KAPRODI (Manager) Diberikan ke {$user->firstname} di Kategori: {$cat->name}");
        }
    }
    mtrace("");
}

// Show current category managers
mtrace("=== RINGKASAN KAPRODI TERDAFTAR DI MOODLE ===");
foreach ($categories as $cat) {
    $context = context_coursecat::instance($cat->id);
    $managers = get_role_users($manager_role->id, $context, false, 'u.id, u.firstname, u.lastname, u.username');
    mtrace("📌 Kategori: {$cat->name}");
    if (empty($managers)) {
        mtrace("   (Belum ada Kaprodi)");
    } else {
        foreach ($managers as $m) {
            mtrace("   🎓 Kaprodi: {$m->firstname} {$m->lastname} [Username: {$m->username}]");
        }
    }
}

purge_all_caches();
