<?php
define('CLI_SCRIPT', true);
@error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);
require(__DIR__ . '/../../config.php');

mtrace("=== CHECKING ADDITIONALHTMLTOPOFBODY CONFIG ===");
$html = get_config('core', 'additionalhtmltopofbody');
mtrace("Length: " . strlen($html));
mtrace("Content snippet:\n" . substr($html, 0, 500));
mtrace("\nDoes contain 'ushTransformDosenDashboard'? " . (strpos($html, 'ushTransformDosenDashboard') !== false ? 'YES' : 'NO'));
mtrace("Does contain 'ushRoleDashBanner'? " . (strpos($html, 'ushRoleDashBanner') !== false ? 'YES' : 'NO'));
