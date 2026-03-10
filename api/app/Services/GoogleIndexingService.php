<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GoogleIndexingService
{
    private $indexingUrl = "https://indexing.googleapis.com/v3/urlNotifications:publish";
    private $tokenUrl = "https://oauth2.googleapis.com/token";

    /**
     * Submit a URL for indexing.
     */
    public function publishUrl($url)
    {
        if (Setting::getValue('google_indexing_enabled') !== '1') {
            return false;
        }

        $token = $this->getAccessToken();
        if (!$token) {
            Log::error('Google Indexing: Failed to obtain access token.');
            return false;
        }

        try {
            $response = Http::withToken($token)
                ->post($this->indexingUrl, [
                    'url' => $url,
                    'type' => 'URL_UPDATED',
                ]);

            if ($response->successful()) {
                Log::info('Google Indexing Success', ['url' => $url, 'response' => $response->json()]);
                return true;
            }

            Log::error('Google Indexing API Error', [
                'url' => $url,
                'status' => $response->status(),
                'body' => $response->body()
            ]);
            
            return false;
        } catch (\Exception $e) {
            Log::error('Google Indexing Exception', ['url' => $url, 'error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Get OAuth2 Access Token using JWT.
     */
    private function getAccessToken()
    {
        $encryptedJson = Setting::getValue('google_indexing_json');
        if (!$encryptedJson) return null;

        // Use controller's decryption (lightweight refactor would move this to a trait or Setting model)
        $controller = new \App\Http\Controllers\AutoBlogController();
        $json = $controller->decryptServiceKey($encryptedJson);
        
        $config = json_decode($json, true);
        if (!$config || !isset($config['private_key'])) return null;

        $now = time();
        $header = $this->base64UrlEncode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $payload = $this->base64UrlEncode(json_encode([
            'iss' => $config['client_email'],
            'scope' => 'https://www.googleapis.com/auth/indexing',
            'aud' => $this->tokenUrl,
            'exp' => $now + 3600,
            'iat' => $now,
        ]));

        $dataToSign = "$header.$payload";
        $signature = '';
        if (!openssl_sign($dataToSign, $signature, $config['private_key'], OPENSSL_ALGO_SHA256)) {
            Log::error('Google Indexing: JWT Signing failed.');
            return null;
        }

        $jwt = "$dataToSign." . $this->base64UrlEncode($signature);

        $response = Http::asForm()->post($this->tokenUrl, [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt,
        ]);

        return $response->json()['access_token'] ?? null;
    }

    /**
     * Base64Url Encoding Helper.
     */
    private function base64UrlEncode($data)
    {
        return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($data));
    }
}
