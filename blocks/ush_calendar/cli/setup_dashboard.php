<?php
// This file is part of Moodle - http://moodle.org/
define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . '/blocks/ush_calendar/lib.php');

block_ush_calendar_setup_dashboard();
mtrace('Kalender akademik dipasang di dasbor default dan dasbor user yang sudah ada.');
