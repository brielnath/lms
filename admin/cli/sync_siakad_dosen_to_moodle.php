<?php
/**
 * Skrip untuk Sinkronisasi 50 Dosen Resmi SIAKAD ke Moodle
 * dan Enrolment Dosen ke Mata Kuliah yang Diampu (Role Editing Teacher).
 */
define('CLI_SCRIPT', true);
@error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->dirroot . '/group/lib.php');
require_once($CFG->libdir . '/moodlelib.php');

mtrace("==================================================");
mtrace("👨‍🏫 SINKRONISASI DOSEN DARI SIAKAD KE MOODLE");
mtrace("==================================================");

$token = "320|Gs2KVb4VtawDCnc2foDC8Up9UXbe7c95NGgu1OKZcfe5622b";

function api_get($url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer 320|Gs2KVb4VtawDCnc2foDC8Up9UXbe7c95NGgu1OKZcfe5622b',
        'Accept: application/json'
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    $result = curl_exec($ch);
    curl_close($ch);
    return json_decode($result, true);
}

// 1. Fetch data dosen & pemetaan dari API SIAKAD
$page = 1;
$lecturers = [];
$mappings = [];

while (true) {
    $res = api_get("https://siakad.sugenghartono.ac.id/api/grades-per-course?page={$page}");
    $items = $res['data'] ?? [];
    if (empty($items)) break;

    foreach ($items as $mhs) {
        $grades = $mhs['grade'] ?? [];
        foreach ($grades as $g) {
            $lec_id = $g['id_lecture'] ?? null;
            $lec_name = trim($g['lecture_name'] ?? '');
            $code = trim($g['lesson_code'] ?? '');

            if ($lec_id && !empty($lec_name) && $lec_name !== 'Unknown') {
                if (!isset($lecturers[$lec_id])) {
                    $lecturers[$lec_id] = $lec_name;
                }
                if (!empty($code)) {
                    $mappings[$code][$lec_id] = $lec_name;
                }
            }
        }
    }

    $page++;
    if ($page > 20) break;
}

mtrace("📋 Total Dosen Valid Terdeteksi: " . count($lecturers));

// Get Editing Teacher Role ID
$teacher_role = $DB->get_record('role', ['shortname' => 'editingteacher']);
$manual_enrol = $DB->get_record('enrol', ['enrol' => 'manual'], '*', IGNORE_MULTIPLE);

$created_dosen = 0;
$enrolled_dosen = 0;

foreach ($lecturers as $lec_id => $full_name) {
    $username = "dosen_" . $lec_id;
    
    // Cek apakah akun dosen sudah ada
    $user = $DB->get_record('user', ['username' => $username]);
    
    if (!$user) {
        // Pisahkan nama depan dan belakang
        $parts = explode(' ', $full_name);
        $firstname = $parts[0];
        $lastname = implode(' ', array_slice($parts, 1));
        if (empty($lastname)) $lastname = 'Dosen USH';

        $clean_user = new stdClass();
        $clean_user->username = $username;
        $clean_user->password = hash_internal_user_password('DosenUSH2026!');
        $clean_user->firstname = $firstname;
        $clean_user->lastname = $lastname;
        $clean_user->email = "dosen.{$lec_id}@sugenghartono.ac.id";
        $clean_user->confirmed = 1;
        $clean_user->mnethostid = $CFG->mnet_localhost_id;
        $clean_user->lang = 'en';
        $clean_user->timecreated = time();
        $clean_user->timemodified = time();

        $user_id = $DB->insert_record('user', $clean_user);
        $user = $DB->get_record('user', ['id' => $user_id]);
        $created_dosen++;
        mtrace("  ✨ Akun Dosen Dibuat: {$full_name} ({$username})");
    }

    // Assign dosen ke mata kuliah yang diampu
    foreach ($mappings as $code => $lecs) {
        if (isset($lecs[$lec_id])) {
            // Cari course di Moodle berdasarkan shortname
            $course = $DB->get_record('course', ['shortname' => $code]);
            if (!$course) {
                $clean_code = str_replace(['*', ' '], '', $code);
                $course = $DB->get_record('course', ['shortname' => $clean_code]);
            }

            if ($course) {
                // Enrol dosen dengan role Editing Teacher
                $enrol_instance = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'manual']);
                if ($enrol_instance && $teacher_role) {
                    // Enrol & assign role jika belum
                    $is_enrolled = $DB->record_exists('user_enrolments', ['enrolid' => $enrol_instance->id, 'userid' => $user->id]);
                    if (!$is_enrolled) {
                        enrol_try_internal_enrol($course->id, $user->id, $teacher_role->id);
                        $enrolled_dosen++;
                        mtrace("     🎓 Assigned to Course: [{$code}] {$course->fullname}");
                    }
                }
            }
        }
    }
}

rebuild_course_cache(0, true);
purge_all_caches();

mtrace("\n==================================================");
mtrace("🎉 SINKRONISASI DOSEN SELESAI!");
mtrace("📊 Rincian:");
mtrace("   • Akun Dosen Baru Dibuat    : {$created_dosen}");
mtrace("   • Dosen Enrolled ke Kelas   : {$enrolled_dosen}");
mtrace("==================================================");
