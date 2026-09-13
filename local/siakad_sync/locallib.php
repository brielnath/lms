<?php
/**
 * Helper mapping prodi SIAKAD → kode cohort LMS.
 *
 * @package local_siakad_sync
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Map nama prodi SIAKAD ke kode cohort dan label tampilan.
 *
 * Needle lebih spesifik harus di atas (mis. Akuntansi sebelum Bisnis Digital).
 *
 * @param string $prodiname
 * @return array{code: string, label: string}|null
 */
function siakad_map_prodi(string $prodiname): ?array {
    $prodiname = trim($prodiname);
    if ($prodiname === '') {
        return null;
    }

    $rules = [
        ['Akuntansi', 'ABD', 'Akuntansi Bisnis Digital'],
        ['Pariwisata', 'PAR', 'Pariwisata'],
        ['Bahasa', 'BKI', 'Bahasa dan Kebudayaan Inggris'],
        ['Inggris', 'BKI', 'Bahasa dan Kebudayaan Inggris'],
        ['Teknologi Pangan', 'TPG', 'Teknologi Pangan'],
        ['Hukum', 'HKM', 'Hukum Bisnis'],
        ['Manajemen', 'MNJ', 'Manajemen Bisnis Internasional'],
        ['Ilmu Gizi', 'SGZ', 'Ilmu Gizi'],
        ['Gizi', 'SGZ', 'Ilmu Gizi'],
        ['Bisnis Digital', 'SBD', 'Bisnis Digital'],
        ['Sistem Informasi', 'SIF', 'Sistem Informasi'],
        ['Informatika', 'SIF', 'Sistem Informasi'],
        ['SIF', 'SIF', 'Sistem Informasi'],
        ['SBD', 'SBD', 'Bisnis Digital'],
        ['SGZ', 'SGZ', 'Ilmu Gizi'],
        ['HKM', 'HKM', 'Hukum Bisnis'],
        ['MNJ', 'MNJ', 'Manajemen Bisnis Internasional'],
    ];

    foreach ($rules as $rule) {
        if (stripos($prodiname, $rule[0]) !== false) {
            return ['code' => $rule[1], 'label' => $rule[2]];
        }
    }

    return null;
}

/**
 * NIM USH: 06 + YY + PP + nomor, contoh 062401037 → angkatan 2024, prodi 01 (SIF).
 *
 * Digit 1–2 = kode kampus (06), 3–4 = tahun, 5–6 = prodi. Bukan owner kode MK (IDM06).
 *
 * @return array{code: string, label: string, year: string, idnumber: string}|null
 */
function siakad_nim_cohort(string $username): ?array {
    $u = strtolower(trim($username));
    if (!preg_match('/^06(\d{2})(\d{2})\d+$/', $u, $m)) {
        return null;
    }
    $year = 2000 + (int) $m[1];
    if ($year < 2018 || $year > 2032) {
        return null;
    }
    $pp = $m[2];
    $map = [
        '01' => ['SIF', 'Sistem Informasi'],
        '02' => ['SBD', 'Bisnis Digital'],
        '03' => ['SGZ', 'Ilmu Gizi'],
        '04' => ['MNJ', 'Manajemen Bisnis Internasional'],
        '05' => ['HKM', 'Hukum Bisnis'],
        '06' => ['TPG', 'Teknologi Pangan'],
        '07' => ['BKI', 'Bahasa dan Kebudayaan Inggris'],
        '08' => ['PAR', 'Pariwisata'],
        '09' => ['ABD', 'Akuntansi Bisnis Digital'],
    ];
    if (!isset($map[$pp])) {
        return null;
    }
    $yearstr = (string) $year;
    return [
        'code' => $map[$pp][0],
        'label' => $map[$pp][1],
        'year' => $yearstr,
        'idnumber' => $map[$pp][0] . $yearstr,
    ];
}

/**
 * Idnumber cohort yang setara (MNJ/MBI, TPG/TPN).
 *
 * @return string[]
 */
function siakad_cohort_idnumber_aliases(string $idnumber): array {
    if (!preg_match('/^([A-Z0-9]+)(20\d{2})$/', $idnumber, $m)) {
        return [$idnumber];
    }
    $code = $m[1];
    $year = $m[2];
    $groups = [
        'MNJ' => ['MNJ', 'MBI'],
        'MBI' => ['MNJ', 'MBI'],
        'TPG' => ['TPG', 'TPN'],
        'TPN' => ['TPG', 'TPN'],
    ];
    $codes = $groups[$code] ?? [$code];
    $out = [];
    foreach ($codes as $c) {
        $out[] = $c . $year;
    }
    return $out;
}

/**
 * Masukkan user ke cohort angkatan sesuai NIM. Tidak mengeluarkan dari cohort lain.
 *
 * @return string|null idnumber cohort, atau null jika username bukan NIM prodi
 */
function siakad_ensure_user_in_nim_cohort(int $userid, string $username): ?string {
    global $DB;

    $info = siakad_nim_cohort($username);
    if ($info === null) {
        return null;
    }
    list($cohort, ) = siakad_find_or_create_cohort($info['code'], $info['label'], $info['year']);
    if (!$DB->record_exists('cohort_members', ['cohortid' => $cohort->id, 'userid' => $userid])) {
        cohort_add_member($cohort->id, $userid);
    }
    return $info['idnumber'];
}

/**
 * Cari cohort existing (idnumber atau nama lama SIF2023) atau buat baru.
 *
 * @return array{0: stdClass, 1: bool} cohort, created
 */
function siakad_find_or_create_cohort(string $code, string $label, string $year): array {
    global $DB;

    $idnumber = $code . $year;
    $displayname = $label . ' Angkatan ' . $year;

    $cohort = $DB->get_record('cohort', ['idnumber' => $idnumber]);
    if (!$cohort) {
        $cohort = $DB->get_record('cohort', ['name' => $idnumber]);
    }
    if (!$cohort) {
        $cohort = $DB->get_record('cohort', ['name' => $displayname]);
    }

    if (!$cohort) {
        $record = new stdClass();
        $record->name = $displayname;
        $record->idnumber = $idnumber;
        $record->contextid = context_system::instance()->id;
        $record->visible = 1;
        $record->description = 'Mahasiswa ' . $label . ' angkatan ' . $year;
        $record->descriptionformat = FORMAT_HTML;
        $record->id = cohort_add_cohort($record);
        $cohort = $DB->get_record('cohort', ['id' => $record->id], '*', MUST_EXIST);
        return [$cohort, true];
    }

    $changed = false;
    if ((string) $cohort->idnumber !== $idnumber) {
        $cohort->idnumber = $idnumber;
        $changed = true;
    }
    if ($cohort->name === $idnumber || trim($cohort->name) === '') {
        $cohort->name = $displayname;
        $changed = true;
    }
    if ($changed) {
        cohort_update_cohort($cohort);
    }

    return [$cohort, false];
}
