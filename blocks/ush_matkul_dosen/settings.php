<?php
defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    $settings->add(new admin_setting_configtext(
        'block_ush_matkul_dosen/apiurl',
        get_string('setting_apiurl', 'block_ush_matkul_dosen'),
        '',
        'https://siakad.sugenghartono.ac.id/api',
        PARAM_URL
    ));
    $settings->add(new admin_setting_configtext(
        'block_ush_matkul_dosen/apiemail',
        get_string('setting_apiemail', 'block_ush_matkul_dosen'),
        get_string('setting_apiemail_desc', 'block_ush_matkul_dosen'),
        '',
        PARAM_RAW_TRIMMED
    ));
    $settings->add(new admin_setting_configpasswordunmask(
        'block_ush_matkul_dosen/apipassword',
        get_string('setting_apipassword', 'block_ush_matkul_dosen'),
        '',
        ''
    ));
    $settings->add(new admin_setting_configtext(
        'block_ush_matkul_dosen/activesemester',
        get_string('setting_activesemester', 'block_ush_matkul_dosen'),
        get_string('setting_activesemester_desc', 'block_ush_matkul_dosen'),
        '',
        PARAM_ALPHANUM
    ));
}
