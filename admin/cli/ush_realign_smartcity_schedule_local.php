<?php
/**
 * Koreksi jadwal Smart City agar mengikuti awal perkuliahan September 2026.
 *
 * Excel menetapkan Senin 10:30–13:00; kalender akademik menentukan tanggal
 * aktif. Pertemuan dinomori berurutan, bukan mengikuti label W kalender.
 * Tanggal yang belum diterbitkan tidak direkayasa: section P11–P15 disembunyikan.
 *
 * php admin/cli/ush_realign_smartcity_schedule_local.php
 * php admin/cli/ush_realign_smartcity_schedule_local.php --confirm
 */
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/attendance/locallib.php');
require_once($CFG->dirroot . '/mod/attendance/classes/structure.php');

if (strpos($CFG->wwwroot, 'localhost') === false && strpos($CFG->wwwroot, '127.0.0.1') === false) {
    mtrace('Dibatalkan: bukan LMS lokal. wwwroot=' . $CFG->wwwroot);
    exit(1);
}

$confirm = in_array('--confirm', array_slice($argv, 1), true);
$admin = get_admin();
\core\session\manager::set_user($admin);
$course = $DB->get_record('course', ['shortname' => 'SIF1001_20262027Ganjil'], '*', MUST_EXIST);
$tz = new DateTimeZone('Asia/Jakarta');

// Excel: Senin 10:30–13:00 WIB. Kalender akademik yang telah dipublikasikan.
$dates = [
    1 => '2026-09-07 10:30:00',
    2 => '2026-09-14 10:30:00',
    3 => '2026-09-21 10:30:00',
    4 => '2026-10-12 10:30:00',
    5 => '2026-10-19 10:30:00',
    6 => '2026-10-26 10:30:00',
    7 => '2026-11-02 10:30:00',
    8 => '2026-11-09 10:30:00',
    9 => '2026-11-16 10:30:00',
    10 => '2026-11-23 10:30:00',
];
$duration = 150 * MINSECS;

mtrace('=== Koreksi jadwal Smart City ===');
mtrace('Mode: ' . ($confirm ? 'LIVE' : 'DRY-RUN'));
mtrace('Acuan: Excel Senin 10:30–13:00 WIB + kalender akademik mulai September.');
mtrace('');

$changedsections = 0;
foreach ($DB->get_records('course_sections', ['course' => $course->id], 'section ASC') as $section) {
    $number = (int) $section->section;
    if ($number < 1 || $number > 15) {
        continue;
    }
    $section->name = 'Pertemuan ' . $number;
    if (isset($dates[$number])) {
        $timestamp = (new DateTime($dates[$number], $tz))->getTimestamp();
        $section->availability = json_encode([
            'op' => '&',
            'c' => [['type' => 'date', 'd' => '>=', 't' => $timestamp]],
            'showc' => [true],
        ]);
        $section->visible = 1;
        mtrace(sprintf('Section P%d → %s WIB', $number, $dates[$number]));
    } else {
        // Tidak menampilkan tanggal fiktif ketika kalender belum selesai.
        $section->availability = null;
        $section->visible = 0;
        mtrace(sprintf('Section P%d → disembunyikan (menunggu kalender)', $number));
    }
    if (
        $section->name !== $DB->get_field('course_sections', 'name', ['id' => $section->id]) ||
        (string) $section->availability !== (string) $DB->get_field('course_sections', 'availability', ['id' => $section->id]) ||
        (int) $section->visible !== (int) $DB->get_field('course_sections', 'visible', ['id' => $section->id])
    ) {
        $changedsections++;
        if ($confirm) {
            $DB->update_record('course_sections', $section);
        }
    }
}

$attendance = $DB->get_record('attendance', ['course' => $course->id]);
if (!$attendance) {
    mtrace('Aktivitas Attendance tidak ditemukan.');
    exit(1);
}
$cm = get_coursemodule_from_instance('attendance', $attendance->id, $course->id, false, MUST_EXIST);
$context = context_module::instance($cm->id);
$structure = new mod_attendance_structure($attendance, $cm, $course, $context);
$sessions = array_values($DB->get_records('attendance_sessions', ['attendanceid' => $attendance->id], 'sessdate ASC'));

$changedattendance = 0;
$deletedattendance = 0;
foreach ($sessions as $index => $session) {
    $meeting = $index + 1;
    if (!isset($dates[$meeting])) {
        $logs = $DB->count_records('attendance_log', ['sessionid' => $session->id]);
        if ($logs > 0) {
            mtrace('BERHENTI: sesi ke-' . $meeting . ' memiliki ' . $logs . ' presensi dan tidak boleh dihapus.');
            exit(1);
        }
        mtrace('Hapus sesi lama tanpa tanggal kalender: id=' . $session->id);
        if ($confirm) {
            $structure->delete_sessions([$session->id]);
        }
        $deletedattendance++;
        continue;
    }
    $session->sessdate = (new DateTime($dates[$meeting], $tz))->getTimestamp();
    $session->duration = $duration;
    $session->description = '<p>Pertemuan ' . $meeting . ' — Smart City.</p>';
    $session->descriptionformat = FORMAT_HTML;
    $session->timemodified = time();
    $session->calendarevent = 0;
    mtrace(sprintf('Attendance P%d → %s WIB', $meeting, $dates[$meeting]));
    if ($confirm) {
        $DB->update_record('attendance_sessions', $session);
    }
    $changedattendance++;
}

for ($meeting = count($sessions) + 1; $meeting <= count($dates); $meeting++) {
    $date = $dates[$meeting];
    mtrace(sprintf('Tambah Attendance P%d → %s WIB', $meeting, $date));
    if ($confirm) {
        $session = (object) [
            'sessdate' => (new DateTime($date, $tz))->getTimestamp(),
            'duration' => $duration,
            'descriptionitemid' => 0,
            'description' => '<p>Pertemuan ' . $meeting . ' — Smart City.</p>',
            'descriptionformat' => FORMAT_HTML,
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
        $structure->add_session($session);
    }
    $changedattendance++;
}

if ($confirm) {
    rebuild_course_cache($course->id, true);
    purge_all_caches();
}

mtrace('');
mtrace('=== RINGKASAN ===');
mtrace('Section diubah: ' . $changedsections);
mtrace('Sesi Attendance diselaraskan: ' . $changedattendance);
mtrace('Sesi Attendance dihapus: ' . $deletedattendance);
if (!$confirm) {
    mtrace('DRY-RUN. Ulangi dengan --confirm untuk menulis.');
}
