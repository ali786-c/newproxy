<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$evomiApiKey = config('services.evomi.key');
$baseUrl = 'https://reseller.evomi.com/v2';

$ch = curl_init("$baseUrl/reseller/my_info");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: application/json', 
    'X-API-KEY: ' . $evomiApiKey
]);
$response = curl_exec($ch);
curl_close($ch);

$data = json_decode($response, true);
if (isset($data['data']['products'])) {
    echo json_encode($data['data']['products'], JSON_PRETTY_PRINT);
} else {
    echo $response;
}
