<?php
define('CLI_SCRIPT', true);

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/enrol/manual/locallib.php');

mtrace("==================================================");
mtrace("🚀 ENROL MAHASISWA KE MATA KULIAH BARU");
mtrace("==================================================");

$enrolments = [
    '062201024' => ['SIF105', 'INF302', 'BDG201'],
    '062201025' => ['MNJ401', 'BDG201', 'GZI102'],
    '062201026' => ['INF302', 'MNJ401', 'SIF105']
];

$plugin = enrol_get_plugin('manual');
$studentrole = $DB->get_record('role', ['shortname' => 'student']);

foreach ($enrolments as $username => $shortnames) {
    $user = $DB->get_record('user', ['username' => $username]);
    if (!$user) continue;

    mtrace("\n👤 Mahasiswa: {$user->firstname} {$user->lastname} ({$username})");

    foreach ($shortnames as $shortname) {
        $course = $DB->get_record('course', ['shortname' => $shortname]);
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
            mtrace("     🎓 Terdaftar di: {$course->fullname}");
        } else {
            mtrace("     ℹ️ Sudah terdaftar di: {$course->fullname}");
        }
    }
}

rebuild_course_cache(0, true);
purge_all_caches();

mtrace("==================================================");
mtrace("🎉 AKUN MAHASISWA BERHASIL DIDRAFTARKAN KE MATA KULIAH BARU!");
mtrace("==================================================");
