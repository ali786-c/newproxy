<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$evomiApiKey = config('services.evomi.key');
$baseUrl = 'https://reseller.evomi.com/v2';

$endpoints = [
    '/reseller/plans',
    '/reseller/prices',
    '/reseller/pricing',
    '/reseller/products',
];

foreach ($endpoints as $ep) {
    echo "Testing $ep...\n";
    $ch = curl_init("$baseUrl$ep");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/json', 
        'X-API-KEY: ' . $evomiApiKey
    ]);
    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    echo "  Status: $status\n";
    echo "  Response: " . substr($response, 0, 100) . "...\n";
}
