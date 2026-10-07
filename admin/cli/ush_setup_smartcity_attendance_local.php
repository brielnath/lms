<?php
/**
 * Kelas uji Smart City: buat Attendance dan sesi presensi dosen.
 *
 * Jadwal: Excel Jadwal Kuliah + kalender akademik SIAKAD.
 * Pertemuan 4, 6, dan 8 tidak dibuat karena tidak memiliki tanggal Senin
 * pada kalender perkuliahan. Mahasiswa tidak dapat mengisi sendiri.
 *
 * php admin/cli/ush_setup_smartcity_attendance_local.php
 * php admin/cli/ush_setup_smartcity_attendance_local.php --confirm
 */
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/course/modlib.php');
require_once($CFG->dirroot . '/mod/attendance/lib.php');
require_once($CFG->dirroot . '/mod/attendance/locallib.php');
require_once($CFG->dirroot . '/mod/attendance/classes/structure.php');

$admin = get_admin();
\core\session\manager::set_user($admin);

if (strpos($CFG->wwwroot, 'localhost') === false && strpos($CFG->wwwroot, '127.0.0.1') === false) {
    mtrace('Dibatalkan: bukan LMS lokal. wwwroot=' . $CFG->wwwroot);
    exit(1);
}

$confirm = in_array('--confirm', array_slice($argv, 1), true);
$course = $DB->get_record('course', ['shortname' => 'SIF1001_20262027Ganjil'], '*', MUST_EXIST);
$attendance_module = $DB->get_record('modules', ['name' => 'attendance'], '*', MUST_EXIST);
$tz = new DateTimeZone('Asia/Jakarta');

// Smart City: Senin 10:30–13:00 (150 menit), sesuai Excel.
$schedule = [
    1 => '2026-08-10 10:30:00',
    2 => '2026-08-17 10:30:00',
    3 => '2026-08-24 10:30:00',
    5 => '2026-09-07 10:30:00',
    7 => '2026-09-21 10:30:00',
    9 => '2026-10-12 10:30:00',
    10 => '2026-10-19 10:30:00',
    11 => '2026-10-26 10:30:00',
    12 => '2026-11-02 10:30:00',
    13 => '2026-11-09 10:30:00',
    14 => '2026-11-16 10:30:00',
    15 => '2026-11-23 10:30:00',
];
$duration = 150 * MINSECS;

mtrace('=== Attendance Smart City ===');
mtrace('Kelas: ' . $course->fullname);
mtrace('Mode: ' . ($confirm ? 'LIVE' : 'DRY-RUN'));
mtrace('Jadwal Excel: Senin 10:30–13:00 WIB');
mtrace('Presensi: dicatat dosen (mahasiswa tidak check-in sendiri)');
mtrace('');

$cm = $DB->get_record_sql(
    "SELECT cm.*
       FROM {course_modules} cm
      WHERE cm.course = :courseid
        AND cm.module = :moduleid
        AND cm.deletioninprogress = 0",
    ['courseid' => $course->id, 'moduleid' => $attendance_module->id]
);

$createdactivity = false;
if (!$cm) {
    mtrace('Akan membuat aktivitas Attendance: Presensi Smart City');
    if ($confirm) {
        $moduleinfo = (object) [
            'module' => $attendance_module->id,
            'modulename' => 'attendance',
            'course' => $course->id,
            'section' => 0,
            'visible' => 1,
            'name' => 'Presensi Smart City',
            'intro' => '<p>Presensi pertemuan Smart City sesuai jadwal kuliah.</p>',
            'introformat' => FORMAT_HTML,
            'showdescription' => 1,
            'grade' => 100,
        ];
        add_moduleinfo($moduleinfo, $course);
        $cm = $DB->get_record_sql(
            "SELECT cm.*
               FROM {course_modules} cm
              WHERE cm.course = :courseid
                AND cm.module = :moduleid
                AND cm.deletioninprogress = 0",
            ['courseid' => $course->id, 'moduleid' => $attendance_module->id],
            MUST_EXIST
        );
        $createdactivity = true;
        mtrace('  dibuat cmid=' . $cm->id);
    }
}

$newsessions = 0;
$existing = 0;
if ($cm) {
    $instance = $DB->get_record('attendance', ['id' => $cm->instance], '*', MUST_EXIST);
    $context = context_module::instance($cm->id);
    $attendance = new mod_attendance_structure($instance, $cm, $course, $context);

    foreach ($schedule as $meeting => $date) {
        $start = (new DateTime($date, $tz))->getTimestamp();
        $found = $DB->record_exists_select(
            'attendance_sessions',
            'attendanceid = :attendanceid AND sessdate = :sessdate',
            ['attendanceid' => $instance->id, 'sessdate' => $start]
        );
        if ($found) {
            $existing++;
            mtrace(sprintf('Sudah ada: Pertemuan %d — %s WIB', $meeting, $date));
            continue;
        }
        mtrace(sprintf('Akan buat: Pertemuan %d — %s WIB (150 menit)', $meeting, $date));
        if ($confirm) {
            $session = (object) [
                'sessdate' => $start,
                'duration' => $duration,
                'descriptionitemid' => 0,
                'description' => '<p>Pertemuan ' . $meeting . ' — Smart City.</p>',
                'descriptionformat' => FORMAT_HTML,
                // CLI lokal tidak memiliki sesi pengguna untuk membuat event kalender.
                // Sesi Attendance tetap tampil dan dapat diisi dosen dari aktivitas.
                'calendarevent' => 0,
                'timemodified' => time(),
                'studentscanmark' => 0,
                'allowupdatestatus' => 0,
                'studentsearlyopentime' => 0,
                'autoassignstatus' => 0,
                'studentpassword' => '',
                'subnet' => '',
                'automark' => 0,
                'absenteereport' => 1,
                'includeqrcode' => 0,
                'statusset' => 0,
                'groupid' => 0,
                'automarkcmid' => 0,
            ];
            $attendance->add_session($session);
        }
        $newsessions++;
    }
} else {
    foreach ($schedule as $meeting => $date) {
        mtrace(sprintf('Akan buat: Pertemuan %d — %s WIB (150 menit)', $meeting, $date));
        $newsessions++;
    }
}

if ($confirm) {
    rebuild_course_cache($course->id, true);
    purge_all_caches();
}

mtrace('');
mtrace('=== RINGKASAN ===');
mtrace(($createdactivity ? 'Aktivitas baru' : 'Aktivitas sudah ada') . ': ' . ($cm ? 'ya' : 'akan dibuat'));
mtrace('Sesi baru: ' . $newsessions);
mtrace('Sesi sudah ada: ' . $existing);
mtrace('Tidak ada sesi: Pertemuan 4, 6, 8 (tidak ada tanggal Senin perkuliahan).');
if (!$confirm) {
    mtrace('DRY-RUN. Ulangi dengan --confirm untuk menulis.');
}
