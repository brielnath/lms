<?php
/**
 * Menu tambah aktivitas dosen dibuat ringkas: hanya jenis di $keep yang bisa ditambah dosen.
 * Aktivitas yang sudah ada di kelas tidak dihapus. Admin tetap melihat semua.
 *
 *   php admin/cli/ush_simplify_activity_chooser.php
 *   php admin/cli/ush_simplify_activity_chooser.php --confirm
 *   php admin/cli/ush_simplify_activity_chooser.php --restore --confirm
 */
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/accesslib.php');

$args = array_slice($argv, 1);
$confirm = in_array('--confirm', $args, true);
$restore = in_array('--restore', $args, true);

$keep = ['label', 'resource', 'folder', 'forum', 'page', 'attendance', 'quiz', 'assign', 'url'];
$roles = ['editingteacher', 'teacher'];

$system = context_system::instance();
$modules = $DB->get_records('modules', null, 'name ASC', 'id, name, visible');

mtrace('=== Menu tambah aktivitas dosen ===');
mtrace('Mode: ' . ($confirm ? 'LIVE' : 'DRY-RUN') . ($restore ? ' (kembalikan semua)' : ''));
mtrace('Tetap tampil: ' . implode(', ', $keep));

$changed = 0;
foreach ($roles as $shortname) {
    $roleid = $DB->get_field('role', 'id', ['shortname' => $shortname]);
    if (!$roleid) {
        continue;
    }
    foreach ($modules as $module) {
        $cap = 'mod/' . $module->name . ':addinstance';
        if (!$DB->record_exists('capabilities', ['name' => $cap])) {
            continue;
        }
        $allow = $restore || in_array($module->name, $keep, true);
        $current = $DB->get_field('role_capabilities', 'permission', [
            'roleid' => $roleid,
            'capability' => $cap,
            'contextid' => $system->id,
        ]);
        $isallowed = ((int) $current === CAP_ALLOW);
        if ($allow === $isallowed || ($allow && $shortname !== 'editingteacher')) {
            continue;
        }
        $used = $DB->count_records('course_modules', ['module' => $module->id]);
        mtrace(sprintf('  %-15s %-12s %s (dipakai %d aktivitas)', $shortname, $module->name, $allow ? 'TAMPIL' : 'SEMBUNYI', $used));
        $changed++;
        if (!$confirm) {
            continue;
        }
        if ($allow) {
            assign_capability($cap, CAP_ALLOW, $roleid, $system->id, true);
        } else {
            unassign_capability($cap, $roleid, $system->id);
        }
    }
}

if ($confirm && $changed) {
    $system->mark_dirty();
    purge_all_caches();
}
mtrace('Perubahan: ' . $changed . ($confirm ? '' : '. DRY-RUN, ulangi dengan --confirm.'));
