<?php
use Illuminate\Contracts\Console\Kernel;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$logs = \App\Models\AdminLog::orderBy('id', 'desc')->take(20)->get();

foreach ($logs as $log) {
    echo "ID: {$log->id} | Action: {$log->action} | Target: {$log->target_user_id}\n";
    echo "Details: " . substr($log->details, 0, 200) . (strlen($log->details) > 200 ? "..." : "") . "\n";
    echo "IP: {$log->ip_address} | Geo: {$log->geo_city}, {$log->geo_country}\n";
    echo "--------------------------------------------------\n";
}
