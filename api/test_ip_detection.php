<?php
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

function test_ip($headers) {
    $request = Request::create('/api/test', 'GET', [], [], [], $headers);
    // Bind the new request to the container so request() returns it
    app()->instance('request', $request);
    
    echo "Headers: " . json_encode($headers) . "\n";
    echo "Detected IP: " . $request->ip() . "\n";
    echo "-----------------------------------\n";
}

test_ip(['REMOTE_ADDR' => '1.2.3.4']);
test_ip(['REMOTE_ADDR' => '127.0.0.1', 'HTTP_X_FORWARDED_FOR' => '5.6.7.8']);
test_ip(['REMOTE_ADDR' => '127.0.0.1', 'HTTP_CF_CONNECTING_IP' => '9.10.11.12']);
