<?php
require __DIR__ . '/api/vendor/autoload.php';
$app = require_once __DIR__ . '/api/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\DB;

$user = User::first();
if (!$user) {
    die("No user found");
}

echo "Testing notifications for user: " . $user->email . "\n";

try {
    $notifications = $user->notifications;
    echo "Count: " . $notifications->count() . "\n";
    foreach ($notifications as $n) {
        echo "ID: " . $n->id . "\n";
        echo "Data Type: " . gettype($n->data) . "\n";
        print_r($n->data);
        echo "\n---\n";
    }
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}
