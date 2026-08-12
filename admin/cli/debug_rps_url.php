<?php
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../config.php');

$rps_file = "SIF201-RPS-673da6cfe4f07.pdf";

// Coba berbagai pola URL
$urls = [
    "https://siakad.sugenghartono.ac.id/storage/rps/{$rps_file}",
    "https://siakad.sugenghartono.ac.id/storage/{$rps_file}",
    "https://siakad.sugenghartono.ac.id/uploads/rps/{$rps_file}",
    "https://siakad.sugenghartono.ac.id/public/storage/rps/{$rps_file}",
    "https://siakad.sugenghartono.ac.id/api/rps/{$rps_file}",
    "https://siakad.sugenghartono.ac.id/rps/{$rps_file}",
    "https://siakad.sugenghartono.ac.id/file/rps/{$rps_file}",
    "https://siakad.sugenghartono.ac.id/api/download/rps/{$rps_file}",
];

$token = "320|Gs2KVb4VtawDCnc2foDC8Up9UXbe7c95NGgu1OKZcfe5622b";

foreach ($urls as $url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $token,
        'Accept: */*'
    ]);
    $result = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $content_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $size = strlen($result);
    $final_url = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    curl_close($ch);
    
    $status = ($http == 200 && $size > 1000) ? "✅ FOUND" : "❌ {$http}";
    echo "{$status} | Size: {$size} | Type: {$content_type}" . PHP_EOL;
    echo "  URL: {$url}" . PHP_EOL;
    if ($final_url !== $url) echo "  Final: {$final_url}" . PHP_EOL;
    echo PHP_EOL;
}

// Juga coba akses halaman utama SIAKAD untuk cari pola storage
echo "=== Cek base storage ===" . PHP_EOL;
$ch = curl_init("https://siakad.sugenghartono.ac.id");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
$html = curl_exec($ch);
curl_close($ch);

// Cari pola /storage/ di HTML
if (preg_match_all('/(?:src|href)=["\']([^"\']*storage[^"\']*)["\']/', $html, $matches)) {
    echo "Storage URLs found in HTML:" . PHP_EOL;
    foreach (array_unique($matches[1]) as $m) {
        echo "  📎 {$m}" . PHP_EOL;
    }
}
