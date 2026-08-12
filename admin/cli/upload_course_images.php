<?php
/**
 * Skrip untuk memasang gambar thumbnail/cover & banner ke Mata Kuliah di Moodle.
 * Gambar akan muncul di daftar course (My Courses) dan di halaman course.
 */
define('CLI_SCRIPT', true);

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->libdir . '/filelib.php');

mtrace("==================================================");
mtrace("🖼️ MEMASANG GAMBAR THUMBNAIL & BANNER KE MATA KULIAH");
mtrace("==================================================");

// Mapping: shortname course => nama file gambar
$image_map = [
    // Sistem Informasi
    'SIF105' => 'course_computational_thinking',
    'SIF104' => 'course_computational_thinking',
    'SIF101' => 'course_computational_thinking',
    'SIF102' => 'course_computational_thinking',
    'SIF103' => 'course_computational_thinking',
    // Web Programming
    'SIF201' => 'course_web_programming',
    'IDM0612' => 'course_web_programming',
    'IDM0622' => 'course_web_programming',
    // Bisnis Digital
    'IDM0517' => 'course_bisnis_digital',
    'IDM0518' => 'course_bisnis_digital',
    'IDM0519' => 'course_bisnis_digital',
    'IDM0520' => 'course_bisnis_digital',
    'IDM0527' => 'course_bisnis_digital',
    'IDM0820' => 'course_bisnis_digital',
    // Gizi
    'IDM0310' => 'course_ilmu_gizi',
    'IDM0313' => 'course_ilmu_gizi',
    'IDM0314' => 'course_ilmu_gizi',
    'IDM0318' => 'course_ilmu_gizi',
    'IDM0319' => 'course_ilmu_gizi',
    // Hukum
    'IDM0708' => 'course_hukum',
    'IDM0711' => 'course_hukum',
    'IDM0713' => 'course_hukum',
    'IDM0715' => 'course_hukum',
    'IDM0716' => 'course_hukum',
    'IDM0717' => 'course_hukum',
    // Manajemen
    'IDM0509' => 'course_manajemen_strategis',
    'IDM0510' => 'course_manajemen_strategis',
    'IDM0511' => 'course_manajemen_strategis',
    'IDM0512' => 'course_manajemen_strategis',
    'IDM0513' => 'course_manajemen_strategis',
];

// Cari file gambar yang tersedia
$image_dir = __DIR__;
$available_images = [];
foreach (glob($image_dir . '/course_*.jpg') as $imgpath) {
    $basename = pathinfo($imgpath, PATHINFO_FILENAME);
    // Strip timestamp suffix: course_xxx_1785... -> course_xxx
    $key = preg_replace('/_\d{13}$/', '', $basename);
    $available_images[$key] = $imgpath;
}

mtrace("📁 Gambar tersedia: " . count($available_images));
foreach ($available_images as $k => $v) {
    mtrace("   • {$k} -> " . basename($v));
}

$fs = get_file_storage();
$applied = 0;

// 1. Pasang gambar ke course yang sudah di-mapping
foreach ($image_map as $shortname => $image_key) {
    $course = $DB->get_record('course', ['shortname' => $shortname]);
    if (!$course) continue;
    if (!isset($available_images[$image_key])) continue;

    $imgpath = $available_images[$image_key];
    $context = context_course::instance($course->id);

    // Hapus gambar lama jika ada
    $fs->delete_area_files($context->id, 'course', 'overviewfiles');

    // Upload gambar baru sebagai course overview image
    $fileinfo = [
        'contextid' => $context->id,
        'component' => 'course',
        'filearea'  => 'overviewfiles',
        'itemid'    => 0,
        'filepath'  => '/',
        'filename'  => $image_key . '.jpg',
    ];

    $fs->create_file_from_pathname($fileinfo, $imgpath);
    mtrace("     ✅ [{$shortname}] {$course->fullname} -> {$image_key}.jpg");
    $applied++;
}

// 2. Juga pasang gambar ke SEMUA course yang belum punya gambar (round-robin)
$all_courses = $DB->get_records('course', ['visible' => 1], 'id ASC');
$default_images = array_values($available_images);
$img_count = count($default_images);

if ($img_count > 0) {
    $idx = 0;
    foreach ($all_courses as $course) {
        if ($course->id <= 1) continue; // skip site course
        if (isset($image_map[$course->shortname])) continue; // sudah dipasang di atas

        $context = context_course::instance($course->id);
        $existing = $fs->get_area_files($context->id, 'course', 'overviewfiles', 0, 'id', false);

        if (empty($existing)) {
            $imgpath = $default_images[$idx % $img_count];
            $image_key = preg_replace('/_\d{13}$/', '', pathinfo($imgpath, PATHINFO_FILENAME));

            $fileinfo = [
                'contextid' => $context->id,
                'component' => 'course',
                'filearea'  => 'overviewfiles',
                'itemid'    => 0,
                'filepath'  => '/',
                'filename'  => $image_key . '.jpg',
            ];

            $fs->create_file_from_pathname($fileinfo, $imgpath);
            $applied++;
            $idx++;
        }
    }
}

// 3. Pasang banner di Section 0 (General) setiap course yang punya gambar
mtrace("\n🎨 Memasang banner di halaman course (Section 0)...");
foreach ($image_map as $shortname => $image_key) {
    $course = $DB->get_record('course', ['shortname' => $shortname]);
    if (!$course) continue;
    if (!isset($available_images[$image_key])) continue;

    $imgpath = $available_images[$image_key];
    $context = context_course::instance($course->id);
    $section = $DB->get_record('course_sections', ['course' => $course->id, 'section' => 0]);
    if (!$section) continue;

    // Upload gambar ke section summary files
    $fileinfo = [
        'contextid' => $context->id,
        'component' => 'course',
        'filearea'  => 'section',
        'itemid'    => $section->id,
        'filepath'  => '/',
        'filename'  => 'banner_' . $image_key . '.jpg',
    ];

    $existing_banner = $fs->get_file($context->id, 'course', 'section', $section->id, '/', 'banner_' . $image_key . '.jpg');
    if (!$existing_banner) {
        $stored = $fs->create_file_from_pathname($fileinfo, $imgpath);
        $img_url = moodle_url::make_pluginfile_url($context->id, 'course', 'section', $section->id, '/', 'banner_' . $image_key . '.jpg');

        // Tambahkan banner HTML ke summary section 0
        $banner_html = '<div style="margin-bottom:20px;"><img src="' . $img_url . '" alt="Course Banner" style="width:100%; max-height:250px; object-fit:cover; border-radius:12px; box-shadow: 0 4px 15px rgba(0,0,0,0.15);"></div>';

        if (strpos($section->summary, 'Course Banner') === false) {
            $section->summary = $banner_html . ($section->summary ?? '');
            $section->summaryformat = FORMAT_HTML;
            $DB->update_record('course_sections', $section);
            mtrace("     🎨 [{$shortname}] Banner dipasang di halaman course.");
        }
    }
}

rebuild_course_cache(0, true);
purge_all_caches();

mtrace("\n==================================================");
mtrace("🎉 TOTAL {$applied} GAMBAR BERHASIL DIPASANG KE MATA KULIAH!");
mtrace("==================================================");
