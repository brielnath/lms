<?php
/**
 * Buat kategori prodi + cangkang kelas satu semester dari katalog MK SIAKAD.
 * Aman dijalankan di production: default DRY-RUN, menulis hanya jika --confirm.
 *
 * Katalog MK bisa diambil dua cara:
 *
 * 1. Dari file (dianjurkan; server tidak perlu kredensial SIAKAD sama sekali).
 *    Di lokal : php admin/cli/ush_export_katalog_siakad.php --out=katalog.json
 *    Unggah katalog.json ke server, lalu:
 *      php admin/cli/ush_prepare_semester_production.php --from-file=katalog.json
 *      php admin/cli/ush_prepare_semester_production.php --from-file=katalog.json --confirm
 *
 * 2. Langsung dari API SIAKAD, dengan kredensial dari environment
 *    SIAKAD_EMAIL / SIAKAD_PASSWORD atau argumen --email= / --password=.
 *
 * Opsi:
 *   --semester=20262027Ganjil   Suffix shortname + label tahun (default 20262027Ganjil)
 *   --from-file=FILE            Baca katalog dari JSON, tidak menghubungi SIAKAD
 *   --confirm                   Benar-benar membuat kategori & kelas
 *   --email= / --password=      Kredensial SIAKAD (kalau tidak pakai --from-file)
 */
define('CLI_SCRIPT', true);

// Moodle memindah working directory, jadi catat dulu supaya --from-file relatif tetap ketemu.
$ushstartcwd = getcwd();

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->libdir . '/enrollib.php');
require_once(__DIR__ . '/ush_course_owner.php');

$SUFFIX = '20262027Ganjil';
$CONFIRM = false;
$FROMFILE = '';
$EMAIL = getenv('SIAKAD_EMAIL') ?: '';
$PASSWORD = getenv('SIAKAD_PASSWORD') ?: '';

foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--confirm') {
        $CONFIRM = true;
    } else if (str_starts_with($arg, '--semester=')) {
        $SUFFIX = substr($arg, 11);
    } else if (str_starts_with($arg, '--from-file=')) {
        $FROMFILE = substr($arg, 12);
    } else if (str_starts_with($arg, '--email=')) {
        $EMAIL = substr($arg, 8);
    } else if (str_starts_with($arg, '--password=')) {
        $PASSWORD = substr($arg, 11);
    } else {
        mtrace('Argumen tidak dikenal: ' . $arg);
        exit(1);
    }
}

if (!preg_match('/^(20\d{2})(20\d{2})(Ganjil|Genap)$/i', $SUFFIX, $m)) {
    mtrace('Format --semester salah. Contoh: 20262027Ganjil');
    exit(1);
}
$TAHUN = $m[1] . '/' . $m[2];
$PERIODE = ucfirst(strtolower($m[3]));
$LABEL = $TAHUN . ' - ' . $PERIODE;
$GANJIL = strcasecmp($PERIODE, 'Ganjil') === 0;
$START = $GANJIL ? mktime(0, 0, 0, 9, 1, (int) $m[1]) : mktime(0, 0, 0, 3, 1, (int) $m[2]);
$END = $GANJIL ? mktime(0, 0, 0, 2, 28, (int) $m[2]) : mktime(0, 0, 0, 8, 31, (int) $m[2]);

if ($FROMFILE === '' && ($EMAIL === '' || $PASSWORD === '')) {
    mtrace('Katalog MK belum ditentukan.');
    mtrace('Pakai --from-file=katalog.json (dianjurkan), atau set SIAKAD_EMAIL dan SIAKAD_PASSWORD.');
    exit(1);
}

mtrace('=== Siapkan semester ' . $LABEL . ' ===');
mtrace('Site   : ' . $CFG->wwwroot);
mtrace('Mode   : ' . ($CONFIRM ? 'LIVE (menulis data)' : 'DRY-RUN (tidak menulis)'));
mtrace('Suffix : *_' . $SUFFIX);
mtrace('Katalog: ' . ($FROMFILE !== '' ? 'file ' . $FROMFILE : 'API SIAKAD langsung'));
mtrace('');

function ush_siakad_login(string $email, string $password): ?string {
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
    // Respon SIAKAD terbaru menaruh token di data.access_token.
    return $json['token']
        ?? $json['access_token']
        ?? $json['data']['token']
        ?? $json['data']['access_token']
        ?? null;
}

function ush_siakad_get(string $url, string $token): array {
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

// Katalog dari file: divalidasi lebih dulu supaya gagal sebelum menyentuh database.
$filelessons = [];
$token = null;
if ($FROMFILE !== '') {
    if (!is_readable($FROMFILE) && $ushstartcwd && is_readable($ushstartcwd . '/' . $FROMFILE)) {
        $FROMFILE = $ushstartcwd . '/' . $FROMFILE;
    }
    if (!is_readable($FROMFILE)) {
        mtrace('File katalog tidak terbaca: ' . $FROMFILE);
        exit(1);
    }
    $payload = json_decode(file_get_contents($FROMFILE), true);
    if (!is_array($payload) || !isset($payload['lessons']) || !is_array($payload['lessons'])) {
        mtrace('Isi file katalog tidak dikenali. Buat ulang dengan ush_export_katalog_siakad.php');
        exit(1);
    }
    if (empty($payload['complete'])) {
        mtrace('File katalog ditandai BELUM LENGKAP (unduhan terputus).');
        mtrace('Jalankan ulang ush_export_katalog_siakad.php di lokal, lalu unggah lagi.');
        exit(1);
    }
    foreach ($payload['lessons'] as $lesson) {
        $code = strtoupper(trim($lesson['code'] ?? ''));
        if ($code !== '') {
            $filelessons[$code] = $lesson;
        }
    }
    if (!$filelessons) {
        mtrace('File katalog kosong.');
        exit(1);
    }
    mtrace('Katalog dibaca dari file: ' . count($filelessons) . ' kode MK (dibuat ' . ($payload['generated'] ?? '?') . ')');
} else {
    $token = ush_siakad_login($EMAIL, $PASSWORD);
    if (!$token) {
        mtrace('Login SIAKAD gagal. Cek kredensial atau koneksi.');
        exit(1);
    }
    mtrace('Login SIAKAD: OK');
}

// --- Kategori induk tahun akademik ---
$prodilist = ush_prodi_labels();
$parentidn = 'TA_' . str_replace(['/', ' ', '-'], '_', $LABEL);
$parent = $DB->get_record('course_categories', ['idnumber' => $parentidn])
    ?: $DB->get_record('course_categories', ['name' => 'TA ' . $LABEL]);

if ($parent) {
    mtrace('Kategori induk sudah ada: ' . $parent->name);
    $parentid = (int) $parent->id;
} else if ($CONFIRM) {
    $parent = core_course_category::create((object) [
        'name' => 'TA ' . $LABEL,
        'idnumber' => $parentidn,
        'parent' => 0,
        'description' => 'Tahun akademik ' . $LABEL,
        'descriptionformat' => FORMAT_PLAIN,
    ]);
    $parentid = (int) $parent->id;
    mtrace('Kategori induk dibuat: TA ' . $LABEL);
} else {
    $parentid = 0;
    mtrace('[DRY] Akan buat kategori induk: TA ' . $LABEL);
}

// --- Sub kategori per prodi ---
$catids = [];
$catnew = 0;
foreach ($prodilist as $kode => $nama) {
    $catname = "$nama ($kode) - $LABEL";
    $idn = "CAT_{$kode}_" . str_replace(['/', ' ', '-'], '_', $LABEL);
    $existing = $DB->get_record('course_categories', ['idnumber' => $idn])
        ?: $DB->get_record('course_categories', ['name' => $catname]);

    if ($existing) {
        $catids[$kode] = (int) $existing->id;
        continue;
    }
    if (!$CONFIRM) {
        $catnew++;
        mtrace("  [DRY] Akan buat kategori: $catname");
        continue;
    }
    $created = core_course_category::create((object) [
        'name' => $catname,
        'idnumber' => $idn,
        'parent' => $parentid,
        'descriptionformat' => FORMAT_PLAIN,
    ]);
    $catids[$kode] = (int) $created->id;
    $catnew++;
    mtrace("  Kategori dibuat: $catname");
}
mtrace('Kategori prodi siap: ' . count($catids) . ' ada, ' . $catnew . ($CONFIRM ? ' baru' : ' akan dibuat'));

// --- Katalog MK ---
$lessons = $filelessons;
$page = 1;
while ($token !== null && $page <= 80) {
    [$http, $res] = ush_siakad_get(
        'https://siakad.sugenghartono.ac.id/api/all-lessons?per_page=100&page=' . $page,
        $token
    );
    if ($http === 429) {
        mtrace("  Katalog page $page kena limit (429), tunggu 8 detik...");
        sleep(8);
        continue;
    }
    if ($http !== 200) {
        mtrace("  Katalog page $page HTTP $http — berhenti");
        break;
    }
    $items = $res['data'] ?? [];
    if (!$items) {
        break;
    }
    foreach ($items as $lesson) {
        $code = strtoupper(trim($lesson['code'] ?? ''));
        if ($code !== '' && !isset($lessons[$code])) {
            $lessons[$code] = $lesson;
        }
    }
    $last = (int) ($res['meta']['last_page'] ?? $res['last_page'] ?? 0);
    if ($last > 0 && $page >= $last) {
        break;
    }
    usleep(300000);
    $page++;
}
mtrace('Kode MK unik di katalog SIAKAD: ' . count($lessons));

// --- Buat cangkang kelas ---
$enrolplugin = enrol_get_plugin('manual');
$created = 0;
$skipped = 0;
$ignoredperiode = 0;
$ignoredkkn = 0;
$nonofficial = 0;
$perprodi = [];
$skipre = '/(kkn|skripsi|kerja praktik|praktik kerja|kuliah kerja|seminar proposal)/i';

foreach ($lessons as $code => $lesson) {
    // Hanya kurikulum resmi (IDM/IUM/IFM/IDE/GDM); kode lama diabaikan.
    if (!ush_is_official_scheme_code($code)) {
        $nonofficial++;
        continue;
    }
    // MK semester genap tidak dibuat di periode ganjil, dan sebaliknya.
    $sem = (int) ($lesson['semester'] ?? 0);
    if ($sem > 0 && (($sem % 2 === 0) === $GANJIL)) {
        $ignoredperiode++;
        continue;
    }
    $name = trim($lesson['name'] ?? '');
    if ($name === '') {
        continue;
    }
    if (preg_match($skipre, $name . ' ' . $code)) {
        $ignoredkkn++;
        continue;
    }

    $shortname = $code . '_' . $SUFFIX;
    if ($DB->record_exists('course', ['shortname' => $shortname])) {
        $skipped++;
        continue;
    }

    $prodi = ush_prodi_from_code($code);
    $perprodi[$prodi] = ($perprodi[$prodi] ?? 0) + 1;

    if (!$CONFIRM) {
        $created++;
        if ($created <= 15) {
            mtrace("  [DRY] $shortname → $prodi — $name");
        }
        continue;
    }

    $categoryid = $catids[$prodi] ?? $catids['MKU'] ?? 0;
    if (!$categoryid) {
        mtrace("  Lewati $shortname: kategori $prodi tidak ada");
        $skipped++;
        continue;
    }

    $sks = (int) ($lesson['sks_total'] ?? 0);
    $newcourse = new stdClass();
    $newcourse->fullname = "$name — $LABEL";
    $newcourse->shortname = $shortname;
    $newcourse->idnumber = $shortname;
    $newcourse->category = $categoryid;
    $newcourse->visible = 1;
    $newcourse->format = 'topics';
    $newcourse->numsections = 16;
    $newcourse->startdate = $START;
    $newcourse->enddate = $END;
    $newcourse->summary = '<p><strong>' . s($name) . '</strong> — ' . s($LABEL)
        . '</p><p>Kode: ' . s($code) . ($sks ? ' | SKS: ' . $sks : '') . '</p>';
    $newcourse->summaryformat = FORMAT_HTML;
    $newcourse->enablecompletion = 1;

    try {
        $course = create_course($newcourse);
        if ($enrolplugin && !$DB->record_exists('enrol', ['courseid' => $course->id, 'enrol' => 'manual'])) {
            $enrolplugin->add_instance($course);
        }
        $created++;
        if ($created % 40 === 0) {
            mtrace("  ... $created kelas dibuat");
        }
    } catch (Throwable $e) {
        $skipped++;
        mtrace('  Gagal ' . $shortname . ': ' . $e->getMessage());
    }
}

if ($CONFIRM) {
    fix_course_sortorder();
    rebuild_course_cache(0, true);
}

ksort($perprodi);
mtrace('');
mtrace('=== RINGKASAN ===');
mtrace('  Kelas ' . ($CONFIRM ? 'dibuat' : 'akan dibuat') . ' : ' . $created);
mtrace('  Sudah ada / dilewati    : ' . $skipped);
mtrace('  MK periode lain         : ' . $ignoredperiode);
mtrace('  KKN/Skripsi/KP dilewati : ' . $ignoredkkn);
mtrace('  Kode non-kurikulum baru : ' . $nonofficial);
foreach ($perprodi as $prodi => $n) {
    mtrace("    $prodi : $n");
}
mtrace('');
if (!$CONFIRM) {
    mtrace('Ini DRY-RUN. Jalankan ulang dengan --confirm untuk benar-benar membuat.');
}
