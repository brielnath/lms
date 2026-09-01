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
