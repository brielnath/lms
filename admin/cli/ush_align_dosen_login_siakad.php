<?php
/**
 * Ubah login dosen LMS dari dosen_{id} menjadi email yang dipakai di SIAKAD.
 * Kata sandi LMS tidak diubah.
 *
 *   php admin/cli/ush_align_dosen_login_siakad.php
 *   php admin/cli/ush_align_dosen_login_siakad.php --confirm
 */
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once(__DIR__ . '/ush_dosen_account.php');

if (strpos($CFG->wwwroot, 'localhost') === false && strpos($CFG->wwwroot, '127.0.0.1') === false) {
    mtrace('Dibatalkan: skrip ini membaca SIAKAD lokal. wwwroot=' . $CFG->wwwroot);
    exit(1);
}

$confirm = in_array('--confirm', array_slice($argv, 1), true);
$admin = get_admin();
\core\session\manager::set_user($admin);

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$siakad = new mysqli('127.0.0.1', 'root', '', 'acc_tes_backup');
$siakad->set_charset('utf8mb4');
$res = $siakad->query("
    SELECT l.id AS lecture_id, u.email
      FROM lecture l
      JOIN users u ON u.id = l.id_user
     WHERE l.is_deleted = 'N' OR l.is_deleted IS NULL OR l.is_deleted = ''
");
$bylec = [];
while ($row = $res->fetch_assoc()) {
    $email = core_text::strtolower(trim((string) $row['email']));
    if ($email !== '') {
        $bylec[(int) $row['lecture_id']] = $email;
    }
}
$siakad->close();

$users = $DB->get_records_select(
    'user',
    "deleted = 0 AND mnethostid = :h AND " . $DB->sql_like('username', ':p'),
    ['h' => $CFG->mnet_localhost_id, 'p' => 'dosen\_%']
);

mtrace('=== Login dosen = email SIAKAD ===');
mtrace('Mode: ' . ($confirm ? 'LIVE' : 'DRY-RUN'));
mtrace('Akun LMS dosen_ : ' . count($users));
mtrace('');

$changed = 0;
$already = 0;
$skipped = 0;
foreach ($users as $user) {
    if (!preg_match('/^dosen_(\d+)$/', $user->username, $m)) {
        mtrace('LEWAT ' . $user->username . ' — bukan dosen_{nomor}');
        $skipped++;
        continue;
    }
    $lecid = (int) $m[1];
    $email = $bylec[$lecid] ?? '';
    if ($email === '' || !validate_email($email)) {
        mtrace('LEWAT ' . $user->username . ' — email SIAKAD tidak ada');
        $skipped++;
        continue;
    }
    $username = clean_param($email, PARAM_USERNAME);
    if ($username !== $email) {
        mtrace('LEWAT ' . $user->username . ' — email tidak bisa jadi username: ' . $email);
        $skipped++;
        continue;
    }
    if ($user->username === $username && core_text::strtolower($user->email) === $email && (string) $user->idnumber === (string) $lecid) {
        $already++;
        continue;
    }

    $clash = $DB->get_record_select(
        'user',
        "id <> :id AND deleted = 0 AND mnethostid = :h
         AND (username = :u OR " . $DB->sql_equal('email', ':e', false, true) . " OR idnumber = :n)",
        [
            'id' => $user->id,
            'h' => $CFG->mnet_localhost_id,
            'u' => $username,
            'e' => $email,
            'n' => (string) $lecid,
        ]
    );
    if ($clash) {
        mtrace('LEWAT ' . $user->username . ' — bentrok dengan ' . $clash->username . ' untuk ' . $email);
        $skipped++;
        continue;
    }

    $name = trim($user->firstname . ' ' . $user->lastname);
    mtrace($user->username . ' → ' . $email . ' | ' . $name);
    if ($confirm) {
        $user->username = $username;
        $user->email = $email;
        $user->idnumber = (string) $lecid;
        user_update_user($user, false, true);
    }
    $changed++;
}

if ($confirm && $changed > 0) {
    set_config('authloginviaemail', 1);
}

mtrace('');
mtrace('Diubah : ' . $changed);
mtrace('Sudah sama : ' . $already);
mtrace('Dilewati : ' . $skipped);
if (!$confirm) {
    mtrace('DRY-RUN. Ulangi dengan --confirm untuk menulis.');
}
