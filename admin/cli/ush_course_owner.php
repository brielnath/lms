<?php
/**
 * Pemetaan kode MK USH → folder prodi LMS.
 * Format resmi: 3 huruf + angka (I/G/D, U/F/D, M/E, pemilik, nomor urut).
 */
defined('MOODLE_INTERNAL') || die();

function ush_prodi_labels(): array {
    return [
        'SIF' => 'Sistem Informasi',
        'SBD' => 'Bisnis Digital',
        'SGZ' => 'Ilmu Gizi',
        'HKM' => 'Hukum Bisnis',
        'MBI' => 'Manajemen Bisnis Internasional',
        'TPN' => 'Teknologi Pangan',
        'BKI' => 'Bahasa dan Kebudayaan Inggris',
        'FITH' => 'Fakultas Ilmu Terapan dan Humaniora',
        'FTHB' => 'Fakultas Teknologi Hukum dan Bisnis',
        'PAR' => 'Pariwisata',
        'ABD' => 'Akuntansi Bisnis Digital',
        'S2SBD' => 'Pascasarjana Bisnis Digital',
        'MKU' => 'Mata Kuliah Umum',
    ];
}

function ush_owner_to_prodi(string $owner): string {
    $map = [
        '0' => 'MKU',
        '1' => 'FITH',
        '2' => 'FTHB',
        '3' => 'SGZ',
        '4' => 'TPN',
        '5' => 'SBD',
        '6' => 'SIF',
        '7' => 'HKM',
        '8' => 'MBI',
        '9' => 'BKI',
        '10' => 'PAR',
        '11' => 'ABD',
    ];
    return $map[$owner] ?? 'MKU';
}

/**
 * True jika kode MK mengikuti skema resmi 3 huruf + angka (I/G/D, U/F/D, M/E, …).
 */
function ush_is_official_scheme_code(string $code): bool {
    $c = strtoupper(trim($code));
    $c = preg_replace('/_20\d{6}(GANJIL|GENAP)$/i', '', $c) ?? $c;
    return (bool) preg_match('/^[IGD][UFD][ME]\d/', $c);
}

/**
 * Ambil kode folder LMS dari shortname/kode MK (IUM001, IDM0602, SBD403, …).
 */
function ush_prodi_from_code(string $code): string {
    $c = strtoupper(trim($code));
    $c = preg_replace('/_20\d{6}(GANJIL|GENAP)$/i', '', $c) ?? $c;

    if (str_starts_with($c, 'SIF')) {
        return 'SIF';
    }
    if (str_starts_with($c, 'SBD')) {
        return 'SBD';
    }
    if (str_starts_with($c, 'SGZ') || str_starts_with($c, 'KGZ')) {
        return 'SGZ';
    }
    if (str_starts_with($c, 'HKM')) {
        return 'HKM';
    }
    if (str_starts_with($c, 'MKU') || str_starts_with($c, 'IUM') || str_starts_with($c, 'USH')) {
        return 'MKU';
    }

    if (preg_match('/^([IGD])[UFD][ME](\d+)/', $c, $m)) {
        $jenjang = $m[1];
        $digits = $m[2];
        if (strlen($digits) >= 2 && in_array(substr($digits, 0, 2), ['10', '11'], true)) {
            $owner = substr($digits, 0, 2);
        } else if (strlen($digits) >= 4) {
            $owner = (string) (int) substr($digits, 0, 2);
        } else {
            $owner = (string) (int) substr($digits, 0, 1);
        }
        $prodi = ush_owner_to_prodi($owner);
        // G = pascasarjana; jangan campur dengan folder S1.
        if ($jenjang === 'G') {
            return 'S2' . $prodi;
        }
        return $prodi;
    }

    return 'MKU';
}
