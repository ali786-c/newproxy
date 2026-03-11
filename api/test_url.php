<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "APP_URL: " . env('APP_URL') . "\n";
echo "url('/test'): " . url('/test') . "\n";
echo "url('test'): " . url('test') . "\n";
echo "url('/api/test'): " . url('/api/test') . "\n";
