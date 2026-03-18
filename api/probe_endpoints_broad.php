<?php

$apiKey = 'M8IEipQEgAFs8ITGIcJG';
$baseUrl = 'https://reseller.evomi.com/v2';

$endpoints = [
    'give_rp_balance',
    'give_residential_balance',
    'give_mp_balance',
    'give_mobile_balance',
    'give_isp_balance',
    'give_static_balance',
    'give_static_residential_balance',
    'give_dc_balance',
    'give_datacenter_balance',
    'give_sdc_balance',
    'give_shared_datacenter_balance',
    'give_sharedDataCenter_balance',
    'give_dc_ipv6_balance',
    'give_datacenter_ipv6_balance',
    'give_dc_unmetered_balance',
    'give_datacenter_unmetered_balance'
];

foreach ($endpoints as $endpoint) {
    $url = "{$baseUrl}/reseller/sub_users/{$endpoint}";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'username' => 'test_user',
        'balance' => 10 // testing with > 0
    ]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'X-API-KEY: ' . $apiKey,
        'Accept: application/json',
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "Endpoint: $endpoint | Status: $status\n";
    if ($status != 404) {
        echo "  Body: " . substr($response, 0, 100) . "...\n";
    }
}
