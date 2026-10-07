<?php
/**
 * Semi-otomatis: sync mahasiswa aktif SIAKAD → akun Moodle (+ opsional enrol).
 *
 * Sumber data: /grades-per-course (endpoint /user/mahasiswa sering 404).
 *
 * Akun baru:
 *   username = NIM (lowercase)
 *   password = Ush@{NIM}
 *   email    = {nim}@sugenghartono.ac.id
 *
 * Pakai (LOKAL dulu):
 *   php admin/cli/sync_siakad_mahasiswa_accounts_local.php --dry-run
 *   php admin/cli/sync_siakad_mahasiswa_accounts_local.php
 *   php admin/cli/sync_siakad_mahasiswa_accounts_local.php --enrol --suffix=20262027Ganjil --tahun=2026/2027 --periode=Ganjil
 *
 * Cron / Task Scheduler (contoh harian jam 02:00):
 *   php .../admin/cli/sync_siakad_mahasiswa_accounts_local.php --enrol --suffix=20262027Ganjil
 *
 * Catatan:
 * - Hanya jalan di LMS lokal (localhost). Untuk produksi, ganti guard atau buat salinan _production.
 * - Enrol hanya jika kelas shortname *_{suffix} sudah ada; tidak membuat kelas baru.
 * - Jika tahun 2026/2027 belum ada di SIAKAD, akun tetap dibuat; enrol akan 0.
 */
define('CLI_SCRIPT', true);

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/enrollib.php');
require_once($CFG->dirroot . '/user/lib.php');

if (strpos($CFG->wwwroot, 'localhost') === false && strpos($CFG->wwwroot, '127.0.0.1') === false) {
    mtrace('Dibatalkan: bukan LMS lokal. wwwroot=' . $CFG->wwwroot);
    exit(1);
}

// ---- args ----
$DRY = false;
$DO_ENROL = false;
$SUFFIX = '20262027Ganjil';
$TAHUN = '';      // kosong = semua tahun (untuk buat akun); untuk enrol filter jika diisi
$PERIODE = '';    // Ganjil|Genap|'' 
$MAX_PAGES = 120;
$SLEEP_MS = 200;  // jeda antar halaman (hindari HTTP 429)

foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--dry-run') {
        $DRY = true;
    } else if ($arg === '--enrol') {
        $DO_ENROL = true;
    } else if (str_starts_with($arg, '--suffix=')) {
        $SUFFIX = substr($arg, 9);
    } else if (str_starts_with($arg, '--tahun=')) {
        $TAHUN = substr($arg, 8);
    } else if (str_starts_with($arg, '--periode=')) {
        $PERIODE = substr($arg, 10);
    } else if (str_starts_with($arg, '--max-pages=')) {
        $MAX_PAGES = max(1, (int) substr($arg, 12));
    }
}

// Dari suffix 20262027Ganjil → tahun 2026/2027 + periode Ganjil (agar KRS lama tidak ikut enrol).
if ($DO_ENROL && $TAHUN === '' && preg_match('/^(20\d{2})(20\d{2})(Ganjil|Genap)$/i', $SUFFIX, $m)) {
    $TAHUN = $m[1] . '/' . $m[2];
    if ($PERIODE === '') {
        $PERIODE = ucfirst(strtolower($m[3]));
    }
}

mtrace('=== Sync mahasiswa SIAKAD → LMS (semi-otomatis) ===');
mtrace('Mode: ' . ($DRY ? 'DRY-RUN (tidak menulis)' : 'LIVE'));
mtrace('Enrol: ' . ($DO_ENROL ? "YA → *_{$SUFFIX}" : 'TIDAK (akun saja)'));
mtrace('Filter tahun: ' . ($TAHUN !== '' ? $TAHUN : '(semua, untuk akun)'));
mtrace('Filter periode: ' . ($PERIODE !== '' ? $PERIODE : '(semua)'));
mtrace('');

function siakad_login(): ?string {
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
        CURLOPT_TIMEOUT => 25,
    ]);
    $raw = curl_exec($ch);
    curl_close($ch);
    $json = json_decode($raw, true);
    return $json['token'] ?? $json['data']['token'] ?? $json['access_token'] ?? $json['data']['access_token'] ?? null;
}

function siakad_get(string $url, string $token): array {
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

function ush_norm_code(string $code): string {
    $c = strtoupper(trim($code));
    $c = str_replace(['*', ' '], '', $c);
    $c = preg_replace('/_20\d{6}(GANJIL|GENAP)$/i', '', $c) ?? $c;
    return $c;
}

function ush_split_name(string $full): array {
    $full = trim(preg_replace('/\s+/', ' ', $full) ?? $full);
    $parts = $full === '' ? [] : explode(' ', $full);
    $firstname = array_shift($parts) ?: 'Mahasiswa';
    $lastname = trim(implode(' ', $parts));
    if ($lastname === '') {
        $lastname = 'USH';
    }
    return [$firstname, $lastname];
}

function ush_tahun_match(string $ta, string $want): bool {
    if ($want === '') {
        return true;
    }
    $ta = trim($ta);
    $want = trim($want);
    if ($ta === $want) {
        return true;
    }
    // 20262027 ↔ 2026/2027
    $norm = static function (string $s): string {
        return preg_replace('/[^0-9]/', '', $s) ?? '';
    };
    return $norm($ta) !== '' && $norm($ta) === $norm($want);
}

function ush_periode_match(string $periode, string $want): bool {
    if ($want === '') {
        return true;
    }
    return strcasecmp(trim($periode), trim($want)) === 0;
}

$token = siakad_login();
if (!$token) {
    mtrace('Gagal login SIAKAD');
    exit(1);
}
mtrace('Login SIAKAD: OK');

// Index kelas semester di Moodle (untuk enrol).
$courseIndex = [];
if ($DO_ENROL) {
    $courses = $DB->get_records_sql(
        "SELECT id, shortname, fullname FROM {course}
          WHERE id > 1 AND " . $DB->sql_like('shortname', ':p'),
        ['p' => '%' . $SUFFIX]
    );
    foreach ($courses as $c) {
        $courseIndex[ush_norm_code($c->shortname)] = $c;
    }
    mtrace('Kelas Moodle *' . $SUFFIX . '*: ' . count($courseIndex));
}

$studentrole = $DB->get_record('role', ['shortname' => 'student'], '*', MUST_EXIST);
$enrolplugin = enrol_get_plugin('manual');
$mnethostid = (int) $CFG->mnet_localhost_id;

// Kumpulkan mahasiswa unik + MK kontrak (opsional filter tahun/periode untuk enrol).
/** @var array<string,array{nim:string,name:string,email?:string,codes:array<string,bool>}> $students */
$students = [];
$page = 1;
$http429 = 0;

while ($page <= $MAX_PAGES) {
    [$http, $res] = siakad_get(
        'https://siakad.sugenghartono.ac.id/api/grades-per-course?per_page=100&page=' . $page,
        $token
    );
    if ($http === 429) {
        $http429++;
        if ($http429 > 5) {
            mtrace("HTTP 429 berulang di page $page — stop scan.");
            break;
        }
        mtrace("HTTP 429 page $page — tunggu 5s lalu ulang...");
        sleep(5);
        continue;
    }
    $http429 = 0;
    if ($http !== 200 || !is_array($res)) {
        mtrace("grades-per-course page $page HTTP $http — stop");
        break;
    }
    $items = $res['data'] ?? [];
    if (!$items) {
        mtrace("page $page kosong — selesai scan");
        break;
    }

    foreach ($items as $mhs) {
        $nim = trim((string) ($mhs['nim'] ?? $mhs['student_nim'] ?? ''));
        if ($nim === '') {
            continue;
        }
        $name = trim((string) ($mhs['name'] ?? $mhs['nama'] ?? $mhs['student_name'] ?? 'Mahasiswa'));
        $email = trim((string) ($mhs['email'] ?? ''));
        $status = strtoupper(trim((string) ($mhs['status'] ?? 'AKTIF')));
        // Skip jelas non-aktif jika field ada.
        if (in_array($status, ['NONAKTIF', 'NON-AKTIF', 'CUTI', 'DO', 'LULUS', 'KELUAR'], true)) {
            continue;
        }

        $key = strtolower($nim);
        if (!isset($students[$key])) {
            $students[$key] = [
                'nim' => $nim,
                'name' => $name,
                'email' => $email,
                'codes' => [],
            ];
        }

        foreach ($mhs['grade'] ?? [] as $g) {
            $ta = (string) ($g['tahun_akademik'] ?? '');
            $periode = (string) ($g['periode'] ?? '');
            // Untuk daftar MK enrol: hormati filter tahun/periode jika diisi.
            if ($DO_ENROL) {
                if ($TAHUN !== '' && !ush_tahun_match($ta, $TAHUN)) {
                    continue;
                }
                if ($PERIODE !== '' && !ush_periode_match($periode, $PERIODE)) {
                    continue;
                }
            }
            $code = ush_norm_code((string) ($g['lesson_code'] ?? ''));
            if ($code !== '') {
                $students[$key]['codes'][$code] = true;
            }
        }
    }

    $last = (int) ($res['meta']['last_page'] ?? $res['last_page'] ?? 0);
    if ($page === 1 || $page % 10 === 0) {
        mtrace("  scan page $page" . ($last ? " / $last" : '') . ' — mhs unik ' . count($students));
    }
    if ($last > 0 && $page >= $last) {
        break;
    }
    usleep($SLEEP_MS * 1000);
    $page++;
}

mtrace('');
mtrace('Mahasiswa unik dari SIAKAD: ' . count($students));
if (!$students) {
    mtrace('Tidak ada data — berhenti.');
    exit(0);
}

$created = 0;
$existing = 0;
$enrolled = 0;
$enrolSkip = 0;
$failed = 0;

foreach ($students as $row) {
    $nim = $row['nim'];
    $username = strtolower($nim);
    $user = $DB->get_record('user', [
        'username' => $username,
        'mnethostid' => $mnethostid,
        'deleted' => 0,
    ]);

    if (!$user) {
        [$firstname, $lastname] = ush_split_name($row['name']);
        $email = $row['email'] !== '' ? $row['email'] : ($username . '@sugenghartono.ac.id');
        // Email harus unik di Moodle.
        if ($DB->record_exists('user', ['email' => $email, 'deleted' => 0])) {
            $email = $username . '.' . substr(sha1($nim), 0, 6) . '@sugenghartono.ac.id';
        }

        if ($DRY) {
            mtrace("[DRY] BUAT akun {$username} — {$row['name']}");
            $created++;
            $userid = 0;
        } else {
            $record = (object) [
                'username' => $username,
                'auth' => 'manual',
                'password' => 'Ush@' . $nim, // user_create_user akan hash jika argumen ke-2 true
                'firstname' => $firstname,
                'lastname' => $lastname,
                'email' => $email,
                'city' => 'Sukoharjo',
                'country' => 'ID',
                'lang' => 'id',
                'confirmed' => 1,
                'mnethostid' => $mnethostid,
                'calendartype' => $CFG->calendartype ?? 'gregorian',
                'mailformat' => 1,
                'maildisplay' => 0,
                'institution' => 'Universitas Sugeng Hartono',
                'department' => 'Mahasiswa',
            ];
            try {
                $userid = user_create_user($record, true, false);
                $user = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);
                mtrace("BUAT {$username} | pass Ush@{$nim} | {$row['name']}");
                $created++;
            } catch (Throwable $e) {
                mtrace("GAGAL buat {$username}: " . $e->getMessage());
                $failed++;
                continue;
            }
        }
    } else {
        $existing++;
        $userid = (int) $user->id;
    }

    if (!$DO_ENROL || ($DRY && !$userid)) {
        // Dry-run tanpa user id: hitung potensi enrol saja.
        if ($DO_ENROL && $DRY) {
            foreach (array_keys($row['codes']) as $code) {
                if (isset($courseIndex[$code]) || isset($courseIndex[$code . 'P'])) {
                    $enrolSkip++; // potensi
                }
            }
        }
        continue;
    }

    foreach (array_keys($row['codes']) as $code) {
        $targets = [];
        if (isset($courseIndex[$code])) {
            $targets[] = $courseIndex[$code];
        }
        if (!str_ends_with($code, 'P') && isset($courseIndex[$code . 'P'])) {
            $targets[] = $courseIndex[$code . 'P'];
        }
        if (!$targets) {
            continue;
        }
        foreach ($targets as $course) {
            $instances = enrol_get_instances($course->id, true);
            $manual = null;
            foreach ($instances as $inst) {
                if ($inst->enrol === 'manual') {
                    $manual = $inst;
                    break;
                }
            }
            if (!$manual) {
                if ($DRY) {
                    continue;
                }
                $eid = $enrolplugin->add_instance($course);
                $manual = $DB->get_record('enrol', ['id' => $eid], '*', MUST_EXIST);
            }
            $already = $DB->record_exists('user_enrolments', [
                'enrolid' => $manual->id,
                'userid' => $userid,
            ]);
            if ($already) {
                $enrolSkip++;
                continue;
            }
            if ($DRY) {
                mtrace("[DRY] ENROL {$username} → {$course->shortname}");
                $enrolled++;
                continue;
            }
            $enrolplugin->enrol_user($manual, $userid, $studentrole->id);
            $enrolled++;
        }
    }
}

mtrace('');
mtrace('=== RINGKASAN ===');
mtrace('  Mahasiswa SIAKAD     : ' . count($students));
mtrace('  Akun sudah ada       : ' . $existing);
mtrace('  Akun baru' . ($DRY ? ' (dry)' : '') . '     : ' . $created);
mtrace('  Enrol baru' . ($DRY ? ' (dry)' : '') . '    : ' . $enrolled);
mtrace('  Enrol sudah / skip   : ' . $enrolSkip);
mtrace('  Gagal                : ' . $failed);
mtrace('');
mtrace('Selesai.');
if (!$DO_ENROL) {
    mtrace('Tip akun+enrol: --enrol --suffix=20262027Ganjil');
    mtrace('  (otomatis filter tahun 2026/2027 Ganjil dari suffix)');
}
