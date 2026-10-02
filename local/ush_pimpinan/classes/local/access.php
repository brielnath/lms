<?php
namespace local_ush_pimpinan\local;

defined('MOODLE_INTERNAL') || die();

class access {

    public static function can_view(): bool {
        return self::resolve('') !== null;
    }

    /**
     * Universitas melihat semua fakultas. Fakultas terkunci pada idnumber FAK_{kode}.
     * Parameter fakultas hanya dipakai akun universitas.
     */
    public static function resolve(string $requestedfaculty): ?scope {
        global $USER;

        $system = \context_system::instance();
        $isuniv = has_capability('local/ush_pimpinan:viewuniversity', $system);
        $isfaculty = has_capability('local/ush_pimpinan:viewfaculty', $system);
        $requested = strtoupper(trim($requestedfaculty));

        if ($isuniv) {
            if ($requested !== '' && catalog::faculty($requested)) {
                return new scope('faculty', $requested, true, true);
            }
            return new scope('univ', null, true, false);
        }

        if ($isfaculty) {
            $own = self::faculty_from_idnumber((string) ($USER->idnumber ?? ''));
            if ($own === null) {
                throw new \moodle_exception('facultyunlinked', 'local_ush_pimpinan');
            }
            return new scope('faculty', $own, false, false);
        }

        return null;
    }

    public static function faculty_from_idnumber(string $idnumber): ?string {
        $idnumber = strtoupper(trim($idnumber));
        if (preg_match('/^FAK_(FTHB|FITH)$/', $idnumber, $matches)) {
            return $matches[1];
        }
        return null;
    }
}
