<?php
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../config.php');

$ch = curl_init('https://siakad.sugenghartono.ac.id/api/login');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode([
        'email' => 'akademik@sugenghartono.ac.id',
        'password' => '321',
    ]),
    CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_TIMEOUT => 25,
]);
$raw = curl_exec($ch);
curl_close($ch);
$j = json_decode($raw, true);
mtrace('top keys: ' . implode(',', array_keys($j ?? [])));
if (isset($j['data'])) {
    if (is_array($j['data'])) {
        mtrace('data keys: ' . implode(',', array_keys($j['data'])));
        foreach ($j['data'] as $k => $v) {
            $t = gettype($v);
            if (is_string($v)) {
                mtrace("  data.$k = string(len=" . strlen($v) . ") prefix=" . substr($v, 0, 8));
            } else if (is_array($v)) {
                mtrace("  data.$k = array keys=" . implode(',', array_keys($v)));
            } else {
                mtrace("  data.$k = $t");
            }
        }
    } else {
        mtrace('data type=' . gettype($j['data']));
    }
}
// Try common nested paths
$candidates = [
    $j['token'] ?? null,
    $j['access_token'] ?? null,
    $j['data']['token'] ?? null,
    $j['data']['access_token'] ?? null,
    $j['data']['accessToken'] ?? null,
    $j['data']['bearer'] ?? null,
    $j['data']['api_token'] ?? null,
    $j['data']['user']['token'] ?? null,
];
foreach ($candidates as $i => $c) {
    mtrace("candidate $i: " . ($c ? ('yes len=' . strlen((string)$c)) : 'no'));
}
