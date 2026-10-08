<?php
defined('MOODLE_INTERNAL') || die();

$plugin->version      = 2026100801;
$plugin->requires     = 2024100100;
$plugin->component    = 'block_ush_pimpinan';
$plugin->maturity     = MATURITY_STABLE;
$plugin->release      = '1.0.0';
$plugin->dependencies = [
    'local_ush_pimpinan' => ANY_VERSION,
];
