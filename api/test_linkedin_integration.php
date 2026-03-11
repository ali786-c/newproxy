<?php

/**
 * Isolated LinkedIn API Integration Test
 * Run this from the server to verify credential decryption and API connectivity.
 */

// Load Laravel Bootstrap
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Setting;
use App\Services\LinkedInService;
use Illuminate\Support\Facades\Log;

echo "--- LinkedIn Integration Test ---\n";

$accessToken = Setting::getValue('linkedin_access_token');
$authorUrn = Setting::getValue('linkedin_urn');

if (!$accessToken) {
    echo "❌ Error: linkedin_access_token not found in settings.\n";
    exit(1);
}

if (!$authorUrn) {
    echo "❌ Error: linkedin_urn not found in settings.\n";
    exit(1);
}

echo "✅ Credentials Found (Token length: " . strlen($accessToken) . " chars)\n";
echo "👤 Author URN: $authorUrn\n";

$service = new LinkedInService();
echo "🚀 Sending test message to LinkedIn...\n";

$result = $service->sendTestMessage("System Verification: LinkedIn Automation is now active on our SaaS. 🤖✅");

if ($result['ok']) {
    echo "🎉 SUCCESS! Test post shared on LinkedIn.\n";
    echo "Response Body: " . json_encode($result['body']) . "\n";
} else {
    echo "❌ FAILED: " . $result['description'] . "\n";
    if (isset($result['body'])) {
        echo "Raw Response: " . json_encode($result['body']) . "\n";
    }
}

echo "--- End of Test ---\n";
