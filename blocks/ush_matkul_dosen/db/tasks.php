<?php
defined('MOODLE_INTERNAL') || die();

$tasks = [
    [
        'classname' => '\block_ush_matkul_dosen\task\sync_task',
        'blocking' => 0,
        'minute' => '20',
        'hour' => '2',
        'day' => '*',
        'month' => '*',
        'dayofweek' => '*',
    ],
];
