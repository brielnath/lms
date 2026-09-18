<?php
/**
 * Tarik jadwal kuliah SIAKAD ke semua kelas LMS 2026/2027 Ganjil.
 *
 * Pola sama seperti Smart City:
 * - Hari + jam dari schedule_lesson SIAKAD (bukan label W kalender).
 * - Pertemuan dinomori berurutan mulai 7 September 2026.
 * - Loncat jendela UTS 26 Sep–6 Okt 2026.
 * - Tidak membuat tanggal setelah 23 Nov 2026 (kalender belum selesai).
 * - P11–P15 disembunyikan.
 * - Presensi dicatat dosen; mahasiswa tidak check-in sendiri.
 *
 *   php admin/cli/ush_apply_jadwal_sequential_local.php
 *   php admin/cli/ush_apply_jadwal_sequential_local.php --confirm
 *   php admin/cli/ush_apply_jadwal_sequential_local.php --export=jadwal_slots_20262027Ganjil.json
 */
define('CLI_SCRIPT', true);
$ushstartcwd = getcwd();
require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/course/modlib.php');
require_once($CFG->dirroot . '/mod/attendance/lib.php');
require_once($CFG->dirroot . '/mod/attendance/locallib.php');
require_once($CFG->dirroot . '/mod/attendance/classes/structure.php');

if (strpos($CFG->wwwroot, 'localhost') === false && strpos($CFG->wwwroot, '127.0.0.1') === false) {
    mtrace('Dibatalkan: bukan LMS lokal. wwwroot=' . $CFG->wwwroot);
    exit(1);
}

$confirm = false;
$export = '';
foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--confirm') {
        $confirm = true;
    } else if (str_starts_with($arg, '--export=')) {
        $export = substr($arg, 9);
    }
}
$admin = get_admin();
\core\session\manager::set_user($admin);

$SUFFIX = '20262027Ganjil';
$SKIPRE = '/(kkn|skripsi|kerja praktik|praktik kerja|kuliah kerja|seminar proposal)/i';
$TZ = new DateTimeZone('Asia/Jakarta');
$FIRST = '2026-09-07';
$LAST = '2026-11-23';
$UTS_FROM = '2026-09-26';
$UTS_TO = '2026-10-06';
$MAXMEETINGS = 10;

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

function ush_parse_short(string $short, string $suffix): array {
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

function ush_sequential_dates(int $dow, string $time, DateTimeZone $tz, string $first, string $last, string $utsfrom, string $utsto, int $max): array {
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

function ush_pick_slot(array $slots): ?array {
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

function ush_attendance_session_object(int $ts, int $duration, int $meeting, string $label): stdClass {
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

$from = $CFG->dirroot . '/peserta_20262027Ganjil.json';
if (!is_readable($from) && $ushstartcwd && is_readable($ushstartcwd . '/peserta_20262027Ganjil.json')) {
    $from = $ushstartcwd . '/peserta_20262027Ganjil.json';
}
if (!is_readable($from)) {
    mtrace('File peserta_20262027Ganjil.json tidak terbaca.');
    exit(1);
}
$peserta = json_decode((string) file_get_contents($from), true);
$bymeta = [];
foreach ($peserta['classes'] ?? [] as $k) {
    $cid = (int) ($k['id_class'] ?? 0);
    if ($cid) {
        $bymeta[$cid] = $k;
    }
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$siakad = new mysqli('127.0.0.1', 'root', '', 'acc_tes_backup');
$siakad->set_charset('utf8mb4');
$res = $siakad->query("
    SELECT sl.id_class_room, sl.id_days, d.name AS hari,
           MIN(h.start) AS jam_mulai, MAX(h.end) AS jam_selesai,
           COUNT(*) AS slots, sl.id_lesson, sl.id_prodi, sl.id_lecture,
           l.code AS lesson_code, l.name AS lesson_name, cr.name AS class_name
      FROM schedule_lesson sl
      JOIN days d ON d.id = sl.id_days
      JOIN hours h ON h.id = sl.id_hours
      JOIN lesson l ON l.id = sl.id_lesson
      JOIN class_room cr ON cr.id = sl.id_class_room
     WHERE sl.id_batch_year = 13
       AND sl.id_sub_batch_year = 20
       AND sl.status = 'Y'
       AND sl.is_deleted = 'N'
     GROUP BY sl.id_class_room, sl.id_days, d.name, sl.id_lesson, sl.id_prodi, sl.id_lecture, l.code, l.name, cr.name
     ORDER BY sl.id_class_room, sl.id_days, jam_mulai
");
$byclassdays = [];
while ($row = $res->fetch_assoc()) {
    $cid = (int) $row['id_class_room'];
    $byclassdays[$cid][] = $row;
}
$siakad->close();

$slotsbycode = [];
foreach ($byclassdays as $cid => $dayslots) {
    usort($dayslots, static function (array $a, array $b): int {
        $cmp = ((int) $b['slots']) <=> ((int) $a['slots']);
        if ($cmp !== 0) {
            return $cmp;
        }
        return ((int) $a['id_days']) <=> ((int) $b['id_days']);
    });
    $best = $dayslots[0];
    $meta = $bymeta[$cid] ?? [];
    $code = strtoupper(trim((string) ($meta['code'] ?? $best['lesson_code'] ?? '')));
    $nama = trim((string) ($meta['lesson_name'] ?? $best['lesson_name'] ?? ''));
    $kelas = trim((string) ($meta['class_name'] ?? $best['class_name'] ?? ''));
    if ($code === '' || preg_match($SKIPRE, $nama . ' ' . $code . ' ' . $kelas)) {
        continue;
    }
    $start = substr((string) $best['jam_mulai'], 0, 8);
    $end = substr((string) $best['jam_selesai'], 0, 8);
    $st = DateTime::createFromFormat('H:i:s', $start) ?: DateTime::createFromFormat('H:i:s', '08:00:00');
    $en = DateTime::createFromFormat('H:i:s', $end) ?: DateTime::createFromFormat('H:i:s', '08:50:00');
    $duration = max(50 * MINSECS, $en->getTimestamp() - $st->getTimestamp());
    $slotsbycode[$code][] = [
        'id_class' => $cid,
        'code' => $code,
        'nama' => $nama,
        'kelas' => $kelas,
        'hari' => (string) $best['hari'],
        'dow' => (int) $best['id_days'],
        'jam_mulai' => $start,
        'jam_selesai' => $end,
        'duration' => $duration,
        'id_prodi' => (int) ($best['id_prodi'] ?: ($meta['id_prodi'] ?? 0)),
        'jml' => (int) ($meta['jml_diambil'] ?? $meta['jml_siakad'] ?? 0),
        'multiday' => count($dayslots) > 1,
    ];
}

if ($export !== '') {
    if ($ushstartcwd && !preg_match('#^([a-zA-Z]:[\\\\/]|[\\\\/])#', $export)) {
        $export = $ushstartcwd . DIRECTORY_SEPARATOR . $export;
    }
    $payload = [
        'generated' => date('c'),
        'semester' => $SUFFIX,
        'first' => $FIRST,
        'last' => $LAST,
        'uts_from' => $UTS_FROM,
        'uts_to' => $UTS_TO,
        'max_meetings' => $MAXMEETINGS,
        'slots' => $slotsbycode,
    ];
    file_put_contents($export, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL);
    mtrace('Diekspor jadwal ' . count($slotsbycode) . ' kode MK ke ' . $export);
}

$courses = $DB->get_records_sql(
    "SELECT id, shortname, fullname FROM {course}
      WHERE id > 1 AND " . $DB->sql_like('shortname', ':p') . "
      ORDER BY shortname",
    ['p' => '%' . $SUFFIX]
);
$attendance_module = $DB->get_record('modules', ['name' => 'attendance'], '*', MUST_EXIST);
set_config('enableavailability', 1);

mtrace('=== Jadwal sequential 2026/2027 Ganjil ===');
mtrace('Site: ' . $CFG->wwwroot);
mtrace('Mode: ' . ($confirm ? 'LIVE' : 'DRY-RUN'));
mtrace('Sumber: SIAKAD schedule_lesson batch 13 / sub 20');
mtrace('Pola: Smart City — mulai ' . $FIRST . ', loncat UTS ' . $UTS_FROM . '–' . $UTS_TO . ', batas ' . $LAST);
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
    $parsed = ush_parse_short($course->shortname, $SUFFIX);
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
    $slot = ush_pick_slot($cands);
    if (!$slot) {
        $unmatched[] = $course->shortname . ' | ' . $course->fullname;
        continue;
    }
    $matched++;
    if (!empty($slot['multiday'])) {
        $multiday++;
    }

    $time = substr($slot['jam_mulai'], 0, 5) . ':00';
    $dates = ush_sequential_dates(
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
        substr($slot['jam_mulai'], 0, 5),
        substr($slot['jam_selesai'], 0, 5),
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
                    $structure->add_session(ush_attendance_session_object($ts, (int) $slot['duration'], $meeting, $label));
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
