<?php
use Illuminate\Contracts\Console\Kernel;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use App\Models\AdminLog;

// Mock an admin user
$admin = User::first();
if (!$admin) {
    echo "No user found to test.\n";
    exit;
}
Auth::login($admin);

echo "--- PHASE 8 SECURITY VERIFICATION ---\n";

// 1. Test 2FA Disable Bypass Fix
echo "\n[TEST 1] 2FA Disable Bypass Attempt...\n";
// Ensure 2FA is 'enabled' for test
$admin->two_factor_confirmed_at = now();
$admin->two_factor_secret = 'TESTSECRET';
$admin->save();

$request = Illuminate\Http\Request::create('/2fa/disable', 'POST', [
    'password' => 'password', // Assuming dummy password is 'password'
    // 'code' is missing!
]);

try {
    $controller = app(\App\Http\Controllers\TwoFactorAuthController::class);
    $response = $controller->disable($request);
    echo "FAILED: 2FA was disabled without a code!\n";
} catch (\Illuminate\Validation\ValidationException $e) {
    echo "SUCCESS: 2FA disable rejected due to missing code (as expected).\n";
}

// 2. Test Password Change Verification
echo "\n[TEST 2] Password Change without Current Password...\n";
$request = Illuminate\Http\Request::create('/profile', 'POST', [
    'password' => 'newpassword123',
    'password_confirmation' => 'newpassword123',
    // 'current_password' is missing!
]);

try {
    $controller = app(\App\Http\Controllers\AuthController::class);
    $response = $controller->updateProfile($request);
    echo "FAILED: Password was changed without checking current password!\n";
} catch (\Illuminate\Validation\ValidationException $e) {
    echo "SUCCESS: Password change rejected due to missing current password (as expected).\n";
}

// 3. Verify Audit Logs
echo "\n[TEST 3] Verifying Audit Logs...\n";
$latestLog = AdminLog::latest()->first();
echo "Latest Log Entry: " . ($latestLog ? $latestLog->action . " - " . $latestLog->details : "None") . "\n";

echo "\n--- VERIFICATION COMPLETE ---\n";
