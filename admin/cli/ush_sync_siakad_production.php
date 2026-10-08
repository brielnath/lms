<?php
/**
 * Poll SIAKAD and import newly available course, student, and lecturer data.
 *
 * Safe by default: the importers only write when --confirm is supplied.
 * Configure SIAKAD_EMAIL and SIAKAD_PASSWORD in the server environment.
 *
 * Usage:
 *   php admin/cli/ush_sync_siakad_production.php --semester=20262027Ganjil --batch=13 --sub=20
 *   php admin/cli/ush_sync_siakad_production.php --semester=20262027Ganjil --batch=13 --sub=20 --confirm
 */
define('CLI_SCRIPT', true);

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/clilib.php');

umask(0077);

$semester = '20262027Ganjil';
$batch = 13;
$sub = 20;
$confirm = false;

foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--confirm') {
        $confirm = true;
    } else if (str_starts_with($arg, '--semester=')) {
        $semester = substr($arg, 11);
    } else if (str_starts_with($arg, '--batch=')) {
        $batch = (int) substr($arg, 8);
    } else if (str_starts_with($arg, '--sub=')) {
        $sub = (int) substr($arg, 6);
    } else {
        cli_error('Argumen tidak dikenal: ' . $arg);
    }
}

if (!preg_match('/^(20\d{2})(20\d{2})(Ganjil|Genap)$/i', $semester)) {
    cli_error('Format semester salah. Contoh: 20262027Ganjil');
}
if ($batch <= 0 || $sub <= 0) {
    cli_error('--batch dan --sub harus berupa ID positif.');
}
if (strtolower((string) parse_url($CFG->wwwroot, PHP_URL_HOST)) !== 'lms.ush.ac.id') {
    cli_error('Dibatalkan: runner ini hanya boleh berjalan di https://lms.ush.ac.id. wwwroot=' . $CFG->wwwroot);
}
if (!getenv('SIAKAD_EMAIL') || !getenv('SIAKAD_PASSWORD')) {
    cli_error('SIAKAD_EMAIL dan SIAKAD_PASSWORD harus tersedia di environment proses.');
}

$workdir = $CFG->dataroot . '/siakad_sync_production';
if (!is_dir($workdir) && !mkdir($workdir, 0700, true) && !is_dir($workdir)) {
    cli_error('Tidak dapat membuat folder kerja privat: ' . $workdir);
}

$logfile = $workdir . '/sync_' . date('Ymd_His') . '.log';
$lock = fopen($workdir . '/sync.lock', 'c+');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
    mtrace('Sinkronisasi sebelumnya masih berjalan; giliran ini dilewati.');
    exit(0);
}

/** Run one existing exporter/importer and append its output to the run log. */
function ush_sync_run_step(string $label, array $args, string $root, string $workdir, string $logfile): void {
    mtrace('=== ' . $label . ' ===');
    file_put_contents($logfile, "\n=== $label ===\n", FILE_APPEND | LOCK_EX);

    $outputfile = tempnam($workdir, 'step_');
    if ($outputfile === false) {
        throw new RuntimeException('Tidak dapat membuat file output sementara.');
    }

    $command = array_merge([PHP_BINARY], $args);
    $descriptors = [
        0 => ['file', '/dev/null', 'r'],
        1 => ['file', $outputfile, 'a'],
        2 => ['file', $outputfile, 'a'],
    ];
    $process = proc_open($command, $descriptors, $pipes, $root, null, ['bypass_shell' => true]);
    if (!is_resource($process)) {
        @unlink($outputfile);
        throw new RuntimeException('Tidak dapat memulai langkah: ' . $label);
    }

    $exitcode = proc_close($process);
    $output = (string) file_get_contents($outputfile);
    @unlink($outputfile);
    if ($output !== '') {
        echo $output;
        file_put_contents($logfile, $output, FILE_APPEND | LOCK_EX);
    }
    if ($exitcode !== 0) {
        throw new RuntimeException($label . ' gagal (exit ' . $exitcode . '). Import tidak dilanjutkan.');
    }
}

try {
    $liveargs = $confirm ? ['--confirm'] : [];
    $catalogfile = $workdir . '/katalog_' . $semester . '.json';
    $studentfile = $workdir . '/peserta_' . $semester . '.json';
    $lecturerfile = $workdir . '/pengampu_' . $semester . '.json';

    mtrace('SIAKAD → LMS production | semester ' . $semester);
    mtrace('Mode: ' . ($confirm ? 'LIVE — data baru akan ditulis' : 'DRY-RUN — tidak menulis perubahan'));
    mtrace('Batch/sub-batch SIAKAD: ' . $batch . '/' . $sub);
    file_put_contents($logfile, 'Mulai ' . date(DATE_ATOM) . ' | semester=' . $semester
        . ' | mode=' . ($confirm ? 'LIVE' : 'DRY-RUN') . ' | batch=' . $batch . ' | sub=' . $sub . "\n");

    // Fetch every input first. A failed or incomplete export prevents all imports.
    ush_sync_run_step('Ambil katalog mata kuliah dari SIAKAD', [
        $CFG->dirroot . '/admin/cli/ush_export_katalog_siakad.php',
        '--out=' . $catalogfile,
    ], $CFG->dirroot, $workdir, $logfile);
    ush_sync_run_step('Ambil roster mahasiswa per kelas dari SIAKAD', [
        $CFG->dirroot . '/admin/cli/ush_export_peserta_siakad.php',
        '--full', '--batch=' . $batch, '--sub=' . $sub, '--out=' . $studentfile,
    ], $CFG->dirroot, $workdir, $logfile);
    ush_sync_run_step('Ambil pemetaan mata kuliah dan dosen dari SIAKAD', [
        $CFG->dirroot . '/admin/cli/ush_export_pengampu_siakad.php',
        '--out=' . $lecturerfile,
    ], $CFG->dirroot, $workdir, $logfile);

    // Reuse the reviewed production importers; these are idempotent for existing records.
    ush_sync_run_step('Siapkan kelas mata kuliah', array_merge([
        $CFG->dirroot . '/admin/cli/ush_prepare_semester_production.php',
        '--semester=' . $semester, '--from-file=' . $catalogfile,
    ], $liveargs), $CFG->dirroot, $workdir, $logfile);
    ush_sync_run_step('Buat/enrol mahasiswa dan masukkan ke cohort', array_merge([
        $CFG->dirroot . '/admin/cli/ush_enrol_mahasiswa_production.php',
        '--semester=' . $semester, '--from-file=' . $studentfile, '--max-new-users=80',
    ], $liveargs), $CFG->dirroot, $workdir, $logfile);
    ush_sync_run_step('Buat/enrol dosen pengampu', array_merge([
        $CFG->dirroot . '/admin/cli/ush_enrol_dosen_production.php',
        '--semester=' . $semester, '--from-file=' . $lecturerfile,
    ], $liveargs), $CFG->dirroot, $workdir, $logfile);

    mtrace('Sinkronisasi selesai.');
    file_put_contents($logfile, 'Selesai ' . date(DATE_ATOM) . "\n", FILE_APPEND | LOCK_EX);
} catch (Throwable $e) {
    $message = 'GAGAL: ' . $e->getMessage();
    mtrace($message);
    file_put_contents($logfile, $message . "\n", FILE_APPEND | LOCK_EX);
    flock($lock, LOCK_UN);
    fclose($lock);
    exit(1);
}

flock($lock, LOCK_UN);
fclose($lock);
