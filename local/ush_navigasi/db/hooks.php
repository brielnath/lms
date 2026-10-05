<?php
defined('MOODLE_INTERNAL') || die();

$callbacks = [
    [
        'hook' => \core\hook\output\before_standard_top_of_body_html_generation::class,
        'callback' => \local_ush_navigasi\local\hook_callbacks::class . '::before_standard_top_of_body_html_generation',
    ],
];
