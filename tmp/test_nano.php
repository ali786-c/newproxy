<?php
require __DIR__ . '/../api/vendor/autoload.php';
$app = require_once __DIR__ . '/../api/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Setting;
use Illuminate\Support\Facades\Http;

$dbApiKey = Setting::getValue('gemini_api_key');
$apiKey = ($dbApiKey && str_starts_with($dbApiKey, 'AIza')) ? $dbApiKey : config('services.gemini.key');

$imageModel = "gemini-3-pro-image-preview"; // Using the Pro version
$url = "https://generativelanguage.googleapis.com/v1beta/models/{$imageModel}:generateContent?key={$apiKey}";

echo "Contacting Gemini API ($imageModel) for image generation...\n";

$imagePrompt = "Create a picture of a nano banana dish in a fancy restaurant with a Gemini theme. Output wide 16:9 banner.";

$response = Http::withoutVerifying()->timeout(150)->post($url, [
    'contents' => [
        ['parts' => [['text' => $imagePrompt]]]
    ]
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
        } elseif (!empty($part['text'])) {
            echo "Received text: " . $part['text'] . "\n";
        }
    }
    if (!$foundImage) {
        echo "⚠️ No inline image found. Run print_r to see response.\n";
    }
}
