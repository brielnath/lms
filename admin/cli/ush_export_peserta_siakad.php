<?php
/**
 * Unduh peserta kelas SIAKAD (NIM + nama) ke satu file JSON.
 *
 * Sumber: menu LMS → Absensi Mahasiswa, bukan grades-per-course.
 * Endpoint ini memakai sesi login akademik (cookie), jadi skrip ini
 * dijalankan di komputer lokal — jangan taruh kredensial di production.
 *
 * Contoh uji satu kelas (Pemrograman Game, 31 mhs):
 *   php admin/cli/ush_export_peserta_siakad.php --pilot --out=peserta_IDM0629.json
 *
 * Semua kelas semester berjalan (setelah --probe menemukan daftar MK):
 *   php admin/cli/ush_export_peserta_siakad.php --full --out=peserta_20262027Ganjil.json
 *
 * Kalau login CLI gagal, tempel cookie sesi dari browser:
 *   php admin/cli/ush_export_peserta_siakad.php --pilot --cookie="laravel_session=...; XSRF-TOKEN=..."
 *
 * Opsi:
 *   --out=FILE
 *   --pilot                 Hanya kelas uji (default: IDM0629 / class 1837)
 *   --full                  Jalan semua MK yang ketemu
 *   --probe                 Coba endpoint daftar prodi/MK, lalu berhenti
 *   --batch=13              id_batch_year (2026/2027)
 *   --sub=20                id_sub_batch_year (20261 Ganjil)
 *   --lesson=873            id_lesson (pilot)
 *   --class=1837            id_class (pilot)
 *   --kode=IDM0629          Kode MK untuk pilot
 *   --prodi=26              id_prodi (untuk --full / --probe)
 *   --cookie="a=b; c=d"     Cookie sesi browser
 *   --cookie-file=FILE      Cookie disimpan di file (lebih aman di CMD)
 *   --email= --password=
 */

const SIAKAD_BASE = 'https://siakad.sugenghartono.ac.id';

$OUT = 'peserta_siakad.json';
$MODE = 'pilot';
$PROBE = false;
$BATCH = 13;
$SUB = 20;
$LESSON = 873;
$CLASS = 1837;
$KODE = 'IDM0629';
$PRODI = 26;
$COOKIE = '';
$COOKIEFILEARG = '';
$EMAIL = getenv('SIAKAD_EMAIL') ?: '';
$PASSWORD = getenv('SIAKAD_PASSWORD') ?: '';
$ushstartcwd = getcwd();

foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--pilot') {
        $MODE = 'pilot';
    } else if ($arg === '--full') {
        $MODE = 'full';
    } else if ($arg === '--probe') {
        $PROBE = true;
    } else if (str_starts_with($arg, '--out=')) {
        $OUT = substr($arg, 6);
    } else if (str_starts_with($arg, '--batch=')) {
        $BATCH = (int) substr($arg, 8);
    } else if (str_starts_with($arg, '--sub=')) {
        $SUB = (int) substr($arg, 6);
    } else if (str_starts_with($arg, '--lesson=')) {
        $LESSON = (int) substr($arg, 9);
    } else if (str_starts_with($arg, '--class=')) {
        $CLASS = (int) substr($arg, 8);
    } else if (str_starts_with($arg, '--kode=')) {
        $KODE = strtoupper(trim(substr($arg, 7)));
    } else if (str_starts_with($arg, '--prodi=')) {
        $PRODI = (int) substr($arg, 8);
    } else if (str_starts_with($arg, '--cookie=')) {
        $COOKIE = substr($arg, 9);
    } else if (str_starts_with($arg, '--cookie-file=')) {
        $COOKIEFILEARG = substr($arg, 14);
    } else if (str_starts_with($arg, '--email=')) {
        $EMAIL = substr($arg, 8);
    } else if (str_starts_with($arg, '--password=')) {
        $PASSWORD = substr($arg, 11);
    } else {
        echo "Argumen tidak dikenal: $arg\n";
        exit(1);
    }
}

if ($ushstartcwd && !preg_match('#^([a-zA-Z]:[\\\\/]|[\\\\/])#', $OUT)) {
    $OUT = $ushstartcwd . DIRECTORY_SEPARATOR . $OUT;
}
if ($COOKIEFILEARG !== '') {
    if ($ushstartcwd && !preg_match('#^([a-zA-Z]:[\\\\/]|[\\\\/])#', $COOKIEFILEARG)
        && is_readable($ushstartcwd . DIRECTORY_SEPARATOR . $COOKIEFILEARG)) {
        $COOKIEFILEARG = $ushstartcwd . DIRECTORY_SEPARATOR . $COOKIEFILEARG;
    }
    if (!is_readable($COOKIEFILEARG)) {
        echo "File cookie tidak terbaca: $COOKIEFILEARG\n";
        exit(1);
    }
    $COOKIE = trim((string) file_get_contents($COOKIEFILEARG));
}
$COOKIE = trim($COOKIE, " \t\n\r\0\x0B\"'");
if (stripos($COOKIE, 'cookie:') === 0) {
    $COOKIE = trim(substr($COOKIE, 7));
}

$cookiefile = tempnam(sys_get_temp_dir(), 'ushsiakad');
$bearertoken = null;

function ush_cookie_names(string $cookie): string {
    if ($cookie === '') {
        return '(kosong)';
    }
    preg_match_all('/(?:^|;)\s*([^=;\s]+)=/', $cookie, $m);
    $names = $m[1] ?? [];
    return $names ? implode(', ', $names) : '(tidak terbaca, panjang ' . strlen($cookie) . ')';
}

function ush_xsrf_from_cookie(string $cookie): string {
    if (!preg_match('/(?:^|;)\s*XSRF-TOKEN=([^;]+)/i', $cookie, $m)) {
        return '';
    }
    return urldecode(trim($m[1]));
}

function siakad_http(string $url, array $opt = []): array {
    global $cookiefile, $COOKIE, $bearertoken;
    $headers = [
        'Accept: application/json, text/javascript, */*; q=0.01',
        'X-Requested-With: XMLHttpRequest',
        'Referer: ' . SIAKAD_BASE . '/absensi-mahasiswa',
        'Origin: ' . SIAKAD_BASE,
    ];
    $xsrf = ush_xsrf_from_cookie($COOKIE);
    if ($xsrf !== '') {
        $headers[] = 'X-XSRF-TOKEN: ' . $xsrf;
    }
    if (!empty($opt['headers'])) {
        $headers = array_merge($headers, $opt['headers']);
    }
    if ($bearertoken && empty($opt['nobearer'])) {
        $headers[] = 'Authorization: Bearer ' . $bearertoken;
    }
    $ch = curl_init($url);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => $opt['timeout'] ?? 60,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_COOKIEFILE => $cookiefile,
        CURLOPT_COOKIEJAR => $cookiefile,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
    ];
    if ($COOKIE !== '') {
        $opts[CURLOPT_COOKIE] = $COOKIE;
    }
    if (!empty($opt['post'])) {
        $opts[CURLOPT_POST] = true;
        $opts[CURLOPT_POSTFIELDS] = $opt['post'];
    }
    curl_setopt_array($ch, $opts);
    $raw = curl_exec($ch);
    $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$http, is_string($raw) ? $raw : ''];
}

function siakad_json(string $path): array {
    $q = str_contains($path, '?') ? '&' : '?';
    [$http, $raw] = siakad_http(SIAKAD_BASE . $path . $q . 'start=0&length=500&draw=1');
    $json = json_decode($raw, true);
    return [$http, is_array($json) ? $json : null, $raw];
}

function ringkas_json($json, string $raw): string {
    if (is_array($json) && $json) {
        $keys = implode(',', array_slice(array_keys($json), 0, 8));
        $n = 0;
        if (isset($json['data']) && is_array($json['data'])) {
            $n = count($json['data']);
        }
        $total = $json['recordsTotal'] ?? $json['count'] ?? '';
        return "keys=$keys n=$n total=$total";
    }
    $awal = str_replace(["\n", "\r"], ' ', substr($raw, 0, 120));
    return 'bukan data peserta (' . strlen($raw) . ' byte) awal: ' . $awal;
}

function ush_norm_code(string $code): string {
    $c = strtoupper(trim($code));
    $c = str_replace(['*', ' '], '', $c);
    return preg_replace('/_20\d{6}(GANJIL|GENAP)$/i', '', $c) ?? $c;
}

function ush_kode_dari_label(string $label): string {
    $label = trim($label);
    if (preg_match('/\b([IGD][UFD][ME]\d+[A-Z]?)\b/i', $label, $m)) {
        return ush_norm_code($m[1]);
    }
    if (preg_match('/\b([A-Z]{2,5}\s?\d{2,4}[A-Z]?)\b/', strtoupper($label), $m)) {
        return ush_norm_code($m[1]);
    }
    return '';
}

function ush_bersih_nama(string $s): string {
    $s = html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $s = preg_replace('/<br\s*\/?>/i', ' ', $s) ?? $s;
    $s = strip_tags($s);
    return trim(preg_replace('/\s+/', ' ', $s) ?? $s);
}

function ush_rows(?array $json): array {
    if (!is_array($json)) {
        return [];
    }
    $data = $json['data'] ?? $json;
    if (!is_array($data)) {
        return [];
    }
    if ($data && array_is_list($data)) {
        return $data;
    }
    return [];
}

function siakad_api_login(string $email, string $password): ?string {
    [$http, $raw] = siakad_http(SIAKAD_BASE . '/api/login', [
        'post' => json_encode(['email' => $email, 'password' => $password]),
        'headers' => ['Content-Type: application/json'],
        'nobearer' => true,
        'timeout' => 30,
    ]);
    if ($http !== 200) {
        return null;
    }
    $json = json_decode($raw, true);
    return $json['token']
        ?? $json['access_token']
        ?? $json['data']['token']
        ?? $json['data']['access_token']
        ?? null;
}

function siakad_web_login(string $email, string $password): bool {
    [$http, $html] = siakad_http(SIAKAD_BASE . '/login', ['nobearer' => true, 'timeout' => 30]);
    if ($http < 200 || $http >= 400) {
        echo "  GET /login HTTP $http\n";
        return false;
    }
    $token = '';
    if (preg_match('/name="_token"\s+value="([^"]+)"/', $html, $m)) {
        $token = $m[1];
    } else if (preg_match('/csrf-token"\s+content="([^"]+)"/', $html, $m)) {
        $token = $m[1];
    }
    $post = http_build_query([
        '_token' => $token,
        'email' => $email,
        'password' => $password,
    ]);
    [$http2, $body] = siakad_http(SIAKAD_BASE . '/login', [
        'post' => $post,
        'headers' => ['Content-Type: application/x-www-form-urlencoded', 'Referer: ' . SIAKAD_BASE . '/login'],
        'nobearer' => true,
        'timeout' => 30,
    ]);
    // Berhasil kalau sudah tidak di halaman login, atau body mengandung dashboard.
    $masihlogin = (bool) preg_match('/Sign In|type="password"/i', $body)
        && !preg_match('/MASTER DATA SIAKAD|Dashboard|Logout|Keluar/i', $body);
    if ($masihlogin && $http2 !== 302) {
        echo "  POST /login HTTP $http2 — masih di halaman login\n";
        return false;
    }
    return true;
}

function ambil_peserta(int $classid, int $lesson, int $batch, int $sub): array {
    $path = "/get-absensi-lms-mahasiswa-akademik/$classid/$lesson/$batch/$sub";
    [$http, $json] = siakad_json($path);
    if ($http !== 200 || !is_array($json)) {
        return ['ok' => false, 'http' => $http, 'students' => [], 'total' => 0];
    }
    $rows = ush_rows($json);
    $total = (int) ($json['recordsTotal'] ?? count($rows));
    $students = [];
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $nim = preg_replace('/\D+/', '', (string) ($row['nim'] ?? '')) ?? '';
        $name = ush_bersih_nama((string) ($row['name'] ?? $row['nama'] ?? ''));
        if ($nim === '' || $name === '') {
            continue;
        }
        $students[$nim] = [
            'id_mahasiswa' => (int) ($row['id_mahasiswa'] ?? 0),
            'nim' => $nim,
            'name' => $name,
        ];
    }
    return [
        'ok' => true,
        'http' => $http,
        'students' => array_values($students),
        'total' => $total ?: count($students),
    ];
}

function ambil_kelas(int $batch, int $sub, int $lesson): array {
    [$http, $json] = siakad_json("/get-kehadiran-dosen-mahasiswa-lms/$batch/$sub/$lesson");
    if ($http === 200 && ush_rows($json)) {
        return [$http, ush_rows($json)];
    }
    [$http2, $json2] = siakad_json("/get-kelas-lesson-akademik/$batch/$sub/$lesson");
    if ($http2 === 200 && ush_rows($json2)) {
        return [$http2, ush_rows($json2)];
    }
    return [$http ?: $http2, []];
}

function ambil_prodi_dari_halaman(): array {
    [$http, $html] = siakad_http(SIAKAD_BASE . '/kehadiran-dosen-mahasiswa-lms');
    if ($http !== 200 || $html === '') {
        echo "  Gagal unduh halaman kehadiran (HTTP $http)\n";
        return [];
    }
    if (!preg_match('/id="prodi_select".*?<\/select>/s', $html, $blk)) {
        echo "  Dropdown prodi tidak ketemu di halaman kehadiran.\n";
        return [];
    }
    preg_match_all('/<option value="(\d+)"[^>]*>([^<]+)/', $blk[0], $m);
    $out = [];
    foreach ($m[1] as $i => $id) {
        $out[(int) $id] = ush_bersih_nama(html_entity_decode($m[2][$i], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
    return $out;
}

function ambil_lesson_prodi(int $prodiid): array {
    [$http, $json] = siakad_json("/get-lesson-prodi/$prodiid");
    if ($http !== 200 || !is_array($json)) {
        return [];
    }
    return ush_rows($json);
}

echo "=== Export peserta kelas SIAKAD ===\n";

$auths = [];
if ($COOKIE !== '') {
    $auths[] = 'cookie-manual';
    echo 'Auth: cookie (' . strlen($COOKIE) . ' karakter, nama: ' . ush_cookie_names($COOKIE) . ")\n";
} else {
    if ($EMAIL === '' || $PASSWORD === '') {
        echo "Kredensial SIAKAD kosong.\n";
        echo "Set SIAKAD_EMAIL dan SIAKAD_PASSWORD, atau --email= --password=, atau --cookie=\n";
        @unlink($cookiefile);
        exit(1);
    }
    $bearertoken = siakad_api_login($EMAIL, $PASSWORD);
    if ($bearertoken) {
        $auths[] = 'bearer';
        echo "Login API (/api/login): OK\n";
    } else {
        echo "Login API: gagal (nanti coba sesi web)\n";
    }
    if (siakad_web_login($EMAIL, $PASSWORD)) {
        $auths[] = 'cookie-web';
        echo "Login web (/login): OK\n";
    } else {
        echo "Login web: gagal\n";
    }
}

$uji = "/get-absensi-lms-mahasiswa-akademik/$CLASS/$LESSON/$BATCH/$SUB";
[$testhttp, $testjson, $testraw] = siakad_json($uji);
echo "Uji endpoint peserta: HTTP $testhttp  " . ringkas_json($testjson, $testraw) . "\n";
$testok = $testhttp === 200 && is_array($testjson)
    && ((int) ($testjson['recordsTotal'] ?? 0) > 0 || ush_rows($testjson));
if (!$testok) {
    echo "\nEndpoint peserta tidak bisa diakses dengan auth yang ada.\n";
    echo "Jangan tempel cookie di CMD (sering terpotong). Simpan di file:\n";
    echo "  1. F12 → Network → klik get-absensi-lms-mahasiswa-akademik/...\n";
    echo "  2. Headers → Request Headers → Cookie → salin nilainya\n";
    echo "  3. Tempel ke notepad, simpan sebagai c:\\wamp64\\www\\lms\\siakad_cookie.txt\n";
    echo "  4. php admin/cli/ush_export_peserta_siakad.php --pilot --out=peserta_IDM0629.json --cookie-file=siakad_cookie.txt\n";
    @unlink($cookiefile);
    exit(1);
}

$kandidat = [
    "/get-lesson-prodi/$PRODI",
    "/get-sub-by/$BATCH",
    "/get-kelas-lesson-akademik/$BATCH/$SUB/$LESSON",
    "/get-kehadiran-dosen-mahasiswa-lms/$BATCH/$SUB/$LESSON",
];

$found = [];
if ($PROBE) {
    echo "\n=== Probe endpoint daftar ===\n";
    foreach ($kandidat as $path) {
        [$http, $json, $raw] = siakad_json($path);
        $ok = $http === 200 && (is_array($json) && ($json !== []));
        echo sprintf("  %-55s HTTP %d  %s\n", $path, $http, $ok ? ringkas_json($json, $raw) : '');
        if ($ok && ush_rows($json)) {
            $found[$path] = ush_rows($json);
        }
        usleep(200000);
    }
    echo "\n";
    if ($MODE !== 'full') {
        echo "Probe selesai. Pakai --pilot atau --full untuk menulis JSON.\n";
        @unlink($cookiefile);
        exit(0);
    }
}

$classesout = [];
$complete = true;

if ($MODE === 'pilot') {
    echo "Mode: PILOT kelas $KODE (lesson $LESSON, class $CLASS)\n";
    [$httpk, $kelasrows] = ambil_kelas($BATCH, $SUB, $LESSON);
    $meta = [];
    foreach ($kelasrows as $k) {
        if ((int) ($k['id'] ?? 0) === $CLASS || (int) ($k['id_class'] ?? 0) === $CLASS) {
            $meta = $k;
            break;
        }
    }
    if (!$meta && $kelasrows) {
        $meta = $kelasrows[0];
    }
    $got = ambil_peserta($CLASS, $LESSON, $BATCH, $SUB);
    $jml = (int) ($meta['jml'] ?? $got['total']);
    if (!$got['ok'] || count($got['students']) === 0) {
        $complete = false;
        echo "Gagal mengambil peserta (HTTP {$got['http']}).\n";
    } else if ($jml > 0 && count($got['students']) !== $jml) {
        $complete = false;
        echo "Jumlah tidak lengkap: " . count($got['students']) . " dari $jml\n";
    }
    $classesout[] = [
        'id_class' => $CLASS,
        'id_lesson' => $LESSON,
        'id_prodi' => (int) ($meta['id_prodi'] ?? $PRODI),
        'id_lecture' => (int) ($meta['id_lecture'] ?? 0),
        'code' => $KODE,
        'lesson_name' => ush_bersih_nama((string) ($meta['lesson_name'] ?? $meta['mata_kuliah'] ?? 'Pemrograman Game')),
        'class_name' => ush_bersih_nama((string) ($meta['name'] ?? 'Reguler Ilmu Komputer')),
        'dosen' => ush_bersih_nama((string) ($meta['dosen'] ?? '')),
        'jml_siakad' => $jml,
        'jml_diambil' => count($got['students']),
        'students' => $got['students'],
    ];
    echo 'Peserta diambil: ' . count($got['students']) . " / $jml\n";
} else {
    echo "Mode: FULL semester batch=$BATCH sub=$SUB\n";
    $prodis = ambil_prodi_dari_halaman();
    if (!$prodis) {
        echo "Daftar prodi tidak ketemu di halaman kehadiran.\n";
        @unlink($cookiefile);
        exit(1);
    }
    echo 'Prodi: ' . count($prodis) . ' (' . implode(', ', $prodis) . ")\n";

    foreach ($prodis as $pid => $pname) {
        $lessons = ambil_lesson_prodi($pid);
        echo "\n[$pname id=$pid] " . count($lessons) . " MK di katalog prodi\n";
        $kelasprodi = 0;
        foreach ($lessons as $lessonrow) {
            $lid = (int) ($lessonrow['id'] ?? 0);
            $code = ush_norm_code((string) ($lessonrow['code'] ?? ''));
            if ($code === '') {
                $code = ush_kode_dari_label((string) ($lessonrow['name'] ?? ''));
            }
            $lname = ush_bersih_nama((string) ($lessonrow['name'] ?? $code));
            if ($lid <= 0 || $code === '') {
                continue;
            }
            [$httpk, $kelasrows] = ambil_kelas($BATCH, $SUB, $lid);
            usleep(180000);
            if (!$kelasrows) {
                continue;
            }
            foreach ($kelasrows as $k) {
                $cid = (int) ($k['id'] ?? $k['id_class'] ?? 0);
                if ($cid <= 0) {
                    continue;
                }
                $got = ambil_peserta($cid, $lid, $BATCH, $SUB);
                $jml = (int) ($k['jml'] ?? $got['total']);
                if (!$got['ok'] || ($jml > 0 && count($got['students']) !== $jml)) {
                    $complete = false;
                    echo "  $code / $cid: TIDAK LENGKAP " . count($got['students']) . " / $jml\n";
                } else {
                    echo "  $code / $cid: " . count($got['students']) . " / $jml — " . ush_bersih_nama((string) ($k['dosen'] ?? '')) . "\n";
                }
                $classesout[] = [
                    'id_class' => $cid,
                    'id_lesson' => $lid,
                    'id_prodi' => (int) ($k['id_prodi'] ?? $pid),
                    'id_lecture' => (int) ($k['id_lecture'] ?? 0),
                    'code' => $code,
                    'lesson_name' => $lname,
                    'class_name' => ush_bersih_nama((string) ($k['name'] ?? '')),
                    'dosen' => ush_bersih_nama((string) ($k['dosen'] ?? '')),
                    'jml_siakad' => $jml,
                    'jml_diambil' => count($got['students']),
                    'students' => $got['students'],
                ];
                $kelasprodi++;
                usleep(180000);
            }
        }
        echo "  → $kelasprodi kelas semester ini\n";
    }
}

$nims = [];
foreach ($classesout as $c) {
    foreach ($c['students'] as $s) {
        $nims[$s['nim']] = true;
    }
}

$payload = [
    'generated' => date('c'),
    'source' => 'siakad.sugenghartono.ac.id/get-absensi-lms-mahasiswa-akademik',
    'auth' => $auths,
    'id_batch_year' => $BATCH,
    'id_sub_batch_year' => $SUB,
    'semester' => '20262027Ganjil',
    'mode' => $MODE,
    'complete' => $complete,
    'classes_count' => count($classesout),
    'students_unique' => count($nims),
    'classes' => $classesout,
];

if (file_put_contents($OUT, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) === false) {
    echo "Gagal menulis $OUT\n";
    @unlink($cookiefile);
    exit(1);
}

@unlink($cookiefile);

echo "\nTersimpan : $OUT\n";
echo 'Kelas     : ' . count($classesout) . "\n";
echo 'Mhs unik  : ' . count($nims) . "\n";
echo 'Lengkap   : ' . ($complete ? 'ya' : 'TIDAK') . "\n";
echo "\nLanjut di LMS (dry-run dulu):\n";
echo '  php admin/cli/ush_enrol_mahasiswa_production.php --from-file=' . basename($OUT) . "\n";
exit($complete ? 0 : 1);
