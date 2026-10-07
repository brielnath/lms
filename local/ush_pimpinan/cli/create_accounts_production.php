<?php
/**
 * Buat dan pasang akun pimpinan universitas dan dekanat fakultas di server Production.
 *
 * Aman dijalankan di production:
 * - Default DRY-RUN (hanya mengecek tanpa menulis).
 * - Tulis ke database hanya jika argumen --confirm disertakan.
 *
 * Cara eksekusi di server production (SSH):
 *   php local/ush_pimpinan/cli/create_accounts_production.php
 *   php local/ush_pimpinan/cli/create_accounts_production.php --confirm
 */
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->dirroot . '/local/ush_pimpinan/lib.php');

$confirm = in_array('--confirm', array_slice($argv, 1), true);

$accounts = [
    [
        'username' => 'universitas@sugenghartono.ac.id',
        'firstname' => 'Pimpinan',
        'lastname' => 'Universitas',
        'password' => 'UnivUSH2026!',
        'idnumber' => 'UNIVERSITAS',
        'role' => 'ushpimpinanuniv',
        'desc' => 'Rektorat / Pimpinan Universitas (Monitoring Seluruh Fakultas & Prodi)',
    ],
    [
        'username' => 'fakultas.fthb@sugenghartono.ac.id',
        'firstname' => 'Dekanat',
        'lastname' => 'FTHB',
        'password' => 'FakultasUSH2026!',
        'idnumber' => 'FAK_FTHB',
        'role' => 'ushpimpinanfak',
        'desc' => 'Fakultas Teknologi, Hukum dan Bisnis (SIF, SBD, MBI, HKM, S2SBD)',
    ],
    [
        'username' => 'fakultas.fith@sugenghartono.ac.id',
        'firstname' => 'Dekanat',
        'lastname' => 'FITH',
        'password' => 'FakultasUSH2026!',
        'idnumber' => 'FAK_FITH',
        'role' => 'ushpimpinanfak',
        'desc' => 'Fakultas Ilmu Terapan dan Humaniora (SGZ, TPN, BKI, PAR, ABD)',
    ],
];

mtrace('===========================================================');
mtrace('🏛️  PEMBUATAN AKUN PIMPINAN UNIVERSITAS & DEKANAT FAKULTAS');
mtrace('===========================================================');
mtrace('Site URL : ' . $CFG->wwwroot);
mtrace('Mode     : ' . ($confirm ? 'LIVE (--confirm aktif)' : 'DRY-RUN (simulasi, belum menulis ke DB)'));
mtrace('');

if (!$confirm) {
    mtrace('Daftar akun yang akan disiapkan:');
    foreach ($accounts as $a) {
        mtrace(" • {$a['username']} [{$a['idnumber']}] ({$a['desc']})");
        mtrace("   Password: {$a['password']} | Role: {$a['role']}");
    }
    mtrace('');
    mtrace('Untuk membuat/mengaktifkan akun di database production, jalankan:');
    mtrace('  php local/ush_pimpinan/cli/create_accounts_production.php --confirm');
    exit(0);
}

// 1. Pastikan role pimpinan terdaftar di sistem
local_ush_pimpinan_ensure_roles();
$system = context_system::instance();

// 2. Loop akun dan buat/perbarui di database
foreach ($accounts as $account) {
    $user = $DB->get_record('user', [
        'username' => $account['username'],
        'mnethostid' => $CFG->mnet_localhost_id,
        'deleted' => 0,
    ]);

    if (!$user) {
        $id = user_create_user((object) [
            'username' => $account['username'],
            'auth' => 'manual',
            'password' => $account['password'],
            'firstname' => $account['firstname'],
            'lastname' => $account['lastname'],
            'email' => $account['username'],
            'idnumber' => $account['idnumber'],
            'institution' => 'Universitas Sugeng Hartono',
            'confirmed' => 1,
            'mnethostid' => $CFG->mnet_localhost_id,
            'lang' => 'id',
            'calendartype' => $CFG->calendartype ?? 'gregorian',
            'mailformat' => 1,
            'maildisplay' => 0,
        ], true, false);

        $user = $DB->get_record('user', ['id' => $id], '*', MUST_EXIST);
        mtrace("✅ Berhasil DIBUAT: {$account['username']} [ID: {$user->id}]");
    } else {
        $user->idnumber = $account['idnumber'];
        $user->firstname = $account['firstname'];
        $user->lastname = $account['lastname'];
        $user->institution = 'Universitas Sugeng Hartono';
        user_update_user($user, false, false);
        mtrace("ℹ️  Sudah ada (diperbarui idnumber): {$account['username']} [ID: {$user->id}]");
    }

    // Pasang role sistem
    $roleid = (int) $DB->get_field('role', 'id', ['shortname' => $account['role']], MUST_EXIST);
    if (!$DB->record_exists('role_assignments', ['roleid' => $roleid, 'userid' => $user->id, 'contextid' => $system->id])) {
        role_assign($roleid, $user->id, $system->id);
        mtrace("   👉 Role '{$account['role']}' berhasil dipasang ke {$account['username']}");
    } else {
        mtrace("   👉 Role '{$account['role']}' sudah terpasang.");
    }
}

mtrace('');
mtrace('🎉 Selesai! Akun Pimpinan & Dekanat sudah aktif di production.');
mtrace('Silakan login di https://lms.ush.ac.id lalu buka menu "Dasbor Pimpinan" (/local/ush_pimpinan/index.php).');
