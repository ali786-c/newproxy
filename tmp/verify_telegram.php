<?php
require __DIR__ . '/../api/vendor/autoload.php';
$app = require_once __DIR__ . '/../api/bootstrap/app.php';

use App\Models\Setting;
use App\Services\TelegramService;
use Illuminate\Support\Facades\Http;

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "Configuring Telegram Settings...\n";

Setting::updateOrCreate(['key' => 'telegram_bot_token'], ['value' => '8026983291:AAEX7YpZpEOXsPl3Im7Hgm1V-uNUCbxH8uY']);
Setting::updateOrCreate(['key' => 'telegram_channel_id'], ['value' => '-1003795255366']);
Setting::updateOrCreate(['key' => 'telegram_auto_post_enabled'], ['value' => '1']);

echo "Settings saved successfully!\n";
echo "Attempting to send a test message...\n";

$telegram = new TelegramService();
$result = $telegram->sendTestMessage("✅ Telegram Automation Setup Success!\n\nBot is now connected to this channel. Blogs will be shared here automatically.");

print_r($result);
