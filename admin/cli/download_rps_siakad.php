<?php
/**
 * Skrip untuk mengunduh file RPS dari SIAKAD dan memasangnya ke Moodle.
 * URL pattern: https://siakad.sugenghartono.ac.id/file/rps/{filename}
 */
define('CLI_SCRIPT', true);

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/course/modlib.php');
require_once($CFG->dirroot . '/mod/resource/lib.php');
require_once($CFG->libdir . '/filelib.php');
require_once($CFG->libdir . '/resourcelib.php');

@error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);

// Set admin user untuk CLI
$admin = get_admin();
\core\session\manager::set_user($admin);

mtrace("==================================================");
mtrace("📚 UNDUH & PASANG FILE RPS DARI SIAKAD KE MOODLE");
mtrace("==================================================");

$token = "320|Gs2KVb4VtawDCnc2foDC8Up9UXbe7c95NGgu1OKZcfe5622b";
$base_api = "https://siakad.sugenghartono.ac.id/api";
$base_file = "https://siakad.sugenghartono.ac.id/file/rps/";

function api_get($url, $token) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $token,
        'Accept: application/json'
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $result = curl_exec($ch);
    curl_close($ch);
    return json_decode($result, true);
}

function download_file($url, $dest) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    $data = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $content_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);
    
    // Pastikan ini file yang valid (bukan HTML error page)
    $is_valid = ($http == 200 && !empty($data) && strlen($data) > 1000);
    $is_document = (strpos($content_type, 'pdf') !== false || 
                    strpos($content_type, 'word') !== false ||
                    strpos($content_type, 'document') !== false ||
                    strpos($content_type, 'octet') !== false);
    
    if ($is_valid && $is_document) {
        file_put_contents($dest, $data);
        return true;
    }
    return false;
}

// Ambil semua lessons dari API (paginated)
$page = 1;
$all_lessons = [];
while (true) {
    $response = api_get($base_api . "/all-lessons?page=" . $page, $token);
    $items = $response['data'] ?? [];
    if (empty($items)) break;
    $all_lessons = array_merge($all_lessons, $items);
    $page++;
    if ($page > 20) break;
}

// Filter yang punya RPS dan deduplicate berdasarkan file
$seen_files = [];
$with_rps = [];
foreach ($all_lessons as $l) {
    $rps = $l['file_rps'] ?? '';
    if (!empty($rps) && !isset($seen_files[$rps])) {
        $seen_files[$rps] = true;
        $with_rps[] = $l;
    }
}

mtrace("📋 Total mata kuliah dari API: " . count($all_lessons));
mtrace("📄 Unik dengan file RPS: " . count($with_rps));

$fs = get_file_storage();
$tmp_dir = $CFG->dataroot . '/temp/rps_downloads';
if (!is_dir($tmp_dir)) mkdir($tmp_dir, 0777, true);

$downloaded = 0;
$uploaded = 0;
$failed = 0;
$skipped = 0;

foreach ($with_rps as $lesson) {
    $code = trim($lesson['code'] ?? '');
    $name = trim($lesson['name'] ?? '');
    $rps_file = trim($lesson['file_rps'] ?? '');
    if (empty($code) || empty($rps_file)) continue;

    // Cari course di Moodle
    $course = $DB->get_record('course', ['shortname' => $code]);
    if (!$course) {
        // Bersihkan shortname (hapus *, spasi, dll.)
        $clean_code = str_replace(['*', ' '], '', $code);
        $course = $DB->get_record('course', ['shortname' => $clean_code]);
    }
    if (!$course) continue;

    // Cek apakah sudah ada RPS di course ini
    $existing = $DB->get_record_select('resource', "course = ? AND name LIKE ?", [$course->id, "%RPS%"]);
    if ($existing) {
        $skipped++;
        continue;
    }

    mtrace("\n[{$code}] {$name}");

    // Download file
    $ext = pathinfo($rps_file, PATHINFO_EXTENSION);
    $local_file = $tmp_dir . '/' . $rps_file;
    $url = $base_file . $rps_file;

    if (!download_file($url, $local_file)) {
        mtrace("     ⚠️ Gagal download, skip.");
        $failed++;
        continue;
    }

    $filesize = filesize($local_file);
    $size_kb = round($filesize / 1024);
    mtrace("     📥 Downloaded ({$size_kb} KB)");
    $downloaded++;

    // Buat resource module di Moodle
    $clean_filename = "RPS_{$code}.{$ext}";
    
    // Upload file ke draft area
    $usercontext = context_user::instance(2);
    $draftitemid = file_get_unused_draft_itemid();

    $fileinfo = [
        'contextid' => $usercontext->id,
        'component' => 'user',
        'filearea'  => 'draft',
        'itemid'    => $draftitemid,
        'filepath'  => '/',
        'filename'  => $clean_filename,
    ];

    $fs->create_file_from_pathname($fileinfo, $local_file);

    // Buat module resource
    $resource_module_id = $DB->get_field('modules', 'id', ['name' => 'resource']);
    $moduleinfo = new stdClass();
    $moduleinfo->module = $resource_module_id;
    $moduleinfo->modulename = 'resource';
    $moduleinfo->course = $course->id;
    $moduleinfo->section = 0;
    $moduleinfo->visible = 1;
    $moduleinfo->name = "📋 RPS - {$name}";
    $moduleinfo->intro = "<p>Rencana Pembelajaran Semester (RPS) resmi untuk mata kuliah <strong>{$name}</strong> ({$code}).</p><p><em>Dokumen ini diunduh otomatis dari SIAKAD Universitas Sugeng Hartono.</em></p>";
    $moduleinfo->introformat = FORMAT_HTML;
    $moduleinfo->showdescription = 1;
    $moduleinfo->display = RESOURCELIB_DISPLAY_AUTO;
    $moduleinfo->showsize = 1;
    $moduleinfo->showtype = 1;
    $moduleinfo->files = $draftitemid;

    try {
        add_moduleinfo($moduleinfo, $course);
        mtrace("     ✅ RPS terpasang: {$clean_filename}");
        $uploaded++;
    } catch (Exception $e) {
        mtrace("     ❌ Gagal: " . substr($e->getMessage(), 0, 80));
        $failed++;
    }

    // Cleanup
    @unlink($local_file);
}

rebuild_course_cache(0, true);
purge_all_caches();

mtrace("\n==================================================");
mtrace("🎉 PROSES SELESAI!");
mtrace("📊 Rincian:");
mtrace("   • File RPS Berhasil Diunduh    : {$downloaded}");
mtrace("   • File RPS Terpasang di Moodle : {$uploaded}");
mtrace("   • Sudah Ada (Skip)             : {$skipped}");
mtrace("   • Gagal/Tidak Ditemukan        : {$failed}");
mtrace("==================================================");
