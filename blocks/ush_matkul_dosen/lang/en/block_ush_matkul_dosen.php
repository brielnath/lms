<?php
defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'USH lecturer courses';
$string['ush_matkul_dosen:addinstance'] = 'Add a USH lecturer courses block';
$string['ush_matkul_dosen:myaddinstance'] = 'Add a USH lecturer courses block to Dashboard';
$string['section_previous'] = 'Last semester courses — {$a}';
$string['section_next'] = 'Next semester courses — {$a}';
$string['note_previous'] = 'Courses you taught according to SIAKAD. This semester\'s classes are in Course overview above.';
$string['note_next'] = 'Courses you will teach according to SIAKAD. Classes open when the semester starts.';
$string['badge_previous'] = 'Last semester';
$string['badge_next'] = 'Not active yet';
$string['task_sync'] = 'Sync lecturer courses from SIAKAD';
$string['error_nocredentials'] = 'SIAKAD API credentials are not set (block settings or SIAKAD_EMAIL / SIAKAD_PASSWORD environment).';
$string['error_login'] = 'SIAKAD login failed (HTTP {$a}).';
$string['error_fetch'] = 'Failed to fetch SIAKAD data: {$a}';
$string['setting_apiurl'] = 'SIAKAD API URL';
$string['setting_apiemail'] = 'SIAKAD API email';
$string['setting_apiemail_desc'] = 'Account used to read teaching data. Falls back to the SIAKAD_EMAIL / SIAKAD_PASSWORD environment variables when empty.';
$string['setting_apipassword'] = 'SIAKAD API password';
$string['setting_activesemester'] = 'Active semester (override)';
$string['setting_activesemester_desc'] = 'e.g. 20262027Ganjil. Leave empty to follow the calendar (Ganjil: September–February, Genap: March–August).';
$string['privacy:metadata:block_ush_matkul_dosen'] = 'Upcoming-semester teaching assignments copied from SIAKAD.';
$string['privacy:metadata:block_ush_matkul_dosen:userid'] = 'The lecturer\'s LMS account.';
$string['privacy:metadata:block_ush_matkul_dosen:lecname'] = 'The lecturer name in SIAKAD.';
$string['privacy:metadata:block_ush_matkul_dosen:code'] = 'The course code.';
$string['privacy:metadata:block_ush_matkul_dosen:lessonname'] = 'The course name.';
$string['privacy:metadata:siakad'] = 'Teaching assignments are read from the university SIAKAD system.';
