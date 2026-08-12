<?php
define('CLI_SCRIPT', true);
@error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);
require(__DIR__ . '/../../config.php');

$NEW_SEMESTER_LABEL = '2025/2026 - Ganjil';
$SHORTNAME_SUFFIX   = '_20252026Ganjil';

mtrace("==================================================");
mtrace("🔒 PASANG RESTRIKSI TANGGAL PERTEMUAN (DATE RESTRICTIONS)");
mtrace("==================================================\n");

// Enable availability feature in Moodle config if not enabled
set_config('enableavailability', 1);

// Course start date: 1 September 2025 (Senin)
$start_date = mktime(0, 0, 0, 9, 1, 2025);

$courses = $DB->get_records_sql("
    SELECT id, fullname, shortname
    FROM {course}
    WHERE shortname LIKE ?
", ['%' . $SHORTNAME_SUFFIX]);

mtrace("📋 Mengatur jadwal rilis pertemuan otomatis untuk " . count($courses) . " kelas...\n");

$count_updated = 0;

foreach ($courses as $c) {
    // Section 0: RPS & Pengumuman (Selalu Terbuka)

    for ($sec = 1; $sec <= 16; $sec++) {
        $section = $DB->get_record('course_sections', ['course' => $c->id, 'section' => $sec]);
        if (!$section) continue;

        // Tanggal rilis pertemuan: startdate + ((sec - 1) * 7 hari)
        // Pertemuan 1: 1 Sept 2025
        // Pertemuan 2: 8 Sept 2025
        // Pertemuan 3: 15 Sept 2025 ... dst.
        $release_time = $start_date + (($sec - 1) * 7 * 86400);

        // JSON restriction structure Moodle
        $availability = json_encode([
            'op' => '&',
            'c' => [
                [
                    'type' => 'date',
                    'd' => '>=',
                    't' => $release_time
                ]
            ],
            'showc' => [false] // false = disembunyikan/greyed out jika belum waktunya
        ]);

        $DB->set_field('course_sections', 'availability', $availability, ['id' => $section->id]);
        $count_updated++;
    }
}

rebuild_course_cache(0, true);
purge_all_caches();

mtrace("==================================================");
mtrace("🎉 SELESAI!");
mtrace("   ✅ Total Section Pertemuan Diberi Jadwal Otomatis: $count_updated section");
mtrace("   📅 Pertemuan 1 : Terbuka mulai 1 Sept 2025");
mtrace("   📅 Pertemuan 2 : Terbuka otomatis 8 Sept 2025");
mtrace("   📅 Pertemuan 3 : Terbuka otomatis 15 Sept 2025");
mtrace("   ... dst secara otomatis setiap minggu!");
mtrace("==================================================");
