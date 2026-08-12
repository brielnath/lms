<?php
define('CLI_SCRIPT', true);
@error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);
require(__DIR__ . '/../../config.php');

$token = "320|Gs2KVb4VtawDCnc2foDC8Up9UXbe7c95NGgu1OKZcfe5622b";

function api_get($url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer 320|Gs2KVb4VtawDCnc2foDC8Up9UXbe7c95NGgu1OKZcfe5622b',
        'Accept: application/json'
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    $result = curl_exec($ch);
    curl_close($ch);
    return json_decode($result, true);
}

mtrace("=== EXTRACTING ALL LECTURERS FROM SIAKAD GRADES API ===");

$page = 1;
$lecturers = [];
$mappings = [];

while (true) {
    $res = api_get("https://siakad.sugenghartono.ac.id/api/grades-per-course?page={$page}");
    $items = $res['data'] ?? [];
    if (empty($items)) break;

    foreach ($items as $mhs) {
        $grades = $mhs['grade'] ?? [];
        foreach ($grades as $g) {
            $lec_id = $g['id_lecture'] ?? null;
            $lec_name = trim($g['lecture_name'] ?? '');
            $code = trim($g['lesson_code'] ?? '');

            if ($lec_id && !empty($lec_name)) {
                if (!isset($lecturers[$lec_id])) {
                    $lecturers[$lec_id] = $lec_name;
                }
                if (!empty($code)) {
                    $mappings[$code][$lec_id] = $lec_name;
                }
            }
        }
    }

    $page++;
    if ($page > 20) break;
}

mtrace("📋 Total Dosen Unik Terdeteksi dari SIAKAD: " . count($lecturers));
foreach ($lecturers as $id => $name) {
    mtrace("  • ID: {$id} | Nama: {$name}");
}

mtrace("\n📌 Total Mata Kuliah dengan Pemetaan Dosen: " . count($mappings));
