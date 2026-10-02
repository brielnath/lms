<?php
// Pastikan halaman "Kursusku" (/my/courses.php) dan "Dasbor" (/my/) punya blok Course overview (myoverview).
//
// Pakai:
//   php admin/cli/ush_fix_mycourses_block.php                       (cek saja)
//   php admin/cli/ush_fix_mycourses_block.php --confirm             (perbaiki)

define('CLI_SCRIPT', true);
require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/my/lib.php');

$confirm = in_array('--confirm', $argv, true);
$sys = context_system::instance();

$plugin = $DB->get_record('block', ['name' => 'myoverview']);
mtrace('Plugin block_myoverview: ' . ($plugin ? ('ada, visible=' . $plugin->visible) : 'TIDAK TERPASANG'));
if (!$plugin) {
    exit(1);
}

$targets = [
    'Kursusku' => $DB->get_record('my_pages', ['userid' => null, 'name' => MY_PAGE_COURSES, 'private' => MY_PAGE_PUBLIC]),
    'Dasbor' => $DB->get_record('my_pages', ['userid' => null, 'name' => MY_PAGE_DEFAULT, 'private' => MY_PAGE_PRIVATE]),
];

$todo = [];
if (!$plugin->visible) {
    $todo[] = ['plugin'];
}
foreach ($targets as $label => $page) {
    if (!$page) {
        mtrace("Halaman sistem $label tidak ada di my_pages.");
        continue;
    }
    mtrace('');
    mtrace("== $label (my_pages id={$page->id}) ==");
    $blocks = $DB->get_records_select('block_instances',
        "parentcontextid = :ctx AND pagetypepattern = 'my-index' AND (subpagepattern IS NULL OR subpagepattern = :sub)",
        ['ctx' => $sys->id, 'sub' => (string) $page->id], 'id');
    $positions = $DB->get_records('block_positions',
        ['contextid' => $sys->id, 'pagetype' => 'my-index', 'subpage' => (string) $page->id], '',
        'blockinstanceid, id, visible, region');
    $overview = null;
    foreach ($blocks as $b) {
        $pos = $positions[$b->id] ?? null;
        mtrace(sprintf('  #%d %s region=%s subpage=%s visible=%d',
            $b->id, $b->blockname, $pos->region ?? $b->defaultregion, $b->subpagepattern ?? 'semua',
            $pos ? (int) $pos->visible : 1));
        if ($b->blockname === 'myoverview') {
            $overview = $b;
        }
    }
    if (!$overview) {
        $todo[] = ['add', $label, $page];
    } else {
        $pos = $positions[$overview->id] ?? null;
        if ($pos && (!$pos->visible || $pos->region !== 'content')) {
            $todo[] = ['show', $label, $pos];
        }
    }
}

$userdash = $DB->count_records_select('my_pages', 'userid IS NOT NULL AND name = ? AND private = ?',
    [MY_PAGE_DEFAULT, MY_PAGE_PRIVATE]);
mtrace('');
mtrace("User dengan Dasbor pribadi (tidak ikut Dasbor default): $userdash");
$nooverview = $DB->get_fieldset_sql(
    "SELECT u.username
       FROM {my_pages} p
       JOIN {user} u ON u.id = p.userid
       JOIN {context} ctx ON ctx.instanceid = p.userid AND ctx.contextlevel = :lvl
      WHERE p.name = :name AND p.private = :priv
        AND NOT EXISTS (SELECT 1 FROM {block_instances} bi
                         WHERE bi.parentcontextid = ctx.id AND bi.blockname = 'myoverview'
                           AND bi.pagetypepattern = 'my-index')",
    ['lvl' => CONTEXT_USER, 'name' => MY_PAGE_DEFAULT, 'priv' => MY_PAGE_PRIVATE]
);
mtrace('Dasbor pribadi tanpa Course overview: ' . count($nooverview)
    . ($nooverview ? ' (' . implode(', ', array_slice($nooverview, 0, 10)) . (count($nooverview) > 10 ? ', ...' : '') . ')' : ''));

mtrace('');
if (!$todo) {
    mtrace('Tidak ada yang perlu diperbaiki.');
    exit(0);
}
foreach ($todo as $t) {
    mtrace('Perlu: ' . ($t[0] === 'plugin' ? 'aktifkan plugin block_myoverview'
        : ($t[0] === 'add' ? "tambah blok myoverview di {$t[1]}" : "tampilkan blok myoverview di {$t[1]}")));
}
if (!$confirm) {
    mtrace('Jalankan ulang dengan --confirm untuk memperbaiki.');
    exit(0);
}

foreach ($todo as $t) {
    if ($t[0] === 'plugin') {
        $DB->set_field('block', 'visible', 1, ['id' => $plugin->id]);
    } else if ($t[0] === 'add') {
        $DB->insert_record('block_instances', (object) [
            'blockname' => 'myoverview',
            'parentcontextid' => $sys->id,
            'showinsubcontexts' => 0,
            'requiredbytheme' => 0,
            'pagetypepattern' => 'my-index',
            'subpagepattern' => (string) $t[2]->id,
            'defaultregion' => 'content',
            'defaultweight' => -10,
            'configdata' => '',
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
    } else {
        $DB->delete_records('block_positions', ['id' => $t[2]->id]);
    }
}
purge_all_caches();
mtrace('Selesai. Buka /my/ dan /my/courses.php lagi (Ctrl+Shift+R).');
