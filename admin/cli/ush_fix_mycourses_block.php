<?php
// Pastikan halaman "Kursusku" (/my/courses.php) punya blok Course overview (myoverview).
//
// Pakai:
//   php admin/cli/ush_fix_mycourses_block.php            (cek saja)
//   php admin/cli/ush_fix_mycourses_block.php --confirm  (perbaiki)

define('CLI_SCRIPT', true);
require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/my/lib.php');

$confirm = in_array('--confirm', $argv, true);
$sys = context_system::instance();

$page = $DB->get_record('my_pages', ['userid' => null, 'name' => MY_PAGE_COURSES, 'private' => MY_PAGE_PUBLIC]);
if (!$page) {
    mtrace('Halaman sistem Kursusku (__courses) tidak ada di my_pages.');
    exit(1);
}
mtrace("Halaman Kursusku id={$page->id}");

$plugin = $DB->get_record('block', ['name' => 'myoverview']);
mtrace('Plugin block_myoverview: ' . ($plugin ? ('ada, visible=' . $plugin->visible) : 'TIDAK TERPASANG'));

$blocks = $DB->get_records_select('block_instances',
    "parentcontextid = :ctx AND pagetypepattern = 'my-index' AND (subpagepattern IS NULL OR subpagepattern = :sub)",
    ['ctx' => $sys->id, 'sub' => (string) $page->id], 'id');
$positions = $DB->get_records('block_positions',
    ['contextid' => $sys->id, 'pagetype' => 'my-index', 'subpage' => (string) $page->id], '', 'blockinstanceid, id, visible, region');

mtrace('Blok yang tampil di Kursusku:');
$overview = null;
foreach ($blocks as $b) {
    $pos = $positions[$b->id] ?? null;
    $vis = $pos ? (int) $pos->visible : 1;
    mtrace(sprintf('  #%d %s region=%s subpage=%s visible=%d',
        $b->id, $b->blockname, $pos->region ?? $b->defaultregion, $b->subpagepattern ?? 'semua', $vis));
    if ($b->blockname === 'myoverview') {
        $overview = $b;
    }
}

$todo = [];
if ($plugin && !$plugin->visible) {
    $todo[] = 'aktifkan plugin block_myoverview';
}
if (!$overview) {
    $todo[] = 'tambah blok myoverview di Kursusku';
} else {
    $pos = $positions[$overview->id] ?? null;
    if ($pos && (!$pos->visible || $pos->region !== 'content')) {
        $todo[] = 'tampilkan blok myoverview #' . $overview->id . ' di region content';
    }
}

if (!$todo) {
    mtrace('Tidak ada yang perlu diperbaiki.');
    exit(0);
}
mtrace('Perlu: ' . implode('; ', $todo));
if (!$confirm) {
    mtrace('Jalankan ulang dengan --confirm untuk memperbaiki.');
    exit(0);
}

if ($plugin && !$plugin->visible) {
    $DB->set_field('block', 'visible', 1, ['id' => $plugin->id]);
}
if (!$overview) {
    $DB->insert_record('block_instances', (object) [
        'blockname' => 'myoverview',
        'parentcontextid' => $sys->id,
        'showinsubcontexts' => 0,
        'requiredbytheme' => 0,
        'pagetypepattern' => 'my-index',
        'subpagepattern' => (string) $page->id,
        'defaultregion' => 'content',
        'defaultweight' => -10,
        'configdata' => '',
        'timecreated' => time(),
        'timemodified' => time(),
    ]);
} else if (isset($positions[$overview->id])) {
    $DB->delete_records('block_positions', ['id' => $positions[$overview->id]->id]);
}
purge_all_caches();
mtrace('Selesai. Buka /my/courses.php lagi (Ctrl+Shift+R).');
