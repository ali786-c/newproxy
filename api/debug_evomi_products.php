<?php

$apiKey = 'M8IEipQEgAFs8ITGIcJG';
$baseUrl = 'https://reseller.evomi.com/v2';

function callEvomi($endpoint, $apiKey) {
    $url = "https://reseller.evomi.com/v2/" . $endpoint;
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'X-API-KEY: ' . $apiKey,
        'Accept: application/json'
    ]);
    // Disable SSL verify for local testing if needed
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return [
        'code' => $httpCode,
        'body' => json_decode($response, true)
    ];
}

echo "--- Fetching Proxy Settings (Selective) ---\n";
$settings = callEvomi('reseller/proxy_settings', $apiKey);
if (isset($settings['body']['data'])) {
    $data = $settings['body']['data'];
    echo "Summary of Keys in Data:\n";
    print_r(array_keys($data));
    
    // In some API versions, products are listed under 'products' or similar
    if (isset($data['products'])) {
        echo "\nProducts found in settings:\n";
        print_r($data['products']);
    }

    // Check for specific categories that might indicate proxy types
    $categories = ['residential', 'mobile', 'datacenter', 'static', 'isp'];
    foreach ($categories as $cat) {
        if (isset($data[$cat])) {
            echo "\nCategory '$cat' is present in settings.\n";
        }
    }
} else {
    echo "Unexpected settings response format.\n";
    print_r($settings);
}

echo "\n--- Fetching Subusers List ---\n";
$subusers = callEvomi('reseller/subusers', $apiKey);
if (isset($subusers['body']['data'][0])) {
    $firstSub = $subusers['body']['data'][0];
    echo "First Subuser: " . $firstSub['username'] . "\n";
    echo "Products keys for this subuser:\n";
    print_r(array_keys($firstSub['products'] ?? []));
} else {
    echo "No subusers found.\n";
}
