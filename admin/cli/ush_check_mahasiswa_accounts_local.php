<?php
/**
 * Ringkas: status akun mahasiswa di LMS lokal (tanpa dump data pribadi).
 */
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../config.php');

if (strpos($CFG->wwwroot, 'localhost') === false && strpos($CFG->wwwroot, '127.0.0.1') === false) {
    mtrace('Dibatalkan: bukan LMS lokal.');
    exit(1);
}

$mnethostid = (int) $CFG->mnet_localhost_id;
$studentrole = $DB->get_field('role', 'id', ['shortname' => 'student']);

// User aktif (bukan guest/admin tipikal): auth manual, confirmed.
$totalusers = $DB->count_records_sql(
    "SELECT COUNT(*) FROM {user}
      WHERE deleted = 0 AND suspended = 0 AND mnethostid = ?
        AND id > 2 AND username <> 'guest'",
    [$mnethostid]
);

// Pola akun mahasiswa USH: username digit (NIM), email @sugenghartono atau sejenis.
$mhsLike = $DB->count_records_sql(
    "SELECT COUNT(*) FROM {user}
      WHERE deleted = 0 AND mnethostid = ?
        AND username REGEXP '^[0-9]{8,}$'",
    [$mnethostid]
);

// Akun dibuat 7 / 30 hari terakhir (pola NIM).
$since7 = time() - (7 * DAYSECS);
$since30 = time() - (30 * DAYSECS);
$new7 = $DB->count_records_sql(
    "SELECT COUNT(*) FROM {user}
      WHERE deleted = 0 AND mnethostid = ?
        AND username REGEXP '^[0-9]{8,}$'
        AND timecreated >= ?",
    [$mnethostid, $since7]
);
$new30 = $DB->count_records_sql(
    "SELECT COUNT(*) FROM {user}
      WHERE deleted = 0 AND mnethostid = ?
        AND username REGEXP '^[0-9]{8,}$'
        AND timecreated >= ?",
    [$mnethostid, $since30]
);

// Enrol student di kelas 2026/2027.
$enrol2026 = 0;
$courses2026 = 0;
if ($studentrole) {
    $courses2026 = $DB->count_records_sql(
        "SELECT COUNT(*) FROM {course} WHERE id > 1 AND " . $DB->sql_like('shortname', ':p'),
        ['p' => '%20262027%']
    );
    $enrol2026 = $DB->count_records_sql(
        "SELECT COUNT(DISTINCT ue.userid)
           FROM {user_enrolments} ue
           JOIN {enrol} e ON e.id = ue.enrolid
           JOIN {course} c ON c.id = e.courseid
           JOIN {user} u ON u.id = ue.userid AND u.deleted = 0
           JOIN {context} ctx ON ctx.instanceid = c.id AND ctx.contextlevel = 50
           JOIN {role_assignments} ra ON ra.contextid = ctx.id AND ra.userid = u.id AND ra.roleid = :roleid
          WHERE " . $DB->sql_like('c.shortname', ':p'),
        ['roleid' => $studentrole, 'p' => '%20262027%']
    );
}

mtrace('=== Status mahasiswa di LMS lokal ===');
mtrace('User aktif total (approx): ' . $totalusers);
mtrace('Akun pola NIM (username angka): ' . $mhsLike);
mtrace('Akun NIM baru 7 hari: ' . $new7);
mtrace('Akun NIM baru 30 hari: ' . $new30);
mtrace('Kelas *20262027*: ' . $courses2026);
mtrace('Mahasiswa unik ter-enrol di *20262027*: ' . $enrol2026);
mtrace('');
mtrace('Skrip sync semi-otomatis: ' . (
    is_readable($CFG->dirroot . '/admin/cli/sync_siakad_mahasiswa_accounts_local.php') ? 'ADA' : 'TIDAK'
));
mtrace('Catatan: sync live penuh belum tentu pernah dijalankan; dry-run dulu hanya sample halaman.');
