<?php
/**
 * Pecah Pancasila Education 2026/2027 Ganjil per prodi, sesuai SIAKAD.
 * Tidak menulis tanpa --confirm.
 */
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->libdir . '/enrollib.php');

$CONFIRM = in_array('--confirm', array_slice($argv, 1), true);
$LABEL = '2026/2027 - Ganjil';
$SUFFIX = '20262027Ganjil';
$START = mktime(0, 0, 0, 9, 1, 2026);
$END = mktime(0, 0, 0, 2, 28, 2027);

$prodi_siakad = [
    24 => ['kode' => 'SBD', 'nama' => 'Bisnis Digital', 'short' => 'IUM00202602_20262027Ganjil', 'reuse' => true, 'title' => 'Pancasila Education BisDig'],
    26 => ['kode' => 'SIF', 'nama' => 'Informatika', 'short' => 'IUM002602_SIF_20262027Ganjil', 'reuse' => false, 'title' => 'Pancasila Education Informatika'],
    31 => ['kode' => 'MBI', 'nama' => 'MBI', 'short' => 'IUM002602_MBI_20262027Ganjil', 'reuse' => false, 'title' => 'Pancasila Education MBI'],
    32 => ['kode' => 'HKM', 'nama' => 'Hukum Bisnis', 'short' => 'IUM00202602_HKM_20262027Ganjil', 'reuse' => false, 'title' => 'Pancasila Education Hubis'],
    25 => ['kode' => 'SGZ', 'nama' => 'Ilmu Gizi', 'short' => 'IUM002602_SGZ_20262027Ganjil', 'reuse' => false, 'title' => 'Pancasila Education Gizi'],
    33 => ['kode' => 'TPN', 'nama' => 'Teknologi Pangan', 'short' => 'IUM002602_TPN_20262027Ganjil', 'reuse' => false, 'title' => 'Pancasila Education Tekpang'],
    34 => ['kode' => 'BKI', 'nama' => 'BKI', 'short' => 'IUM002602_BKI_20262027Ganjil', 'reuse' => false, 'title' => 'Pancasila Education BKI'],
    35 => ['kode' => 'PAR', 'nama' => 'Pariwisata', 'short' => 'IUM002602_PAR_20262027Ganjil', 'reuse' => false, 'title' => 'Pancasila Education Pariwisata'],
    36 => ['kode' => 'DUM', 'nama' => 'Diploma', 'short' => 'DUM002602_20262027Ganjil', 'reuse' => false, 'title' => 'Pancasila Education Diploma'],
];

$mixedshort = 'IUM002602_20262027Ganjil';
$veronica = $DB->get_record('user', ['username' => 'dosen_8420', 'deleted' => 0], '*', MUST_EXIST);
$imam = $DB->get_record('user', ['username' => 'dosen_8451', 'deleted' => 0], '*', MUST_EXIST);
$teacherrole = (int) $DB->get_field('role', 'id', ['shortname' => 'editingteacher'], MUST_EXIST);
$studentrole = (int) $DB->get_field('role', 'id', ['shortname' => 'student'], MUST_EXIST);
$enrolplugin = enrol_get_plugin('manual');

$cat = $DB->get_record_sql(
    "SELECT * FROM {course_categories} WHERE " . $DB->sql_like('name', ':n'),
    ['n' => '%Mata Kuliah Umum%2026/2027%Ganjil%']
);
if (!$cat) {
    mtrace('Kategori MKU 2026/2027 Ganjil tidak ada');
    exit(1);
}

$d = json_decode(file_get_contents($CFG->dirroot . '/peserta_20262027Ganjil.json'), true);
$byprodi = [];
$dosenprodi = [];
foreach ($d['classes'] ?? [] as $k) {
    $name = (string) ($k['lesson_name'] ?? '');
    if (stripos($name, 'pancasila') === false) {
        continue;
    }
    $pid = (int) ($k['id_prodi'] ?? 0);
    if (!isset($prodi_siakad[$pid])) {
        continue;
    }
    $dosenprodi[$pid] = (int) ($k['id_lecture'] ?? 0);
    foreach ($k['students'] ?? [] as $s) {
        $nim = strtolower(trim((string) ($s['nim'] ?? '')));
        if ($nim !== '') {
            $byprodi[$pid][$nim] = trim((string) ($s['name'] ?? $nim));
        }
    }
}

function ush_unenrol(int $courseid, int $userid): void {
    foreach (enrol_get_instances($courseid, false) as $instance) {
        $plugin = enrol_get_plugin($instance->enrol);
        if ($plugin) {
            $plugin->unenrol_user($instance, $userid);
        }
    }
}

function ush_user_by_nim(string $nim): ?stdClass {
    global $DB, $CFG;
    return $DB->get_record('user', [
        'username' => $nim,
        'mnethostid' => $CFG->mnet_localhost_id,
        'deleted' => 0,
    ]) ?: null;
}

$mixed = $DB->get_record('course', ['shortname' => $mixedshort], '*', MUST_EXIST);
$cms = $DB->count_records('course_modules', ['course' => $mixed->id, 'deletioninprogress' => 0]);
mtrace('=== Pecah Pancasila per prodi ===');
mtrace('Site: ' . $CFG->wwwroot);
mtrace('Mode: ' . ($CONFIRM ? 'LIVE' : 'DRY-RUN'));
mtrace('Kelas campur: ' . $mixed->shortname . ' | modul=' . $cms);
mtrace('');

foreach ($prodi_siakad as $pid => $info) {
    $nims = $byprodi[$pid] ?? [];
    $teacher = (($dosenprodi[$pid] ?? 0) === 8451) ? $imam : $veronica;
    mtrace(sprintf('%s (%s) SIAKAD=%d teacher=%s short=%s',
        $info['title'], $info['nama'], count($nims), $teacher->username, $info['short']));

    $course = $DB->get_record('course', ['shortname' => $info['short']]);
    if (!$course && !$info['reuse']) {
        mtrace('  akan dibuat');
        if ($CONFIRM) {
            $new = new stdClass();
            $new->fullname = $info['title'] . ' — ' . $LABEL;
            $new->shortname = $info['short'];
            $new->idnumber = $info['short'];
            $new->category = (int) $cat->id;
            $new->visible = 1;
            $new->format = 'topics';
            $new->numsections = 16;
            $new->startdate = $START;
            $new->enddate = $END;
            $new->summary = '<p><strong>' . s($info['title']) . '</strong> — ' . s($LABEL)
                . '</p><p>Dipisah per prodi sesuai SIAKAD. Prodi: ' . s($info['nama']) . '</p>';
            $new->summaryformat = FORMAT_HTML;
            $new->enablecompletion = 1;
            $course = create_course($new);
            mtrace('  dibuat id=' . $course->id);
        }
    } else if ($course) {
        mtrace('  pakai kelas yang sudah ada id=' . $course->id);
    } else {
        mtrace('  ERROR: kelas reuse tidak ketemu');
        continue;
    }

    if (!$CONFIRM || !$course) {
        continue;
    }
    if ($enrolplugin && !$DB->record_exists('enrol', ['courseid' => $course->id, 'enrol' => 'manual'])) {
        $enrolplugin->add_instance($course);
    }
    enrol_try_internal_enrol($course->id, $teacher->id, $teacherrole);

    $enrolled = 0;
    $missinguser = 0;
    $already = 0;
    foreach ($nims as $nim => $name) {
        $user = ush_user_by_nim($nim);
        if (!$user) {
            $missinguser++;
            mtrace("    belum ada akun: $nim $name");
            continue;
        }
        $ctx = context_course::instance($course->id);
        if (is_enrolled($ctx, $user, '', true)) {
            $already++;
        } else if (enrol_try_internal_enrol($course->id, $user->id, $studentrole)) {
            $enrolled++;
        }
        $mctx = context_course::instance($mixed->id);
        if ((int) $course->id !== (int) $mixed->id && is_enrolled($mctx, $user, '', true)) {
            ush_unenrol((int) $mixed->id, (int) $user->id);
        }
        // Jangan biarkan Hubis/SIF/MBI tertinggal di kelas BisDig.
        $bisdig = $DB->get_record('course', ['shortname' => 'IUM00202602_20262027Ganjil']);
        if ($bisdig && (int) $course->id !== (int) $bisdig->id) {
            $bctx = context_course::instance($bisdig->id);
            if (is_enrolled($bctx, $user, '', true) && $pid !== 24) {
                ush_unenrol((int) $bisdig->id, (int) $user->id);
            }
        }
    }
    mtrace("  enrol baru=$enrolled sudah=$already tanpa akun=$missinguser");
}

if ($CONFIRM) {
    // Veronica tidak perlu di kelas campur / kode lama kosong.
    foreach (['IUM002602_20262027Ganjil', 'IUM0002_20262027Ganjil'] as $sn) {
        $c = $DB->get_record('course', ['shortname' => $sn]);
        if (!$c) {
            continue;
        }
        ush_unenrol((int) $c->id, (int) $veronica->id);
        ush_unenrol((int) $c->id, (int) $imam->id);
        $left = (int) $DB->count_records_sql(
            "SELECT COUNT(DISTINCT ue.userid)
               FROM {user_enrolments} ue
               JOIN {enrol} e ON e.id = ue.enrolid
               JOIN {context} ctx ON ctx.instanceid = e.courseid AND ctx.contextlevel = 50
               JOIN {role_assignments} ra ON ra.userid = ue.userid AND ra.contextid = ctx.id AND ra.roleid = :r
              WHERE e.courseid = :cid AND ue.status = 0",
            ['r' => $studentrole, 'cid' => $c->id]
        );
        if ($left === 0) {
            $DB->set_field('course', 'visible', 0, ['id' => $c->id]);
            mtrace("Sembunyikan $sn (kosong)");
        } else {
            mtrace("$sn sisa mahasiswa=$left");
        }
    }
    fix_course_sortorder();
    rebuild_course_cache(0, true);
}

mtrace('');
mtrace('Selesai. Tanpa --confirm tidak ada kelas baru.');
