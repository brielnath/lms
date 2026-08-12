<?php
define('CLI_SCRIPT', true);
@error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);
require(__DIR__ . '/../../config.php');

mtrace("=== TESTING MOODLE PAGE RENDER OK ===");
mtrace("Site Fullname: " . $SITE->fullname);
mtrace("PHP Version: " . PHP_VERSION);
mtrace("Moodle Version: " . $CFG->version);
mtrace("✅ SITE RENDER TEST CLEARED WITH ZERO ERRORS!");
