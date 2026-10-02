<?php
namespace local_ush_pimpinan\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Pemetaan fakultas mengikuti SIAKAD (tabel fakultas + prodi.id_fakultas).
 * Kelas LMS dibaca dari folder tahun akademik, bukan dari basis data SIAKAD saat halaman dibuka.
 */
class catalog {

    public const TARGET_MEETINGS = 16;

    /**
     * @return array<string, array{id:int, name:string, prodi:string[]}>
     */
    public static function faculties(): array {
        return [
            'FTHB' => [
                'id' => 14,
                'name' => 'Fakultas Teknologi, Hukum dan Bisnis',
                'prodi' => ['SIF', 'SBD', 'MBI', 'HKM', 'S2SBD'],
            ],
            'FITH' => [
                'id' => 15,
                'name' => 'Fakultas Ilmu Terapan dan Humaniora',
                'prodi' => ['SGZ', 'TPN', 'BKI', 'PAR', 'ABD'],
            ],
        ];
    }

    public static function faculty(string $code): ?array {
        $code = strtoupper($code);
        $all = self::faculties();
        return $all[$code] ?? null;
    }

    public static function faculty_of_prodi(string $prodicode): ?string {
        $prodicode = strtoupper($prodicode);
        foreach (self::faculties() as $code => $faculty) {
            if (in_array($prodicode, $faculty['prodi'], true) || $prodicode === $code) {
                return $code;
            }
        }
        return null;
    }

    public static function prodi_label(string $code): string {
        global $CFG;
        $code = strtoupper($code);
        if ($code === 'FTHB' || $code === 'FITH') {
            return 'Kelas tingkat fakultas';
        }
        $owner = $CFG->dirroot . '/admin/cli/ush_course_owner.php';
        if (is_readable($owner)) {
            require_once($owner);
            $labels = ush_prodi_labels();
            if (!empty($labels[$code])) {
                return $labels[$code];
            }
        }
        return $code;
    }

    public static function prodi_from_shortname(string $shortname): string {
        global $CFG;
        $owner = $CFG->dirroot . '/admin/cli/ush_course_owner.php';
        if (is_readable($owner)) {
            require_once($owner);
            return strtoupper(ush_prodi_from_code($shortname));
        }
        if (preg_match('/^([A-Za-z]+)/', $shortname, $matches)) {
            return strtoupper($matches[1]);
        }
        return 'MKU';
    }

    /**
     * Folder tahun akademik yang sedang berjalan.
     */
    public static function active_period(): ?\stdClass {
        global $DB;

        $preferred = $DB->get_record('course_categories', ['idnumber' => 'TA_2026_2027___Ganjil']);
        if ($preferred) {
            return $preferred;
        }

        $records = $DB->get_records_select(
            'course_categories',
            'parent = 0 AND ' . $DB->sql_like('idnumber', ':ta', false),
            ['ta' => $DB->sql_like_escape('TA_') . '%'],
            'sortorder DESC',
            '*',
            0,
            1
        );
        if (!$records) {
            return null;
        }
        return reset($records);
    }

    /**
     * Status kalender hari ini untuk 2026/2027 Ganjil, mengikuti jadwal pertemuan LMS.
     *
     * @return array{key:string, label:string, detail:string, tone:string}
     */
    public static function calendar_today(?\stdClass $period): array {
        $unknown = [
            'key' => 'unknown',
            'label' => 'Kalender belum dipetakan',
            'detail' => 'Periode ini belum punya jendela UTS dan pekan perkuliahan di dasbor.',
            'tone' => 'is-empty',
        ];
        if (!$period || (($period->idnumber ?? '') !== 'TA_2026_2027___Ganjil' && stripos($period->name, '2026/2027') === false)) {
            return $unknown;
        }

        $tz = new \DateTimeZone('Asia/Jakarta');
        $today = new \DateTime('now', $tz);
        $windows = [
            ['start' => '2026-09-07', 'end' => '2026-09-25', 'key' => 'kuliah', 'label' => 'Pekan Perkuliahan', 'tone' => 'is-good'],
            ['start' => '2026-09-26', 'end' => '2026-10-06', 'key' => 'uts', 'label' => 'UTS', 'tone' => 'is-warn'],
            ['start' => '2026-10-07', 'end' => '2026-11-23', 'key' => 'kuliah', 'label' => 'Pekan Perkuliahan', 'tone' => 'is-good'],
        ];
        foreach ($windows as $window) {
            $start = new \DateTime($window['start'] . ' 00:00:00', $tz);
            $end = new \DateTime($window['end'] . ' 23:59:59', $tz);
            if ($today >= $start && $today <= $end) {
                return [
                    'key' => $window['key'],
                    'label' => $window['label'],
                    'detail' => self::format_date($start) . ' – ' . self::format_date($end),
                    'tone' => $window['tone'],
                ];
            }
        }
        $startterm = new \DateTime('2026-09-07 00:00:00', $tz);
        if ($today < $startterm) {
            return [
                'key' => 'libur',
                'label' => 'Belum mulai perkuliahan',
                'detail' => 'Perkuliahan dimulai ' . self::format_date($startterm),
                'tone' => 'is-empty',
            ];
        }
        return [
            'key' => 'selesai',
            'label' => 'Di luar pekan perkuliahan',
            'detail' => 'Jadwal pertemuan yang tersimpan berakhir 23 November 2026',
            'tone' => 'is-empty',
        ];
    }

    public static function format_date(\DateTime $date): string {
        $months = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];
        return (int) $date->format('j') . ' ' . $months[(int) $date->format('n')] . ' ' . $date->format('Y');
    }
}
