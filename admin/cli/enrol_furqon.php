<?php
define('CLI_SCRIPT', true);

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/enrol/manual/locallib.php');

mtrace("==================================================");
mtrace("🚀 ENROL MAHASISWA FURQON KE COURSE 'INTERNET OF THINKS' (ID: 14)");
mtrace("==================================================");

$username = '062201024';
$course_id = 14;

$user = $DB->get_record('user', ['username' => $username]);
$course = $DB->get_record('course', ['id' => $course_id]);

if (!$user) {
    mtrace("❌ User dengan username {$username} tidak ditemukan!");
    exit(1);
}

if (!$course) {
    mtrace("❌ Course dengan ID {$course_id} tidak ditemukan!");
    exit(1);
}

// Cari plugin manual enrolment pada course ini
$instances = enrol_get_instances($course->id, true);
$manualinstance = null;
foreach ($instances as $instance) {
    if ($instance->enrol === 'manual') {
        $manualinstance = $instance;
        break;
    }
}

// Jika belum ada instance manual enrol, buatkan
if (!$manualinstance) {
    $plugin = enrol_get_plugin('manual');
    $instance_id = $plugin->add_instance($course);
    $manualinstance = $DB->get_record('enrol', ['id' => $instance_id]);
}

$plugin = enrol_get_plugin('manual');
$studentrole = $DB->get_record('role', ['shortname' => 'student']);

try {
    $plugin->enrol_user($manualinstance, $user->id, $studentrole->id);
    mtrace("✅ SUKSES! Mahasiswa {$user->firstname} {$user->lastname} ({$username}) telah terdaftar di Course '{$course->fullname}'!");
} catch (Exception $e) {
    mtrace("❌ Gagal mendaftarkan user: " . $e->getMessage());
}

mtrace("==================================================");
