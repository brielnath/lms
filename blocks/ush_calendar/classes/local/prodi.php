<?php
namespace block_ush_calendar\local;

defined('MOODLE_INTERNAL') || die();

use moodle_url;

/**
 * USH study-program (prodi) mapping for calendar and navbar filters.
 */
class prodi {
    /**
     * @return array<string, array{label: string, prefixes: string[], needles: string[]}>
     */
    public static function list(): array {
        return [
            'sif' => [
                'label' => 'Sistem Informasi',
                'prefixes' => ['SIF', 'INF', 'IFM'],
                'needles' => ['Sistem Informasi', '(SIF)'],
            ],
            'sbd' => [
                'label' => 'Bisnis Digital',
                'prefixes' => ['SBD', 'BDG', 'FTB'],
                'needles' => ['Bisnis Digital', '(SBD)'],
            ],
            'sgz' => [
                'label' => 'Ilmu Gizi',
                'prefixes' => ['SGZ', 'GZI', 'IDM', 'KGZ', 'FIK'],
                'needles' => ['Ilmu Gizi', '(SGZ)', 'Gizi'],
            ],
            'hkm' => [
                'label' => 'Hukum',
                'prefixes' => ['HKM'],
                'needles' => ['Hukum', '(HKM)'],
            ],
            'mnj' => [
                'label' => 'Manajemen',
                'prefixes' => ['MNJ'],
                'needles' => ['Manajemen (MNJ)', '(MNJ)'],
            ],
            'mku' => [
                'label' => 'Mata Kuliah Umum',
                'prefixes' => ['MKU', 'IUM'],
                'needles' => ['Mata Kuliah Umum', '(MKU)'],
            ],
        ];
    }

    public static function label(string $code): string {
        $list = self::list();
        return $list[$code]['label'] ?? $code;
    }

    public static function detect(string $shortname, string $categoryname): string {
        $prefix = self::shortname_prefix($shortname);
        foreach (self::list() as $code => $prodi) {
            if ($prefix !== '' && in_array($prefix, $prodi['prefixes'], true)) {
                return $code;
            }
        }
        $haystack = $categoryname;
        foreach (self::list() as $code => $prodi) {
            foreach ($prodi['needles'] as $needle) {
                if ($needle !== '' && stripos($haystack, $needle) !== false) {
                    return $code;
                }
            }
        }
        return '';
    }

    public static function shortname_prefix(string $shortname): string {
        if (preg_match('/^([A-Za-z]+)/', $shortname, $matches)) {
            return strtoupper($matches[1]);
        }
        return '';
    }

    /**
     * Options for the calendar dropdown.
     *
     * @return array<int, array{id: string, name: string, selected: bool}>
     */
    public static function filter_options(string $selected): array {
        $options = [];
        foreach (self::list() as $code => $prodi) {
            $options[] = [
                'id' => $code,
                'name' => $prodi['label'],
                'selected' => ($code === $selected),
            ];
        }
        return $options;
    }

    /**
     * Navbar Kategori items pointing at the dashboard calendar filter.
     *
     * @return array<int, array{name: string, url: string}>
     */
    public static function navbar_items(): array {
        $items = [];
        foreach (self::list() as $code => $prodi) {
            $items[] = [
                'name' => $prodi['label'],
                'url' => (new moodle_url('/my/index.php', ['ushcalprodi' => $code]))->out(false),
            ];
        }
        return $items;
    }
}
