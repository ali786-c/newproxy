<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Models\Product;
use App\Models\Order;
use App\Notifications\ProxyCreatedNotification;
use Illuminate\Support\Facades\Notification;

// Use the user from the log
$user = User::where('email', 'wahab75786@gmail.com')->first();
$product = Product::first();
$order = Order::latest()->first();

if (!$user || !$product || !$order) {
    die("User, Product or Order not found.\n");
}

echo "Testing notification for: " . $user->email . "\n";

try {
    // Send synchronously (ignoring ShouldQueue)
    $user->notifyNow(new ProxyCreatedNotification([
        'user' => ['name' => $user->name],
        'product' => ['name' => $product->name],
        'order' => ['id' => $order->id],
        'action_url' => url('/app/proxies'),
        'year' => date('Y')
    ]));
    echo "Notification sent successfully (sync)!\n";
} catch (\Exception $e) {
    echo "ERROR sending notification: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}
