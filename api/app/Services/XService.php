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
        $apiKey = Setting::getValue('x_api_key');
        $apiSecret = Setting::getValue('x_api_secret');
        $accessToken = Setting::getValue('x_access_token');
        $accessSecret = Setting::getValue('x_access_token_secret');

        if (!$enabled || !$apiKey || !$apiSecret || !$accessToken || !$accessSecret) {
            Log::info('X (Twitter): Skipping post share (disabled or missing config).');
            return false;
        }

        try {
            $websiteUrl = config('app.url', 'https://upgraderproxy.com');
            $rootUrl = str_replace('/api', '', rtrim($websiteUrl, '/'));
            $postUrl = $rootUrl . '/blog/' . $post->slug;

            $text = $post->title . "\n\n" . "Read more: " . $postUrl;

            $response = $this->makeOAuth1Request('POST', 'https://api.twitter.com/2/tweets', [
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
        $apiKey = Setting::getValue('x_api_key');
        $apiSecret = Setting::getValue('x_api_secret');
        $accessToken = Setting::getValue('x_access_token');
        $accessSecret = Setting::getValue('x_access_token_secret');

        if (!$apiKey || !$apiSecret || !$accessToken || !$accessSecret) {
            return [
                'ok' => false,
                'description' => "Missing configuration (API Key, Secret, Access Token, or Access Secret)."
            ];
        }

        try {
            $response = $this->makeOAuth1Request('POST', 'https://api.twitter.com/2/tweets', [
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
            'oauth_timestamp' => $timestamp,
            'oauth_token' => $accessToken,
            'oauth_version' => '1.0',
        ];

        // Base string components
        $baseStringParams = $params;
        $baseString = strtoupper($method) . '&' . rawurlencode($url) . '&' . rawurlencode(http_build_query($baseStringParams, '', '&', PHP_QUERY_RFC3986));
        
        $signingKey = rawurlencode($apiSecret) . '&' . rawurlencode($accessSecret);
        $signature = base64_encode(hash_hmac('sha1', $baseString, $signingKey, true));

        $params['oauth_signature'] = $signature;

        // Build Authorization header
        $headerParts = [];
        foreach ($params as $key => $value) {
            $headerParts[] = rawurlencode($key) . '="' . rawurlencode($value) . '"';
        }
        $authHeader = 'OAuth ' . implode(', ', $headerParts);

        return Http::withoutVerifying()
            ->withHeaders(['Authorization' => $authHeader])
            ->timeout(15)
            ->post($url, $data);
    }
}
