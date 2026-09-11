<?php
/**
 * Pasang peran Kaprodi (Manager) di folder prodi 2026/2027.
 * Bukan enrol pengajar ke setiap MK. Default DRY-RUN; --confirm untuk menulis.
 *
 *   php admin/cli/ush_assign_kaprodi_production.php
 *   php admin/cli/ush_assign_kaprodi_production.php --confirm
 */
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/accesslib.php');

$CONFIRM = in_array('--confirm', array_slice($argv, 1), true);

$kaprodi = [
    ['username' => 'dosen_64',  'kode' => 'SIF', 'prodi' => 'Sistem Informasi'],
    ['username' => 'dosen_839', 'kode' => 'SBD', 'prodi' => 'Bisnis Digital'],
    ['username' => 'dosen_60',  'kode' => 'SGZ', 'prodi' => 'Ilmu Gizi'],
    ['username' => 'dosen_8412','kode' => 'MBI', 'prodi' => 'Manajemen Bisnis Internasional'],
    ['username' => 'dosen_8420','kode' => 'HKM', 'prodi' => 'Hukum Bisnis'],
    ['username' => 'dosen_8414','kode' => 'TPN', 'prodi' => 'Teknologi Pangan'],
    ['username' => 'dosen_8442','kode' => 'BKI', 'prodi' => 'Bahasa dan Kebudayaan Inggris'],
    ['username' => 'dosen_8438','kode' => 'PAR', 'prodi' => 'Pariwisata'],
    ['username' => 'dosen_70',  'kode' => 'ABD', 'prodi' => 'Akuntansi Bisnis Digital'],
];

$roleid = (int) $DB->get_field('role', 'id', ['shortname' => 'manager'], MUST_EXIST);

mtrace('=== Assign Kaprodi (Manager folder 2026/2027) ===');
mtrace('Site: ' . $CFG->wwwroot);
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
            'idn' => 'CAT_' . $row['kode'] . '_2026_2027_%',
            'nm' => '%(' . $row['kode'] . ')%2026/2027%',
        ]
    );

    mtrace(sprintf('%s %s (%s) → %s',
        $user->firstname, $user->lastname, $user->username, $row['prodi']));

    if (!$cats) {
        mtrace('  folder tidak ketemu (buat semester dulu)');
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
