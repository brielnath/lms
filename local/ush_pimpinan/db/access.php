<?php
defined('MOODLE_INTERNAL') || die();

$capabilities = [
    'local/ush_pimpinan:viewuniversity' => [
        'captype' => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [],
    ],
    'local/ush_pimpinan:viewfaculty' => [
        'captype' => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [],
    ],
];
