<?php
/**
 * Cek satu kelas di SIAKAD: berapa mahasiswa yang terbaca lewat API.
 *
 * Skrip berdiri sendiri — tidak memuat Moodle, jadi tidak butuh database.
 * Dipakai untuk mencocokkan angka di dasbor SIAKAD dengan yang bisa ditarik API,
 * sekaligus mencari endpoint menu "LMS" (Kehadiran, Absensi, Jurnal).
 *
 * Kredensial dari environment SIAKAD_EMAIL / SIAKAD_PASSWORD,
 * atau argumen --email= / --password=.
 *
 * Contoh:
 *   php admin/cli/ush_cek_kelas_siakad.php --kode=IDM0629
 *   php admin/cli/ush_cek_kelas_siakad.php --kode=IDM0629 --probe
 *
 * Opsi:
 *   --kode=IDM0629    Kode MK yang dicek (wajib)
 *   --probe           Coba daftar endpoint menu LMS, laporkan mana yang hidup
 *   --max-pages=N     Batas halaman grades-per-course (default 12)
 */

$KODE = '';
$PROBE = false;
$MAXPAGES = 12;
$EMAIL = getenv('SIAKAD_EMAIL') ?: '';
$PASSWORD = getenv('SIAKAD_PASSWORD') ?: '';

foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--probe') {
        $PROBE = true;
    } else if (str_starts_with($arg, '--kode=')) {
        $KODE = strtoupper(trim(substr($arg, 7)));
    } else if (str_starts_with($arg, '--max-pages=')) {
        $MAXPAGES = max(1, (int) substr($arg, 12));
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
    echo "Kredensial SIAKAD kosong.\n";
    echo "Set environment SIAKAD_EMAIL dan SIAKAD_PASSWORD, atau pakai --email= --password=\n";
    exit(1);
}
if ($KODE === '' && !$PROBE) {
    echo "Wajib --kode=IDM0629 (atau pakai --probe saja untuk mencari endpoint).\n";
    exit(1);
}

const SIAKAD_BASE = 'https://siakad.sugenghartono.ac.id';

function siakad_login(string $email, string $password): ?string {
    $ch = curl_init(SIAKAD_BASE . '/api/login');
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

function siakad_get(string $path, string $token): array {
    $ch = curl_init(SIAKAD_BASE . $path);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token, 'Accept: application/json'],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 90,
    ]);
    $raw = curl_exec($ch);
    $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$http, $raw];
}

/** Ringkas bentuk respon supaya mudah dibaca. */
function ringkas($json): string {
    if (!is_array($json)) {
        return gettype($json);
    }
    $keys = array_slice(array_keys($json), 0, 8);
    $out = 'keys: ' . implode(', ', $keys);
    if (isset($json['data']) && is_array($json['data'])) {
        $out .= ' | data: ' . count($json['data']) . ' item';
        $first = reset($json['data']);
        if (is_array($first)) {
            $out .= ' | field item: ' . implode(', ', array_slice(array_keys($first), 0, 10));
        }
    }
    return $out;
}

$token = siakad_login($EMAIL, $PASSWORD);
if (!$token) {
    echo "Login SIAKAD gagal.\n";
    exit(1);
}
echo "Login SIAKAD: OK\n\n";

if ($PROBE) {
    echo "=== Cari endpoint menu LMS ===\n";
    $kandidat = [
        '/api/kehadiran-dosen-mahasiswa-lms',
        '/api/kehadiran-lms',
        '/api/kehadiran',
        '/api/lms/kehadiran',
        '/api/absensi-mahasiswa',
        '/api/lms/absensi-mahasiswa',
        '/api/jurnal-perkuliahan',
        '/api/rps-rpp',
        '/api/kelas',
        '/api/kelas-mahasiswa',
        '/api/mahasiswa-per-kelas',
        '/api/krs',
        '/api/all-classes',
    ];
    foreach ($kandidat as $path) {
        [$http, $raw] = siakad_get($path, $token);
        $json = json_decode($raw, true);
        if ($http === 200) {
            echo sprintf("  %-40s HTTP %d  %s\n", $path, $http, ringkas($json));
        } else {
            echo sprintf("  %-40s HTTP %d\n", $path, $http);
        }
        usleep(400000);
    }
    echo "\n";
    if ($KODE === '') {
        exit(0);
    }
}

echo "=== Hitung mahasiswa untuk kode $KODE lewat grades-per-course ===\n";
$mahasiswa = [];
$tahunterlihat = [];
$contoh = null;
$page = 1;
$nonew = 0;

while ($page <= $MAXPAGES) {
    [$http, $raw] = siakad_get('/api/grades-per-course?per_page=100&page=' . $page, $token);
    if ($http === 429) {
        echo "  Page $page: limit 429, tunggu 10 detik\n";
        sleep(10);
        continue;
    }
    if ($http !== 200) {
        echo "  Page $page: HTTP $http, berhenti\n";
        break;
    }
    $json = json_decode($raw, true);
    $items = $json['data'] ?? [];
    if (!$items) {
        break;
    }

    $before = count($mahasiswa);
    foreach ($items as $mhs) {
        $nim = (string) ($mhs['nim'] ?? $mhs['student_number'] ?? $mhs['id'] ?? '');
        foreach ($mhs['grade'] ?? [] as $g) {
            $code = strtoupper(str_replace([' ', '*'], '', (string) ($g['lesson_code'] ?? '')));
            $th = (string) ($g['academic_year'] ?? $g['tahun'] ?? $g['year'] ?? '');
            if ($th !== '') {
                $tahunterlihat[$th] = ($tahunterlihat[$th] ?? 0) + 1;
            }
            if ($code === $KODE) {
                $mahasiswa[$nim] = trim((string) ($mhs['name'] ?? $mhs['nama'] ?? $nim));
                if ($contoh === null) {
                    $contoh = $g;
                }
            }
        }
    }
    $baru = count($mahasiswa) - $before;
    echo "  Page $page: " . count($items) . " mhs, +$baru cocok, total cocok " . count($mahasiswa) . "\n";

    if ($baru > 0) {
        $nonew = 0;
    } else if (++$nonew >= 3) {
        echo "  3 halaman tanpa tambahan — berhenti.\n";
        break;
    }
    usleep(400000);
    $page++;
}

echo "\n=== HASIL ===\n";
echo "Kode MK           : $KODE\n";
echo "Mahasiswa terbaca : " . count($mahasiswa) . "\n";
foreach (array_slice($mahasiswa, 0, 10, true) as $nim => $nama) {
    echo "  $nim  $nama\n";
}
if (count($mahasiswa) > 10) {
    echo '  ... (+' . (count($mahasiswa) - 10) . " lagi)\n";
}

if ($contoh !== null) {
    echo "\nField pada satu entri nilai (untuk tahu ada info tahun/periode atau tidak):\n";
    foreach ($contoh as $k => $v) {
        if (is_scalar($v) || $v === null) {
            echo sprintf("  %-20s %s\n", $k, is_null($v) ? 'null' : (string) $v);
        } else {
            echo sprintf("  %-20s [%s]\n", $k, gettype($v));
        }
    }
}

if ($tahunterlihat) {
    arsort($tahunterlihat);
    echo "\nTahun akademik yang muncul di data:\n";
    foreach (array_slice($tahunterlihat, 0, 8, true) as $th => $n) {
        echo "  $th : $n baris\n";
    }
}
