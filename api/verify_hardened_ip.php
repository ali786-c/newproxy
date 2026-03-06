<?php
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use App\Models\AdminLog;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

function verify_log_ip($headers, $testName) {
    echo "[TEST] {$testName}\n";
    $request = Request::create('/api/test', 'GET', [], [], [], $headers);
    // Bind the fake request
    app()->instance('request', $request);
    
    // Trigger log (mocking admin id if necessary, or just check the logic)
    // We'll just look at how AdminLog::log() would interpret the request
    
    $ip = $request->header('CF-Connecting-IP') ?? 
          $request->header('X-Real-IP') ?? 
          $request->header('X-Forwarded-For') ?? 
          $request->ip();

    if (str_contains($ip, ',')) {
        $ip = trim(explode(',', $ip)[0]);
    }

    echo "Input Headers: " . json_encode($headers) . "\n";
    echo "Resolved IP for Log: {$ip}\n";
    echo "-----------------------------------\n";
}

// Case 1: Standard Direct
verify_log_ip(['REMOTE_ADDR' => '4.4.4.4'], "Direct Request");

// Case 2: Cloudflare
verify_log_ip(['REMOTE_ADDR' => '127.0.0.1', 'HTTP_CF_CONNECTING_IP' => '9.9.9.9'], "Cloudflare Proxy");

// Case 3: Nginx X-Real-IP
verify_log_ip(['REMOTE_ADDR' => '127.0.0.1', 'HTTP_X_REAL_IP' => '7.7.7.7'], "Nginx X-Real-IP");

// Case 4: X-Forwarded-For list
verify_log_ip(['REMOTE_ADDR' => '127.0.0.1', 'HTTP_X_FORWARDED_FOR' => '1.1.1.1, 2.2.2.2, 3.3.3.3'], "X-Forwarded-For List");
