<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$evomiApiKey = config('services.evomi.key');
$baseUrl = 'https://reseller.evomi.com/v2';

$username = 'up_4_jxycmc';
$ch = curl_init("$baseUrl/reseller/sub_users/isp/stock?username=$username");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: application/json', 
    'X-API-KEY: ' . $evomiApiKey
]);
$response = curl_exec($ch);
curl_close($ch);

$data = json_decode($response, true);
echo json_encode($data, JSON_PRETTY_PRINT);
