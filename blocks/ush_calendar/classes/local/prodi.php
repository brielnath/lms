<?php
namespace block_ush_calendar\local;

defined('MOODLE_INTERNAL') || die();

use moodle_url;

/**
 * USH study-program mapping for calendar + navbar Kategori.
 * Selaras skema kode resmi (IDM06 = Informatika/SIF, IDM08 = MBI, dst).
 */
class prodi {
    /**
     * @return array<string, array{label: string, prefixes: string[], needles: string[], foldercode: string}>
     */
    public static function list(): array {
        return [
            'sif' => [
                'label' => 'Sistem Informasi',
                'foldercode' => 'SIF',
                'prefixes' => ['IDM06', 'SIF'],
                'needles' => ['Sistem Informasi', '(SIF)'],
            ],
            'sbd' => [
                'label' => 'Bisnis Digital',
                'foldercode' => 'SBD',
                'prefixes' => ['IDM05', 'SBD'],
                'needles' => ['Bisnis Digital', '(SBD)'],
            ],
            'sgz' => [
                'label' => 'Ilmu Gizi',
                'foldercode' => 'SGZ',
                'prefixes' => ['IDM03', 'SGZ', 'KGZ'],
                'needles' => ['Ilmu Gizi', '(SGZ)'],
            ],
            'hkm' => [
                'label' => 'Hukum Bisnis',
                'foldercode' => 'HKM',
                'prefixes' => ['IDM07', 'HKM'],
                'needles' => ['Hukum Bisnis', '(HKM)', 'Hukum (HKM)'],
            ],
            'mbi' => [
                'label' => 'Manajemen Bisnis Internasional',
                'foldercode' => 'MBI',
                'prefixes' => ['IDM08'],
                'needles' => ['Manajemen Bisnis Internasional', '(MBI)'],
            ],
            'tpn' => [
                'label' => 'Teknologi Pangan',
                'foldercode' => 'TPN',
                'prefixes' => ['IDM04', 'IDE04', 'IDE'],
                'needles' => ['Teknologi Pangan', '(TPN)'],
            ],
            'bki' => [
                'label' => 'Bahasa dan Kebudayaan Inggris',
                'foldercode' => 'BKI',
                'prefixes' => ['IDM09'],
                'needles' => ['Bahasa dan Kebudayaan Inggris', '(BKI)'],
            ],
            'fith' => [
                'label' => 'Fakultas Ilmu Terapan dan Humaniora',
                'foldercode' => 'FITH',
                'prefixes' => ['IFM01'],
                'needles' => ['Fakultas Ilmu Terapan dan Humaniora', '(FITH)'],
            ],
            'fthb' => [
                'label' => 'Fakultas Teknologi Hukum dan Bisnis',
                'foldercode' => 'FTHB',
                'prefixes' => ['IFM02', 'IDM02'],
                'needles' => ['Fakultas Teknologi Hukum dan Bisnis', '(FTHB)'],
            ],
            's2sbd' => [
                'label' => 'Pascasarjana Bisnis Digital',
                'foldercode' => 'S2SBD',
                'prefixes' => ['GDM5', 'GDM'],
                'needles' => ['Pascasarjana Bisnis Digital', '(S2SBD)'],
            ],
            'mku' => [
                'label' => 'Mata Kuliah Umum',
                'foldercode' => 'MKU',
                'prefixes' => ['IUM', 'MKU', 'USH'],
                'needles' => ['Mata Kuliah Umum', '(MKU)'],
            ],
        ];
    }

    public static function label(string $code): string {
        $list = self::list();
        return $list[$code]['label'] ?? $code;
    }

    /**
     * Deteksi prodi dari shortname / nama kategori.
     * Prioritas: skema resmi IDM/IUM → prefix panjang → needles kategori.
     */
    public static function detect(string $shortname, string $categoryname = ''): string {
        global $CFG;

        $ownerfile = $CFG->dirroot . '/admin/cli/ush_course_owner.php';
        if (is_readable($ownerfile)) {
            require_once($ownerfile);
            if (function_exists('ush_prodi_from_code')) {
                $folder = strtolower(ush_prodi_from_code($shortname));
                if ($folder !== '' && array_key_exists($folder, self::list())) {
                    return $folder;
                }
                // S2SBD dll.
                foreach (self::list() as $code => $info) {
                    if (strcasecmp($info['foldercode'], ush_prodi_from_code($shortname)) === 0) {
                        return $code;
                    }
                }
            }
        }

        $code = strtoupper(trim($shortname));
        $code = preg_replace('/_20\d{6}(GANJIL|GENAP)$/i', '', $code) ?? $code;

        // Prefix terpanjang dulu (IDM06 sebelum IDM0 / IDM).
        $best = '';
        $bestlen = 0;
        foreach (self::list() as $key => $prodi) {
            foreach ($prodi['prefixes'] as $prefix) {
                $prefix = strtoupper($prefix);
                if ($prefix !== '' && str_starts_with($code, $prefix) && strlen($prefix) > $bestlen) {
                    $best = $key;
                    $bestlen = strlen($prefix);
                }
            }
        }
        if ($best !== '') {
            return $best;
        }

        foreach (self::list() as $key => $prodi) {
            foreach ($prodi['needles'] as $needle) {
                if ($needle !== '' && stripos($categoryname, $needle) !== false) {
                    return $key;
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
     * Navbar Kategori: arahkan ke folder prodi TA terbaru bila ada,
     * kalau tidak ke filter kalender dasbor.
     *
     * @return array<int, array{name: string, url: string}>
     */
    public static function navbar_items(): array {
        global $DB;

        $items = [];
        foreach (self::list() as $code => $prodi) {
            $url = self::category_browse_url($prodi['foldercode'], $prodi['needles']);
            if ($url === '') {
                $url = (new moodle_url('/my/index.php', ['ushcalprodi' => $code]))->out(false);
            }
            $items[] = [
                'name' => $prodi['label'],
                'url' => $url,
            ];
        }
        $items[] = [
            'name' => 'Semua kategori',
            'url' => (new moodle_url('/course/index.php'))->out(false),
        ];
        return $items;
    }

    /**
     * Cari kategori prodi: utamakan 2026/2027, lalu 2025/2026, lalu nama mengandung (KODE).
     */
    private static function category_browse_url(string $foldercode, array $needles): string {
        global $DB;

        $patterns = [
            '%(' . $foldercode . ')%2026/2027%',
            '%(' . $foldercode . ')%2025/2026%',
            '%(' . $foldercode . ')%',
        ];
        foreach ($patterns as $like) {
            $cat = $DB->get_record_sql(
                "SELECT id, name FROM {course_categories}
                  WHERE " . $DB->sql_like('name', ':n') . "
               ORDER BY sortorder",
                ['n' => $like]
            );
            if ($cat) {
                return (new moodle_url('/course/index.php', ['categoryid' => $cat->id]))->out(false);
            }
        }

        foreach ($needles as $needle) {
            if ($needle === '') {
                continue;
            }
            $cat = $DB->get_record_sql(
                "SELECT id FROM {course_categories}
                  WHERE " . $DB->sql_like('name', ':n') . "
               ORDER BY sortorder",
                ['n' => '%' . $DB->sql_like_escape($needle) . '%']
            );
            if ($cat) {
                return (new moodle_url('/course/index.php', ['categoryid' => $cat->id]))->out(false);
            }
        }
        return '';
    }
}
