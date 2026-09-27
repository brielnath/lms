<?php
/**
 * Ubah login dosen production dari dosen_{id} menjadi email SIAKAD.
 * Kata sandi tidak diubah. Default DRY-RUN.
 *
 *   php admin/cli/ush_align_dosen_login_production.php --from-file=admin/cli/dosen_login_siakad.json
 *   php admin/cli/ush_align_dosen_login_production.php --from-file=admin/cli/dosen_login_siakad.json --confirm
 */
define('CLI_SCRIPT', true);
$ushstartcwd = getcwd();
require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/user/lib.php');

$islocal = strpos($CFG->wwwroot, 'localhost') !== false || strpos($CFG->wwwroot, '127.0.0.1') !== false;
$isprod = strpos($CFG->wwwroot, 'lms.ush.ac.id') !== false;
if (!$islocal && !$isprod) {
    mtrace('Dibatalkan: wwwroot bukan LMS lokal atau production. wwwroot=' . $CFG->wwwroot);
    exit(1);
}

$confirm = false;
$from = '';
foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--confirm') {
        $confirm = true;
    } else if (str_starts_with($arg, '--from-file=')) {
        $from = substr($arg, 12);
    }
}
if ($from === '') {
    mtrace('Wajib --from-file=admin/cli/dosen_login_siakad.json');
    exit(1);
}
if (!is_readable($from) && $ushstartcwd && is_readable($ushstartcwd . '/' . $from)) {
    $from = $ushstartcwd . '/' . $from;
}
if (!is_readable($from)) {
    mtrace('File tidak terbaca: ' . $from);
    exit(1);
}
$payload = json_decode((string) file_get_contents($from), true);
$lecturers = $payload['lecturers'] ?? [];
if (!$lecturers) {
    mtrace('Daftar dosen kosong.');
    exit(1);
}

$admin = get_admin();
\core\session\manager::set_user($admin);

mtrace('=== Login dosen production = email SIAKAD ===');
mtrace('Site: ' . $CFG->wwwroot);
mtrace('Mode: ' . ($confirm ? 'LIVE' : 'DRY-RUN'));
mtrace('Dosen di file: ' . count($lecturers));
mtrace('');

$changed = 0;
$already = 0;
$missing = 0;
$skipped = 0;
foreach ($lecturers as $row) {
    $lecid = (int) ($row['id'] ?? 0);
    $email = core_text::strtolower(trim((string) ($row['email'] ?? '')));
    $name = trim((string) ($row['name'] ?? ''));
    if ($lecid <= 0 || !validate_email($email) || clean_param($email, PARAM_USERNAME) !== $email) {
        mtrace('LEWAT id ' . $lecid . ' — email tidak valid');
        $skipped++;
        continue;
    }
    $user = $DB->get_record('user', [
        'username' => 'dosen_' . $lecid,
        'mnethostid' => $CFG->mnet_localhost_id,
        'deleted' => 0,
    ]);
    if (!$user) {
        $user = $DB->get_record('user', [
            'idnumber' => (string) $lecid,
            'mnethostid' => $CFG->mnet_localhost_id,
            'deleted' => 0,
        ]);
    }
    if (!$user) {
        mtrace('TIDAK ADA dosen_' . $lecid . ' — ' . $name);
        $missing++;
        continue;
    }
    if ($user->username === $email && core_text::strtolower($user->email) === $email && (string) $user->idnumber === (string) $lecid) {
        $already++;
        continue;
    }
    $clash = $DB->get_record_select(
        'user',
        "id <> :id AND deleted = 0 AND mnethostid = :h AND (username = :u OR " . $DB->sql_equal('email', ':e', false, true) . ')',
        ['id' => $user->id, 'h' => $CFG->mnet_localhost_id, 'u' => $email, 'e' => $email]
    );
    if ($clash) {
        mtrace('LEWAT dosen_' . $lecid . ' — email dipakai ' . $clash->username);
        $skipped++;
        continue;
    }
    mtrace($user->username . ' → ' . $email . ' | ' . $name);
    if ($confirm) {
        $user->username = $email;
        $user->email = $email;
        $user->idnumber = (string) $lecid;
        user_update_user($user, false, true);
    }
    $changed++;
}

mtrace('');
mtrace('Diubah     : ' . $changed);
mtrace('Sudah sama : ' . $already);
mtrace('Tidak ada  : ' . $missing);
mtrace('Dilewati   : ' . $skipped);
if (!$confirm) {
    mtrace('DRY-RUN. Tambahkan --confirm untuk menulis. Kata sandi tidak diubah.');
}
