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

$settings = callEvomi('reseller/proxy_settings', $apiKey);

if (isset($settings['data'])) {
    $out = [];
    foreach ($settings['data'] as $type => $data) {
        if (isset($data['countries'])) {
            $out[$type] = $data['countries'];
        }
    }
    echo json_encode($out, JSON_PRETTY_PRINT);
} else {
    echo "Failed to fetch settings";
}
