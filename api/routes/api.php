<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
 
Route::prefix('v1')->middleware(['api_key', 'api_log', 'banned'])->group(function () {
    Route::get('/me/balance', [\App\Http\Controllers\Api\V1\ProxyApiController::class, 'balance'])->name('api.v1.balance');
    Route::get('/products',   [\App\Http\Controllers\Api\V1\ProxyApiController::class, 'products'])->name('api.v1.products');
    
    Route::post('/proxies/generate', [\App\Http\Controllers\Api\V1\ProxyApiController::class, 'generate'])->name('api.v1.proxies.generate');
    Route::get('/proxies',           [\App\Http\Controllers\Api\V1\ProxyApiController::class, 'orders'])->name('api.v1.proxies.list');
    Route::get('/proxies/{id}',      [\App\Http\Controllers\Api\V1\ProxyApiController::class, 'proxyDetail'])->name('api.v1.proxies.detail');
    Route::get('/logs',              [\App\Http\Controllers\Api\V1\ProxyApiController::class, 'logs'])->name('api.v1.logs');
});


// Debug routes removed from public space for security. Moving to admin group.


// ─────────────────────────────────────────────
// Auth Routes (prefix: /auth)
// Frontend calls: /auth/login, /auth/signup, /auth/me, /auth/logout
// ─────────────────────────────────────────────
Route::prefix('auth')->group(function () {
    Route::post('/login',  [\App\Http\Controllers\AuthController::class, 'login']);
    Route::post('/signup', [\App\Http\Controllers\AuthController::class, 'register']);

    // Google OAuth
    Route::get('/google/redirect', [\App\Http\Controllers\GoogleAuthController::class, 'redirectToGoogle']);
    Route::get('/google/callback', [\App\Http\Controllers\GoogleAuthController::class, 'handleGoogleCallback']);

    Route::middleware(['auth:sanctum', 'banned'])->group(function () {
        Route::get('/me',      [\App\Http\Controllers\AuthController::class, 'me']);
        Route::post('/logout', [\App\Http\Controllers\AuthController::class, 'logout']);
        Route::post('/2fa/verify', [\App\Http\Controllers\AuthController::class, 'verify2fa'])->withoutMiddleware(['auth:sanctum']);
    });
});

// ─────────────────────────────────────────────
// Protected Routes
// ─────────────────────────────────────────────
Route::middleware(['auth:sanctum', 'banned'])->group(function () {

    // Profile & Stats
    Route::get('/profile',  [\App\Http\Controllers\AuthController::class, 'profile']);
    Route::post('/profile', [\App\Http\Controllers\AuthController::class, 'updateProfile']);
    Route::get('/stats',    [\App\Http\Controllers\AuthController::class, 'stats']);

    // Proxy Routes
    Route::post('/proxies/generate', [\App\Http\Controllers\ProxyController::class, 'generate']);
    Route::get('/proxies',           [\App\Http\Controllers\ProxyController::class, 'list']);
    Route::get('/proxies/settings',  [\App\Http\Controllers\ProxyController::class, 'settings']);
    Route::get('/proxies/isp-stock', [\App\Http\Controllers\ProxyController::class, 'ispStock']);
    Route::post('/proxies/isp-order', [\App\Http\Controllers\ProxyController::class, 'orderIsp']);

    // Subuser Routes
    Route::post('/subusers/setup',  [\App\Http\Controllers\SubuserController::class, 'setup']);
    Route::get('/subusers/status', [\App\Http\Controllers\SubuserController::class, 'status']);

    // Billing Routes
    Route::post('/billing/checkout', [\App\Http\Controllers\BillingController::class, 'createCheckout']);
    Route::post('/billing/product-checkout', [\App\Http\Controllers\BillingController::class, 'createProductCheckout']);
    Route::post('/billing/nowpayments-checkout', [\App\Http\Controllers\BillingController::class, 'createNowPaymentsCheckout']);
    Route::post('/billing/submit-crypto', [\App\Http\Controllers\BillingController::class, 'submitCrypto']);
    Route::post('/billing/setup-intent', [\App\Http\Controllers\BillingController::class, 'createSetupIntent']);
    Route::post('/billing/verify-session', [\App\Http\Controllers\BillingController::class, 'verifySession']);
    Route::get('/billing/gateways', [\App\Http\Controllers\BillingController::class, 'publicGatewayStatus']);
    Route::get('/invoices',          [\App\Http\Controllers\BillingController::class, 'invoices']);

    // IP Allowlist
    Route::get('/allowlist',        [\App\Http\Controllers\AllowlistController::class, 'index']);
    Route::post('/allowlist',       [\App\Http\Controllers\AllowlistController::class, 'store']);
    Route::match(['DELETE', 'POST'], '/allowlist/{id}', [\App\Http\Controllers\AllowlistController::class, 'destroy']);

    // Support Routes
    Route::get('/support/tickets',      [\App\Http\Controllers\SupportController::class, 'index']);
    Route::post('/support/tickets',     [\App\Http\Controllers\SupportController::class, 'store']);
    Route::get('/support/tickets/{id}', [\App\Http\Controllers\SupportController::class, 'show']);
    Route::post('/support/tickets/{id}/reply', [\App\Http\Controllers\SupportController::class, 'reply']);

    // Referral Routes
    Route::get('/referrals', [\App\Http\Controllers\ReferralController::class, 'index']);

    // 2FA Routes
    Route::prefix('2fa')->group(function () {
        Route::get('/setup',          [\App\Http\Controllers\TwoFactorAuthController::class, 'setup']);
        Route::post('/confirm',       [\App\Http\Controllers\TwoFactorAuthController::class, 'confirm']);
        Route::post('/disable',       [\App\Http\Controllers\TwoFactorAuthController::class, 'disable']);
        Route::get('/recovery-codes', [\App\Http\Controllers\TwoFactorAuthController::class, 'getRecoveryCodes']);
    });

    // API Key Routes
    Route::get('/api_keys',         [\App\Http\Controllers\ApiKeyController::class, 'index']);
    Route::post('/api_keys',        [\App\Http\Controllers\ApiKeyController::class, 'store']);
    Route::delete('/api_keys/{id}', [\App\Http\Controllers\ApiKeyController::class, 'destroy']);

    // Notifications
    Route::get('/notifications',            [\App\Http\Controllers\NotificationController::class, 'index']);
    Route::post('/notifications/mark-read', [\App\Http\Controllers\NotificationController::class, 'markAllRead']);
    Route::post('/notifications/{id}/read', [\App\Http\Controllers\NotificationController::class, 'markRead']);

    // Coupon validation
    Route::post('/coupons/validate', [\App\Http\Controllers\CouponController::class, 'validateCoupon']);
    Route::get('/me/usage',          [\App\Http\Controllers\AuthController::class, 'usage']);
    Route::get('/me/events',         [\App\Http\Controllers\AuthController::class, 'events']);
    Route::get('/me/subscription',   [\App\Http\Controllers\AuthController::class, 'subscription']);
    Route::get('/me/topup-settings', [\App\Http\Controllers\AuthController::class, 'getTopUpSettings']);
    Route::post('/me/topup-settings', [\App\Http\Controllers\AuthController::class, 'updateTopUpSettings']);
});

// ─────────────────────────────────────────────
// Public Routes (no auth)
// ─────────────────────────────────────────────
Route::post('/webhook/stripe', [\App\Http\Controllers\BillingController::class, 'handleWebhook']);
Route::post('/webhook/cryptomus', [\App\Http\Controllers\BillingController::class, 'handleCryptomusWebhook']);
Route::post('/webhook/nowpayments', [\App\Http\Controllers\BillingController::class, 'handleNowPaymentsWebhook']);
Route::get('/currencies', [\App\Http\Controllers\CurrencyController::class, 'index']);
Route::get('/products', [\App\Http\Controllers\ProductController::class, 'index']);
Route::post('/auth/password/email', [\App\Http\Controllers\PasswordResetController::class, 'sendResetLink']);
Route::post('/auth/password/reset', [\App\Http\Controllers\PasswordResetController::class, 'reset']);
Route::post('/newsletters', function() {
    return response()->json(['message' => 'Successfully subscribed to newsletter.']);
});
 
// Public Blog Routes
Route::get('/blog', [\App\Http\Controllers\BlogController::class, 'publicIndex']);
Route::get('/blog/{slug}', [\App\Http\Controllers\BlogController::class, 'show']);

// ─────────────────────────────────────────────
// Admin Routes
// ─────────────────────────────────────────────
// FIX #10: Add rate limiting to all admin routes.
// 60 requests/min is generous for real-human admin usage but blocks automated abuse.
Route::middleware(['auth:sanctum', 'role:admin', 'throttle:60,1'])->prefix('admin')->group(function () {
    Route::get('/users',          [\App\Http\Controllers\AdminController::class, 'users']);
    Route::get('/users/{id}',     [\App\Http\Controllers\AdminController::class, 'show']);
    Route::get('/users/{id}/stats', [\App\Http\Controllers\AdminController::class, 'userStats']);
    Route::get('/users/{id}/orders', [\App\Http\Controllers\AdminController::class, 'getUserOrders']);
    Route::post('/users/{id}/orders', [\App\Http\Controllers\AdminController::class, 'addUserOrder']);
    Route::post('/users/balance', [\App\Http\Controllers\AdminController::class, 'updateBalance']);
    Route::post('/users/ban',     [\App\Http\Controllers\AdminController::class, 'banUser']);
    Route::post('/users/{id}/role', [\App\Http\Controllers\AdminController::class, 'updateRole']);
    Route::post('/users/{id}/password', [\App\Http\Controllers\AdminController::class, 'updatePassword']);
    Route::delete('/orders/{orderId}', [\App\Http\Controllers\AdminController::class, 'deleteOrder']);
    Route::delete('/proxies/{proxyId}', [\App\Http\Controllers\AdminController::class, 'deleteProxy']);
    Route::get('/stats',          [\App\Http\Controllers\AdminController::class, 'stats']);
    Route::get('/logs',           [\App\Http\Controllers\AdminController::class, 'logs']);
    Route::get('/invoices',       [\App\Http\Controllers\BillingController::class, 'adminInvoices']);
    Route::patch('/invoices/{id}/status', [\App\Http\Controllers\BillingController::class, 'updateInvoiceStatus'])->middleware('throttle:6,1');
    Route::get('/payment-gateways', [\App\Http\Controllers\BillingController::class, 'gatewayStatus']);
    Route::get('/fulfillment-logs', [\App\Http\Controllers\AdminController::class, 'fulfillmentLogs']);
    
    // Admin crypto management
    Route::get('/crypto/pending', [\App\Http\Controllers\BillingController::class, 'adminPendingCrypto']);
    Route::post('/crypto/{id}/approve', [\App\Http\Controllers\BillingController::class, 'adminApproveCrypto']);
    Route::get('/settings',       [\App\Http\Controllers\SettingsController::class, 'index']);
    Route::post('/settings',      [\App\Http\Controllers\SettingsController::class, 'update']);
    Route::get('/alerts/config',  [\App\Http\Controllers\AdminController::class, 'getAlertConfig']);
    Route::patch('/alerts/config', [\App\Http\Controllers\AdminController::class, 'updateAlertConfig']);

    // Blog
    // Orders (Handled by ProxyController now, or redundant)
    // Route::get('/orders', [\App\Http\Controllers\OrderController::class, 'index']);
    // Route::post('/orders', [\App\Http\Controllers\OrderController::class, 'store']);
    Route::get('/blog',           [\App\Http\Controllers\BlogController::class, 'index']);
    Route::post('/blog',          [\App\Http\Controllers\BlogController::class, 'store']);
    Route::put('/blog/{id}',      [\App\Http\Controllers\BlogController::class, 'update']);
    Route::post('/blog/{id}/publish', [\App\Http\Controllers\BlogController::class, 'publish']);
    Route::delete('/blog/{id}',   [\App\Http\Controllers\BlogController::class, 'destroy']);

    // Admin Auto-Blog Management
    Route::get('/blog/automation',            [\App\Http\Controllers\AutoBlogController::class, 'index']);
    Route::post('/blog/automation/settings',  [\App\Http\Controllers\AutoBlogController::class, 'updateSettings']);
    Route::post('/blog/automation/keywords',  [\App\Http\Controllers\AutoBlogController::class, 'storeKeyword']);
    Route::delete('/blog/automation/keywords/{id}', [\App\Http\Controllers\AutoBlogController::class, 'destroyKeyword']);
    Route::post('/blog/automation/trigger',   [\App\Http\Controllers\AutoBlogController::class, 'trigger']);
    Route::post('/blog/automation/telegram-test', function(\App\Services\TelegramService $telegram) {
        return response()->json($telegram->sendTestMessage());
    });
    Route::post('/blog/automation/google-test', [\App\Http\Controllers\AutoBlogController::class, 'testIndexing']);
    Route::post('/blog/automation/google-index-post', [\App\Http\Controllers\AutoBlogController::class, 'indexPost']);
    Route::post('/blog/automation/telegram-share-post', [\App\Http\Controllers\AutoBlogController::class, 'sharePostToTelegram']);

    // Admin Products Management
    Route::get('/products',          [\App\Http\Controllers\ProductController::class, 'adminIndex']);
    Route::post('/products',        [\App\Http\Controllers\ProductController::class, 'store']);
    Route::put('/products/{id}',     [\App\Http\Controllers\ProductController::class, 'update']);
    Route::delete('/products/{id}',  [\App\Http\Controllers\ProductController::class, 'destroy']);

    // Admin Coupons Management
    Route::get('/coupons',           [\App\Http\Controllers\CouponController::class, 'index']);
    Route::post('/coupons',          [\App\Http\Controllers\CouponController::class, 'store']);
    Route::delete('/coupons/{id}',   [\App\Http\Controllers\CouponController::class, 'destroy']);
    Route::post('/coupons/{id}/toggle', [\App\Http\Controllers\CouponController::class, 'toggle']);

    // Admin Support Management
    Route::get('/support/tickets',    [\App\Http\Controllers\SupportController::class, 'adminIndex']);
    Route::post('/support/tickets/{id}/status', [\App\Http\Controllers\SupportController::class, 'updateStatus']);
    Route::post('/support/tickets/{id}/reply', [\App\Http\Controllers\SupportController::class, 'reply']);

    // Admin Resellers Management
    Route::get('/resellers',          [\App\Http\Controllers\AdminController::class, 'listResellers']);
    Route::post('/resellers',         [\App\Http\Controllers\AdminController::class, 'storeReseller']);
    Route::put('/resellers/{id}',      [\App\Http\Controllers\AdminController::class, 'updateReseller']);

    // Admin Currencies Management
    Route::get('/currencies',          [\App\Http\Controllers\CurrencyController::class, 'adminIndex']);
    Route::post('/currencies',         [\App\Http\Controllers\CurrencyController::class, 'store']);
    Route::put('/currencies/{id}',      [\App\Http\Controllers\CurrencyController::class, 'update']);
    Route::post('/currencies/{id}/toggle', [\App\Http\Controllers\CurrencyController::class, 'toggle']);

    // Admin Fraud Detection
    Route::get('/fraud/signals',      [\App\Http\Controllers\FraudController::class, 'signals']);
    Route::get('/fraud/risk-scores',  [\App\Http\Controllers\FraudController::class, 'riskScores']);
    Route::get('/fraud/login-history', [\App\Http\Controllers\FraudController::class, 'loginHistory']);
    Route::put('/fraud/signals/{id}/resolve', [\App\Http\Controllers\FraudController::class, 'resolveSignal']);

    // Admin SLA Monitoring
    Route::get('/sla', [\App\Http\Controllers\SLAController::class, 'index']);
    Route::get('/sla/configs', [\App\Http\Controllers\SLAController::class, 'getConfigs']);
    Route::post('/sla/configs', [\App\Http\Controllers\SLAController::class, 'storeConfig']);
    Route::get('/sla/credits', [\App\Http\Controllers\SLAController::class, 'getCredits']);
    Route::post('/sla/credits/{id}/approve', [\App\Http\Controllers\SLAController::class, 'approveCredit']);

    // Secured Debug Routes
    Route::get('/debug-logs', function() {
        $path  = storage_path('logs/laravel.log');
        if (!file_exists($path)) return 'No log file found.';
        $lines = file($path);
        return implode('', array_slice($lines, -100));
    });

    // Admin Referral Routes
    Route::get('/referrals/stats', [\App\Http\Controllers\AdminController::class, 'referralStats']);
    Route::get('/referrals/earnings', [\App\Http\Controllers\AdminController::class, 'listReferralEarnings']);
    Route::post('/referrals/earnings/{id}/status', [\App\Http\Controllers\AdminController::class, 'updateReferralEarningStatus']);
    Route::post('/referrals/influencer-rate', [\App\Http\Controllers\AdminController::class, 'updateInfluencerRate']);

    // Admin Email Templates Management
    Route::apiResource('email-templates', \App\Http\Controllers\Api\Admin\EmailTemplateController::class);
    Route::match(['GET', 'POST'], '/email-templates/{id}/preview', [\App\Http\Controllers\Api\Admin\EmailTemplateController::class, 'preview']);
    Route::post('/email-templates/{id}/test', [\App\Http\Controllers\Api\Admin\EmailTemplateController::class, 'testSend']);
});

