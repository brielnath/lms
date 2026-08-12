<?php
define('CLI_SCRIPT', true);

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/enrol/manual/locallib.php');

mtrace("==================================================");
mtrace("🚀 SINKRONISASI OTOMATIS: SIAKAD -> USER & COURSE MOODLE");
mtrace("==================================================");

$mnet_localhostid = !empty($CFG->mnet_localhostid) ? $CFG->mnet_localhostid : 1;

// 1. Ambil data dari API SIAKAD (Menjalankan mock API via PHP CLI)
$php_binary = "C:\\wamp64\\bin\\php\\php8.2.29\\php.exe";
$mock_api_file = "C:\\wamp64\\www\\mock_api_mahasiswa.php";
$json_raw = shell_exec("\"{$php_binary}\" \"{$mock_api_file}\"");

$response = json_decode($json_raw, true);

if (!$response || empty($response['success']) || !isset($response['data'])) {
    mtrace("❌ Gagal membaca respon API SIAKAD!");
    exit(1);
}

$mahasiswa_list = $response['data'];
mtrace("Ditemukan " . count($mahasiswa_list) . " data mahasiswa aktif dari API SIAKAD.");
mtrace("--------------------------------------------------");

// Function Helper 1: Dapatkan atau Buatkan Course Otomatis jika Belum Ada
function get_or_create_course($lesson_code, $lesson_name) {
    global $DB;

    $shortname = strtoupper(trim($lesson_code));
    $fullname = trim($lesson_name);

    $course = $DB->get_record('course', ['shortname' => $shortname]);
    if ($course) {
        return $course;
    }

    $newcourse = new stdClass();
    $newcourse->fullname = $fullname;
    $newcourse->shortname = $shortname;
    $newcourse->idnumber = $shortname;
    $newcourse->category = 1; // Default Category
    $newcourse->format = 'topics';
    $newcourse->numsections = 14;
    $newcourse->startdate = time();
    $newcourse->visible = 1;

    $course = create_course($newcourse);
    mtrace("     ✨ [AUTO-CREATE COURSE] Mata kuliah baru dibuat: '{$fullname}' ({$shortname})");
    return $course;
}

// Function Helper 2: Mendaftarkan Mahasiswa ke Course
function enrol_student_to_course($user_id, $course) {
    global $DB;

    $plugin = enrol_get_plugin('manual');
    $instances = enrol_get_instances($course->id, true);
    $manualinstance = null;

    foreach ($instances as $instance) {
        if ($instance->enrol === 'manual') {
            $manualinstance = $instance;
            break;
        }
    }

    if (!$manualinstance) {
        $instance_id = $plugin->add_instance($course);
        $manualinstance = $DB->get_record('enrol', ['id' => $instance_id]);
    }

    $studentrole = $DB->get_record('role', ['shortname' => 'student']);

    if (!$DB->record_exists('user_enrolments', ['enrolid' => $manualinstance->id, 'userid' => $user_id])) {
        $plugin->enrol_user($manualinstance, $user_id, $studentrole->id);
        mtrace("     🎓 [ENROLLED] Terdaftar ke kelas: '{$course->fullname}' ({$course->shortname})");
    } else {
        mtrace("     ℹ️ [ENROLLED] Sudah terdaftar di kelas: '{$course->fullname}'");
    }
}

// 2. Loop Utama Proses Sync Mahasiswa & Course
$created_user_count = 0;
$enrolled_count = 0;

foreach ($mahasiswa_list as $index => $mhs) {
    $nim = trim($mhs['nim']);
    $username = strtolower($nim);
    $full_name = trim($mhs['name']);
    $status = strtoupper($mhs['status']);

    if ($status !== 'AKTIF') {
        continue;
    }

    mtrace("[" . sprintf("%02d", $index + 1) . "] 👤 Memproses Mahasiswa: {$full_name} (NIM: {$nim})");

    // Memisah Nama Depan & Nama Belakang
    $name_parts = explode(' ', $full_name);
    if (count($name_parts) > 1) {
        $firstname = array_shift($name_parts);
        $lastname = implode(' ', $name_parts);
    } else {
        $firstname = $full_name;
        $lastname = '.';
    }

    $email = strtolower($nim) . "@sugenghartono.ac.id";

    // Cek atau buat user Moodle
    $user = $DB->get_record('user', ['username' => $username, 'mnethostid' => $mnet_localhostid]);

    if (!$user) {
        $user_obj = new stdClass();
        $user_obj->username = $username;
        $user_obj->password = hash_internal_user_password('Ush@' . $nim);
        $user_obj->firstname = $firstname;
        $user_obj->lastname = $lastname;
        $user_obj->email = $email;
        $user_obj->city = 'Sukoharjo';
        $user_obj->country = 'ID';
        $user_obj->lang = 'en';
        $user_obj->auth = 'manual';
        $user_obj->confirmed = 1;
        $user_obj->department = 'Semester ' . $mhs['semester'];
        $user_obj->institution = 'Universitas Sugeng Hartono';
        $user_obj->mnethostid = $mnet_localhostid;
        $user_obj->timecreated = time();
        $user_obj->timemodified = time();

        $user_id = user_create_user($user_obj, false, false);
        $user = $DB->get_record('user', ['id' => $user_id]);
        mtrace("     ✅ [AKUN] User dibuat baru -> Username: {$username}");
        $created_user_count++;
    } else {
        mtrace("     ℹ️ [AKUN] User sudah ada -> Username: {$username}");
    }

    // Process Enrolment & Course Creation dari Array Grade SIAKAD
    if (!empty($mhs['grade']) && is_array($mhs['grade'])) {
        foreach ($mhs['grade'] as $matkul) {
            $lesson_code = $matkul['lesson_code'];
            $lesson_name = $matkul['lesson_name'];

            // 1. Dapatkan / Buatkan Course
            $course = get_or_create_course($lesson_code, $lesson_name);

            // 2. Enrol Mahasiswa ke Course
            enrol_student_to_course($user->id, $course);
            $enrolled_count++;
        }
    }
    mtrace("--------------------------------------------------");
}

mtrace("🎉 SINKRONISASI SELESAI!");
mtrace("Summary:");
mtrace("  - Akun Baru Dibuat      : {$created_user_count}");
mtrace("  - Total Enrolment Kelas : {$enrolled_count}");
mtrace("==================================================");
