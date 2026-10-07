<?php
/**
 * Hitung mahasiswa terdaftar di SIAKAD (agregat saja, tanpa dump NIM).
 *
 *   php admin/cli/ush_count_mahasiswa_siakad.php --cookie-file=siakad_cookie.txt
 */
const SIAKAD_BASE = 'https://siakad.sugenghartono.ac.id';

$COOKIEFILEARG = '';
$EMAIL = getenv('SIAKAD_EMAIL') ?: '';
$PASSWORD = getenv('SIAKAD_PASSWORD') ?: '';
$GRADESONLY = false;
$ushstartcwd = getcwd();

foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--grades-only') {
        $GRADESONLY = true;
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

if ($EMAIL === '' || $PASSWORD === '') {
    echo "Set SIAKAD_EMAIL dan SIAKAD_PASSWORD, atau --cookie-file=\n";
    exit(1);
}

$COOKIE = '';
if ($COOKIEFILEARG !== '') {
    if ($ushstartcwd && is_readable($ushstartcwd . DIRECTORY_SEPARATOR . $COOKIEFILEARG)) {
        $COOKIEFILEARG = $ushstartcwd . DIRECTORY_SEPARATOR . $COOKIEFILEARG;
    }
    if (!is_readable($COOKIEFILEARG)) {
        echo "File cookie tidak terbaca: $COOKIEFILEARG\n";
        exit(1);
    }
    $COOKIE = trim((string) file_get_contents($COOKIEFILEARG));
}

$cookiefile = tempnam(sys_get_temp_dir(), 'ushsiakad');
$bearertoken = null;

function siakad_http(string $url, array $opt = []): array {
    global $cookiefile, $COOKIE, $bearertoken;
    $headers = [
        'Accept: application/json, text/html, */*; q=0.01',
        'X-Requested-With: XMLHttpRequest',
        'Referer: ' . SIAKAD_BASE . '/mahasiswa',
        'Origin: ' . SIAKAD_BASE,
    ];
    if ($bearertoken && empty($opt['nobearer'])) {
        $headers[] = 'Authorization: Bearer ' . $bearertoken;
    }
    if (!empty($opt['headers'])) {
        $headers = array_merge($headers, $opt['headers']);
    }
    $ch = curl_init($url);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => $opt['timeout'] ?? 45,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_COOKIEFILE => $cookiefile,
        CURLOPT_COOKIEJAR => $cookiefile,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
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

function ringkas($http, $raw): string {
    $json = json_decode($raw, true);
    if (is_array($json)) {
        $keys = implode(',', array_slice(array_keys($json), 0, 10));
        $n = isset($json['data']) && is_array($json['data']) ? count($json['data']) : 0;
        $total = $json['recordsTotal'] ?? $json['total'] ?? ($json['meta']['total'] ?? '');
        return "HTTP $http keys=$keys n=$n total=$total";
    }
    $loginish = (stripos($raw, 'login') !== false && strlen($raw) < 80000);
    return "HTTP $http html=" . strlen($raw) . 'b' . ($loginish ? ' (mungkin halaman login)' : '');
}

echo "=== Hitung mahasiswa SIAKAD ===\n";

if ($COOKIE !== '') {
    echo 'Cookie: ' . strlen($COOKIE) . " karakter\n";
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
    [$http2, $body] = siakad_http(SIAKAD_BASE . '/login', [
        'post' => http_build_query([
            '_token' => $token,
            'email' => $email,
            'password' => $password,
        ]),
        'headers' => ['Content-Type: application/x-www-form-urlencoded', 'Referer: ' . SIAKAD_BASE . '/login'],
        'nobearer' => true,
        'timeout' => 30,
    ]);
    $masihlogin = (bool) preg_match('/Sign In|type="password"/i', $body)
        && !preg_match('/MASTER DATA SIAKAD|Dashboard|Logout|Keluar/i', $body);
    return !($masihlogin && $http2 !== 302);
}

if ($EMAIL !== '' && $PASSWORD !== '') {
    [$http, $raw] = siakad_http(SIAKAD_BASE . '/api/login', [
        'post' => json_encode(['email' => $EMAIL, 'password' => $PASSWORD]),
        'headers' => ['Content-Type: application/json', 'Accept: application/json'],
        'nobearer' => true,
    ]);
    $j = json_decode($raw, true);
    $bearertoken = is_array($j)
        ? ($j['token'] ?? $j['access_token'] ?? $j['data']['token'] ?? $j['data']['access_token'] ?? null)
        : null;
    echo 'Login API: ' . ($bearertoken ? "OK (HTTP $http)" : "gagal HTTP $http") . "\n";
    if (!$GRADESONLY) {
        echo 'Login web: ' . (siakad_web_login($EMAIL, $PASSWORD) ? 'OK' : 'gagal') . "\n";
    }
}

if (!$GRADESONLY) {

$pages = [
    '/',
    '/home',
    '/dashboard',
    '/mahasiswa',
    '/data-mahasiswa',
    '/mahasiswa-aktif',
    '/master-data-mahasiswa',
    '/data-mahasiswa-semua-prodi',
    '/mahasiswa-semua-prodi',
    '/student',
    '/students',
    '/master/mahasiswa',
    '/akademik/mahasiswa',
    '/kehadiran-dosen-mahasiswa-lms',
];
echo "\n--- Halaman web ---\n";
foreach ($pages as $p) {
    [$http, $raw] = siakad_http(SIAKAD_BASE . $p, ['timeout' => 25]);
    echo sprintf("  %-28s %s\n", $p, ringkas($http, $raw));
    if ($http === 200 && $raw !== '' && strlen($raw) > 500 && stripos($raw, '<a') !== false) {
        preg_match_all('/href="([^"]*mahasiswa[^"]*)"/i', $raw, $hm);
        $hrefs = array_unique($hm[1] ?? []);
        if ($hrefs) {
            echo '    tautan mahasiswa: ' . implode(', ', array_slice($hrefs, 0, 12)) . "\n";
        }
    }
    usleep(150000);
}

$ajax = [
    '/get-mahasiswa',
    '/get-mahasiswa-akademik',
    '/get-data-mahasiswa',
    '/get-mahasiswa-lms',
    '/get-all-mahasiswa',
    '/mahasiswa/data',
    '/api/user/mahasiswa',
    '/api/mahasiswa',
    '/api/students',
    '/api/all-students',
    '/api/user/mahasiswa?per_page=1&page=1',
    '/api/grades-per-course?per_page=1&page=1',
];
echo "\n--- Endpoint data ---\n";
$hit = null;
foreach ($ajax as $p) {
    $url = str_starts_with($p, '/api') ? SIAKAD_BASE . $p : SIAKAD_BASE . $p . '?start=0&length=10&draw=1';
    [$http, $raw] = siakad_http($url, ['timeout' => 30]);
    $line = ringkas($http, $raw);
    echo sprintf("  %-42s %s\n", $p, $line);
    $json = json_decode($raw, true);
    if ($http === 200 && is_array($json) && (
        isset($json['recordsTotal']) || isset($json['meta']['total']) || isset($json['total'])
        || (isset($json['data']) && is_array($json['data']) && $json['data'])
    )) {
        $hit = [$p, $json, $raw];
    }
    usleep(120000);
}

if ($hit) {
    [$path, $json] = $hit;
    echo "\nSumber terpakai: $path\n";
    $total = $json['recordsTotal'] ?? $json['total'] ?? ($json['meta']['total'] ?? null);
    if ($total !== null) {
        echo "Total di meta: $total\n";
    }
}

}

function ush_status_kelompok(string $status): string {
    $s = strtoupper(trim($status));
    $s = str_replace(['-', '_', ' '], '', $s);
    if ($s === '' || $s === '?' || $s === 'NULL') {
        return 'kosong';
    }
    if (in_array($s, ['AKTIF', 'ACTIVE'], true)) {
        return 'aktif';
    }
    if (preg_match('/LULUS|ALUMNI|WISUDA/', $s)) {
        return 'lulus';
    }
    if (preg_match('/DO|DROPOUT|PUTUS/', $s)) {
        return 'DO';
    }
    if (preg_match('/CUTI/', $s)) {
        return 'cuti';
    }
    if (preg_match('/KELUAR|UNDUR|NONAKTIF|INACTIVE|MENGUNDURKAN/', $s)) {
        return 'keluar/nonaktif';
    }
    return 'lainnya';
}

if ($bearertoken) {
    echo "\n--- Status mahasiswa (/api/grades-per-course) ---\n";
    $byid = [];
    $page = 1;
    while ($page <= 200) {
        [$http, $raw] = siakad_http(SIAKAD_BASE . '/api/grades-per-course?per_page=10&page=' . $page, ['timeout' => 50]);
        if ($http === 429) {
            echo "  page $page: 429, tunggu\n";
            sleep(8);
            continue;
        }
        $json = json_decode($raw, true);
        if ($http !== 200 || !is_array($json)) {
            echo "  page $page HTTP $http\n";
            break;
        }
        $rows = $json['data'] ?? [];
        if (!$rows) {
            break;
        }
        $pag = $json['pagination'] ?? $json['meta'] ?? [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = (string) ($row['id_mahasiswa'] ?? $row['nim'] ?? '');
            $nim = preg_replace('/\D+/', '', (string) ($row['nim'] ?? '')) ?? '';
            $st = (string) ($row['status'] ?? '');
            $sem = (string) ($row['semester'] ?? '');
            $has2026 = false;
            foreach ($row['grade'] ?? [] as $g) {
                if (!is_array($g)) {
                    continue;
                }
                $ta = (string) ($g['tahun_akademik'] ?? $g['academic_year'] ?? '');
                if (str_contains($ta, '2026')) {
                    $has2026 = true;
                    break;
                }
            }
            $year = '';
            if (preg_match('/^06(\d{2})/', $nim, $m)) {
                $year = '20' . $m[1];
            }
            $key = $id !== '' ? $id : $nim;
            if ($key === '') {
                continue;
            }
            if (!isset($byid[$key])) {
                $byid[$key] = [
                    'status' => $st,
                    'semester' => $sem,
                    'year' => $year,
                    'has2026' => $has2026,
                    'nim' => $nim,
                ];
            } else {
                $byid[$key]['has2026'] = $byid[$key]['has2026'] || $has2026;
            }
        }
        $last = (int) ($pag['last_page'] ?? 0);
        if ($page === 1 || $page % 20 === 0 || ($last && $page >= $last)) {
            echo "  page $page / " . ($last ?: '?') . ' — unik ' . count($byid) . "\n";
        }
        if ($last > 0 && $page >= $last) {
            break;
        }
        $page++;
        usleep(220000);
    }

    $bystatus = [];
    $bykel = [];
    $byyear = [];
    $aktif2026 = 0;
    $aktifno2026 = 0;
    $nonaktif = 0;
    foreach ($byid as $row) {
        $st = $row['status'] !== '' ? $row['status'] : '(kosong)';
        $bystatus[$st] = ($bystatus[$st] ?? 0) + 1;
        $kel = ush_status_kelompok($row['status']);
        $bykel[$kel] = ($bykel[$kel] ?? 0) + 1;
        $y = $row['year'] !== '' ? $row['year'] : '(NIM tidak standar)';
        $byyear[$y] = ($byyear[$y] ?? 0) + 1;
        if ($kel === 'aktif') {
            if ($row['has2026']) {
                $aktif2026++;
            } else {
                $aktifno2026++;
            }
        } else {
            $nonaktif++;
        }
    }

    echo "\nTotal akun mahasiswa di API: " . count($byid) . "\n";
    echo "\nKelompok status:\n";
    ksort($bykel);
    foreach ($bykel as $k => $n) {
        echo sprintf("  %-18s %4d\n", $k, $n);
    }
    echo "\nStatus mentah SIAKAD:\n";
    arsort($bystatus);
    foreach ($bystatus as $k => $n) {
        echo sprintf("  %4d  %s\n", $n, $k);
    }
    echo "\nAngkatan (dari NIM):\n";
    ksort($byyear);
    foreach ($byyear as $k => $n) {
        echo sprintf("  %s  %4d\n", $k, $n);
    }
    echo "\nAktif dan ada MK 2026/2027 : $aktif2026\n";
    echo "Aktif tanpa MK 2026        : $aktifno2026\n";
    echo "Bukan aktif (lulus/keluar/dll): $nonaktif\n";
}

$peserta = $ushstartcwd . DIRECTORY_SEPARATOR . 'peserta_20262027Ganjil.json';
if (is_readable($peserta)) {
    $payload = json_decode((string) file_get_contents($peserta), true);
    $n = 0;
    $byprodi = [];
    if (is_array($payload)) {
        $seen = [];
        foreach ($payload['classes'] ?? [] as $kelas) {
            $pid = (int) ($kelas['id_prodi'] ?? 0);
            foreach ($kelas['students'] ?? [] as $s) {
                $nim = strtolower((string) ($s['nim'] ?? ''));
                if ($nim === '' || isset($seen[$nim])) {
                    continue;
                }
                $seen[$nim] = true;
                $byprodi[$pid] = ($byprodi[$pid] ?? 0) + 1;
            }
        }
        $n = count($seen);
    }
    echo "\n--- File absensi 2026/2027 Ganjil (bukan seluruh mahasiswa) ---\n";
    echo 'Unik di kelas berjalan: ' . $n . ' (field students_unique=' . ($payload['students_unique'] ?? '?') . ")\n";
    echo 'Kelas: ' . ($payload['classes_count'] ?? '?') . ' | lengkap: ' . (!empty($payload['complete']) ? 'ya' : 'tidak') . "\n";
    echo 'Diambil: ' . ($payload['generated'] ?? '?') . "\n";
}

@unlink($cookiefile);
echo "\nSelesai.\n";
