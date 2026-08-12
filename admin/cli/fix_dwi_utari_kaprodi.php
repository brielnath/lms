<?php
define('CLI_SCRIPT', true);
@error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);
require(__DIR__ . '/../../config.php');

$manager_role = $DB->get_record('role', ['shortname' => 'manager']);

// Remove Dwi Setyaji manager role
$DB->delete_records('role_assignments', ['roleid' => $manager_role->id, 'userid' => 52]);

// Ensure Dwi Utari Iswavigra account exists
$dwi_utari = $DB->get_record('user', ['username' => 'dosen_64']);
if (!$dwi_utari) {
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
    $dwi_utari = $DB->get_record('user', ['id' => $uid]);
} else {
    $DB->set_field('user', 'firstname', 'Dwi Utari', ['id' => $dwi_utari->id]);
    $DB->set_field('user', 'lastname', 'Iswavigra, S.T., M.Kom.', ['id' => $dwi_utari->id]);
}

// Assign Dwi Utari to SIF categories
$categories = $DB->get_records_sql("
    SELECT id, name FROM {course_categories}
    WHERE name LIKE '%Sistem Informasi%' OR name LIKE '%Informatika%'
");

foreach ($categories as $cat) {
    $ctx = context_coursecat::instance($cat->id);
    role_assign($manager_role->id, $dwi_utari->id, $ctx->id);
}

// Check all Kaprodi assignments
mtrace("=== DAFTAR KAPRODI RESMI DI MOODLE USH ===");
$all_cats = $DB->get_records_sql("SELECT id, name FROM {course_categories} WHERE parent != 0");

foreach ($all_cats as $cat) {
    $ctx = context_coursecat::instance($cat->id);
    $managers = get_role_users($manager_role->id, $ctx, false, 'u.id, u.firstname, u.lastname, u.username');
    if (!empty($managers)) {
        mtrace("📌 Kategori: {$cat->name}");
        foreach ($managers as $m) {
            mtrace("   🎓 Kaprodi: {$m->firstname} {$m->lastname} [Username: {$m->username}]");
        }
    }
}

purge_all_caches();
