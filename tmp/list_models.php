<?php
require __DIR__ . '/../api/vendor/autoload.php';
$app = require_once __DIR__ . '/../api/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Setting;
use Illuminate\Support\Facades\Http;

$dbApiKey = Setting::getValue('gemini_api_key');
$apiKey = ($dbApiKey && str_starts_with($dbApiKey, 'AIza')) ? $dbApiKey : config('services.gemini.key');

$url = "https://generativelanguage.googleapis.com/v1beta/models?key={$apiKey}";

echo "Contacting Gemini API for models list...\n";

$response = Http::withoutVerifying()->timeout(150)->get($url);

if ($response->failed()) {
    echo "❌ API Request Failed!\n";
    print_r($response->json() ?? $response->body());
} else {
    echo "✅ API Request Succeeded!\n";
    $models = $response->json('models');
    foreach ($models as $m) {
        if (str_contains(strtolower($m['name']), 'image') || str_contains(strtolower($m['name']), 'imagen')) {
            echo $m['name'] . " - " . $m['version'] . "\n";
            echo "  Method: " . $m['supportedGenerationMethods'][0] . "\n";
        }
    }
}
