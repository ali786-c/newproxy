<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Log;

class NowPaymentsHelper
{
    /**
     * Verify the signature from NOWPayments IPN.
     * 
     * @param array $payload The raw POST data array
     * @param string $receivedSignature The signature from the x-nowpayments-sig header
     * @param string $ipnSecret The IPN Secret from NOWPayments dashboard
     * @return bool
     */
    public static function verifySignature(array $payload, string $receivedSignature, string $ipnSecret): bool
    {
        if (empty($receivedSignature) || empty($ipnSecret)) {
            return false;
        }

        // 1. Sort the payload alphabetically by keys
        ksort($payload);

        // 2. Convert to JSON string (canonical form)
        // Note: PHP's json_encode with these flags ensures consistency with the documentation's requirements
        $jsonPayload = json_encode($payload, JSON_UNESCAPED_SLASHES);

        // 3. Generate HMAC-SHA512 signature using the IPN Secret
        $expectedSignature = hash_hmac('sha512', $jsonPayload, $ipnSecret);

        $isValid = hash_equals($expectedSignature, $receivedSignature);

        if (!$isValid) {
            Log::warning("NowPayments IPN Signature Mismatch.", [
                'expected' => $expectedSignature,
                'received' => $receivedSignature,
                'payload' => $jsonPayload
            ]);
        }

        return $isValid;
    }
}
