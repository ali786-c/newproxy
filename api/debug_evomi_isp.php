<?php

$apiKey = 'M8IEipQEgAFs8ITGIcJG';

function callEvomi($endpoint, $apiKey) {
    $url = "https://reseller.evomi.com/v2/" . $endpoint;
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'X-API-KEY: ' . $apiKey,
        'Accept: application/json'
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

echo "--- Fetching ISP Stock (Verifying Static/ISP Support) ---\n";
// The endpoint requires a username in some versions, but let's try a global check if possible
// Or just check if the endpoint exists/responds
$stock = callEvomi('reseller/sub_users/isp/stock', $apiKey);
print_r($stock);
