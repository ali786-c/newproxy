<?php

namespace App\Services;

use App\Models\BlogPost;
use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class LinkedInService
{
    /**
     * Send a blog post to LinkedIn.
     *
     * @param BlogPost $post
     * @return bool
     */
    public function sendBlogPost(BlogPost $post)
    {
        $enabled = Setting::getValue('linkedin_auto_post_enabled', '0') === '1';
        $accessToken = Setting::getValue('linkedin_access_token');
        $authorUrn = Setting::getValue('linkedin_urn');

        if (!$enabled || !$accessToken || !$authorUrn) {
            Log::info('LinkedIn: Skipping post share (disabled or missing config).');
            return false;
        }

        try {
            $websiteUrl = config('app.url', 'https://upgraderproxy.com');
            $rootUrl = rtrim($websiteUrl, '/');
            if (str_ends_with($rootUrl, '/api')) {
                $rootUrl = Str::replaceLast('/api', '', $rootUrl);
            }
            $postUrl = $rootUrl . '/blog/' . $post->slug;

            $message = $post->title . "\n\n" . $post->excerpt . "\n\n" . "Read more: " . $postUrl;

            // Attempt to upload image if available
            $imageUrn = null;
            if ($post->image_url) {
                $imageUrn = $this->uploadImage($post->image_url, $accessToken, $authorUrn);
            }

            $payload = [
                'author' => $authorUrn,
                'commentary' => $message,
                'visibility' => 'PUBLIC',
                'distribution' => [
                    'feedDistribution' => 'MAIN_FEED',
                    'targetEntities' => [],
                    'thirdPartyDistributionChannels' => [],
                ],
                'lifecycleState' => 'PUBLISHED',
                'isReshareDisabledByAuthor' => false,
            ];

            if ($imageUrn) {
                $payload['content'] = [
                    'media' => [
                        'id' => $imageUrn,
                        'altText' => $post->title,
                    ],
                ];
            } else {
                $payload['content'] = [
                    'article' => [
                        'source' => $postUrl,
                        'title' => $post->title,
                        'description' => $post->excerpt,
                    ],
                ];
            }

            $response = Http::withToken($accessToken)
                ->withHeaders([
                    'LinkedIn-Version' => '202503',
                    'X-Restli-Protocol-Version' => '2.0.0',
                ])
                ->withoutVerifying()
                ->post('https://api.linkedin.com/rest/posts', $payload);

            if ($response->successful()) {
                Log::info("LinkedIn: Post successfully shared: {$post->title}");
                return true;
            } else {
                Log::error("LinkedIn API Error [Post ID: {$post->id}]: " . $response->body());
                return false;
            }

        } catch (\Exception $e) {
            Log::error("LinkedIn Service Exception: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Upload an image to LinkedIn and return its URN.
     */
    private function uploadImage($imageUrl, $accessToken, $authorUrn)
    {
        try {
            Log::info("LinkedIn: Handling image upload for: $imageUrl");

            // 1. Resolve local path elegantly
            $path = null;
            
            if (str_starts_with($imageUrl, 'http')) {
                // Absolute URL
                $parsed = parse_url($imageUrl);
                $pathPart = ltrim($parsed['path'] ?? '', '/');
                $path = public_path($pathPart);
            } else {
                // Relative path
                $path = public_path(ltrim($imageUrl, '/'));
            }

            // Fallback for common storage paths if first attempt fails
            if (!file_exists($path)) {
                $filename = basename($imageUrl);
                $fallback = public_path('storage/blog/' . $filename);
                if (file_exists($fallback)) {
                    $path = $fallback;
                }
            }

            if (!file_exists($path)) {
                Log::warning("LinkedIn: Image not found at $path. Posting text/article only.");
                return null;
            }

            Log::info("LinkedIn: Resolved local path: $path");

            // 1. Initialize Upload
            $initResponse = Http::withToken($accessToken)
                ->withHeaders([
                    'LinkedIn-Version' => '202503',
                    'X-Restli-Protocol-Version' => '2.0.0',
                ])
                ->withoutVerifying()
                ->post('https://api.linkedin.com/rest/images?action=initializeUpload', [
                    'initializeUploadRequest' => [
                        'owner' => $authorUrn,
                    ],
                ]);

            if (!$initResponse->successful()) {
                Log::error("LinkedIn: Image Init Failed: " . $initResponse->body());
                return null;
            }

            $initData = $initResponse->json();
            $uploadUrl = $initData['value']['uploadUrl'];
            $imageUrn = $initData['value']['image'];

            // 2. Upload Binary
            $imageBinary = file_get_contents($path);
            $mimeType = mime_content_type($path);

            $uploadResponse = Http::withBody($imageBinary, $mimeType)
                ->withoutVerifying()
                ->put($uploadUrl);

            if (!$uploadResponse->successful()) {
                Log::error("LinkedIn: Image Binary Upload Failed status: " . $uploadResponse->status());
                return null;
            }

            Log::info("LinkedIn: Image successfully uploaded as asset: $imageUrn");
            return $imageUrn;

        } catch (\Exception $e) {
            Log::error("LinkedIn: Image Upload Exception: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Test method to verify connectivity.
     */
    public function sendTestMessage($message = "Hello from our AI Blog System! 🚀 Testing LinkedIn Integration.")
    {
        $accessToken = Setting::getValue('linkedin_access_token');
        $authorUrn = Setting::getValue('linkedin_urn');

        if (!$accessToken || !$authorUrn) {
            return [
                'ok' => false,
                'description' => "Missing configuration (Access Token or Author URN)."
            ];
        }

        try {
            $payload = [
                'author' => $authorUrn,
                'commentary' => $message,
                'visibility' => 'PUBLIC',
                'distribution' => [
                    'feedDistribution' => 'MAIN_FEED',
                    'targetEntities' => [],
                    'thirdPartyDistributionChannels' => [],
                ],
                'lifecycleState' => 'PUBLISHED',
                'isReshareDisabledByAuthor' => false,
            ];

            $response = Http::withToken($accessToken)
                ->withHeaders([
                    'LinkedIn-Version' => '202503',
                    'X-Restli-Protocol-Version' => '2.0.0',
                ])
                ->withoutVerifying()
                ->post('https://api.linkedin.com/rest/posts', $payload);

            return [
                'ok' => $response->successful(),
                'body' => $response->json(),
                'description' => $response->successful() ? "Post successful!" : "API Error: " . $response->body()
            ];
        } catch (\Exception $e) {
            Log::error("LinkedIn Test Exception: " . $e->getMessage());
            return [
                'ok' => false,
                'description' => "Connection Error: " . $e->getMessage()
            ];
        }
    }
}
