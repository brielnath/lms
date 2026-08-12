<?php
/**
 * =========================================================================
 * 🎓 SKRIP SINKRONISASI OTOMATIS: SIAKAD KAMPUS USH -> LMS MOODLE
 * =========================================================================
 * 
 * FUNGSI SKRIP:
 * 1. Login ke API SIAKAD & mengambil Bearer Token secara otomatis.
 * 2. Mengambil seluruh daftar Mata Kuliah SIAKAD & membuat Cangkang Kelas di Moodle.
 * 3. Memfilter Mahasiswa Aktif Angkatan 2023.
 * 4. Membuatkan Akun Moodle resmi (Username = NIM, Pass Default = Ush@NIM).
 * 5. Mendaftarkan (Enrol) Mahasiswa ke seluruh Mata Kuliah Kontraknya.
 * 6. SISTEM DECOUPLED (Aman & Tidak Merusak Data SIAKAD maupun Moodle).
 *
 * CARA PAKAI:
 * 1. Isi $API_USERNAME dan $API_PASSWORD di bawah ini dengan kredensial Anda.
 * 2. Jalankan perintah ini di PowerShell / Terminal Command Prompt:
 * 
 *    & "C:\wamp64\bin\php\php8.2.29\php.exe" "C:\wamp64\www\moodle\admin\cli\sync_siakad_production.php"
 * =========================================================================
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/enrol/manual/locallib.php');

mtrace("==================================================");
mtrace("🚀 AUTOMATED PRODUCTION SYNC: SIAKAD USH -> MOODLE LMS");
mtrace("==================================================");

// =========================================================================
// KONFIGURASI KREDENSIAL API SIAKAD 
// =========================================================================
$SIAKAD_BASE_URL = "https://siakad.sugenghartono.ac.id/api";
$API_USERNAME    = "akademik@sugenghartono.ac.id"; 
$API_PASSWORD    = "321"; 
$BEARER_TOKEN    = "";     
// =========================================================================

// -------------------------------------------------------------------------
// FUNGSI HELPER 1: LOGIN SIAKAD DENGAN CURL
// -------------------------------------------------------------------------
function get_siakad_token($base_url, $username, $password) {
    $url = $base_url . "/login";
    $payload = json_encode(['email' => $username, 'password' => $password]);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json'
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);

    $result = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code == 200 && $result) {
        $json = json_decode($result, true);
        if (isset($json['token'])) return $json['token'];
        if (isset($json['data']['token'])) return $json['data']['token'];
        if (isset($json['data']['access_token'])) return $json['data']['access_token'];
        if (isset($json['access_token'])) return $json['access_token'];
    }
    return null;
}

// -------------------------------------------------------------------------
// FUNGSI HELPER 2: CALL GET ENDPOINT DENGAN BEARER TOKEN
// -------------------------------------------------------------------------
function call_siakad_api($url, $token) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $token,
        'Accept: application/json'
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $result = curl_exec($ch);
    curl_close($ch);

    return json_decode($result, true);
}

// -------------------------------------------------------------------------
// FUNGSI HELPER 3: UNDUH & PASANG FOTO PROFIL DARI API SIAKAD
// -------------------------------------------------------------------------
function sync_user_profile_photo($user, $photo_url_or_path) {
    global $DB, $CFG;

    if (empty($photo_url_or_path)) {
        return false;
    }

    // Tentukan URL lengkap jika berupa path relatif
    $url = $photo_url_or_path;
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        if (strpos($url, '/') === 0) {
            $url = "https://siakad.sugenghartono.ac.id" . $url;
        } else {
            $url = "https://siakad.sugenghartono.ac.id/assets/ijazah/students/" . ltrim($url, '/');
        }
    }

    require_once($CFG->libdir . '/gdlib.php');

    $temp_dir = make_temp_directory('profilepics');
    $temp_file = $temp_dir . '/' . $user->id . '_' . time() . '.jpg';

    // Unduh gambar dari URL API menggunakan cURL
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    $image_data = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code == 200 && !empty($image_data)) {
        file_put_contents($temp_file, $image_data);

        // Pastikan file gambar valid
        $image_info = @getimagesize($temp_file);
        if ($image_info !== false) {
            $context = context_user::instance($user->id);
            $newpicture = process_new_icon($context, 'user', 'icon', 0, $temp_file);
            @unlink($temp_file);

            if ($newpicture) {
                $DB->set_field('user', 'picture', $newpicture, ['id' => $user->id]);
                return true;
            }
        }
        @unlink($temp_file);
    }
    return false;
}

// -------------------------------------------------------------------------
// PROSES AUTENTIKASI API SIAKAD
// -------------------------------------------------------------------------
if (empty($BEARER_TOKEN)) {
    mtrace("🔑 Mengontak SIAKAD API ({$SIAKAD_BASE_URL}/login) untuk autentikasi...");
    mtrace("   Username: {$API_USERNAME}");
    $BEARER_TOKEN = get_siakad_token($SIAKAD_BASE_URL, $API_USERNAME, $API_PASSWORD);
}

if (empty($BEARER_TOKEN)) {
    mtrace("GAGAL AUTENTIKASI: Username/Password salah atau API SIAKAD tidak merespon.");
    exit(1);
}

mtrace("AUTENTIKASI BERHASIL! Bearer Token berhasil diperoleh.");

// -------------------------------------------------------------------------
// PROSES 1: MEMBUAT SEMUA KELAS / MATA KULIAH DARI SIAKAD
// -------------------------------------------------------------------------
mtrace("\n--------------------------------------------------");
mtrace("PROSES 1: Menyinkronkan Seluruh Mata Kuliah dari SIAKAD...");

$lessons_url = $SIAKAD_BASE_URL . "/all-lessons";
$lessons_data = call_siakad_api($lessons_url, $BEARER_TOKEN);

$created_courses = 0;
$items = $lessons_data['data'] ?? $lessons_data ?? [];

if (is_array($items)) {
    foreach ($items as $lesson) {
        $code = $lesson['lesson_code'] ?? $lesson['code'] ?? null;
        if (empty($code)) continue;

        $shortname = strtoupper(trim($code));
        $fullname = trim($lesson['lesson_name'] ?? $lesson['name'] ?? $shortname);

        $course = $DB->get_record('course', ['shortname' => $shortname]);
        if (!$course) {
            $nc = new stdClass();
            $nc->fullname = $fullname;
            $nc->shortname = $shortname;
            $nc->idnumber = $shortname;
            $nc->category = 1;
            $nc->format = 'topics';
            $nc->numsections = 8;
            $nc->coursedisplay = 0;
            $nc->startdate = time();
            $nc->visible = 1;
            $nc->enablecompletion = 1;

            $course = create_course($nc);
            mtrace("     [KELAS BARU] {$fullname} ({$shortname})");
            $created_courses++;
        }
    }
}
mtrace("Selesai: Total {$created_courses} Mata Kuliah baru dibuat.");

// -------------------------------------------------------------------------
// 👥 PROSES 2: MEMBUAT AKUN, FOTO PROFIL, & ENROLMENT MAHASISWA ANGKATAN 2023
// -------------------------------------------------------------------------
mtrace("\n--------------------------------------------------");
mtrace("👥 PROSES 2: Menyinkronkan Mahasiswa Angkatan 2023 & Foto Profil...");

$mhs_url = $SIAKAD_BASE_URL . "/user/mahasiswa";
$mhs_response = call_siakad_api($mhs_url, $BEARER_TOKEN);

$mnet_localhostid = !empty($CFG->mnet_localhostid) ? $CFG->mnet_localhostid : 1;
$plugin = enrol_get_plugin('manual');
$studentrole = $DB->get_record('role', ['shortname' => 'student']);

$students = $mhs_response['data'] ?? $mhs_response ?? [];

$processed_mhs = 0;
$created_users = 0;
$enrolled_total = 0;
$synced_photos = 0;

if (is_array($students)) {
    foreach ($students as $mhs) {
        $nim = trim($mhs['nim'] ?? '');
        if (empty($nim)) continue;

        $full_name = trim($mhs['name'] ?? $mhs['nama'] ?? 'Mahasiswa');
        $status = strtoupper($mhs['status'] ?? 'AKTIF');

        // Filter khusus Angkatan 2023 (berdasarkan NIM atau atribut tahun masuk)
        $is_2023 = (
            substr($nim, 2, 2) === '23' || 
            substr($nim, 0, 2) === '23' || 
            (isset($mhs['tahun_masuk']) && $mhs['tahun_masuk'] == '2023') ||
            (isset($mhs['angkatan']) && $mhs['angkatan'] == '2023')
        );

        if (!$is_2023) continue; // Skip jika bukan angkatan 2023
        if ($status !== 'AKTIF' && $status !== 'A') continue; // Skip jika non-aktif

        $processed_mhs++;
        $username = strtolower($nim);

        mtrace("\n[" . sprintf("%02d", $processed_mhs) . "] 👤 Mahasiswa 2023: {$full_name} (NIM: {$nim})");

        // A. Cek / Buat Akun Moodle
        $user = $DB->get_record('user', ['username' => $username, 'mnethostid' => $mnet_localhostid]);
        if (!$user) {
            $name_parts = explode(' ', $full_name);
            $firstname = count($name_parts) > 1 ? array_shift($name_parts) : $full_name;
            $lastname = count($name_parts) > 0 ? implode(' ', $name_parts) : '.';

            $u = new stdClass();
            $u->username = $username;
            $u->password = hash_internal_user_password('Ush@' . $nim); // Default Pass: Ush@NIM
            $u->firstname = $firstname;
            $u->lastname = $lastname;
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
            mtrace("     ✨ [AKUN MOODLE DIBUAT] Username: {$username} | Pass: Ush@{$nim}");
            $created_users++;
        } else {
            mtrace("     Akun Moodle sudah ada.");
        }

        // B. Sinkronkan Foto Profil jika ada di API SIAKAD
        $photo_source = $mhs['foto_url'] ?? $mhs['foto_url_api'] ?? $mhs['foto'] ?? $mhs['avatar'] ?? null;
        if (!empty($photo_source)) {
            if (sync_user_profile_photo($user, $photo_source)) {
                mtrace("     📸 [FOTO PROFIL] Berhasil diperbarui dari SIAKAD.");
                $synced_photos++;
            }
        }

        // C. Enrolment ke Mata Kuliah Kontrak
        $courses_taken = $mhs['grade'] ?? $mhs['lessons'] ?? $mhs['matkul'] ?? [];

        if (is_array($courses_taken)) {
            foreach ($courses_taken as $matkul) {
                $code = strtoupper(trim($matkul['lesson_code'] ?? $matkul['code'] ?? ''));
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
                    mtrace("     Enrolled ke: {$course->fullname}");
                    $enrolled_total++;
                }
            }
        }
    }
}

// -------------------------------------------------------------------------
// REBUILD & PURGE CACHE MOODLE
// -------------------------------------------------------------------------
rebuild_course_cache(0, true);
purge_all_caches();

mtrace("\n==================================================");
mtrace(" SINKRONISASI PRODUKSI SELESAI DENGAN SUKSES!");
mtrace(" Rincian Hasil:");
mtrace("   • Total Mahasiswa 2023 Diproses : {$processed_mhs}");
mtrace("   • Akun Moodle Baru Dibuat        : {$created_users}");
mtrace("   • Foto Profil Disinkronkan       : {$synced_photos}");
mtrace("   • Enrolment Kelas Sukses         : {$enrolled_total}");
mtrace("==================================================");

