<?php
namespace filter_ushlabels;

defined('MOODLE_INTERNAL') || die();

/**
 * Shows Indonesian course labels in English when the active language is English.
 */
class text_filter extends \core_filters\text_filter {
    /**
     * @param string $text
     * @param array $options
     * @return string
     */
    public function filter($text, array $options = []) {
        return self::translate($text);
    }

    /**
     * Translate known USH labels when the current language is English.
     *
     * @param string $text
     * @return string
     */
    public static function translate(string $text): string {
        $lang = current_language();
        if ($lang !== 'en' && !str_starts_with($lang, 'en')) {
            return $text;
        }
        if ($text === '' || strlen($text) > 400) {
            return $text;
        }

        $phrases = [
            'Fakultas Ilmu Terapan dan Humaniora' => 'Faculty of Applied Sciences and Humanities',
            'Fakultas Teknologi Hukum dan Bisnis' => 'Faculty of Law and Business Technology',
            'Manajemen Bisnis Internasional' => 'International Business Management',
            'Bahasa dan Kebudayaan Inggris' => 'English Language and Culture',
            'Pascasarjana Bisnis Digital' => 'Postgraduate Digital Business',
            'Akuntansi Bisnis Digital' => 'Digital Business Accounting',
            'Informasi Umum &amp; Silabus' => 'General information &amp; syllabus',
            'Informasi Umum & Silabus' => 'General information & syllabus',
            'Ujian Tengah Semester (UTS)' => 'Midterm exam (UTS)',
            'Ujian Akhir Semester (UAS)' => 'Final exam (UAS)',
            'Mata Kuliah Umum' => 'General courses',
            'Sistem Informasi' => 'Information Systems',
            'Bisnis Digital' => 'Digital Business',
            'Ilmu Gizi' => 'Nutrition Science',
            'Hukum Bisnis' => 'Business Law',
            'Hukum (HKM)' => 'Law (HKM)',
            'Teknologi Pangan' => 'Food Technology',
            'Mahasiswa Baru' => 'New students',
            'Semua kategori' => 'All categories',
            'Pariwisata' => 'Tourism',
            'Panduan' => 'Guide',
            'Kategori' => 'Categories',
            'Angkatan' => 'Cohort',
            'Lanjutan' => 'Continuing',
            'Ganjil' => 'Odd',
            'Genap' => 'Even',
        ];
        $text = str_replace(array_keys($phrases), array_values($phrases), $text);
        $text = preg_replace('/\bTA\b/u', 'AY', $text);
        $text = preg_replace('/Pertemuan\s+(\d+)/u', 'Session $1', $text);
        return $text;
    }
}
