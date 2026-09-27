<?php
/**
 * Pasang peran Kaprodi (Manager) di folder prodi LMS.
 * Bukan enrol pengajar ke setiap MK.
 *
 * Daftar dari akademik (Sep 2026). Lokal saja. Default DRY-RUN; --confirm untuk menulis.
 */
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/accesslib.php');

if (strpos($CFG->wwwroot, 'localhost') === false && strpos($CFG->wwwroot, '127.0.0.1') === false) {
    mtrace('Dibatalkan: bukan LMS lokal.');
    exit(1);
}

$CONFIRM = in_array('--confirm', array_slice($argv, 1), true);

$kaprodi = [
    ['username' => 'kaprodi.sif@sugenghartono.ac.id', 'kode' => 'SIF', 'prodi' => 'Sistem Informasi'],
    ['username' => 'kaprodi.sbd@sugenghartono.ac.id', 'kode' => 'SBD', 'prodi' => 'Bisnis Digital'],
    ['username' => 'kaprodi.sgz@sugenghartono.ac.id', 'kode' => 'SGZ', 'prodi' => 'Ilmu Gizi'],
    ['username' => 'kaprodi.mbi@sugenghartono.ac.id', 'kode' => 'MBI', 'prodi' => 'Manajemen Bisnis Internasional'],
    ['username' => 'kaprodi.hkm@sugenghartono.ac.id', 'kode' => 'HKM', 'prodi' => 'Hukum Bisnis'],
    ['username' => 'kaprodi.tpn@sugenghartono.ac.id', 'kode' => 'TPN', 'prodi' => 'Teknologi Pangan'],
    ['username' => 'kaprodi.bki@sugenghartono.ac.id', 'kode' => 'BKI', 'prodi' => 'Bahasa dan Kebudayaan Inggris'],
    ['username' => 'kaprodi.par@sugenghartono.ac.id', 'kode' => 'PAR', 'prodi' => 'Pariwisata'],
    ['username' => 'kaprodi.abd@sugenghartono.ac.id', 'kode' => 'ABD', 'prodi' => 'Akuntansi Bisnis Digital'],
];

$roleid = (int) $DB->get_field('role', 'id', ['shortname' => 'manager'], MUST_EXIST);

mtrace('=== Assign Kaprodi (Manager folder prodi) ===');
mtrace('Mode: ' . ($CONFIRM ? 'LIVE' : 'DRY-RUN'));
mtrace('');

$baru = 0;
$sudah = 0;

foreach ($kaprodi as $row) {
    $user = $DB->get_record('user', ['username' => $row['username'], 'deleted' => 0]);
    if (!$user) {
        mtrace("AKUN TIDAK ADA: {$row['username']} ({$row['prodi']})");
        continue;
    }

    $idn = $DB->sql_like('cc.idnumber', ':idn');
    $nm = $DB->sql_like('cc.name', ':nm');
    $cats = $DB->get_records_sql(
        "SELECT cc.id, cc.name, cc.idnumber
           FROM {course_categories} cc
          WHERE $idn OR $nm
          ORDER BY cc.name",
        [
            'idn' => 'CAT_' . $row['kode'] . '_%',
            'nm' => '%(' . $row['kode'] . ')%',
        ]
    );

    mtrace(sprintf('%s %s (%s) → %s',
        $user->firstname, $user->lastname, $user->username, $row['prodi']));

    if (!$cats) {
        mtrace('  folder tidak ketemu');
        continue;
    }

    foreach ($cats as $cat) {
        $ctx = context_coursecat::instance($cat->id);
        if (user_has_role_assignment($user->id, $roleid, $ctx->id)) {
            mtrace('  sudah: ' . $cat->name);
            $sudah++;
            continue;
        }
        mtrace('  PASANG: ' . $cat->name);
        if ($CONFIRM) {
            role_assign($roleid, $user->id, $ctx->id);
        }
        $baru++;
    }
}

mtrace('');
mtrace("Sudah ada: $sudah");
mtrace($CONFIRM ? "Baru dipasang: $baru" : "Akan dipasang: $baru");
if (!$CONFIRM) {
    mtrace('DRY-RUN. Ulangi dengan --confirm untuk menulis.');
} else {
    purge_all_caches();
}
