<?php
/**
 * Akun pimpinan lokal. Tidak dijalankan di production.
 *
 *   php local/ush_pimpinan/cli/create_accounts_local.php --confirm
 */
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->dirroot . '/local/ush_pimpinan/lib.php');

if (strpos($CFG->wwwroot, 'localhost') === false && strpos($CFG->wwwroot, '127.0.0.1') === false) {
    mtrace('Dibatalkan: bukan LMS lokal.');
    exit(1);
}

$confirm = in_array('--confirm', array_slice($argv, 1), true);
$accounts = [
    [
        'username' => 'universitas@sugenghartono.ac.id',
        'firstname' => 'Pimpinan',
        'lastname' => 'Universitas',
        'password' => 'UnivUSH2026!',
        'idnumber' => 'UNIVERSITAS',
        'role' => 'ushpimpinanuniv',
    ],
    [
        'username' => 'fakultas.fthb@sugenghartono.ac.id',
        'firstname' => 'Dekanat',
        'lastname' => 'FTHB',
        'password' => 'FakultasUSH2026!',
        'idnumber' => 'FAK_FTHB',
        'role' => 'ushpimpinanfak',
    ],
    [
        'username' => 'fakultas.fith@sugenghartono.ac.id',
        'firstname' => 'Dekanat',
        'lastname' => 'FITH',
        'password' => 'FakultasUSH2026!',
        'idnumber' => 'FAK_FITH',
        'role' => 'ushpimpinanfak',
    ],
];

mtrace('=== Akun pimpinan universitas dan fakultas ===');
foreach ($accounts as $account) {
    mtrace($account['username'] . ' / ' . $account['password'] . ' / ' . $account['idnumber']);
}
if (!$confirm) {
    mtrace('DRY-RUN. Ulangi dengan --confirm untuk menulis.');
    exit(0);
}

local_ush_pimpinan_ensure_roles();
$system = context_system::instance();

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
        mtrace('Dibuat ' . $account['username']);
    } else {
        $user->idnumber = $account['idnumber'];
        $user->firstname = $account['firstname'];
        $user->lastname = $account['lastname'];
        $user->institution = 'Universitas Sugeng Hartono';
        user_update_user($user, false, false);
        mtrace('Sudah ada ' . $account['username']);
    }

    $roleid = (int) $DB->get_field('role', 'id', ['shortname' => $account['role']], MUST_EXIST);
    role_assign($roleid, $user->id, $system->id);
}
mtrace('Selesai. Buka /local/ush_pimpinan/index.php lewat menu Dasbor Pimpinan.');
