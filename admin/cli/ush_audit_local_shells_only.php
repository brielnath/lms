<?php
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../config.php');

function n(string $c): string {
    $c = strtoupper(trim($c));
    $c = str_replace(['*', ' '], '', $c);
    return preg_replace('/_20\d{6}(GANJIL|GENAP)$/i', '', $c) ?? $c;
}

$rows = $DB->get_records_sql(
    "SELECT shortname FROM {course}
      WHERE id > 1 AND " . $DB->sql_like('shortname', ':p') . "
   ORDER BY shortname",
    ['p' => '%20262027Ganjil%']
);
mtrace('LMS shells *_20262027Ganjil: ' . count($rows));
$official = 0;
$i = 0;
foreach ($rows as $r) {
    $b = n($r->shortname);
    if (preg_match('/^(IDM|IUM|IFM|IDE|GDM)\d+/i', $b)) {
        $official++;
    }
    if ($i++ < 10) {
        mtrace("  {$r->shortname} => {$b}");
    }
}
mtrace("Official scheme: $official");
mtrace('Sync script: ' . (is_readable($CFG->dirroot . '/admin/cli/sync_siakad_mahasiswa_accounts_local.php') ? 'OK' : 'NO'));

// Probe login without printing secrets.
$ch = curl_init('https://siakad.sugenghartono.ac.id/api/login');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode([
        'email' => 'akademik@sugenghartono.ac.id',
        'password' => '321',
    ]),
    CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_TIMEOUT => 20,
]);
$raw = curl_exec($ch);
$http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err = curl_error($ch);
curl_close($ch);
$j = json_decode($raw ?: '', true);
$ok = is_array($j) && !empty($j['token'] ?? $j['data']['token'] ?? $j['access_token'] ?? null);
mtrace("SIAKAD login sekarang: HTTP $http " . ($ok ? 'OK' : 'GAGAL') . ($err ? " curl=$err" : ''));
if (!$ok && $raw) {
    mtrace('  body keys: ' . (is_array($j) ? implode(',', array_keys($j)) : 'non-json'));
}
