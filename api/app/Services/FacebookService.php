<?php

namespace App\Services;

use App\Models\BlogPost;
use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FacebookService
{
    /**
     * Send a blog post to the configured Facebook Page.
     *
     * @param BlogPost $post
     * @return bool
     */
    public function sendBlogPost(BlogPost $post)
    {
        $enabled = Setting::getValue('facebook_auto_post_enabled', '0') === '1';
        $pageId = Setting::getValue('facebook_page_id');
        $encryptedToken = Setting::getValue('facebook_access_token');
        $accessToken = $this->decryptToken($encryptedToken);

        if (!$enabled || !$pageId || !$accessToken) {
            Log::info('Facebook: Skipping post share (disabled or missing config).');
            return false;
        }

        try {
            $websiteUrl = config('app.url', 'http://upgraderproxy.com'); 
            // Ensure the blog link points to the root (no /api/)
            $rootUrl = str_replace('/api', '', rtrim($websiteUrl, '/'));
            $postUrl = $rootUrl . '/blog/' . $post->slug;

            $message = $post->title . "\n\n" . $post->excerpt . "\n\n" . "Read more: " . $postUrl;

            // Handle Image vs Text-only post
            if (!empty($post->image_url)) {
                $photoUrl = $post->image_url;
                
                // If it's already an absolute URL, use it
                if (str_starts_with($photoUrl, 'http')) {
                    // No change needed
                } else {
                    // It's a relative path starting with 'api/storage/...' or '/api/storage/...'
                    // We need to append it to the BASE website URL (upgraderproxy.com)
                    // NOT to the APP_URL if it already includes /api
                    $rootUrl = str_replace('/api', '', rtrim($websiteUrl, '/'));
                    $photoUrl = $rootUrl . '/' . ltrim($photoUrl, '/');
                }

                Log::info("Facebook: Attempting to share photo [Post ID: {$post->id}]: " . $photoUrl);

                $response = Http::withoutVerifying()->post("https://graph.facebook.com/v25.0/{$pageId}/photos", [
                    'url'          => $photoUrl,
                    'caption'      => $message,
                    'access_token' => $accessToken,
                ]);
            } else {
                $response = Http::withoutVerifying()->post("https://graph.facebook.com/v25.0/{$pageId}/feed", [
                    'message'      => $message,
                    'access_token' => $accessToken,
                ]);
            }

            if ($response->successful()) {
                Log::info("Facebook: Post successfully shared: {$post->title}");
                return true;
            } else {
                Log::error("Facebook API Error for ID {$post->id}: " . $response->body() . " | URL attempted: " . ($photoUrl ?? 'None'));
                return false;
            }

        } catch (\Exception $e) {
            Log::error("Facebook Service Exception: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Test method to verify connectivity.
     */
    public function sendTestMessage($message = "Hello from our AI Blog System! 🚀 Testing Facebook Integration.")
    {
        $pageId = Setting::getValue('facebook_page_id');
        $encryptedToken = Setting::getValue('facebook_access_token');
        $accessToken = $this->decryptToken($encryptedToken);

        if (!$pageId || !$accessToken) {
            return [
                'ok' => false,
                'description' => "Missing configuration (Page ID or Access Token)."
            ];
        }

        try {
            $response = Http::withoutVerifying()->timeout(10)->post("https://graph.facebook.com/v25.0/{$pageId}/feed", [
                'message'      => $message,
                'access_token' => $accessToken,
            ]);

            return [
                'ok' => $response->successful(),
                'body' => $response->json(),
                'description' => $response->successful() ? "Post successful!" : "API Error: " . $response->body()
            ];
        } catch (\Exception $e) {
            Log::error("Facebook Test Exception: " . $e->getMessage());
            return [
                'ok' => false,
                'description' => "Connection Error: " . $e->getMessage()
            ];
        }
    }

    /**
     * Decrypt Access Token using the shared indexing key.
     */
    private function decryptToken($data)
    {
        $key = config('services.google.indexing_key');
        if (!$key || empty($data)) return $data;

        try {
            $decoded = base64_decode($data);
            $ivLen = openssl_cipher_iv_length('aes-256-cbc');
            
            if (strlen($decoded) <= $ivLen) return $data;

            $iv = substr($decoded, 0, $ivLen);
            $ciphertext = substr($decoded, $ivLen);

            $decrypted = openssl_decrypt($ciphertext, 'aes-256-cbc', hex2bin($key), 0, $iv);
            
            return $decrypted ?: $data;
        } catch (\Exception $e) {
            return $data;
        }
    }
}
