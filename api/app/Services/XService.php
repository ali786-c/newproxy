<?php

namespace App\Services;

use App\Models\BlogPost;
use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class XService
{
    /**
     * Send a blog post as a tweet to X (Twitter).
     *
     * @param BlogPost $post
     * @return bool
     */
    public function sendTweet(BlogPost $post)
    {
        $enabled = Setting::getValue('x_auto_post_enabled', '0') === '1';
        $apiKey = trim(Setting::getValue('x_api_key', ''));
        $apiSecretEnc = trim(Setting::getValue('x_api_secret', ''));
        $accessToken = trim(Setting::getValue('x_access_token', ''));
        $accessSecretEnc = trim(Setting::getValue('x_access_token_secret', ''));

        $apiSecret = $this->decryptServiceKey($apiSecretEnc);
        $accessSecret = $this->decryptServiceKey($accessSecretEnc);

        if (!$enabled || !$apiKey || !$apiSecret || !$accessToken || !$accessSecret) {
            Log::info('X (Twitter): Skipping post share (disabled or missing config).');
            return false;
        }

        try {
            $websiteUrl = config('app.url', 'https://upgraderproxy.com');
            $rootUrl = str_replace('/api', '', rtrim($websiteUrl, '/'));
            $postUrl = $rootUrl . '/blog/' . $post->slug;

            $text = $post->title . "\n\n" . "Read more: " . $postUrl;

            $response = $this->makeOAuth1Request('POST', 'https://api.x.com/2/tweets', [
                'text' => $text
            ], $apiKey, $apiSecret, $accessToken, $accessSecret);

            if ($response->successful()) {
                Log::info("X (Twitter): Tweet successfully posted: {$post->title}");
                return true;
            } else {
                Log::error("X (Twitter) API Error: " . $response->body());
                return false;
            }

        } catch (\Exception $e) {
            Log::error("X (Twitter) Service Exception: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Test method to verify connectivity.
     */
    public function sendTestTweet($message = "Hello from our AI Blog System! 🚀 Testing X (Twitter) Integration.")
    {
        $apiKey = trim(Setting::getValue('x_api_key', ''));
        $apiSecretEnc = trim(Setting::getValue('x_api_secret', ''));
        $accessToken = trim(Setting::getValue('x_access_token', ''));
        $accessSecretEnc = trim(Setting::getValue('x_access_token_secret', ''));

        $apiSecret = $this->decryptServiceKey($apiSecretEnc);
        $accessSecret = $this->decryptServiceKey($accessSecretEnc);

        if (!$apiKey || !$apiSecret || !$accessToken || !$accessSecret) {
            return [
                'ok' => false,
                'description' => "Missing configuration (API Key, Secret, Access Token, or Access Secret)."
            ];
        }

        try {
            $response = $this->makeOAuth1Request('POST', 'https://api.x.com/2/tweets', [
                'text' => $message
            ], $apiKey, $apiSecret, $accessToken, $accessSecret);

            return [
                'ok' => $response->successful(),
                'body' => $response->json(),
                'description' => $response->successful() ? "Tweet successful!" : "API Error: " . $response->body()
            ];
        } catch (\Exception $e) {
            return [
                'ok' => false,
                'description' => "Connection Error: " . $e->getMessage()
            ];
        }
    }

    /**
     * Decrypt sensitive service keys.
     */
    private function decryptServiceKey($data)
    {
        if (empty($data)) return null;
        
        // If it doesn't look like base64-iv format, it might be unencrypted 
        // (for legacy or if encryption is not yet configured)
        if (strlen($data) < 32 || !preg_match('%^[a-zA-Z0-9/+]*={0,2}$%', $data)) {
            return $data; 
        }

        $encryptionKey = config('services.google.indexing_key');
        if (!$encryptionKey) return $data;

        try {
            $decoded = base64_decode($data);
            $ivLen = openssl_cipher_iv_length('aes-256-cbc');
            if (strlen($decoded) <= $ivLen) return $data;

            $iv = substr($decoded, 0, $ivLen);
            $ciphertext = substr($decoded, $ivLen);

            $decrypted = openssl_decrypt($ciphertext, 'aes-256-cbc', hex2bin($encryptionKey), 0, $iv);
            return $decrypted !== false ? $decrypted : $data;
        } catch (\Exception $e) {
            return $data;
        }
    }

    /**
     * Helper to make an OAuth 1.0a signed request.
     */
    private function makeOAuth1Request($method, $url, $data, $apiKey, $apiSecret, $accessToken, $accessSecret)
    {
        $nonce = bin2hex(random_bytes(16));
        $timestamp = time();

        $params = [
            'oauth_consumer_key' => $apiKey,
            'oauth_nonce' => $nonce,
            'oauth_signature_method' => 'HMAC-SHA1',
            'oauth_timestamp' => (string)$timestamp,
            'oauth_token' => $accessToken,
            'oauth_version' => '1.0',
        ];

        // Sort parameters alphabetically
        ksort($params);

        // Build the base string
        $parameterString = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        $baseString = strtoupper($method) . '&' . rawurlencode($url) . '&' . rawurlencode($parameterString);
        
        $signingKey = rawurlencode($apiSecret) . '&' . rawurlencode($accessSecret);
        $signature = base64_encode(hash_hmac('sha1', $baseString, $signingKey, true));

        $params['oauth_signature'] = $signature;

        // Build Authorization header string
        $headerParts = [];
        foreach ($params as $key => $value) {
            $headerParts[] = rawurlencode($key) . '="' . rawurlencode($value) . '"';
        }
        $authHeader = 'OAuth ' . implode(', ', $headerParts);

        // Log for debugging (Sensitive keys masked)
        $maskedBase = str_replace([$apiKey, $accessToken], ['MASKED_CONSUMER_KEY', 'MASKED_ACCESS_TOKEN'], $baseString);
        \App\Models\AdminLog::log('x_oauth_debug', 'X (Twitter) OAuth Debug Signature', null, [
            'timestamp' => $timestamp,
            'server_time' => date('Y-m-d H:i:s'),
            'base_string_masked' => $maskedBase,
            'url' => $url
        ]);

        return Http::withoutVerifying()
            ->withHeaders(['Authorization' => $authHeader])
            ->timeout(15)
            ->post($url, $data);
    }
}
