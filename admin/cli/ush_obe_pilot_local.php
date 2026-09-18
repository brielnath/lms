<?php
/**
 * Uji sambungan OBE satu MK: Pemograman Game IDM0629.
 *
 * 1. Pastikan kategori nilai USH (Presensi/Tugas/Kuis/UTS/UAS).
 * 2. Tempel idnumber OBE di kategori itu.
 * 3. Tampilkan peta CPL/CPMK di section 0.
 * 4. Hitung skor CPMK dari gradebook (kalau sudah ada nilai).
 *
 * Tidak mengirim nilai ke SIAKAD/KHS.
 *
 *   php admin/cli/ush_obe_pilot_local.php
 *   php admin/cli/ush_obe_pilot_local.php --confirm
 */
define('CLI_SCRIPT', true);
$ushstartcwd = getcwd();
require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/gradelib.php');
require_once($CFG->libdir . '/grade/constants.php');
require_once($CFG->libdir . '/grade/grade_category.php');
require_once($CFG->libdir . '/grade/grade_item.php');
require_once($CFG->libdir . '/grade/grade_grade.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/course/modlib.php');

if (strpos($CFG->wwwroot, 'localhost') === false && strpos($CFG->wwwroot, '127.0.0.1') === false) {
    mtrace('Dibatalkan: bukan LMS lokal. wwwroot=' . $CFG->wwwroot);
    exit(1);
}

$confirm = in_array('--confirm', array_slice($argv, 1), true);
$mapfile = __DIR__ . '/obe_pilot_IDM0629.json';
if ($ushstartcwd && is_readable($ushstartcwd . '/admin/cli/obe_pilot_IDM0629.json')) {
    $mapfile = $ushstartcwd . '/admin/cli/obe_pilot_IDM0629.json';
}
$map = json_decode((string) file_get_contents($mapfile), true);
if (!is_array($map)) {
    mtrace('Peta OBE tidak terbaca: ' . $mapfile);
    exit(1);
}

$admin = get_admin();
\core\session\manager::set_user($admin);

$course = $DB->get_record('course', ['shortname' => $map['shortname']]);
if (!$course) {
    mtrace('Kelas belum ada: ' . $map['shortname']);
    exit(1);
}

$SCHEME = [
    'Presensi' => ['idnumber' => 'USH_PRESENSI', 'weight' => 0.10],
    'Tugas' => ['idnumber' => 'USH_TUGAS', 'weight' => 0.20],
    'Kuis' => ['idnumber' => 'USH_KUIS', 'weight' => 0.10],
    'UTS' => ['idnumber' => 'USH_UTS', 'weight' => 0.30],
    'UAS' => ['idnumber' => 'USH_UAS', 'weight' => 0.30],
];

mtrace('=== Uji OBE satu MK ===');
mtrace('Site : ' . $CFG->wwwroot);
mtrace('Mode : ' . ($confirm ? 'LIVE' : 'DRY-RUN'));
mtrace('Kelas: ' . $course->shortname . ' — ' . $course->fullname);
mtrace('Peta : ' . $mapfile);
mtrace('');

$coursecat = grade_category::fetch_course_category($course->id);
if ($confirm && (int) $coursecat->aggregation !== GRADE_AGGREGATE_WEIGHTED_MEAN) {
    $coursecat->aggregation = GRADE_AGGREGATE_WEIGHTED_MEAN;
    $coursecat->update();
}

$catids = [];
foreach ($SCHEME as $name => $meta) {
    $existing = grade_category::fetch([
        'courseid' => $course->id,
        'fullname' => $name,
        'parent' => $coursecat->id,
    ]);
    if (!$existing) {
        mtrace('Kategori ' . $name . ' belum ada — akan dibuat');
        if ($confirm) {
            $cat = new grade_category([
                'courseid' => $course->id,
                'fullname' => $name,
                'parent' => $coursecat->id,
                'aggregation' => GRADE_AGGREGATE_MEAN,
            ], false);
            $cat->insert();
            $existing = grade_category::fetch(['id' => $cat->id]);
        }
    }
    if (!$existing) {
        continue;
    }
    $catids[$name] = (int) $existing->id;
    $catitem = $existing->load_grade_item();
    $need = ((string) $catitem->idnumber !== $meta['idnumber'])
        || ((float) $catitem->aggregationcoef !== $meta['weight'] * 100)
        || ((float) $catitem->grademax !== 100.0);
    if ($need) {
        mtrace(sprintf('  Tag %s → %s (bobot %d%%)', $name, $meta['idnumber'], (int) ($meta['weight'] * 100)));
        if ($confirm) {
            $catitem->idnumber = $meta['idnumber'];
            $catitem->aggregationcoef = $meta['weight'] * 100;
            $catitem->grademax = 100;
            $catitem->grademin = 0;
            $catitem->update();
        }
    } else {
        mtrace('  Sudah tertag: ' . $name . ' = ' . $meta['idnumber']);
    }
}

if ($confirm) {
    $courseitem = grade_item::fetch(['courseid' => $course->id, 'itemtype' => 'course']);
    $courseitem->grademax = 100;
    $courseitem->grademin = 0;
    $courseitem->update();
}

function ush_obe_page_html(array $map): string {
    $rows = '';
    foreach ($map['cpmk'] as $cpmk) {
        $src = [];
        foreach ($cpmk['sources'] as $s) {
            $src[] = $s['idnumber'] . ' ' . round($s['weight'] * 100) . '%';
        }
        $rows .= '<tr><td>' . s($cpmk['id']) . '</td><td>' . s($cpmk['text'])
            . '</td><td>' . s(implode(', ', $cpmk['cpl'])) . '</td><td>'
            . s(implode(', ', $src)) . '</td></tr>';
    }
    $cpl = '';
    foreach ($map['cpl'] as $c) {
        $cpl .= '<li><strong>' . s($c['id']) . '</strong> — ' . s($c['text']) . '</li>';
    }
    return '<div class="ush-obe-map">'
        . '<p><strong>Uji OBE satu mata kuliah.</strong> '
        . s($map['note']) . '</p>'
        . '<p><strong>CPL yang diampu</strong></p><ul>' . $cpl . '</ul>'
        . '<table class="generaltable"><thead><tr>'
        . '<th>CPMK</th><th>Rumusan</th><th>CPL</th><th>Bukti di LMS</th>'
        . '</tr></thead><tbody>' . $rows . '</tbody></table>'
        . '<p>Nilai akhir MK (ke SIAKAD nanti): Presensi 10%, Tugas 20%, Kuis 10%, UTS 30%, UAS 30%.</p>'
        . '</div>';
}

$pagemod = $DB->get_record('modules', ['name' => 'page']);
$pagecm = $DB->get_record_sql(
    "SELECT cm.* FROM {course_modules} cm
      WHERE cm.course = :cid AND cm.module = :mid AND cm.idnumber = :idn
        AND cm.deletioninprogress = 0",
    ['cid' => $course->id, 'mid' => $pagemod->id, 'idn' => 'OBE_MAP_IDM0629']
);
$html = ush_obe_page_html($map);
if (!$pagecm) {
    mtrace('Halaman peta OBE akan dibuat di section 0');
    if ($confirm) {
        $moduleinfo = (object) [
            'module' => $pagemod->id,
            'modulename' => 'page',
            'course' => $course->id,
            'section' => 0,
            'visible' => 1,
            'name' => 'Peta OBE — Pemograman Game',
            'intro' => '<p>CPL, CPMK, dan bukti penilaian di LMS untuk uji OBE.</p>',
            'introformat' => FORMAT_HTML,
            'content' => $html,
            'contentformat' => FORMAT_HTML,
            'display' => 5,
            'printheading' => 1,
            'printintro' => 0,
            'idnumber' => 'OBE_MAP_IDM0629',
        ];
        add_moduleinfo($moduleinfo, $course);
        mtrace('  halaman dibuat');
    }
} else {
    mtrace('Halaman peta OBE sudah ada (cmid=' . $pagecm->id . ') — isi akan diselaraskan');
    if ($confirm) {
        $DB->set_field('page', 'content', $html, ['id' => $pagecm->instance]);
        $DB->set_field('page', 'contentformat', FORMAT_HTML, ['id' => $pagecm->instance]);
    }
}

if ($confirm) {
    rebuild_course_cache($course->id, true);
    grade_regrade_final_grades($course->id);
}

$byidnumber = [];
$items = grade_item::fetch_all(['courseid' => $course->id]);
foreach ($items ?: [] as $item) {
    if ($item->idnumber) {
        $byidnumber[$item->idnumber] = $item;
    }
}

$studentrole = (int) $DB->get_field('role', 'id', ['shortname' => 'student'], MUST_EXIST);
$students = $DB->get_records_sql(
    "SELECT DISTINCT u.id, u.username, u.firstname, u.lastname
       FROM {user} u
       JOIN {role_assignments} ra ON ra.userid = u.id
       JOIN {context} ctx ON ctx.id = ra.contextid AND ctx.contextlevel = 50
      WHERE ctx.instanceid = :cid AND ra.roleid = :r AND u.deleted = 0
      ORDER BY u.username",
    ['cid' => $course->id, 'r' => $studentrole]
);

function ush_obe_item_score(?grade_item $item, int $userid): ?float {
    if (!$item) {
        return null;
    }
    $g = grade_grade::fetch(['itemid' => $item->id, 'userid' => $userid]);
    if (!$g || $g->finalgrade === null || $g->finalgrade === '') {
        return null;
    }
    $max = (float) $item->grademax ?: 100;
    return round(100 * ((float) $g->finalgrade / $max), 2);
}

function ush_obe_weighted(array $parts): ?float {
    $sum = 0.0;
    $w = 0.0;
    foreach ($parts as $p) {
        if ($p['score'] === null) {
            continue;
        }
        $sum += $p['score'] * $p['weight'];
        $w += $p['weight'];
    }
    if ($w <= 0) {
        return null;
    }
    return round($sum / $w, 2);
}

mtrace('');
mtrace('=== Skor CPMK (mahasiswa yang sudah punya nilai) ===');
$export = [];
$shown = 0;
$withscore = 0;
foreach ($students as $u) {
    $components = [];
    foreach ($SCHEME as $meta) {
        $components[$meta['idnumber']] = ush_obe_item_score($byidnumber[$meta['idnumber']] ?? null, (int) $u->id);
    }
    $cpmkscores = [];
    foreach ($map['cpmk'] as $cpmk) {
        $parts = [];
        foreach ($cpmk['sources'] as $s) {
            $parts[] = [
                'weight' => (float) $s['weight'],
                'score' => $components[$s['idnumber']] ?? null,
            ];
        }
        $cpmkscores[$cpmk['id']] = ush_obe_weighted($parts);
    }
    $cplscores = [];
    foreach ($map['cpl_from_cpmk'] as $cplid => $parts) {
        $plist = [];
        foreach ($parts as $p) {
            $plist[] = [
                'weight' => (float) $p['weight'],
                'score' => $cpmkscores[$p['id']] ?? null,
            ];
        }
        $cplscores[$cplid] = ush_obe_weighted($plist);
    }
    $courseitem = grade_item::fetch(['courseid' => $course->id, 'itemtype' => 'course']);
    $coursetotal = ush_obe_item_score($courseitem, (int) $u->id);
    $has = $coursetotal !== null;
    foreach ($cpmkscores as $v) {
        if ($v !== null) {
            $has = true;
        }
    }
    if ($has) {
        $withscore++;
        if ($shown < 12) {
            $bits = [];
            foreach ($cpmkscores as $id => $v) {
                $bits[] = $id . '=' . ($v === null ? '-' : $v);
            }
            mtrace(sprintf(
                '  %s %s | MK=%s | %s',
                $u->username,
                trim($u->firstname . ' ' . $u->lastname),
                $coursetotal === null ? '-' : $coursetotal,
                implode(' ', $bits)
            ));
            $shown++;
        }
    }
    $export[] = [
        'nim' => $u->username,
        'name' => trim($u->firstname . ' ' . $u->lastname),
        'lesson_code' => $map['code'],
        'nilai' => $coursetotal,
        'cpmk' => $cpmkscores,
        'cpl' => $cplscores,
        'components' => $components,
    ];
}

mtrace('Mahasiswa di kelas : ' . count($students));
mtrace('Yang sudah ada nilai: ' . $withscore);
if ($withscore === 0) {
    mtrace('Belum ada nilai di kategori USH. Peta OBE tetap hidup; isi Tugas/Kuis/UTS/UAS lalu ulangi skrip ini.');
}

$outfile = $CFG->dirroot . '/obe_export_IDM0629.json';
$payload = [
    'generated' => date('c'),
    'course' => $map['shortname'],
    'siakad_endpoint' => 'POST /api/update-grades-from-obe',
    'siakad_note' => 'Belum dikirim. Kirim hanya field nim+lesson_code+nilai setelah kaprodi setuju.',
    'grades' => array_values(array_filter(array_map(static function (array $row): ?array {
        if ($row['nilai'] === null) {
            return null;
        }
        return [
            'nim' => $row['nim'],
            'lesson_code' => $row['lesson_code'],
            'nilai' => $row['nilai'],
        ];
    }, $export))),
    'detail' => $export,
];
if ($confirm) {
    file_put_contents($outfile, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL);
    mtrace('Ekspor disimpan: ' . $outfile);
}

mtrace('');
mtrace('Lihat kelas: ' . $CFG->wwwroot . '/course/view.php?id=' . $course->id);
mtrace('Nilai     : ' . $CFG->wwwroot . '/grade/report/grader/index.php?id=' . $course->id);
if (!$confirm) {
    mtrace('DRY-RUN. Ulangi dengan --confirm untuk menulis peta ke LMS.');
}
