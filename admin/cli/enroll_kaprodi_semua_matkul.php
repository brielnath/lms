<?php
/**
 * Enroll Kaprodi sebagai Editing Teacher di SEMUA matkul prodi mereka
 */
define('CLI_SCRIPT', true);
@error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);
require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/course/lib.php');

$SHORTNAME_SUFFIX = '_20252026Ganjil';
$teacher_role = $DB->get_record('role', ['shortname' => 'editingteacher']);

// Mapping Kaprodi → username → kode prodi yang diampu
$kaprodi_map = [
    'dosen_64' => [
        'nama'  => 'Dwi Utari Iswavigra, S.T., M.Kom.',
        'prodi' => 'Sistem Informasi / Informatika',
        'codes' => ['SIF'],
    ],
    'dosen_60' => [
        'nama'  => 'Yuniars Renowening, M.Gz',
        'prodi' => 'Ilmu Gizi',
        'codes' => ['SGZ', 'KGZ'],
    ],
    'dosen_839' => [
        'nama'  => 'Graceilla Kristia S.B., S.E., M.B.A',
        'prodi' => 'Bisnis Digital',
        'codes' => ['SBD'],
    ],
];

mtrace("==================================================");
mtrace("👨‍🏫 ENROLL KAPRODI KE SEMUA MATKUL PRODI");
mtrace("==================================================\n");

// Get all new semester courses
$all_courses = $DB->get_records_sql("
    SELECT id, shortname, fullname
    FROM {course}
    WHERE shortname LIKE ?
    ORDER BY shortname
", ['%' . $SHORTNAME_SUFFIX]);

mtrace("📋 Total Kelas Semester Baru: " . count($all_courses) . "\n");

foreach ($kaprodi_map as $username => $info) {
    $user = $DB->get_record('user', ['username' => $username]);
    if (!$user) {
        mtrace("⚠️  User $username tidak ditemukan, skip...");
        continue;
    }

    mtrace("🎓 Kaprodi: {$info['nama']} ({$username})");
    mtrace("   Prodi: {$info['prodi']}");

    $enrolled = 0;
    $already  = 0;

    foreach ($all_courses as $course) {
        $code = str_replace($SHORTNAME_SUFFIX, '', $course->shortname);

        // Check if course code starts with any of the prodi prefixes
        $match = false;
        foreach ($info['codes'] as $prefix) {
            if (str_starts_with($code, $prefix)) {
                $match = true;
                break;
            }
        }
        if (!$match) continue;

        // Check if already enrolled
        $is_enrolled = $DB->record_exists_sql("
            SELECT 1 FROM {enrol} e
            JOIN {user_enrolments} ue ON ue.enrolid = e.id
            WHERE e.courseid = ? AND ue.userid = ?
        ", [$course->id, $user->id]);

        if (!$is_enrolled) {
            enrol_try_internal_enrol($course->id, $user->id, $teacher_role->id);
            $enrolled++;
        } else {
            $already++;
        }
    }

    mtrace("   ✅ Baru di-enroll   : $enrolled kelas");
    mtrace("   ℹ️  Sudah terdaftar : $already kelas\n");
}

// Also enroll in OLD semester courses (category 1)
mtrace("📌 Enroll Kaprodi juga ke kelas LAMA (semester sebelumnya)...");
$old_courses = $DB->get_records_sql("
    SELECT id, shortname, fullname
    FROM {course}
    WHERE shortname NOT LIKE ? AND id != 1
    ORDER BY shortname
", ['%' . $SHORTNAME_SUFFIX]);

foreach ($kaprodi_map as $username => $info) {
    $user = $DB->get_record('user', ['username' => $username]);
    if (!$user) continue;

    $enrolled_old = 0;
    foreach ($old_courses as $course) {
        $match = false;
        foreach ($info['codes'] as $prefix) {
            if (str_starts_with($course->shortname, $prefix)) {
                $match = true;
                break;
            }
        }
        if (!$match) continue;

        $is_enrolled = $DB->record_exists_sql("
            SELECT 1 FROM {enrol} e
            JOIN {user_enrolments} ue ON ue.enrolid = e.id
            WHERE e.courseid = ? AND ue.userid = ?
        ", [$course->id, $user->id]);

        if (!$is_enrolled) {
            enrol_try_internal_enrol($course->id, $user->id, $teacher_role->id);
            $enrolled_old++;
        }
    }
    if ($enrolled_old > 0) {
        mtrace("   {$info['nama']}: +$enrolled_old kelas lama");
    }
}

rebuild_course_cache(0, true);
purge_all_caches();

mtrace("\n==================================================");
mtrace("🎉 SELESAI! Semua Kaprodi sudah di-enroll ke seluruh kelas prodinya.");
mtrace("==================================================");
