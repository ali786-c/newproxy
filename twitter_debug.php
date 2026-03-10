<?php
/**
 * Standalone Twitter API v2 Debug Script
 * 
 * Instructions:
 * 1. Upload this file to your public_html or xampp/htdocs folder.
 * 2. Access it via your browser: https://upgraderproxy.com/twitter_debug.php
 * 3. It will attempt to post a test tweet using the credentials below.
 */

// --- CONFIGURATION ---
$apiKey       = "Ibp6bchZvWKRbTc7g5sXbVprF";
$apiSecret    = "d5MQsYJQKDYxYe0chWbqWZOBANuxPUE7AGVeVtQZACTlgpMTj6";
$accessToken  = "2031414293149974528-PLLtOJXAw507T5PuvcBlHbIgSyFP3p";
$accessSecret = "W1ueAKeDok4v7QfTEWTPHdfzSDgxrUsqJllSlVpOi4ZgA";

$method = 'POST';
$url    = 'https://api.twitter.com/2/tweets';
$text   = "Debug Tweet from upgraderproxy.com - Time: " . date('Y-m-d H:i:s');

echo "<h1>Twitter API v2 Debugger</h1>";
echo "<pre>";

function makeOAuth1Request($method, $url, $data, $apiKey, $apiSecret, $accessToken, $accessSecret) {
    $nonce = bin2hex(random_bytes(16));
    $timestamp = time();

    $params = [
        'oauth_consumer_key'     => $apiKey,
        'oauth_nonce'            => $nonce,
        'oauth_signature_method' => 'HMAC-SHA1',
        'oauth_timestamp'        => (string)$timestamp,
        'oauth_token'            => $accessToken,
        'oauth_version'          => '1.0',
    ];

    // Sort parameters alphabetically
    ksort($params);

    // Build the base string
    $parameterString = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    $baseString = strtoupper($method) . '&' . rawurlencode($url) . '&' . rawurlencode($parameterString);
    
    echo "<b>Base String:</b> " . htmlspecialchars($baseString) . "\n\n";

    $signingKey = rawurlencode($apiSecret) . '&' . rawurlencode($accessSecret);
    $signature = base64_encode(hash_hmac('sha1', $baseString, $signingKey, true));

    echo "<b>Signature generated:</b> $signature \n\n";

    $params['oauth_signature'] = $signature;

    // Build Authorization header string
    $headerParts = [];
    foreach ($params as $key => $value) {
        $headerParts[] = rawurlencode($key) . '="' . rawurlencode($value) . '"';
    }
    $authHeader = 'OAuth ' . implode(', ', $headerParts);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: ' . $authHeader,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    echo "<b>Sending Request...</b>\n";
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if (curl_errno($ch)) {
        echo "<b>CURL Error:</b> " . curl_error($ch) . "\n";
    }
    
    curl_close($ch);
    
    return [
        'code' => $httpCode,
        'body' => $response
    ];
}

$result = makeOAuth1Request($method, $url, ['text' => $text], $apiKey, $apiSecret, $accessToken, $accessSecret);

echo "\n------------------------------------------------\n";
echo "<b>HTTP Status Code:</b> " . $result['code'] . "\n";
echo "<b>Response Body:</b> \n" . htmlspecialchars($result['body']) . "\n";
echo "------------------------------------------------\n";
echo "</pre>";
