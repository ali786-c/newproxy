<?php
require __DIR__ . '/api/vendor/autoload.php';
$app = require_once __DIR__ . '/api/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\NotificationController;

$user = User::first();
Auth::login($user);

$request = Request::create('/api/notifications', 'GET');
$request->setUserResolver(function () use ($user) {
    return $user;
});

$controller = new NotificationController();

try {
    $response = $controller->index($request);
    echo "Response Status: " . $response->getStatusCode() . "\n";
    echo "Response Content: " . $response->getContent() . "\n";
} catch (\Throwable $e) {
    echo "CAUGHT ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . " Line: " . $e->getLine() . "\n";
    echo $e->getTraceAsString();
}
