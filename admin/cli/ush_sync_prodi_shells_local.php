<?php
/**
 * Bandingkan & samakan cangkang MK kurikulum baru 2026/2027 Ganjil vs katalog SIAKAD.
 * Tidak membuat KKN / Skripsi / Kerja Praktik / Seminar Proposal.
 * Tidak membuat kode kurikulum lama (SIF101, SBD401, …).
 * Lokal saja. Default DRY-RUN; tulis dengan --confirm.
 */
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->libdir . '/enrollib.php');
require_once(__DIR__ . '/ush_course_owner.php');

if (strpos($CFG->wwwroot, 'localhost') === false && strpos($CFG->wwwroot, '127.0.0.1') === false) {
    mtrace('Dibatalkan: bukan LMS lokal. wwwroot=' . $CFG->wwwroot);
    exit(1);
}

$CONFIRM = in_array('--confirm', array_slice($argv, 1), true);
$LABEL = '2026/2027 - Ganjil';
$SUFFIX = '20262027Ganjil';
$START = mktime(0, 0, 0, 9, 1, 2026);
$END = mktime(0, 0, 0, 2, 28, 2027);
$skipre = '/(kkn|skripsi|kerja praktik|praktik kerja|kuliah kerja|seminar proposal)/i';

$labels = ush_prodi_labels();
$enrolplugin = enrol_get_plugin('manual');

$parent = $DB->get_record('course_categories', ['idnumber' => 'TA_' . str_replace(['/', ' ', '-'], '_', $LABEL)]);
if (!$parent) {
    $parent = $DB->get_record('course_categories', ['name' => 'TA ' . $LABEL]);
}
if (!$parent) {
    mtrace('Kategori induk TA ' . $LABEL . ' tidak ada');
    exit(1);
}

$cat_ids = [];
foreach ($labels as $kode => $nama) {
    $cat_name = "$nama ($kode) - $LABEL";
    $idn = 'CAT_' . $kode . '_' . str_replace(['/', ' ', '-'], '_', $LABEL);
    $existing = $DB->get_record('course_categories', ['idnumber' => $idn]);
    if (!$existing) {
        $existing = $DB->get_record('course_categories', ['name' => $cat_name]);
    }
    if (!$existing && $CONFIRM) {
        $createdcat = core_course_category::create((object) [
            'name' => $cat_name,
            'idnumber' => $idn,
            'parent' => (int) $parent->id,
            'descriptionformat' => FORMAT_PLAIN,
        ]);
        $existing = $DB->get_record('course_categories', ['id' => $createdcat->id]);
        mtrace('Kategori dibuat: ' . $cat_name);
    }
    if ($existing) {
        $cat_ids[$kode] = (int) $existing->id;
    }
}

$lms = [];
$courses = $DB->get_records_sql(
    "SELECT id, shortname, fullname, category FROM {course}
      WHERE id > 1 AND " . $DB->sql_like('shortname', ':p'),
    ['p' => '%' . $SUFFIX]
);
foreach ($courses as $c) {
    $base = strtoupper(preg_replace('/_20262027Ganjil$/i', '', $c->shortname) ?? $c->shortname);
    $lms[$base] = $c;
}

$katfile = $CFG->dirroot . '/katalog_20262027Ganjil.json';
$payload = json_decode(file_get_contents($katfile), true);
$want = [];
foreach ($payload['lessons'] ?? [] as $l) {
    $code = strtoupper(trim((string) ($l['code'] ?? '')));
    $name = trim((string) ($l['name'] ?? ''));
    $sem = (int) ($l['semester'] ?? 0);
    if ($code === '' || $name === '') {
        continue;
    }
    if ($sem > 0 && $sem % 2 === 0) {
        continue;
    }
    if (preg_match($skipre, $name) || preg_match($skipre, $code)) {
        continue;
    }
    if (!ush_is_official_scheme_code($code)) {
        continue;
    }
    // DUM* duplikat IUM* (jenjang D vs I, sisa kodenya sama).
    if (str_starts_with($code, 'DUM')) {
        $ium = 'I' . substr($code, 1);
        if (isset($lms[$ium])) {
            continue;
        }
    }
    $prodi = ush_prodi_from_code($code);
    $want[$prodi][$code] = $l;
}

mtrace('=== Cangkang kurikulum baru *_' . $SUFFIX . ' ===');
mtrace('Mode: ' . ($CONFIRM ? 'LIVE' : 'DRY-RUN'));
mtrace('');
mtrace(sprintf("  %-6s %-36s %5s %5s %5s", 'Kode', 'Prodi', 'SIAKAD', 'LMS', 'Gap'));

$missing = [];
$extra = [];
foreach ($labels as $kode => $nama) {
    $siakad = $want[$kode] ?? [];
    $lmsprodi = [];
    foreach ($lms as $code => $c) {
        if (ush_prodi_from_code($code) === $kode && ush_is_official_scheme_code($code)) {
            $lmsprodi[$code] = $c;
        }
    }
    foreach ($siakad as $code => $l) {
        if (!isset($lms[$code])) {
            $missing[$kode][$code] = $l;
        }
    }
    foreach ($lmsprodi as $code => $c) {
        if (!isset($siakad[$code])) {
            $extra[$kode][$code] = $c;
        }
    }
    $s = count($siakad);
    $n = 0;
    foreach ($lmsprodi as $code => $c) {
        if (isset($siakad[$code])) {
            $n++;
        }
    }
    $gap = $n - $s;
    mtrace(sprintf("  %-6s %-36s %5d %5d %5s", $kode, $nama, $s, $n, $gap === 0 ? 'OK' : sprintf('%+d', $gap)));
}

mtrace('');
mtrace('=== Belum ada di LMS (akan dibuat) ===');
if (!$missing) {
    mtrace('  (tidak ada)');
}
$created = 0;
$failed = 0;
foreach ($missing as $kode => $rows) {
    ksort($rows);
    mtrace("  $kode (" . count($rows) . ")");
    foreach ($rows as $code => $l) {
        $name = trim((string) ($l['name'] ?? ''));
        $sks = (int) ($l['sks_total'] ?? 0);
        mtrace(sprintf("    %s  sem=%s  %s", $code, $l['semester'] ?? '', $name));
        if (!$CONFIRM) {
            continue;
        }
        if (empty($cat_ids[$kode])) {
            mtrace('      GAGAL: kategori ' . $kode . ' tidak ada');
            $failed++;
            continue;
        }
        $short = $code . '_' . $SUFFIX;
        $newcourse = new stdClass();
        $newcourse->fullname = $name . ' — ' . $LABEL;
        $newcourse->shortname = $short;
        $newcourse->idnumber = $short;
        $newcourse->category = $cat_ids[$kode];
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
            mtrace('      dibuat id=' . $course->id);
        } catch (Throwable $e) {
            $failed++;
            mtrace('      GAGAL: ' . $e->getMessage());
        }
    }
}

mtrace('');
mtrace('=== Ada di LMS, tidak di target SIAKAD ganjil (tidak dihapus) ===');
if (!$extra) {
    mtrace('  (tidak ada)');
}
foreach ($extra as $kode => $rows) {
    mtrace("  $kode");
    foreach ($rows as $code => $c) {
        mtrace('    ' . $c->shortname . ' | ' . $c->fullname);
    }
}

if ($CONFIRM) {
    fix_course_sortorder();
    rebuild_course_cache(0, true);
    mtrace('');
    mtrace("Dibuat: $created  Gagal: $failed");
} else {
    mtrace('');
    mtrace('DRY-RUN. Ulangi dengan --confirm untuk membuat kelas yang belum ada.');
}
