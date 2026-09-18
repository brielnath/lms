<?php
/**
 * Baca RPS SIAKAD untuk satu kelas Ganjil (lokal).
 * php admin/cli/ush_obe_probe_rps_local.php [ID_CLASS]
 */
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../config.php');
if (strpos($CFG->wwwroot, 'localhost') === false && strpos($CFG->wwwroot, '127.0.0.1') === false) {
    mtrace('Lokal saja.');
    exit(1);
}
$idclass = (int) ($argv[1] ?? 1837);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$m = new mysqli('127.0.0.1', 'root', '', 'acc_tes_backup');
$m->set_charset('utf8mb4');

$cr = $m->query("SELECT id, name, id_lesson, id_lecture FROM class_room WHERE id = $idclass")->fetch_assoc();
mtrace('class_room: ' . json_encode($cr, JSON_UNESCAPED_UNICODE));
$cols = $m->query("SHOW COLUMNS FROM class_room_rps");
mtrace('kolom class_room_rps:');
while ($c = $cols->fetch_assoc()) {
    mtrace('  ' . $c['Field'] . ' ' . $c['Type']);
}
$rps = $m->query("SELECT * FROM class_room_rps WHERE id_class_room = $idclass")->fetch_assoc();
if (!$rps) {
    mtrace('Tidak ada RPS untuk class ' . $idclass);
} else {
    foreach (['id', 'cp_mk', 'cp_lulus', 'desc'] as $k) {
        if (isset($rps[$k])) {
            $v = trim(strip_tags((string) $rps[$k]));
            $v = preg_replace('/\s+/', ' ', $v);
            mtrace($k . ': ' . mb_substr($v, 0, 800));
        }
    }
    $wid = (int) $rps['id'];
    $weeks = $m->query("SELECT id, kemampuan_akhir, indikator, bobot_penilaian FROM class_room_rps_real WHERE id_class_room_rps = $wid ORDER BY id LIMIT 16");
    $n = 0;
    while ($w = $weeks->fetch_assoc()) {
        $n++;
        $ka = trim(preg_replace('/\s+/', ' ', strip_tags((string) $w['kemampuan_akhir'])));
        $ind = trim(preg_replace('/\s+/', ' ', strip_tags((string) $w['indikator'])));
        mtrace(sprintf('W%d bobot=%s | %s | ind=%s', $n, $w['bobot_penilaian'], mb_substr($ka, 0, 120), mb_substr($ind, 0, 80)));
    }
}
$m->close();
