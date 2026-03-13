<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Log;

class CoinbaseHelper
{
    /**
     * Verify Coinbase API webhook signature.
     * 
     * @param string $payload The raw request body
     * @param string $signature The X-Cc-Webhook-Signature header
     * @param string $secret The shared webhook secret
     * @return bool
     */
    public static function verifySignature($payload, $signature, $secret)
    {
        if (empty($signature) || empty($secret)) {
            return false;
        }

        $computedSignature = hash_hmac('sha256', $payload, $secret);

        return hash_equals($computedSignature, $signature);
    }
}
