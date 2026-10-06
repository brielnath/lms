<?php
/**
 * Cek kesiapan LMS untuk Google Assignments LTI / Gemini LTI (hanya membaca, tidak mengubah apa pun).
 *
 *   php admin/cli/ush_cek_google_lti.php
 */
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../config.php');

$ok = true;
$check = function (string $label, bool $pass, string $hint = '') use (&$ok): void {
    mtrace(($pass ? '[OK]    ' : '[GAGAL] ') . $label . (!$pass && $hint !== '' ? "\n        -> $hint" : ''));
    $ok = $ok && $pass;
};

mtrace('=== Kesiapan Google Workspace LTI ===');
mtrace('wwwroot: ' . $CFG->wwwroot);

$check('Alamat LMS memakai HTTPS', str_starts_with($CFG->wwwroot, 'https://'),
    'Google hanya menerima LMS beralamat https:// (ubah $CFG->wwwroot di config.php setelah SSL aktif).');
$host = parse_url($CFG->wwwroot, PHP_URL_HOST) ?: '';
$check('Alamat LMS bisa diakses publik (bukan localhost/IP lokal)',
    $host !== '' && !preg_match('/^(localhost|127\.|10\.|192\.168\.|172\.(1[6-9]|2\d|3[01])\.)/', $host),
    'Daftarkan alat dari server produksi, bukan dari localhost.');

$check('Modul External tool (LTI) aktif', (bool) $DB->get_field('modules', 'visible', ['name' => 'lti']),
    'Site administration > Plugins > Activity modules > Manage activities: aktifkan External tool.');

$check('Ekstensi PHP openssl tersedia', extension_loaded('openssl'), 'LTI 1.3 butuh openssl untuk tanda tangan JWT.');
$check('Ekstensi PHP curl tersedia', extension_loaded('curl'));

$privatekey = get_config('mod_lti', 'privatekey');
$check('Kunci LTI 1.3 situs sudah dibuat', !empty($privatekey) || !empty(get_config('mod_lti', 'kid')),
    'Biasanya dibuat otomatis; buka sekali Site administration > Plugins > External tool > Manage tools.');

$roleid = (int) $DB->get_field('role', 'id', ['shortname' => 'editingteacher']);
$system = context_system::instance();
foreach (['mod/lti:addinstance', 'mod/lti:addpreconfiguredinstance', 'mod/lti:view'] as $cap) {
    $perm = (int) $DB->get_field('role_capabilities', 'permission', [
        'roleid' => $roleid, 'capability' => $cap, 'contextid' => $system->id,
    ]);
    $check("Dosen (editingteacher) punya $cap", $perm === CAP_ALLOW,
        $cap === 'mod/lti:addinstance'
            ? 'Jalankan: php admin/cli/ush_simplify_activity_chooser.php --confirm'
            : 'Periksa izin peran editingteacher.');
}

foreach ([
    'Google Assignments' => 'https://assignments.google.com/lti/register',
    'Gemini' => 'https://lti.gemini.google.com/lti/register',
] as $name => $url) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_NOBODY => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15]);
    curl_exec($ch);
    $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    $check("Server bisa menghubungi $name ($url)", $http > 0,
        'Koneksi keluar ke Google diblokir? ' . $err);
}

$tools = $DB->get_records_select('lti_types', $DB->sql_like('baseurl', ':g', false), ['g' => '%google.com%'], '', 'id, name, state, coursevisible');
mtrace('');
mtrace('Alat Google yang sudah didaftarkan: ' . count($tools));
foreach ($tools as $t) {
    $state = [1 => 'aktif', 2 => 'menunggu', 3 => 'ditolak'][(int) $t->state] ?? $t->state;
    $usage = [0 => 'tersembunyi', 1 => 'hanya preconfigured', 2 => 'tampil di menu aktivitas'][(int) $t->coursevisible] ?? $t->coursevisible;
    mtrace("  - {$t->name}: $state, $usage");
}

mtrace('');
mtrace($ok ? 'Siap. Lanjutkan pendaftaran alat di Manage tools.' : 'Perbaiki item [GAGAL] di atas sebelum mendaftarkan alat Google.');
exit($ok ? 0 : 1);
