<?php
/**
 * Tarik jadwal kuliah ke kelas LMS production 2026/2027 Ganjil.
 * Sumber: JSON slot dari laptop (schedule_lesson SIAKAD).
 * Default DRY-RUN; tulis dengan --confirm.
 *
 *   php admin/cli/ush_apply_jadwal_sequential_production.php --from-file=jadwal_slots_20262027Ganjil.json
 *   php admin/cli/ush_apply_jadwal_sequential_production.php --from-file=jadwal_slots_20262027Ganjil.json --confirm
 */
define('CLI_SCRIPT', true);
$ushstartcwd = getcwd();
require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/course/modlib.php');
require_once($CFG->dirroot . '/mod/attendance/lib.php');
require_once($CFG->dirroot . '/mod/attendance/locallib.php');
require_once($CFG->dirroot . '/mod/attendance/classes/structure.php');

if (strpos($CFG->wwwroot, 'lms.ush.ac.id') === false) {
    mtrace('Dibatalkan: bukan LMS production. wwwroot=' . $CFG->wwwroot);
    exit(1);
}

$confirm = false;
$fromfile = '';
foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--confirm') {
        $confirm = true;
    } else if (str_starts_with($arg, '--from-file=')) {
        $fromfile = substr($arg, 12);
    }
}

if ($fromfile === '') {
    mtrace('Wajib --from-file=jadwal_slots_20262027Ganjil.json');
    exit(1);
}
if (!is_readable($fromfile) && $ushstartcwd && is_readable($ushstartcwd . '/' . $fromfile)) {
    $fromfile = $ushstartcwd . '/' . $fromfile;
}
if (!is_readable($fromfile) && is_readable($CFG->dirroot . '/' . basename($fromfile))) {
    $fromfile = $CFG->dirroot . '/' . basename($fromfile);
}
if (!is_readable($fromfile)) {
    mtrace('File jadwal tidak terbaca: ' . $fromfile);
    exit(1);
}

$payload = json_decode((string) file_get_contents($fromfile), true);
if (!is_array($payload) || empty($payload['slots']) || !is_array($payload['slots'])) {
    mtrace('Isi file jadwal tidak dikenali.');
    exit(1);
}

$admin = get_admin();
\core\session\manager::set_user($admin);

$SUFFIX = (string) ($payload['semester'] ?? '20262027Ganjil');
$SKIPRE = '/(kkn|skripsi|kerja praktik|praktik kerja|kuliah kerja|seminar proposal)/i';
$TZ = new DateTimeZone('Asia/Jakarta');
$FIRST = (string) ($payload['first'] ?? '2026-09-07');
$LAST = (string) ($payload['last'] ?? '2026-11-23');
$UTS_FROM = (string) ($payload['uts_from'] ?? '2026-09-26');
$UTS_TO = (string) ($payload['uts_to'] ?? '2026-10-06');
$MAXMEETINGS = (int) ($payload['max_meetings'] ?? 10);
$slotsbycode = $payload['slots'];

$PRODI = [
    24 => 'SBD',
    25 => 'SGZ',
    26 => 'SIF',
    31 => 'MBI',
    32 => 'HKM',
    33 => 'TPN',
    34 => 'BKI',
    35 => 'PAR',
    36 => 'ABD',
];
$PRODIID = array_flip($PRODI);

function ush_parse_short_prod(string $short, string $suffix): array {
    $base = strtoupper((string) preg_replace('/_' . preg_quote($suffix, '/') . '$/i', '', $short));
    $a2 = false;
    if (preg_match('/^(.+)A2$/', $base, $m)) {
        $a2 = true;
        $base = $m[1];
    }
    $known = 'SIF|SBD|SGZ|HKM|MBI|TPN|BKI|PAR|ABD|MKU|FITH|FTHB|S2SBD';
    if (preg_match('/^(.+)_(' . $known . ')(\d{2})$/', $base, $m)) {
        return ['code' => $m[1], 'prodi' => $m[2], 'a2' => $a2];
    }
    if (preg_match('/^(.+)_(' . $known . ')$/', $base, $m)) {
        return ['code' => $m[1], 'prodi' => $m[2], 'a2' => $a2];
    }
    return ['code' => $base, 'prodi' => '', 'a2' => $a2];
}

function ush_sequential_dates_prod(int $dow, string $time, DateTimeZone $tz, string $first, string $last, string $utsfrom, string $utsto, int $max): array {
    $cursor = new DateTime($first . ' 12:00:00', $tz);
    while ((int) $cursor->format('N') !== $dow) {
        $cursor->modify('+1 day');
    }
    $limit = new DateTime($last . ' 23:59:59', $tz);
    $uts1 = new DateTime($utsfrom . ' 00:00:00', $tz);
    $uts2 = new DateTime($utsto . ' 23:59:59', $tz);
    $dates = [];
    while (count($dates) < $max && $cursor <= $limit) {
        $day = $cursor->format('Y-m-d');
        $dt = new DateTime($day . ' ' . $time, $tz);
        if ($dt < $uts1 || $dt > $uts2) {
            $dates[count($dates) + 1] = $dt->format('Y-m-d H:i:s');
        }
        $cursor->modify('+7 days');
    }
    return $dates;
}

function ush_pick_slot_prod(array $slots): ?array {
    if (!$slots) {
        return null;
    }
    usort($slots, static function (array $a, array $b): int {
        $cmp = ((int) $b['jml']) <=> ((int) $a['jml']);
        if ($cmp !== 0) {
            return $cmp;
        }
        $ab = (stripos($a['kelas'], 'reguler b') !== false) ? 1 : 0;
        $bb = (stripos($b['kelas'], 'reguler b') !== false) ? 1 : 0;
        if ($ab !== $bb) {
            return $ab <=> $bb;
        }
        $cmp = ((int) $a['dow']) <=> ((int) $b['dow']);
        if ($cmp !== 0) {
            return $cmp;
        }
        return strcmp((string) $a['jam_mulai'], (string) $b['jam_mulai']);
    });
    return $slots[0];
}

function ush_attendance_session_object_prod(int $ts, int $duration, int $meeting, string $label): stdClass {
    return (object) [
        'sessdate' => $ts,
        'duration' => $duration,
        'descriptionitemid' => 0,
        'description' => '<p>Pertemuan ' . $meeting . ' — ' . s($label) . '.</p>',
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
}

$courses = $DB->get_records_sql(
    "SELECT id, shortname, fullname FROM {course}
      WHERE id > 1 AND " . $DB->sql_like('shortname', ':p') . "
      ORDER BY shortname",
    ['p' => '%' . $SUFFIX]
);
$attendance_module = $DB->get_record('modules', ['name' => 'attendance'], '*', MUST_EXIST);
set_config('enableavailability', 1);

mtrace('=== Jadwal sequential production 2026/2027 Ganjil ===');
mtrace('Site: ' . $CFG->wwwroot);
mtrace('Mode: ' . ($confirm ? 'LIVE' : 'DRY-RUN'));
mtrace('Sumber: ' . $fromfile . ' (dibuat ' . ($payload['generated'] ?? '?') . ')');
mtrace('Pola: mulai ' . $FIRST . ', loncat UTS ' . $UTS_FROM . '–' . $UTS_TO . ', batas ' . $LAST);
mtrace('Kelas LMS: ' . count($courses));
mtrace('');

$matched = 0;
$skipped = 0;
$unmatched = [];
$multiday = 0;
$sectionchanged = 0;
$attcreated = 0;
$attsynced = 0;
$attdeleted = 0;
$shown = 0;
$errors = [];

foreach ($courses as $course) {
    if (preg_match($SKIPRE, $course->fullname . ' ' . $course->shortname)) {
        $skipped++;
        continue;
    }
    $parsed = ush_parse_short_prod($course->shortname, $SUFFIX);
    $code = $parsed['code'];
    $cands = $slotsbycode[$code] ?? [];
    if ($parsed['a2']) {
        $cands = array_values(array_filter($cands, static function (array $s): bool {
            return stripos($s['kelas'], 'A2') !== false;
        }));
    } else {
        $cands = array_values(array_filter($cands, static function (array $s): bool {
            return stripos($s['kelas'], 'A2') === false;
        }));
    }
    if ($parsed['prodi'] !== '' && isset($PRODIID[$parsed['prodi']])) {
        $wantprodi = (int) $PRODIID[$parsed['prodi']];
        $prodicands = array_values(array_filter($cands, static function (array $s) use ($wantprodi): bool {
            return (int) $s['id_prodi'] === $wantprodi;
        }));
        if ($prodicands) {
            $cands = $prodicands;
        }
    }
    $slot = ush_pick_slot_prod($cands);
    if (!$slot) {
        $unmatched[] = $course->shortname . ' | ' . $course->fullname;
        continue;
    }
    $matched++;
    if (!empty($slot['multiday'])) {
        $multiday++;
    }

    $time = substr((string) $slot['jam_mulai'], 0, 5) . ':00';
    $dates = ush_sequential_dates_prod(
        (int) $slot['dow'],
        $time,
        $TZ,
        $FIRST,
        $LAST,
        $UTS_FROM,
        $UTS_TO,
        $MAXMEETINGS
    );
    $label = $slot['nama'] !== '' ? $slot['nama'] : $course->fullname;
    $line = sprintf(
        '%s | %s %s-%s | kelas %s | mhs=%d | P1-%d',
        $course->shortname,
        $slot['hari'],
        substr((string) $slot['jam_mulai'], 0, 5),
        substr((string) $slot['jam_selesai'], 0, 5),
        $slot['kelas'] !== '' ? $slot['kelas'] : '-',
        $slot['jml'],
        count($dates)
    );
    if ($shown < 20 || strcasecmp($course->shortname, 'SIF1001_20262027Ganjil') === 0) {
        mtrace($line);
        $bits = [];
        foreach ($dates as $p => $dt) {
            $bits[] = 'P' . $p . '=' . substr($dt, 0, 16);
        }
        mtrace('  ' . implode('  ', $bits));
        $shown++;
    }

    $sections = $DB->get_records('course_sections', ['course' => $course->id], 'section ASC');
    foreach ($sections as $section) {
        $number = (int) $section->section;
        if ($number < 1 || $number > 15) {
            continue;
        }
        $name = trim((string) $section->name);
        if ($name !== '' && preg_match('/(uts|uas|perbaikan)/i', $name)) {
            continue;
        }
        $section->name = 'Pertemuan ' . $number;
        if (isset($dates[$number])) {
            $timestamp = (new DateTime($dates[$number], $TZ))->getTimestamp();
            $section->availability = json_encode([
                'op' => '&',
                'c' => [['type' => 'date', 'd' => '>=', 't' => $timestamp]],
                'showc' => [true],
            ]);
            $section->visible = 1;
        } else {
            $section->availability = null;
            $section->visible = 0;
        }
        $old = $DB->get_record('course_sections', ['id' => $section->id], 'name, availability, visible');
        if (
            (string) $section->name !== (string) $old->name ||
            (string) $section->availability !== (string) $old->availability ||
            (int) $section->visible !== (int) $old->visible
        ) {
            $sectionchanged++;
            if ($confirm) {
                $DB->update_record('course_sections', $section);
            }
        }
    }

    $cm = $DB->get_record_sql(
        "SELECT cm.*
           FROM {course_modules} cm
          WHERE cm.course = :courseid
            AND cm.module = :moduleid
            AND cm.deletioninprogress = 0",
        ['courseid' => $course->id, 'moduleid' => $attendance_module->id]
    );

    try {
        if (!$cm) {
            $attcreated++;
            if ($confirm) {
                $moduleinfo = (object) [
                    'module' => $attendance_module->id,
                    'modulename' => 'attendance',
                    'course' => $course->id,
                    'section' => 0,
                    'visible' => 1,
                    'name' => 'Presensi ' . $label,
                    'intro' => '<p>Presensi pertemuan sesuai jadwal kuliah SIAKAD.</p>',
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
            }
        }

        if ($cm || !$confirm) {
            if ($cm) {
                $instance = $DB->get_record('attendance', ['id' => $cm->instance], '*', MUST_EXIST);
                $context = context_module::instance($cm->id);
                $structure = new mod_attendance_structure($instance, $cm, $course, $context);
                $sessions = array_values($DB->get_records('attendance_sessions', ['attendanceid' => $instance->id], 'sessdate ASC'));
            } else {
                $structure = null;
                $sessions = [];
            }

            foreach ($sessions as $index => $session) {
                $meeting = $index + 1;
                if (!isset($dates[$meeting])) {
                    $logs = $cm ? $DB->count_records('attendance_log', ['sessionid' => $session->id]) : 0;
                    if ($logs > 0) {
                        throw new RuntimeException(
                            'Sesi ke-' . $meeting . ' punya ' . $logs . ' presensi; tidak dihapus.'
                        );
                    }
                    $attdeleted++;
                    if ($confirm && $structure) {
                        $structure->delete_sessions([$session->id]);
                    }
                    continue;
                }
                $session->sessdate = (new DateTime($dates[$meeting], $TZ))->getTimestamp();
                $session->duration = (int) $slot['duration'];
                $session->description = '<p>Pertemuan ' . $meeting . ' — ' . s($label) . '.</p>';
                $session->descriptionformat = FORMAT_HTML;
                $session->timemodified = time();
                $session->calendarevent = 0;
                $session->studentscanmark = 0;
                if ($confirm) {
                    $DB->update_record('attendance_sessions', $session);
                }
                $attsynced++;
            }

            $have = count($sessions);
            $need = count($dates);
            for ($meeting = $have + 1; $meeting <= $need; $meeting++) {
                $attsynced++;
                if ($confirm && $structure) {
                    $ts = (new DateTime($dates[$meeting], $TZ))->getTimestamp();
                    $structure->add_session(ush_attendance_session_object_prod($ts, (int) $slot['duration'], $meeting, $label));
                }
            }
        }
    } catch (Throwable $e) {
        $errors[] = $course->shortname . ': ' . $e->getMessage();
        mtrace('  ERROR ' . $course->shortname . ': ' . $e->getMessage());
    }

    if ($confirm) {
        rebuild_course_cache($course->id, true);
    }
}

if ($confirm) {
    purge_all_caches();
}

mtrace('');
mtrace('=== RINGKASAN ===');
mtrace('  Cocok ke jadwal SIAKAD     : ' . $matched);
mtrace('  Tanpa jadwal SIAKAD        : ' . count($unmatched));
mtrace('  Dilewati (KKN/Skripsi/dll) : ' . $skipped);
mtrace('  Kelas multi-hari (pakai slot terpanjang) : ' . $multiday);
mtrace('  Seksi yang ' . ($confirm ? 'diubah' : 'akan diubah') . ' : ' . $sectionchanged);
mtrace('  Attendance baru            : ' . $attcreated);
mtrace('  Sesi presensi diselaraskan : ' . $attsynced);
mtrace('  Sesi presensi dihapus      : ' . $attdeleted);
mtrace('  Error                      : ' . count($errors));
if ($unmatched) {
    mtrace('  Tanpa jadwal (contoh 30):');
    foreach (array_slice($unmatched, 0, 30) as $line) {
        mtrace('    ' . $line);
    }
    if (count($unmatched) > 30) {
        mtrace('    ... ' . (count($unmatched) - 30) . ' lagi');
    }
}
if (!$confirm) {
    mtrace('DRY-RUN. Ulangi dengan --confirm untuk menulis.');
}
