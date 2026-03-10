<?php
/**
 * Standalone Twitter API v2 Debug Script (Enhanced)
 */

$apiKey       = "Ibp6bchZvWKRbTc7g5sXbVprF";
$apiSecret    = "d5MQsYJQKDYxYe0chWbqWZOBANuxPUE7AGVeVtQZACTlgpMTj6";
$accessToken  = "2031414293149974528-PLLtOJXAw507T5PuvcBlHbIgSyFP3p";
$accessSecret = "W1ueAKeDok4v7QfTEWTPHdfzSDgxrUsqJllSlVpOi4ZgA";

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

    ksort($params);

    $parameterString = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    $baseString = strtoupper($method) . '&' . rawurlencode($url) . '&' . rawurlencode($parameterString);
    
    $signingKey = rawurlencode($apiSecret) . '&' . rawurlencode($accessSecret);
    $signature = base64_encode(hash_hmac('sha1', $baseString, $signingKey, true));

    $params['oauth_signature'] = $signature;

    $headerParts = [];
    foreach ($params as $key => $value) {
        $headerParts[] = rawurlencode($key) . '="' . rawurlencode($value) . '"';
    }
    $authHeader = 'OAuth ' . implode(', ', $headerParts);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: ' . $authHeader,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $httpCode, 'body' => $response];
}

echo "<h2>Step 1: Testing GET /2/users/me (Verify Keys)</h2>";
$res1 = makeOAuth1Request('GET', 'https://api.x.com/2/users/me', null, $apiKey, $apiSecret, $accessToken, $accessSecret);
echo "Status: " . $res1['code'] . "\n";
echo "Body: " . $res1['body'] . "\n";

echo "<h2>Step 2: Testing POST /2/tweets (Verify Write Permissions)</h2>";
$res2 = makeOAuth1Request('POST', 'https://api.x.com/2/tweets', ['text' => "Testing from debugger " . time()], $apiKey, $apiSecret, $accessToken, $accessSecret);
echo "Status: " . $res2['code'] . "\n";
echo "Body: " . $res2['body'] . "\n";

echo "<h2>Diagnostic Check</h2>";
if ($res1['code'] == 401) {
    echo "❌ <b>401 on GET:</b> Your Keys or Tokens are INVALID. Please regenerate them in Developer Portal.\n";
} elseif ($res1['code'] == 200 && $res2['code'] == 403) {
    echo "❌ <b>403 on POST:</b> Keys are working, but you don't have WRITE permissions. 
    1. Go to App Settings -> User authentication settings.
    2. Change permissions to 'Read and Write'.
    3. REGENERATE your Access Token and Secret AFTER changing permissions.\n";
} elseif ($res1['code'] == 200 && $res2['code'] == 401) {
    echo "❌ <b>401 on POST:</b> This is rare but usually means the Access Token doesn't support v2 Write. Regenerate them.\n";
}

echo "</pre>";
