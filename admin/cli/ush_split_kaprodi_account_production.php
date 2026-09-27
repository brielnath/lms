<?php
/**
 * Buat akun kaprodi di production dan pindahkan peran Manager dari akun dosen.
 * Akun dosen tetap mengampu kelas. Kata sandi akun baru: KaprodiUSH2026!
 *
 *   php admin/cli/ush_split_kaprodi_account_production.php
 *   php admin/cli/ush_split_kaprodi_account_production.php --confirm
 */
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->libdir . '/accesslib.php');

$islocal = strpos($CFG->wwwroot, 'localhost') !== false || strpos($CFG->wwwroot, '127.0.0.1') !== false;
$isprod = strpos($CFG->wwwroot, 'lms.ush.ac.id') !== false;
if (!$islocal && !$isprod) {
    mtrace('Dibatalkan: wwwroot bukan LMS lokal atau production. wwwroot=' . $CFG->wwwroot);
    exit(1);
}

$confirm = in_array('--confirm', array_slice($argv, 1), true);
$admin = get_admin();
\core\session\manager::set_user($admin);

$people = [
    ['idnumber' => '64', 'kode' => 'SIF', 'prodi' => 'Sistem Informasi'],
    ['idnumber' => '839', 'kode' => 'SBD', 'prodi' => 'Bisnis Digital'],
    ['idnumber' => '60', 'kode' => 'SGZ', 'prodi' => 'Ilmu Gizi'],
    ['idnumber' => '8412', 'kode' => 'MBI', 'prodi' => 'Manajemen Bisnis Internasional'],
    ['idnumber' => '8420', 'kode' => 'HKM', 'prodi' => 'Hukum Bisnis'],
    ['idnumber' => '8414', 'kode' => 'TPN', 'prodi' => 'Teknologi Pangan'],
    ['idnumber' => '8442', 'kode' => 'BKI', 'prodi' => 'Bahasa dan Kebudayaan Inggris'],
    ['idnumber' => '8438', 'kode' => 'PAR', 'prodi' => 'Pariwisata'],
    ['idnumber' => '70', 'kode' => 'ABD', 'prodi' => 'Akuntansi Bisnis Digital'],
];

$manager = (int) $DB->get_field('role', 'id', ['shortname' => 'manager'], MUST_EXIST);
$password = 'KaprodiUSH2026!';

mtrace('=== Akun kaprodi production ===');
mtrace('Site: ' . $CFG->wwwroot);
mtrace('Mode: ' . ($confirm ? 'LIVE' : 'DRY-RUN'));
mtrace('');

foreach ($people as $row) {
    $dosen = $DB->get_record('user', [
        'idnumber' => $row['idnumber'],
        'deleted' => 0,
        'mnethostid' => $CFG->mnet_localhost_id,
    ]);
    if (!$dosen) {
        $dosen = $DB->get_record('user', [
            'username' => 'dosen_' . $row['idnumber'],
            'deleted' => 0,
            'mnethostid' => $CFG->mnet_localhost_id,
        ]);
    }
    if (!$dosen) {
        mtrace('LEWAT ' . $row['prodi'] . ' — akun dosen id ' . $row['idnumber'] . ' tidak ada');
        continue;
    }

    $login = 'kaprodi.' . strtolower($row['kode']) . '@sugenghartono.ac.id';
    $kaprodi = $DB->get_record('user', [
        'username' => $login,
        'deleted' => 0,
        'mnethostid' => $CFG->mnet_localhost_id,
    ]);

    mtrace(trim($dosen->firstname . ' ' . $dosen->lastname) . ' — ' . $row['prodi']);
    mtrace('  dosen   : ' . $dosen->username);
    mtrace('  kaprodi : ' . $login . ($kaprodi ? ' (sudah ada)' : ' (baru, sandi KaprodiUSH2026!)'));

    if ($confirm && !$kaprodi) {
        $newid = user_create_user((object) [
            'username' => $login,
            'auth' => 'manual',
            'password' => $password,
            'firstname' => $dosen->firstname,
            'lastname' => trim($dosen->lastname . ' (Kaprodi)'),
            'email' => $login,
            'idnumber' => 'KAPRODI_' . $row['kode'],
            'confirmed' => 1,
            'mnethostid' => $CFG->mnet_localhost_id,
            'lang' => 'id',
            'calendartype' => $CFG->calendartype ?? 'gregorian',
            'mailformat' => 1,
            'maildisplay' => 0,
        ], true, false);
        $kaprodi = $DB->get_record('user', ['id' => $newid], '*', MUST_EXIST);
    }

    $roles = $DB->get_records_sql(
        "SELECT ra.id, ctx.id AS contextid, cc.name
           FROM {role_assignments} ra
           JOIN {context} ctx ON ctx.id = ra.contextid AND ctx.contextlevel = :lvl
           JOIN {course_categories} cc ON cc.id = ctx.instanceid
          WHERE ra.userid = :uid AND ra.roleid = :rid",
        ['lvl' => CONTEXT_COURSECAT, 'uid' => $dosen->id, 'rid' => $manager]
    );
    if (!$roles) {
        mtrace('  peran kaprodi di akun dosen: tidak ada');
    }
    foreach ($roles as $role) {
        mtrace('  PINDAH ' . $role->name);
        if ($confirm && $kaprodi) {
            role_assign($manager, $kaprodi->id, $role->contextid);
            role_unassign($manager, $dosen->id, $role->contextid);
        }
    }
    mtrace('');
}

if ($confirm) {
    purge_all_caches();
}
if (!$confirm) {
    mtrace('DRY-RUN. Ulangi dengan --confirm untuk membuat akun. Masuk lewat tab Dosen / Admin.');
}
