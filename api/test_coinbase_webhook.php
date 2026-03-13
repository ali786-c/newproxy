<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

use Illuminate\Support\Facades\Http;
use App\Helpers\CoinbaseHelper;

// 1. Setup mock data
$secret = 'test_secret';
putenv("COINBASE_WEBHOOK_SECRET=$secret");
config(['services.coinbase.webhook_secret' => $secret]);

$payload = [
    'event' => [
        'type' => 'charge:confirmed',
        'data' => [
            'id' => 'TEST_CHARGE_ID',
            'metadata' => [
                'user_id' => 1, // Ensure this user exists in your local DB
                'type' => 'topup',
                'amount' => 50.00,
                'original_amount' => 50.00
            ],
            'payments' => [
                [
                    'transaction_id' => '0x_test_tx_hash',
                    'status' => 'confirmed'
                ]
            ]
        ]
    ]
];

$jsonPayload = json_encode($payload);
$signature = hash_hmac('sha256', $jsonPayload, $secret);

echo "Simulating Coinbase Webhook...\n";
echo "Payload: $jsonPayload\n";
echo "Signature: $signature\n\n";

// 2. Make local request to the webhook endpoint
$url = 'http://localhost/api/webhook/coinbase'; // Adjust if your local URL is different

$response = Http::withHeaders([
    'X-Cc-Webhook-Signature' => $signature,
    'Content-Type' => 'application/json',
])->post($url, $payload);

echo "Status: " . $response->status() . "\n";
echo "Response: " . $response->body() . "\n";

if ($response->successful()) {
    echo "\nSUCCESS: Webhook handled correctly.\n";
} else {
    echo "\nFAILED: Webhook handling failed.\n";
}
