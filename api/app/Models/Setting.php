<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = ['key', 'value', 'type'];

    /**
     * List of keys that are sensitive and should be encrypted in the database.
     */
    protected static $sensitiveKeys = [
        'stripe_secret_key',
        'stripe_webhook_secret',
        'cryptomus_api_key',
        'cryptomus_webhook_secret',
        'smtp_pass',
        'crypto_api_key',
        'nowpayments_api_key',
        'nowpayments_ipn_secret',
        'linkedin_access_token',
    ];

    public static function getValue($key, $default = null)
    {
        $setting = self::where('key', $key)->first();
        if (!$setting) return $default;

        $value = $setting->value;

        // Decrypt if it's a sensitive key
        if (in_array($key, self::$sensitiveKeys) && !empty($value)) {
            try {
                return \Illuminate\Support\Facades\Crypt::decryptString($value);
            } catch (\Exception $e) {
                // If decryption fails, return as-is (maybe it wasn't encrypted yet)
                return $value;
            }
        }

        return $value;
    }

    /**
     * Helper to check if a key is sensitive.
     */
    public static function isSensitive($key)
    {
        return in_array($key, self::$sensitiveKeys);
    }
}
