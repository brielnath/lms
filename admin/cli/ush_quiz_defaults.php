<?php
/**
 * Nilai bawaan kuis baru mengikuti panduan EMAS 3 UI.
 * Kuis yang sudah ada tidak diubah.
 *
 *   php admin/cli/ush_quiz_defaults.php
 *   php admin/cli/ush_quiz_defaults.php --confirm
 */
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../config.php');

$confirm = in_array('--confirm', array_slice($argv, 1), true);
$target = [
    'overduehandling' => 'autosubmit',
    'browsersecurity' => 'securewindow',
];

mtrace('=== Nilai bawaan kuis baru ===');
mtrace('Mode: ' . ($confirm ? 'LIVE' : 'DRY-RUN'));
foreach ($target as $name => $value) {
    $current = get_config('quiz', $name);
    mtrace(sprintf('  %-16s %s -> %s', $name, $current === false ? '(kosong)' : $current, $value));
    if ($confirm && $current !== $value) {
        set_config($name, $value, 'quiz');
    }
}
if ($confirm) {
    purge_all_caches();
    mtrace('Selesai.');
} else {
    mtrace('DRY-RUN. Ulangi dengan --confirm.');
}
