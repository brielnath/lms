<?php
/**
 * Cari akun dosen LMS.
 * Urutan: idnumber = id lecture SIAKAD, username lama dosen_{id}, lalu email/username SIAKAD.
 */
defined('MOODLE_INTERNAL') || die();

function ush_find_dosen_user(int $lecid, string $email = ''): ?stdClass {
    global $DB, $CFG;

    $host = $CFG->mnet_localhost_id;
    if ($lecid > 0) {
        $byid = $DB->get_record('user', [
            'idnumber' => (string) $lecid,
            'mnethostid' => $host,
            'deleted' => 0,
        ]);
        if ($byid) {
            return $byid;
        }
        $byold = $DB->get_record('user', [
            'username' => 'dosen_' . $lecid,
            'mnethostid' => $host,
            'deleted' => 0,
        ]);
        if ($byold) {
            return $byold;
        }
    }

    $email = core_text::strtolower(trim($email));
    if ($email !== '' && str_contains($email, '@')) {
        $bymail = $DB->get_record_select(
            'user',
            'mnethostid = :h AND deleted = 0 AND (username = :u OR ' . $DB->sql_equal('email', ':e', false, true) . ')',
            ['h' => $host, 'u' => $email, 'e' => $email]
        );
        if ($bymail) {
            return $bymail;
        }
    }

    return null;
}
