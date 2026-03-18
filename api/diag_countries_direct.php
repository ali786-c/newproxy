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
    foreach ($settings['data'] as $type => $data) {
        if (isset($data['countries'])) {
            echo "$type: " . count($data['countries']) . " countries\n";
            // Print first 5 countries as sample
            $sample = array_slice($data['countries'], 0, 5, true);
            foreach ($sample as $code => $name) {
                echo "  $code -> $name\n";
            }
        }
    }
} else {
    echo "Failed to fetch settings\n";
    print_r($settings);
}
