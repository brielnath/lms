<?php
/**
 * Cek kenapa matkul semester lain muncul di "Kursus saya" (hanya membaca, tidak mengubah apa pun).
 *
 *   php admin/cli/ush_cek_kursusku.php --all                       (semua dosen sekaligus)
 *   php admin/cli/ush_cek_kursusku.php --user=email_atau_username  (rinci satu dosen)
 */
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/theme/academi/lib.php');

$username = '';
$all = false;
foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--user=')) {
        $username = trim(substr($arg, 7));
    } else if ($arg === '--all') {
        $all = true;
    }
}

$active = theme_academi_ush_active_semester();
mtrace('Tema aktif           : ' . $CFG->theme);
mtrace('Semester aktif       : ' . ($active ? $active->idnumber . ' (' . $active->name . ')' : 'TIDAK DITEMUKAN'));
mtrace('Override tema        : [' . get_config('theme_academi', 'ush_active_semester') . ']');
mtrace('Terakhir isi field   : ' . userdate((int) get_config('theme_academi', 'ush_semester_synced')));

$values = $DB->get_records_sql(
    "SELECT cd.value, COUNT(*) AS n
       FROM {customfield_data} cd
       JOIN {customfield_field} f ON f.id = cd.fieldid AND f.shortname = 'ush_semester'
   GROUP BY cd.value"
);
mtrace('Isi field ush_semester:');
foreach ($values as $v) {
    mtrace('  [' . $v->value . '] ' . $v->n . ' kursus');
}

if ($active) {
    $wrong = $DB->get_records_sql(
        "SELECT c.id, c.shortname, cc.name AS catname
           FROM {course} c
           JOIN {course_categories} cc ON cc.id = c.category
           JOIN {customfield_data} cd ON cd.instanceid = c.id
           JOIN {customfield_field} f ON f.id = cd.fieldid AND f.shortname = 'ush_semester'
          WHERE cd.value = :active AND " . $DB->sql_like('c.shortname', ':suffix', false, false, true),
        ['active' => $active->idnumber, 'suffix' => '%' . preg_replace('/^TA_(\d{4})_(\d{4})___(\w+)$/', '$1$2$3', $active->idnumber)]
    );
    mtrace('Kursus bertanda semester aktif tapi shortname-nya semester lain: ' . count($wrong));
    foreach (array_slice($wrong, 0, 30) as $c) {
        mtrace('  ' . $c->shortname . ' | kategori: ' . $c->catname);
    }
}

if ($all && $active) {
    $rows = $DB->get_records_sql(
        "SELECT DISTINCT " . $DB->sql_concat('u.id', "'-'", 'c.id') . " AS k, u.username, c.shortname, c.visible
           FROM {user} u
           JOIN {role_assignments} ra ON ra.userid = u.id
           JOIN {role} r ON r.id = ra.roleid AND r.shortname IN ('editingteacher', 'teacher')
           JOIN {context} ctx ON ctx.id = ra.contextid AND ctx.contextlevel = :ctxlevel
           JOIN {course} c ON c.id = ctx.instanceid
           JOIN {customfield_data} cd ON cd.instanceid = c.id
           JOIN {customfield_field} f ON f.id = cd.fieldid AND f.shortname = 'ush_semester'
          WHERE u.deleted = 0 AND cd.value = :active
            AND " . $DB->sql_like('c.shortname', ':suffix', false, false, true) . "
       ORDER BY u.username, c.shortname",
        [
            'ctxlevel' => CONTEXT_COURSE,
            'active' => $active->idnumber,
            'suffix' => '%' . preg_replace('/^TA_(\d{4})_(\d{4})___(\w+)$/', '$1$2$3', $active->idnumber),
        ]
    );
    $peruser = [];
    foreach ($rows as $r) {
        $peruser[$r->username][] = $r->shortname;
    }
    mtrace('');
    mtrace('Dosen yang "Kursus saya"-nya memuat kursus semester lain: ' . count($peruser));
    foreach ($peruser as $u => $list) {
        mtrace('  ' . $u . ': ' . implode(', ', $list));
    }
}

if ($username !== '') {
    $user = $DB->get_record_select('user', 'deleted = 0 AND (username = :u OR email = :e)', ['u' => $username, 'e' => $username], '*', IGNORE_MULTIPLE);
    if (!$user) {
        mtrace('User tidak ditemukan: ' . $username);
        exit(1);
    }
    mtrace('');
    mtrace('Kursus yang terdaftar untuk ' . $user->username . ':');
    $courses = enrol_get_all_users_courses($user->id, false, 'id, shortname, visible');
    foreach ($courses as $c) {
        $value = (string) $DB->get_field_sql(
            "SELECT cd.value FROM {customfield_data} cd
               JOIN {customfield_field} f ON f.id = cd.fieldid AND f.shortname = 'ush_semester'
              WHERE cd.instanceid = ?",
            [$c->id]
        );
        $shown = $active && $value === $active->idnumber;
        $hiddenpref = get_user_preferences('block_myoverview_hidden_course_' . $c->id, 0, $user);
        mtrace(sprintf('  %-4s %-40s semester=[%s]%s%s',
            $shown ? 'TAMPIL' : '-',
            $c->shortname,
            $value,
            $c->visible ? '' : ' (tersembunyi)',
            $hiddenpref ? ' (disembunyikan dari Kursus saya)' : ''
        ));
    }
}
