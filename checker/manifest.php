<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache');

$scriptName = isset($_GET['from']) ? (string)$_GET['from'] : '';
$base       = isset($_GET['base']) ? rtrim((string)$_GET['base'], '/') : '';

// validate: ต้องเป็น path ที่ขึ้นต้นด้วย / และไม่มี ..
if ($scriptName === '' || strpos($scriptName, '..') !== false || $scriptName[0] !== '/') {
    $scriptName = '/';
}
if ($base !== '' && (strpos($base, '..') !== false || $base[0] !== '/')) {
    $base = '';
}

$startUrl = $scriptName;
$scopeDir = rtrim(str_replace('\\', '/', dirname($scriptName)), '/') . '/';
$iconBase = $base !== '' ? $base : rtrim(str_replace('\\', '/', dirname($scriptName)), '/');

echo json_encode([
    'name'             => 'Checker KDS',
    'short_name'       => 'Checker',
    'description'      => 'Kitchen Display System - Checker',
    'start_url'        => $startUrl,
    'scope'            => $scopeDir,
    'display'          => 'standalone',
    'background_color' => '#0A3FCC',
    'theme_color'      => '#1683FF',
    'orientation'      => 'landscape',
    'icons'            => [
        [
            'src'     => $iconBase . '/icon-192.png',
            'sizes'   => '192x192',
            'type'    => 'image/png',
            'purpose' => 'any maskable',
        ],
        [
            'src'     => $iconBase . '/icon-512.png',
            'sizes'   => '512x512',
            'type'    => 'image/png',
            'purpose' => 'any maskable',
        ],
    ],
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
