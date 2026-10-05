<?php
defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Mata kuliah dosen USH';
$string['ush_matkul_dosen:addinstance'] = 'Tambah blok mata kuliah dosen USH';
$string['ush_matkul_dosen:myaddinstance'] = 'Tambah blok mata kuliah dosen USH ke Dasbor';
$string['section_previous'] = 'Mata kuliah semester lalu — {$a}';
$string['section_next'] = 'Mata kuliah semester berikutnya — {$a}';
$string['note_previous'] = 'Mata kuliah yang Anda ampu menurut SIAKAD. Kelas semester ini ada di Course overview di atas.';
$string['note_next'] = 'Mata kuliah yang akan Anda ampu menurut SIAKAD. Kelas dibuka saat semester dimulai.';
$string['badge_previous'] = 'Semester lalu';
$string['badge_next'] = 'Belum aktif';
$string['task_sync'] = 'Sinkronkan mata kuliah dosen dari SIAKAD';
$string['error_nocredentials'] = 'Kredensial API SIAKAD belum diisi (pengaturan blok atau environment SIAKAD_EMAIL / SIAKAD_PASSWORD).';
$string['error_login'] = 'Login SIAKAD gagal (HTTP {$a}).';
$string['error_fetch'] = 'Gagal mengambil data SIAKAD: {$a}';
$string['setting_apiurl'] = 'URL API SIAKAD';
$string['setting_apiemail'] = 'Email API SIAKAD';
$string['setting_apiemail_desc'] = 'Akun untuk membaca data pengampu. Jika kosong, memakai environment SIAKAD_EMAIL / SIAKAD_PASSWORD.';
$string['setting_apipassword'] = 'Password API SIAKAD';
$string['setting_activesemester'] = 'Semester aktif (paksa)';
$string['setting_activesemester_desc'] = 'Contoh 20262027Ganjil. Kosongkan agar mengikuti kalender (Ganjil: September–Februari, Genap: Maret–Agustus).';
$string['privacy:metadata:block_ush_matkul_dosen'] = 'Data pengampu semester berikutnya yang disalin dari SIAKAD.';
$string['privacy:metadata:block_ush_matkul_dosen:userid'] = 'Akun LMS dosen.';
$string['privacy:metadata:block_ush_matkul_dosen:lecname'] = 'Nama dosen di SIAKAD.';
$string['privacy:metadata:block_ush_matkul_dosen:code'] = 'Kode mata kuliah.';
$string['privacy:metadata:block_ush_matkul_dosen:lessonname'] = 'Nama mata kuliah.';
$string['privacy:metadata:siakad'] = 'Data pengampu dibaca dari sistem SIAKAD universitas.';
