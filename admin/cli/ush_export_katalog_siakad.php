<?php
/**
 * Unduh katalog mata kuliah SIAKAD ke satu file JSON.
 *
 * Dipakai supaya server production tidak perlu menyimpan kredensial SIAKAD:
 * jalankan skrip ini di komputer lokal, lalu unggah file hasilnya ke server dan
 * pakai ush_prepare_semester_production.php --from-file=...
 *
 * Kredensial dibaca dari environment SIAKAD_EMAIL / SIAKAD_PASSWORD,
 * atau argumen --email= / --password=.
 *
 * Contoh:
 *   php admin/cli/ush_export_katalog_siakad.php --out=katalog_siakad.json
 *
 * Opsi:
 *   --out=FILE        Nama file hasil (default katalog_siakad.json di folder ini)
 *   --max-pages=N     Batas halaman yang ditarik (default 80)
 */
define('CLI_SCRIPT', true);

// Moodle memindah working directory, jadi catat dulu supaya --out relatif jatuh di tempat yang diharapkan.
$ushstartcwd = getcwd();

require(__DIR__ . '/../../config.php');

$OUT = __DIR__ . '/katalog_siakad.json';
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

// Path relatif dihitung dari folder tempat perintah dijalankan, bukan folder Moodle.
if ($ushstartcwd && !preg_match('#^([a-zA-Z]:[\\\\/]|[\\\\/])#', $OUT)) {
    $OUT = $ushstartcwd . DIRECTORY_SEPARATOR . $OUT;
}

if ($EMAIL === '' || $PASSWORD === '') {
    mtrace('Kredensial SIAKAD kosong.');
    mtrace('Set environment SIAKAD_EMAIL dan SIAKAD_PASSWORD, atau pakai --email= --password=');
    exit(1);
}

function ush_katalog_login(string $email, string $password): ?string {
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

function ush_katalog_get(string $url, string $token): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token, 'Accept: application/json'],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 60,
    ]);
    $raw = curl_exec($ch);
    $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$http, json_decode($raw, true)];
}

mtrace('=== Export katalog MK SIAKAD ===');

$token = ush_katalog_login($EMAIL, $PASSWORD);
if (!$token) {
    mtrace('Login SIAKAD gagal. Cek kredensial atau koneksi.');
    exit(1);
}
mtrace('Login SIAKAD: OK');

$lessons = [];
$page = 1;
$lastpage = 0;
$complete = false;
$retry = 0;
// API ini tidak mengirim last_page dan cenderung mengulang halaman terakhir.
// Katalog dianggap habis kalau beberapa halaman berturut-turut tidak menambah kode baru.
$nonew = 0;
$NONEW_LIMIT = 3;
$lastnewpage = 0;

while ($page <= $MAXPAGES) {
    [$http, $res] = ush_katalog_get(
        'https://siakad.sugenghartono.ac.id/api/all-lessons?per_page=100&page=' . $page,
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
    $before = count($lessons);
    foreach ($items as $lesson) {
        $code = strtoupper(trim($lesson['code'] ?? ''));
        if ($code !== '' && !isset($lessons[$code])) {
            $lessons[$code] = [
                'code' => $code,
                'name' => trim($lesson['name'] ?? ''),
                'semester' => (int) ($lesson['semester'] ?? 0),
                'sks_total' => (int) ($lesson['sks_total'] ?? 0),
            ];
        }
    }
    $baru = count($lessons) - $before;

    $lastpage = (int) ($res['meta']['last_page'] ?? $res['last_page'] ?? $lastpage);
    mtrace("  Page $page/" . ($lastpage ?: '?') . ': ' . count($items) . " item, +$baru baru, unique " . count($lessons));

    if (!$items) {
        $complete = true;
        break;
    }
    if ($lastpage > 0 && $page >= $lastpage) {
        $complete = true;
        break;
    }
    if ($baru > 0) {
        $nonew = 0;
        $lastnewpage = $page;
    } else if (++$nonew >= $NONEW_LIMIT) {
        $complete = true;
        mtrace("  $NONEW_LIMIT halaman berturut-turut tanpa kode baru — katalog dianggap habis.");
        break;
    }
    usleep(300000);
    $page++;
}

if (!$complete) {
    mtrace('');
    mtrace('PERINGATAN: katalog TIDAK terunduh penuh. File tetap ditulis, tapi ditandai belum lengkap.');
    mtrace('Skrip production akan menolak file yang belum lengkap. Jalankan ulang skrip ini.');
}

$payload = [
    'generated' => date('c'),
    'source' => 'siakad.sugenghartono.ac.id/api/all-lessons',
    'pages_fetched' => $page,
    'last_page' => $lastpage,
    'last_new_page' => $lastnewpage,
    'complete' => $complete,
    'count' => count($lessons),
    'lessons' => array_values($lessons),
];

if (file_put_contents($OUT, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) === false) {
    mtrace('Gagal menulis file: ' . $OUT);
    exit(1);
}

mtrace('');
mtrace('Tersimpan : ' . $OUT);
mtrace('Kode MK   : ' . count($lessons));
mtrace('Lengkap   : ' . ($complete ? 'ya' : 'TIDAK'));
mtrace('');
mtrace('Unggah file ini ke server, lalu jalankan di production:');
mtrace('  php admin/cli/ush_prepare_semester_production.php --from-file=/path/' . basename($OUT));
exit($complete ? 0 : 1);
