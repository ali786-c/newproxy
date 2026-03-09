<?php
require __DIR__ . '/../api/vendor/autoload.php';
$app = require_once __DIR__ . '/../api/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Setting;
use Illuminate\Support\Facades\Http;

$dbApiKey = Setting::getValue('gemini_api_key');
$apiKey = ($dbApiKey && str_starts_with($dbApiKey, 'AIza')) ? $dbApiKey : config('services.gemini.key');

$imageModel = "imagen-3.0-generate-001"; 
$url = "https://generativelanguage.googleapis.com/v1beta/models/{$imageModel}:predict?key={$apiKey}";

echo "Contacting Gemini API (Imagen) for image generation...\n";

$imagePrompt = "A futuristic city skyline at sunset.";

$response = Http::withoutVerifying()->timeout(150)->post($url, [
    'instances' => [
        ['prompt' => $imagePrompt]
    ],
    'parameters' => [
        'sampleCount' => 1,
        'aspectRatio' => '16:9',
        'outputOptions' => [
            'mimeType' => 'image/jpeg'
        ]
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
    print_r(array_keys($result));
}
