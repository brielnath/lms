<?php
define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . '/blocks/ush_kaprodi_dosen/lib.php');

block_ush_kaprodi_dosen_setup_dashboard();
mtrace('Blok dosen prodi dipasang di dasbor default dan dasbor user yang sudah ada.');
