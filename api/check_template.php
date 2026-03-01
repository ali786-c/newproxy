<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\EmailTemplate;

$key = 'proxy_created_user';
$template = EmailTemplate::where('key', $key)->first();

if ($template) {
    echo "Template '$key' exists.\n";
    echo "Status: " . ($template->is_active ? 'Active' : 'Inactive') . "\n";
    echo "Subject: " . $template->subject . "\n";
} else {
    echo "Template '$key' NOT FOUND in database.\n";
}

$allKeys = EmailTemplate::pluck('key')->toArray();
echo "All available keys: " . implode(', ', $allKeys) . "\n";
