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
    echo "Top-level keys in 'data':\n";
    $keys = array_keys($settings['data']);
    print_r($keys);
    
    $checkKeys = ['residential', 'mobile', 'dataCenter', 'sharedDataCenter', 'static', 'isp', 'residentialIPV6', 'dataCenterIPV6'];
    echo "\nVerification of specific keys:\n";
    foreach ($checkKeys as $ck) {
        echo "$ck: " . (in_array($ck, $keys) ? "FOUND" : "NOT FOUND") . "\n";
    }
} else {
    echo "Data key not found in response.\n";
    print_r($settings);
}
