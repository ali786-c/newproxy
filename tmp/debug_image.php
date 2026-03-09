<?php
require __DIR__ . '/../api/vendor/autoload.php';
$app = require_once __DIR__ . '/../api/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Setting;
use Illuminate\Support\Facades\Http;

$dbApiKey = Setting::getValue('gemini_api_key');
$apiKey = ($dbApiKey && str_starts_with($dbApiKey, 'AIza')) ? $dbApiKey : config('services.gemini.key');

$imageModel = "gemini-3-pro-image-preview"; 
$url = "https://generativelanguage.googleapis.com/v1beta/models/{$imageModel}:generateContent?key={$apiKey}";

echo "Contacting Gemini API for image generation...\n";

$imagePrompt = "A beautiful sunset over the mountains.";

$response = Http::withoutVerifying()->timeout(150)->post($url, [
    'contents' => [
        ['parts' => [['text' => $imagePrompt]]]
    ],
    'generationConfig' => [
        'temperature' => 0.7,
    ],
    'imageConfig' => [
        'aspectRatio' => '16:9',
        'imageSize' => '2K',
    ],
]);

if ($response->failed()) {
    echo "❌ API Request Failed!\n";
    echo "Status: " . $response->status() . "\n";
    echo "Error Body:\n";
    print_r($response->json() ?? $response->body());
} else {
    echo "✅ API Request Succeeded!\n";
    $result = $response->json();
    $parts = $result['candidates'][0]['content']['parts'] ?? [];
    $foundImage = false;
    foreach ($parts as $part) {
        if (!empty($part['inlineData']['data'])) {
            $foundImage = true;
            echo "Successfully received image data (size: " . strlen($part['inlineData']['data']) . " bytes).\n";
        }
    }
    if (!$foundImage) {
        echo "⚠️ API succeeded but no image data found in response. Raw response:\n";
        print_r($result);
    }
}
