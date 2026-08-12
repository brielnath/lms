<?php
define('CLI_SCRIPT', true);

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/user/lib.php');

mtrace("==================================================");
mtrace("🚀 SINKRONISASI 20 MAHASISWA BARU VIA MOCK API");
mtrace("==================================================");

$mnet_localhostid = !empty($CFG->mnet_localhostid) ? $CFG->mnet_localhostid : 1;

// Simulated API Data Generator (Mocking SIAKAD API Response)
$first_names = ["Budi", "Siti", "Rian", "Dewi", "Ahmad", "Fajar", "Nadia", "Andi", "Putri", "Diki", "Maya", "Bagas", "Fitri", "Eko", "Laras", "Reza", "Tari", "Hendra", "Anisa", "Rudi"];
$last_names  = ["Santoso", "Aminah", "Hidayat", "Lestari", "Fauzi", "Pratama", "Wijaya", "Kurniawan", "Sari", "Permana", "Kusuma", "Nugroho", "Wulandari", "Saputra", "Anggraini", "Fadillah", "Utami", "Setiawan", "Rahmawati", "Hartono"];
$prodi_list  = ["S1 Manajemen", "S1 Teknik Informatika", "S1 Bisnis Digital", "S1 Gizi"];

$mahasiswa_list = [];
for ($i = 0; $i < 20; $i++) {
    $nim = 2401001 + $i;
    $fname = $first_names[$i];
    $lname = $last_names[$i];
    $email = strtolower($fname) . "." . strtolower($lname) . "@sugenghartono.ac.id";
    $prodi = $prodi_list[$i % count($prodi_list)];

    $mahasiswa_list[] = [
        "nim" => (string)$nim,
        "firstname" => $fname,
        "lastname" => $lname,
        "email" => $email,
        "prodi" => $prodi,
        "kota" => "Sukoharjo"
    ];
}

mtrace("Ditemukan " . count($mahasiswa_list) . " data mahasiswa dari Mock API SIAKAD.");
mtrace("--------------------------------------------------");

$created_count = 0;
$skipped_count = 0;

foreach ($mahasiswa_list as $index => $mhs) {
    $username = strtolower($mhs['nim']);

    // Check if user already exists in Moodle DB
    if ($DB->record_exists('user', ['username' => $username, 'mnethostid' => $mnet_localhostid])) {
        mtrace("[" . sprintf("%02d", $index + 1) . "/20] ⚠️  Mahasiswa NIM {$mhs['nim']} ({$mhs['firstname']} {$mhs['lastname']}) sudah ada -> SKIPPED");
        $skipped_count++;
        continue;
    }

    // Create user object for Moodle
    $user = new stdClass();
    $user->username = $username;
    $user->password = hash_internal_user_password('Ush@2025Mhs!'); // Default password
    $user->firstname = $mhs['firstname'];
    $user->lastname = $mhs['lastname'];
    $user->email = $mhs['email'];
    $user->city = $mhs['kota'];
    $user->country = 'ID';
    $user->lang = 'en';
    $user->auth = 'manual';
    $user->confirmed = 1;
    $user->department = $mhs['prodi'];
    $user->institution = 'Universitas Sugeng Hartono';
    $user->mnethostid = $mnet_localhostid;
    $user->timecreated = time();
    $user->timemodified = time();

    try {
        $user_id = user_create_user($user, false, false);
        mtrace("[" . sprintf("%02d", $index + 1) . "/20] ✅ SUCCESS: Akun Dibuat -> NIM: {$mhs['nim']} | Nama: {$mhs['firstname']} {$mhs['lastname']} | Email: {$mhs['email']}");
        $created_count++;
    } catch (Exception $e) {
        mtrace("[" . sprintf("%02d", $index + 1) . "/20] ❌ ERROR: Gagal membuat user {$mhs['nim']}: " . $e->getMessage());
    }
}

mtrace("--------------------------------------------------");
mtrace("🎉 SINKRONISASI SELESAI!");
mtrace("Total Berhasil Dibuat: {$created_count}");
mtrace("Total Dilewati (Sudah Ada): {$skipped_count}");
mtrace("==================================================");
