<?php
require(__DIR__ . '/../../config.php');

require_login();

$system = context_system::instance();
$PAGE->set_context($system);
$PAGE->set_url(new moodle_url('/local/ush_pimpinan/index.php'));
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('pluginname', 'local_ush_pimpinan'));
$PAGE->set_heading(get_string('pluginname', 'local_ush_pimpinan'));
$PAGE->add_body_class('ush-pimpinan-page');

$requested = optional_param('fakultas', '', PARAM_ALPHA);
$scope = \local_ush_pimpinan\local\access::resolve($requested);
if ($scope === null) {
    throw new moodle_exception('nopermission', 'local_ush_pimpinan');
}

$data = (new \local_ush_pimpinan\local\dashboard())->build($scope);

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_ush_pimpinan/dashboard', $data);
echo $OUTPUT->footer();
