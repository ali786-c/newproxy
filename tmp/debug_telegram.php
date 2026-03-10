<?php
require __DIR__ . '/../api/vendor/autoload.php';
$app = require_once __DIR__ . '/../api/bootstrap/app.php';

use App\Models\Setting;
use Illuminate\Support\Facades\Http;

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$token = Setting::getValue('telegram_bot_token');
$channelId = Setting::getValue('telegram_channel_id');

echo "Testing with Token: " . substr($token, 0, 10) . "...\n";
echo "Testing with Channel ID: " . $channelId . "\n";

// 1. Try a simple text message first
$response = Http::withoutVerifying()->post("https://api.telegram.org/bot{$token}/sendMessage", [
    'chat_id' => $channelId,
    'text'    => "🔍 Debug Test: Simple Text Message",
    'parse_mode' => 'HTML',
]);

echo "Text Message Response Status: " . $response->status() . "\n";
echo "Text Message Response Body: " . $response->body() . "\n";

// 2. Try an image message with an ABSOLUTE URL (if possible)
$websiteUrl = config('app.url', 'https://yourwebsite.com');
$testImageUrl = "https://images.unsplash.com/photo-1488590528505-98d2b5aba04b?auto=format&fit=crop&q=80&w=800"; // Sample online image

$responsePhoto = Http::withoutVerifying()->post("https://api.telegram.org/bot{$token}/sendPhoto", [
    'chat_id' => $channelId,
    'photo'   => $testImageUrl,
    'caption' => "🔍 Debug Test: Image Message",
    'parse_mode' => 'HTML',
]);

echo "\nPhoto Message Response Status: " . $responsePhoto->status() . "\n";
echo "Photo Message Response Body: " . $responsePhoto->body() . "\n";
