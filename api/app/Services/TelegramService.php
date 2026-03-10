<?php

namespace App\Services;

use App\Models\BlogPost;
use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramService
{
    /**
     * Send a blog post to the configured Telegram channel.
     *
     * @param BlogPost $post
     * @return bool
     */
    public function sendBlogPost(BlogPost $post)
    {
        $enabled = Setting::getValue('telegram_auto_post_enabled', '0') === '1';
        $token = Setting::getValue('telegram_bot_token');
        $channelId = Setting::getValue('telegram_channel_id');

        if (!$enabled || !$token || !$channelId) {
            Log::info('Telegram: Skipping post share (disabled or missing config).');
            return false;
        }

        try {
            $websiteUrl = config('app.url', 'https://yourwebsite.com'); // Fallback URL
            $postUrl = rtrim($websiteUrl, '/') . '/blog/' . $post->slug;

            // Simple formatting for Telegram
            $caption = "<b>" . htmlspecialchars($post->title) . "</b>\n\n";
            $caption .= htmlspecialchars($post->excerpt) . "\n\n";
            $caption .= "🔗 <a href='{$postUrl}'>Read Full Article</a>";

            // If we have an image, use sendPhoto. Otherwise sendSendMessage.
            if ($post->image_url) {
                // Ensure photo URL is absolute for Telegram
                $photoUrl = $post->image_url;
                if (!str_starts_with($photoUrl, 'http')) {
                    $photoUrl = rtrim($websiteUrl, '/') . '/' . ltrim($photoUrl, '/');
                }

                $payload = [
                    'chat_id' => $channelId,
                    'photo'   => $photoUrl,
                    'caption' => $caption,
                    'parse_mode' => 'HTML',
                ];

                Log::info('Telegram: Sending photo', ['payload' => $payload]);

                $response = Http::withoutVerifying()->post("https://api.telegram.org/bot{$token}/sendPhoto", $payload);
            } else {
                $response = Http::withoutVerifying()->post("https://api.telegram.org/bot{$token}/sendMessage", [
                    'chat_id' => $channelId,
                    'text'    => $caption,
                    'parse_mode' => 'HTML',
                ]);
            }

            if ($response->successful()) {
                Log::info("Telegram: Post successfully shared: {$post->title}");
                return true;
            } else {
                Log::error("Telegram API Error: " . $response->body());
                return false;
            }

        } catch (\Exception $e) {
            Log::error("Telegram Service Exception: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Simple test method to verify connectivity.
     */
    public function sendTestMessage($message = "Hello from your AI Blog System! 🚀")
    {
        $token = Setting::getValue('telegram_bot_token');
        $channelId = Setting::getValue('telegram_channel_id');

        if (!$token || !$channelId) {
            return [
                'ok' => false,
                'description' => "Missing configuration (Token or Channel ID)."
            ];
        }

        try {
            $response = Http::withoutVerifying()->timeout(10)->post("https://api.telegram.org/bot{$token}/sendMessage", [
                'chat_id' => $channelId,
                'text'    => $message,
                'parse_mode' => 'HTML',
            ]);

            return $response->json();
        } catch (\Exception $e) {
            Log::error("Telegram Test Exception: " . $e->getMessage());
            return [
                'ok' => false,
                'description' => "Connection Error: " . $e->getMessage()
            ];
        }
    }
}
