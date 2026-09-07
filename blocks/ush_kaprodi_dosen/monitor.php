<?php
/**
 * Laporan pantauan Kaprodi: progres mahasiswa per aktivitas.
 *
 * @package block_ush_kaprodi_dosen
 */
require('../../config.php');

require_login();

$courseid = optional_param('courseid', 0, PARAM_INT);

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/blocks/ush_kaprodi_dosen/monitor.php', $courseid ? ['courseid' => $courseid] : []));
$PAGE->set_pagelayout('report');
$PAGE->set_title(get_string('monitortitle', 'block_ush_kaprodi_dosen'));
$PAGE->set_heading(get_string('monitortitle', 'block_ush_kaprodi_dosen'));

$monitor = new \block_ush_kaprodi_dosen\local\monitor();
$data = $monitor->export_page($courseid ?: null);

if (empty($data['iskaprodi'])) {
    echo $OUTPUT->header();
    echo $OUTPUT->notification(get_string('notkaprodi', 'block_ush_kaprodi_dosen'), 'error');
    echo $OUTPUT->footer();
    exit;
}

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('block_ush_kaprodi_dosen/monitor', $data);
echo $OUTPUT->footer();
