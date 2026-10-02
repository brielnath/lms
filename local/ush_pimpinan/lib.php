<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Peran kosong untuk akun pimpinan. Pengguna dibuat terpisah, tidak di sini.
 */
function local_ush_pimpinan_ensure_roles(): void {
    global $DB;

    $roles = [
        'ushpimpinanuniv' => [
            'name' => 'Pimpinan Universitas',
            'cap' => 'local/ush_pimpinan:viewuniversity',
        ],
        'ushpimpinanfak' => [
            'name' => 'Pimpinan Fakultas',
            'cap' => 'local/ush_pimpinan:viewfaculty',
        ],
    ];

    $contextid = context_system::instance()->id;
    foreach ($roles as $shortname => $info) {
        if (!$DB->record_exists('capabilities', ['name' => $info['cap']])) {
            continue;
        }
        $roleid = $DB->get_field('role', 'id', ['shortname' => $shortname]);
        if (!$roleid) {
            $roleid = create_role($info['name'], $shortname, $info['name'], '');
        }
        assign_capability($info['cap'], CAP_ALLOW, $roleid, $contextid, true);
    }
}
