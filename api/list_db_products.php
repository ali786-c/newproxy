<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Product;

echo "--- Current Products in Database ---\n";
foreach (Product::all() as $p) {
    echo "ID: {$p->id} | Name: {$p->name} | Type: {$p->type} | EvomiID: {$p->evomi_product_id}\n";
}
