<?php
require __DIR__ . '/../api/vendor/autoload.php';
$app = require_once __DIR__ . '/../api/bootstrap/app.php';

use App\Models\Setting;

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "Token: " . Setting::getValue('telegram_bot_token') . "\n";
echo "Chat ID: " . Setting::getValue('telegram_channel_id') . "\n";
echo "Enabled: " . Setting::getValue('telegram_auto_post_enabled') . "\n";
