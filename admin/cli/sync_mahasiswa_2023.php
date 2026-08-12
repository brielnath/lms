<?php
define('CLI_SCRIPT', true);

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/enrol/manual/locallib.php');

mtrace("==================================================");
mtrace("🚀 SYNC MAHASISWA ANGKATAN 2023 DARI SIAKAD API");
mtrace("==================================================");

$SIAKAD_BASE_URL = "https://siakad.sugenghartono.ac.id/api";
$BEARER_TOKEN = "320|Gs2KVb4VtawDCnc2foDC8Up9UXbe7c95NGgu1OKZcfe5622b";

function call_api($url, $token) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $token,
        'Accept: application/json'
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    $result = curl_exec($ch);
    curl_close($ch);
    return json_decode($result, true);
}

$mnet_localhostid = !empty($CFG->mnet_localhostid) ? $CFG->mnet_localhostid : 1;
$plugin = enrol_get_plugin('manual');
$studentrole = $DB->get_record('role', ['shortname' => 'student']);

$processed_mhs = 0;
$created_users = 0;
$enrolled_total = 0;
$page = 1;
$has_more = true;

while ($has_more) {
    mtrace("\n📄 Mengambil data halaman {$page}...");
    $url = $SIAKAD_BASE_URL . "/grades-per-course?page=" . $page;
    $response = call_api($url, $BEARER_TOKEN);

    $students = $response['data'] ?? [];

    if (empty($students)) {
        $has_more = false;
        break;
    }

    foreach ($students as $mhs) {
        $nim = trim($mhs['nim'] ?? '');
        if (empty($nim)) continue;

        $full_name = trim($mhs['name'] ?? 'Mahasiswa');
        $status = strtoupper($mhs['status'] ?? 'AKTIF');

        // Filter Angkatan 2023: NIM diawali 0623
        $is_2023 = (substr($nim, 0, 4) === '0623');

        if (!$is_2023) continue;
        if ($status !== 'AKTIF') continue;

        $processed_mhs++;
        $username = strtolower($nim);

        mtrace("\n[" . sprintf("%03d", $processed_mhs) . "] 👤 {$full_name} (NIM: {$nim})");

        // Buat Akun Moodle
        $user = $DB->get_record('user', ['username' => $username, 'mnethostid' => $mnet_localhostid]);
        if (!$user) {
            $name_parts = explode(' ', $full_name);
            $firstname = count($name_parts) > 1 ? array_shift($name_parts) : $full_name;
            $lastname = count($name_parts) > 0 ? implode(' ', $name_parts) : '.';

            $u = new stdClass();
            $u->username = $username;
            $u->password = hash_internal_user_password('Ush@' . $nim);
            $u->firstname = strtoupper($firstname);
            $u->lastname = strtoupper($lastname);
            $u->email = $username . "@sugenghartono.ac.id";
            $u->city = 'Sukoharjo';
            $u->country = 'ID';
            $u->lang = 'en';
            $u->auth = 'manual';
            $u->confirmed = 1;
            $u->department = 'Angkatan 2023';
            $u->institution = 'Universitas Sugeng Hartono';
            $u->mnethostid = $mnet_localhostid;
            $u->timecreated = time();
            $u->timemodified = time();

            $user_id = user_create_user($u, false, false);
            $user = $DB->get_record('user', ['id' => $user_id]);
            mtrace("     ✨ Akun dibuat: {$username} | Pass: Ush@{$nim}");
            $created_users++;
        } else {
            mtrace("     ℹ️ Akun sudah ada.");
        }

        // Enrolment ke Mata Kuliah
        $grades = $mhs['grade'] ?? [];
        if (is_array($grades)) {
            foreach ($grades as $matkul) {
                $code = trim($matkul['lesson_code'] ?? '');
                if (empty($code)) continue;

                $course = $DB->get_record('course', ['shortname' => $code]);
                if (!$course) continue;

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

                if (!$DB->record_exists('user_enrolments', ['enrolid' => $manualinstance->id, 'userid' => $user->id])) {
                    $plugin->enrol_user($manualinstance, $user->id, $studentrole->id);
                    mtrace("     🎓 -> {$course->fullname}");
                    $enrolled_total++;
                }
            }
        }
    }

    $page++;

    // Safety: max 50 pages
    if ($page > 50) {
        $has_more = false;
    }
}

rebuild_course_cache(0, true);
purge_all_caches();

mtrace("\n==================================================");
mtrace("🎉 SINKRONISASI MAHASISWA ANGKATAN 2023 SELESAI!");
mtrace("📊 Rincian:");
mtrace("   • Mahasiswa 2023 Diproses  : {$processed_mhs}");
mtrace("   • Akun Moodle Baru Dibuat  : {$created_users}");
mtrace("   • Enrolment Kelas Sukses   : {$enrolled_total}");
mtrace("==================================================");
