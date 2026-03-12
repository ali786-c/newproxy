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

            $payload = [
                'author' => $authorUrn,
                'commentary' => $message,
                'visibility' => 'PUBLIC',
                'distribution' => [
                    'feedDistribution' => 'MAIN_FEED',
                    'targetEntities' => [],
                    'thirdPartyDistributionChannels' => [],
                ],
                'content' => [
                    'article' => [
                        'source' => $postUrl,
                        'title' => $post->title,
                        'description' => $post->excerpt,
                    ],
                ],
                'lifecycleState' => 'PUBLISHED',
                'isReshareDisabledByAuthor' => false,
            ];

            $response = Http::withToken($accessToken)
                ->withHeaders([
                    'LinkedIn-Version' => '202501',
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
                    'LinkedIn-Version' => '202501',
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
