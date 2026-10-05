<?php
namespace block_ush_matkul_dosen\task;

defined('MOODLE_INTERNAL') || die();

use block_ush_matkul_dosen\local\sync;

class sync_task extends \core\task\scheduled_task {

    public function get_name() {
        return get_string('task_sync', 'block_ush_matkul_dosen');
    }

    public function execute() {
        $sync = new sync();
        $sync->sync_upcoming();
        $sync->hide_inactive_courses();
    }
}
