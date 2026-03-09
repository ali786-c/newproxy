<?php
require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$products = \App\Models\Product::where('is_active', true)->get();
foreach ($products as $p) {
    echo "ID: {$p->id}, Type: {$p->type}, Unit: '{$p->unit}'\n";
}
