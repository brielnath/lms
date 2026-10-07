<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Redirect pimpinan (univ/fak) dari /my/ ke halaman monitoring.
 * Dipanggil otomatis oleh Moodle via after_config hook.
 */
function local_ush_pimpinan_after_config(): void {
    global $USER, $CFG, $DB;

    if (!isloggedin() || isguestuser() || CLI_SCRIPT) {
        return;
    }

    // Hanya redirect dari halaman dasbor default /my/ atau /?redirect=...
    $uri = ltrim($_SERVER['REQUEST_URI'] ?? '', '/');
    $ismy = (strpos($uri, 'my/') === 0 || $uri === 'my' || $uri === '' || $uri === 'index.php');
    if (!$ismy) {
        return;
    }

    // Cek role pimpinan
    try {
        $roles = $DB->get_records_list('role', 'shortname', ['ushpimpinanuniv', 'ushpimpinanfak'], '', 'id');
        if (empty($roles)) {
            return;
        }
        $roleids = array_keys($roles);
        [$insql, $inparams] = $DB->get_in_or_equal($roleids);
        $ispimpinan = $DB->record_exists_sql(
            "SELECT 1 FROM {role_assignments} ra WHERE ra.userid = ? AND ra.roleid $insql",
            array_merge([$USER->id], $inparams)
        );
        if ($ispimpinan) {
            redirect(new moodle_url('/local/ush_pimpinan/index.php'));
        }
    } catch (Exception $e) {
        // Jangan redirect jika ada error DB
    }
}

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
