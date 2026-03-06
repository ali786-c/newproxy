<?php
use Illuminate\Contracts\Console\Kernel;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

use App\Models\AdminLog;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

// Mock an admin user
$admin = User::where('role', 'admin')->first();
if (!$admin) {
    echo "No admin found to test.\n";
    exit;
}
Auth::login($admin);

echo "--- PHASE 9 AUDIT LOG VERIFICATION ---\n";

function check_last_log($action, $expected_snippet) {
    $log = AdminLog::orderBy('id', 'desc')->first();
    if ($log && $log->action === $action && str_contains($log->details, $expected_snippet)) {
        echo "[SUCCESS] Log found for '{$action}': {$log->details}\n";
    } else {
        echo "[FAILED] Expected log for '{$action}' with '{$expected_snippet}' but found: " . ($log ? "{$log->action} - {$log->details}" : "NONE") . "\n";
    }
}

// 1. Test Product Log
echo "\n[TEST] Product log...\n";
$productController = app(\App\Http\Controllers\ProductController::class);
$request = Illuminate\Http\Request::create('/admin/products', 'POST', [
    'name' => 'Verify Test Product',
    'type' => 'rp',
    'price' => 10,
    'evomi_product_id' => 'vt_'.time(),
]);
$productController->store($request);
check_last_log('create_product', 'Verify Test Product');

// 2. Test Coupon Log
echo "\n[TEST] Coupon log...\n";
$couponController = app(\App\Http\Controllers\CouponController::class);
$request = Illuminate\Http\Request::create('/admin/coupons', 'POST', [
    'code' => 'VERIFY_'.time(),
    'type' => 'percentage',
    'value' => 10,
]);
$couponController->store($request);
check_last_log('create_coupon', 'VERIFY_');

// 3. Test Auto-Blog Setting log
echo "\n[TEST] AutoBlog Setting log...\n";
$blogController = app(\App\Http\Controllers\AutoBlogController::class);
$request = Illuminate\Http\Request::create('/admin/blog/automation/settings', 'POST', [
    'auto_blog_enabled' => true,
]);
$blogController->updateSettings($request);
check_last_log('update_autoblog_settings', 'Updated Gemini Auto-Blog Settings');

echo "\n--- VERIFICATION COMPLETE ---\n";
