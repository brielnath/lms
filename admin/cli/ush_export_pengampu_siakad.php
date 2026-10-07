<?php
/**
 * Unduh pemetaan "kode MK -> dosen pengampu" dari SIAKAD ke satu file JSON.
 *
 * Sumbernya endpoint grades-per-course, karena di situlah id_lecture dan
 * lesson_code muncul berpasangan. Dipakai supaya server production tidak perlu
 * menyimpan kredensial SIAKAD: jalankan di lokal, unggah hasilnya, lalu
 * ush_enrol_dosen_production.php --from-file=...
 *
 * Kredensial dari environment SIAKAD_EMAIL / SIAKAD_PASSWORD,
 * atau argumen --email= / --password=.
 *
 * Contoh:
 *   php admin/cli/ush_export_pengampu_siakad.php --out=pengampu_20262027Ganjil.json
 *
 * Opsi:
 *   --out=FILE        Nama file hasil (default pengampu_siakad.json)
 *   --max-pages=N     Batas halaman (default 80)
 */
define('CLI_SCRIPT', true);

// Moodle memindah working directory, jadi catat dulu supaya --out relatif jatuh di tempat yang diharapkan.
$ushstartcwd = getcwd();

require(__DIR__ . '/../../config.php');

$OUT = 'pengampu_siakad.json';
$MAXPAGES = 80;
$EMAIL = getenv('SIAKAD_EMAIL') ?: '';
$PASSWORD = getenv('SIAKAD_PASSWORD') ?: '';

foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--out=')) {
        $OUT = substr($arg, 6);
    } else if (str_starts_with($arg, '--max-pages=')) {
        $MAXPAGES = max(1, (int) substr($arg, 12));
    } else if (str_starts_with($arg, '--email=')) {
        $EMAIL = substr($arg, 8);
    } else if (str_starts_with($arg, '--password=')) {
        $PASSWORD = substr($arg, 11);
    } else {
        mtrace('Argumen tidak dikenal: ' . $arg);
        exit(1);
    }
}

if ($ushstartcwd && !preg_match('#^([a-zA-Z]:[\\\\/]|[\\\\/])#', $OUT)) {
    $OUT = $ushstartcwd . DIRECTORY_SEPARATOR . $OUT;
}

if ($EMAIL === '' || $PASSWORD === '') {
    mtrace('Kredensial SIAKAD kosong.');
    mtrace('Set environment SIAKAD_EMAIL dan SIAKAD_PASSWORD, atau pakai --email= --password=');
    exit(1);
}

function ush_pengampu_login(string $email, string $password): ?string {
    $ch = curl_init('https://siakad.sugenghartono.ac.id/api/login');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode(['email' => $email, 'password' => $password]),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 30,
    ]);
    $raw = curl_exec($ch);
    curl_close($ch);
    $json = json_decode($raw, true);
    return $json['token']
        ?? $json['access_token']
        ?? $json['data']['token']
        ?? $json['data']['access_token']
        ?? null;
}

function ush_pengampu_get(string $url, string $token): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token, 'Accept: application/json'],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 90,
    ]);
    $raw = curl_exec($ch);
    $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$http, json_decode($raw, true)];
}

function ush_pengampu_norm_code(string $code): string {
    $c = strtoupper(trim($code));
    $c = str_replace(['*', ' '], '', $c);
    return preg_replace('/_20\d{6}(GANJIL|GENAP)$/i', '', $c) ?? $c;
}

mtrace('=== Export pemetaan MK -> dosen pengampu ===');

$token = ush_pengampu_login($EMAIL, $PASSWORD);
if (!$token) {
    mtrace('Login SIAKAD gagal. Cek kredensial atau koneksi.');
    exit(1);
}
mtrace('Login SIAKAD: OK');

$lecturers = [];
$mappings = [];
$page = 1;
$complete = false;
$retry = 0;
// API mengulang halaman terakhir dan tidak mengirim last_page, jadi berhenti
// setelah beberapa halaman tanpa pasangan MK-dosen baru.
$nonew = 0;
$NONEW_LIMIT = 3;

while ($page <= $MAXPAGES) {
    [$http, $res] = ush_pengampu_get(
        'https://siakad.sugenghartono.ac.id/api/grades-per-course?per_page=100&page=' . $page,
        $token
    );

    if ($http === 429) {
        if (++$retry > 5) {
            mtrace("  Page $page: kena limit terus, berhenti.");
            break;
        }
        mtrace("  Page $page: limit 429, tunggu 10 detik (percobaan $retry)");
        sleep(10);
        continue;
    }
    if ($http !== 200) {
        mtrace("  Page $page: HTTP $http, berhenti.");
        break;
    }

    $retry = 0;
    $items = $res['data'] ?? [];
    $before = 0;
    foreach ($mappings as $lecs) {
        $before += count($lecs);
    }

    foreach ($items as $mhs) {
        foreach ($mhs['grade'] ?? [] as $g) {
            $lecid = (int) ($g['id_lecture'] ?? 0);
            $lecname = trim($g['lecture_name'] ?? '');
            $code = ush_pengampu_norm_code($g['lesson_code'] ?? '');
            if ($lecid <= 0 || $code === '' || $lecname === '' || strcasecmp($lecname, 'Unknown') === 0) {
                continue;
            }
            $lecturers[$lecid] = $lecname;
            $mappings[$code][$lecid] = $lecname;
        }
    }

    $after = 0;
    foreach ($mappings as $lecs) {
        $after += count($lecs);
    }
    $baru = $after - $before;
    mtrace("  Page $page: " . count($items) . " mhs, +$baru pasangan baru, MK " . count($mappings) . ', dosen ' . count($lecturers));

    if (!$items) {
        $complete = true;
        break;
    }
    if ($baru > 0) {
        $nonew = 0;
    } else if (++$nonew >= $NONEW_LIMIT) {
        $complete = true;
        mtrace("  $NONEW_LIMIT halaman berturut-turut tanpa pasangan baru — data dianggap habis.");
        break;
    }
    usleep(400000);
    $page++;
}

if (!$complete) {
    mtrace('');
    mtrace('PERINGATAN: data TIDAK terunduh penuh. File ditandai belum lengkap dan akan ditolak di production.');
}

$rows = [];
ksort($mappings);
foreach ($mappings as $code => $lecs) {
    $list = [];
    foreach ($lecs as $id => $name) {
        $list[] = ['id' => (int) $id, 'name' => $name];
    }
    $rows[] = ['code' => $code, 'lecturers' => $list];
}

$payload = [
    'generated' => date('c'),
    'source' => 'siakad.sugenghartono.ac.id/api/grades-per-course',
    'pages_fetched' => $page,
    'complete' => $complete,
    'count_codes' => count($rows),
    'count_lecturers' => count($lecturers),
    'mappings' => $rows,
];

if (file_put_contents($OUT, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) === false) {
    mtrace('Gagal menulis file: ' . $OUT);
    exit(1);
}

mtrace('');
mtrace('Tersimpan   : ' . $OUT);
mtrace('Kode MK     : ' . count($rows));
mtrace('Dosen unik  : ' . count($lecturers));
mtrace('Lengkap     : ' . ($complete ? 'ya' : 'TIDAK'));
exit($complete ? 0 : 1);
