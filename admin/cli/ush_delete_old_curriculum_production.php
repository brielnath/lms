<?php
/**
 * Hapus kelas kurikulum lama (kode di luar skema resmi IDM/IUM/IFM/IDE/GDM).
 *
 * PERINGATAN: penghapusan course di Moodle ikut menghapus aktivitas, submission,
 * dan NILAI mahasiswa di kelas tersebut. Backup database + moodledata dulu.
 *
 * Default DRY-RUN. Menghapus hanya jika kedua flag diberikan:
 *   --confirm --yes-delete-permanently
 *
 * Contoh:
 *   php admin/cli/ush_delete_old_curriculum_production.php
 *   php admin/cli/ush_delete_old_curriculum_production.php --limit=20 --confirm --yes-delete-permanently
 *
 * Opsi:
 *   --limit=N     Proses maksimal N kelas (batch; default semua)
 *   --show=N      Jumlah baris detail di dry-run (default 40)
 */
define('CLI_SCRIPT', true);

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once(__DIR__ . '/ush_course_owner.php');
require_once(__DIR__ . '/ush_force_delete_course.php');

$CONFIRM = false;
$YES = false;
$LIMIT = 0;
$SHOW = 40;

foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--confirm') {
        $CONFIRM = true;
    } else if ($arg === '--yes-delete-permanently') {
        $YES = true;
    } else if (str_starts_with($arg, '--limit=')) {
        $LIMIT = max(0, (int) substr($arg, 8));
    } else if (str_starts_with($arg, '--show=')) {
        $SHOW = max(0, (int) substr($arg, 7));
    } else {
        mtrace('Argumen tidak dikenal: ' . $arg);
        exit(1);
    }
}

$LIVE = $CONFIRM && $YES;

mtrace('=== Hapus kurikulum lama ===');
mtrace('Site : ' . $CFG->wwwroot);
mtrace('Mode : ' . ($LIVE ? 'LIVE (menghapus permanen)' : 'DRY-RUN (tidak menghapus)'));
if ($CONFIRM && !$YES) {
    mtrace('Catatan: --confirm saja belum cukup. Tambahkan --yes-delete-permanently.');
}
mtrace('');

$all = $DB->get_records_sql("SELECT id, shortname, fullname, category FROM {course} WHERE id > 1 ORDER BY shortname");
$targets = [];
$keep = 0;
foreach ($all as $course) {
    if (ush_is_official_scheme_code($course->shortname)) {
        $keep++;
        continue;
    }
    $targets[] = $course;
}

mtrace('Total kelas (selain site) : ' . count($all));
mtrace('Skema resmi (dipertahankan) : ' . $keep);
mtrace('Kurikulum lama (target)     : ' . count($targets));
mtrace('');

if (!$targets) {
    mtrace('Tidak ada kelas kurikulum lama. Selesai.');
    exit(0);
}

// Ukur dampak: peserta dan nilai yang ikut terhapus.
$totalenrol = 0;
$totalgrades = 0;
$withgrades = 0;
$details = [];

foreach ($targets as $course) {
    $enrolled = $DB->count_records_sql(
        "SELECT COUNT(DISTINCT ue.userid)
           FROM {user_enrolments} ue
           JOIN {enrol} e ON e.id = ue.enrolid
          WHERE e.courseid = ?",
        [$course->id]
    );
    $grades = $DB->count_records_sql(
        "SELECT COUNT(*)
           FROM {grade_grades} gg
           JOIN {grade_items} gi ON gi.id = gg.itemid
          WHERE gi.courseid = ? AND gg.finalgrade IS NOT NULL",
        [$course->id]
    );
    $totalenrol += $enrolled;
    $totalgrades += $grades;
    if ($grades > 0) {
        $withgrades++;
    }
    $details[] = [
        'course' => $course,
        'enrolled' => $enrolled,
        'grades' => $grades,
    ];
}

mtrace('DAMPAK bila dihapus:');
mtrace('  Total pendaftaran peserta : ' . $totalenrol);
mtrace('  Total nilai tersimpan     : ' . $totalgrades);
mtrace('  Kelas yang punya nilai    : ' . $withgrades);
mtrace('');

// Tampilkan yang paling berdampak lebih dulu supaya mudah ditinjau.
usort($details, static function ($a, $b) {
    return $b['grades'] <=> $a['grades'];
});

mtrace('Contoh target (urut dari yang paling banyak nilainya):');
$shown = 0;
foreach ($details as $row) {
    if ($shown++ >= $SHOW) {
        mtrace('  ... (+' . (count($details) - $SHOW) . ' kelas lain)');
        break;
    }
    mtrace(sprintf(
        '  %-28s peserta %-5d nilai %-6d | %s',
        $row['course']->shortname,
        $row['enrolled'],
        $row['grades'],
        mb_substr($row['course']->fullname, 0, 45)
    ));
}
mtrace('');

if (!$LIVE) {
    mtrace('DRY-RUN selesai. Tidak ada yang dihapus.');
    mtrace('Backup dulu (mysqldump + moodledata), lalu jalankan:');
    mtrace('  php admin/cli/ush_delete_old_curriculum_production.php --limit=20 --confirm --yes-delete-permanently');
    exit(0);
}

// Urut ulang: hapus yang paling sedikit dampaknya dulu supaya batch awal aman.
usort($details, static function ($a, $b) {
    return $a['grades'] <=> $b['grades'];
});

$deleted = 0;
$failed = 0;
$processed = 0;

foreach ($details as $row) {
    if ($LIMIT > 0 && $processed >= $LIMIT) {
        break;
    }
    $processed++;
    $course = $row['course'];
    try {
        ush_force_delete_course($course);
        $deleted++;
        mtrace("  hapus {$course->shortname} (nilai {$row['grades']})");
    } catch (Throwable $e) {
        $failed++;
        mtrace("  GAGAL {$course->shortname}: " . $e->getMessage());
    }
}

fix_course_sortorder();
rebuild_course_cache(0, true);

$sisa = 0;
foreach ($DB->get_records_sql("SELECT id, shortname FROM {course} WHERE id > 1") as $c) {
    if (!ush_is_official_scheme_code($c->shortname)) {
        $sisa++;
    }
}

mtrace('');
mtrace('=== RINGKASAN ===');
mtrace('  Terhapus      : ' . $deleted);
mtrace('  Gagal         : ' . $failed);
mtrace('  Lama tersisa  : ' . $sisa);
if ($sisa > 0) {
    mtrace('Jalankan lagi untuk batch berikutnya.');
}
