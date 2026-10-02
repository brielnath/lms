<?php
namespace local_ush_pimpinan\local;

defined('MOODLE_INTERNAL') || die();

class hook_callbacks {
    public static function primary_extend(\core\hook\navigation\primary_extend $hook): void {
        if (!isloggedin() || isguestuser() || !access::can_view()) {
            return;
        }
        $hook->get_primaryview()->add(
            get_string('pluginname', 'local_ush_pimpinan'),
            new \moodle_url('/local/ush_pimpinan/index.php'),
            \navigation_node::TYPE_CUSTOM,
            null,
            'ushpimpinan'
        );
    }
}
