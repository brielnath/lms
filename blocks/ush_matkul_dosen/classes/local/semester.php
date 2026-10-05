<?php
namespace block_ush_matkul_dosen\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Academic semester helpers. Ganjil runs September–February, Genap March–August.
 */
class semester {

    /**
     * @param string $suffix e.g. 20262027Ganjil
     * @return array{tahun: string, periode: string, suffix: string, label: string}|null
     */
    public static function from_suffix(string $suffix): ?array {
        if (!preg_match('/^(20\d{2})(20\d{2})(Ganjil|Genap)$/i', trim($suffix), $m)) {
            return null;
        }
        return self::make((int) $m[1], ucfirst(strtolower($m[3])));
    }

    /**
     * Active semester: admin override, otherwise derived from today's date.
     */
    public static function active(?int $time = null): array {
        $override = (string) get_config('block_ush_matkul_dosen', 'activesemester');
        if ($override !== '' && ($sem = self::from_suffix($override))) {
            return $sem;
        }
        $time = $time ?? time();
        $year = (int) date('Y', $time);
        $month = (int) date('n', $time);
        if ($month >= 9) {
            return self::make($year, 'Ganjil');
        }
        if ($month <= 2) {
            return self::make($year - 1, 'Ganjil');
        }
        return self::make($year - 1, 'Genap');
    }

    /**
     * The semester right after the given one.
     */
    public static function next(array $sem): array {
        $start = (int) substr($sem['tahun'], 0, 4);
        return $sem['periode'] === 'Ganjil' ? self::make($start, 'Genap') : self::make($start + 1, 'Ganjil');
    }

    /**
     * Same period one academic year earlier (used while SIAKAD has no data for the upcoming semester yet).
     */
    public static function previous_year(array $sem): array {
        return self::make((int) substr($sem['tahun'], 0, 4) - 1, $sem['periode']);
    }

    private static function make(int $startyear, string $periode): array {
        $tahun = $startyear . '/' . ($startyear + 1);
        return [
            'tahun' => $tahun,
            'periode' => $periode,
            'suffix' => $startyear . ($startyear + 1) . $periode,
            'label' => $tahun . ' ' . $periode,
        ];
    }
}
