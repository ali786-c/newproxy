<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Models\Proxy;
use App\Models\WalletTransaction;
use App\Services\EvomiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProxyController extends Controller
{
    protected $evomi;

    public function __construct(EvomiService $evomi)
    {
        $this->evomi = $evomi;
    }

    public function generate(Request $request)
    {
        $request->validate([
            'product_id'   => 'required|exists:products,id',
            'quantity'     => 'required|integer|min:1|max:100',
            'country'      => 'nullable|string|max:10',
            'session_type' => 'nullable|string|in:sticky,rotating',
        ]);

        $user    = $request->user()->fresh();
        $product = Product::findOrFail($request->product_id);

        $unitPrice = $product->price;

        if (!empty($product->volume_discounts) && is_array($product->volume_discounts)) {
            $discounts = collect($product->volume_discounts)->sortByDesc('min_qty');
            foreach ($discounts as $discount) {
                if ($request->quantity >= $discount['min_qty']) {
                    $unitPrice = $discount['price'];
                    break;
                }
            }
        }

        $totalCost = $unitPrice * $request->quantity;

        if ($user->balance < $totalCost) {
            return response()->json([
                'message' => 'Insufficient balance.',
            ], 402);
        }

        try {
            // ── Step 1: Create Order (Pending) ──────────────────────────
            $bandwidthTotal = $request->quantity * 1024; // MB
            $order = Order::create([
                'user_id'         => $user->id,
                'product_id'      => $product->id,
                'status'          => 'pending',
                'bandwidth_total' => $bandwidthTotal,
                'expires_at'      => now()->addDays(30),
            ]);

            // ── Step 2: Ensure order-specific subuser ───────────────────
            $subuserResult = $this->evomi->ensureOrderSubuser($order);

            if (!$subuserResult['success']) {
                $order->update(['status' => 'failed']);
                return response()->json(['message' => $subuserResult['error']], 503);
            }

            $order    = $order->fresh();
            $userKeys = $order->evomi_keys ?? [];

            // ── Step 3: Get the proxy key for this product type ────────────
            $proxyKey = $userKeys[$product->type] ?? ($userKeys['residential'] ?? null);

            if (!$proxyKey) {
                Log::error('ProxyController: no proxy key found for order', [
                    'order_id'     => $order->id,
                    'product_type' => $product->type,
                    'available_keys' => array_keys($userKeys),
                ]);
                $order->update(['status' => 'failed']);
                return response()->json(['message' => "No proxy key found for type '{$product->type}' in this batch."], 400);
            }

            // ── Step 4: Allocate bandwidth on Evomi side ───────────────────
            $evomiResult = $this->evomi->allocateBandwidth($order->evomi_username, $bandwidthTotal, $product->type);

            if (!$evomiResult) {
                $order->update(['status' => 'failed']);
                return response()->json(['message' => 'Failed to allocate bandwidth on provider side. Batch marked as failed.'], 503);
            }

            // ── Step 5: Deduct balance + finalize order + save proxies ───────
            $orderData = DB::transaction(function () use ($user, $product, $totalCost, $request, $proxyKey, $order) {

                $user->balance -= $totalCost;
                $user->save();

                WalletTransaction::create([
                    'user_id'     => $user->id,
                    'type'        => 'debit',
                    'amount'      => $totalCost,
                    'description' => "Purchase: {$request->quantity}x {$product->name} (Batch #{$order->id})",
                ]);

                $order->update(['status' => 'active']);

                $portMap = [
                    'rp'           => 1000,
                    'mp'           => 3000,
                    'sdc'          => 2000, // Shared Datacenter use port 2000 usually
                    'dc'           => 2000, 
                    'dc_ipv6'      => 4000,
                    'dc_unmetered' => 5000,
                    'rp_ipv6'      => 6000,
                ];
                $hostMap = [
                    'rp'           => 'proxy.upgraderproxy.com',
                    'mp'           => 'proxy.upgraderproxy.com',
                    'dc'           => 'proxy.upgraderproxy.com',
                    'sdc'          => 'proxy.upgraderproxy.com',
                    'dc_ipv6'      => 'proxy.upgraderproxy.com',
                    'dc_unmetered' => 'proxy.upgraderproxy.com',
                    'isp'          => 'proxy.upgraderproxy.com',
                    'isp_shared'   => 'proxy.upgraderproxy.com',
                    'isp_private'  => 'proxy.upgraderproxy.com',
                    'isp_virgin'   => 'proxy.upgraderproxy.com',
                    'rp_ipv6'      => 'proxy.upgraderproxy.com',
                ];

                $port    = $portMap[$product->type] ?? 1000;
                $host    = $hostMap[$product->type] ?? 'proxy.upgraderproxy.com';

                $country     = $request->country      ?? 'US';
                $sessionType = $request->session_type ?? 'rotating';

                $proxies = [];
                for ($i = 0; $i < $request->quantity; $i++) {
                    $password = "{$proxyKey}_country-{$country}_session-{$sessionType}";
                    $proxy    = Proxy::create([
                        'order_id' => $order->id,
                        'host'     => $host,
                        'port'     => $port,
                        'username' => $order->evomi_username,
                        'password' => $password,
                        'country'  => $country,
                    ]);
                    $proxies[] = $proxy;
                }

                // Trigger auto top-up check if enabled
                app(\App\Http\Controllers\BillingController::class)->checkAndTriggerAutoTopUp($user);

                return [
                    'order'   => $order,
                    'proxies' => $proxies,
                    'user'    => $user
                ];
            });

            // --- NEW: Trigger Proxy Created Emails (Outside Transaction) ---
            try {
                $order = $orderData['order'];
                $proxies = $orderData['proxies'];
                $user = $orderData['user'];

                // 1. Notify User
                \Illuminate\Support\Facades\Log::info("ORDER_NOTIF: Sending User Email to: " . $user->email);
                \Illuminate\Support\Facades\Notification::route('mail', $user->email)
                    ->notify(new \App\Notifications\GenericDynamicNotification('proxy_created_user', [
                        'user' => ['name' => $user->name],
                        'product' => ['name' => $product->name],
                        'order' => ['id' => $order->id],
                        'action_url' => url('/app/proxies'),
                        'year' => date('Y')
                    ]));
                \Illuminate\Support\Facades\Log::info("ORDER_NOTIF: User notification call finished.");

                // 2. Alert Admin
                $adminEmail = \App\Models\Setting::getValue('admin_notification_email');
                \Illuminate\Support\Facades\Log::info("ORDER_NOTIF: Sending Admin Alert to: " . $adminEmail);
                \Illuminate\Support\Facades\Notification::route('mail', $adminEmail)
                    ->notify(new \App\Notifications\GenericDynamicNotification('admin_new_order', [
                        'user' => ['email' => $user->email],
                        'order' => [
                            'id' => $order->id,
                            'amount' => '$' . number_format($totalCost, 2)
                        ],
                        'admin_url' => url('/admin/billing'),
                        'year' => date('Y')
                    ]));
                \Illuminate\Support\Facades\Log::info("ORDER_NOTIF: Admin alert finished.");

            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error("ORDER_NOTIF: Proxy Delivery Email Error: " . $e->getMessage());
                \Illuminate\Support\Facades\Log::error($e->getTraceAsString());
            }
            // ---------------------------------------------------------------

            return response()->json([
                'message' => 'Proxies generated successfully.',
                'proxies' => collect($orderData['proxies'])->map(fn($p) => [
                    'host'     => $p->host,
                    'port'     => (int) $p->port,
                    'username' => $p->username,
                    'password' => $p->password,
                ]),
                'expires_at' => $orderData['order']->expires_at,
                'balance' => $orderData['user']->balance,
            ]);

        } catch (\Exception $e) {
            Log::error('ProxyController Generate Error: ' . $e->getMessage());
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function list(Request $request)
    {
        $type = $request->query('type');

        $query = Order::with('proxies', 'product')
            ->where('user_id', $request->user()->id);

        if ($type) {
            $query->whereHas('product', function ($q) use ($type) {
                $q->where('type', $type);
            });
        }

        $orders = $query->latest()->get();

        // Optimized: Fetch all balances once per minute instead of per-order
        $allBalances = $this->evomi->getSubuserBalances();

        // Enrich with usage stats
        $orders->each(function ($order) use ($allBalances) {
            $username = $order->evomi_username;
            if ($username && isset($allBalances[$username])) {
                $balances = $allBalances[$username];
                
                // Map our product type code to Evomi's balance keys if needed
                $typeCode = $order->product->type; // e.g., 'rp'
                
                // Evomi balances typically use internal names like 'residential', 'static', etc.
                // But our extractKeys during creation mapped them to codes. 
                // Let's check both just in case.
                $typeMap = [
                    'rp'  => 'residential',
                    'mp'  => 'mobile',
                    'dc'  => 'dataCenter',
                    'isp' => 'static',
                ];
                
                $evomiType = $typeMap[$typeCode] ?? $typeCode;
                $currentBalance = (float) ($balances[$evomiType] ?? ($balances[$typeCode] ?? 0));

                // Usage = Total Allocated - Current Balance
                $used = max(0, (float) $order->bandwidth_total - $currentBalance);
                $order->bandwidth_used = $used;
            } else {
                $order->bandwidth_used = 0;
            }
        });

        return response()->json($orders);
    }

    public function settings()
    {
        $cacheKey = 'evomi_proxy_settings';
        // Pull from cache (refreshed by Cron every 30m)
        $settings = \Illuminate\Support\Facades\Cache::get($cacheKey);

        if (!$settings) {
            // Fallback if cache empty
            $settings = $this->evomi->getProxySettings();
            if ($settings && !isset($settings['error'])) {
                \Illuminate\Support\Facades\Cache::put($cacheKey, $settings, 3600);
            }
        }

        if (!$settings || isset($settings['error'])) {
            return response()->json(['message' => 'Could not fetch settings.', 'detail' => $settings], 502);
        }

        return response()->json($settings);
    }

    public function ispStock(Request $request)
    {
        $cacheKey = 'evomi_isp_stock_global';
        $stock = \Illuminate\Support\Facades\Cache::get($cacheKey);

        if (!$stock) {
            $user = $request->user();
            if (!$user->evomi_username) $this->evomi->ensureSubuser($user);
            $stock = $this->evomi->getIspStock($user->evomi_username);
            if ($stock) \Illuminate\Support\Facades\Cache::put($cacheKey, $stock, 3600);
        }

        return response()->json($stock);
    }

    /**
     * Order Static ISP proxies.
     */
    public function orderIsp(Request $request)
    {
        $request->validate([
            'product_id'      => 'required|exists:products,id',
            'quantity'        => 'required|integer|min:1|max:1000',
            'country_code'    => 'required|string|size:2',
            'city'            => 'required|string',
            'isp'             => 'required|string',
            'months'          => 'required|integer|min:1|max:12',
            'high_concurrency' => 'boolean',
        ]);

        $user    = $request->user()->fresh();
        $product = Product::findOrFail($request->product_id);

        if (!in_array($product->type, ['isp_shared', 'isp_private', 'isp_virgin'])) {
            return response()->json(['message' => 'Invalid product type for ISP order.'], 400);
        }

        // Calculate cost (Quantity * Months * Price)
        $totalCost = $request->quantity * $request->months * (float)$product->price;

        if ($user->balance < $totalCost) {
            return response()->json(['message' => 'Insufficient balance.'], 402);
        }

        try {
            // Determine Evomi params based on product type
            $params = [
                'months'          => $request->months,
                'countryCode'     => $request->country_code,
                'city'            => $request->city,
                'isp'             => $request->isp,
                'ips'             => $request->quantity,
                'highConcurrency' => $request->high_concurrency ?? true,
                'sharedType'      => ($product->type === 'isp_shared') ? 'shared' : 'dedicated',
                'virgin'          => ($product->type === 'isp_virgin'),
            ];

            // 1. Order from Evomi
            $evomiResult = $this->evomi->orderIspPackage($user->evomi_username, $params);

            if (!$evomiResult || !isset($evomiResult['data'])) {
                $errorMsg = $evomiResult['message'] ?? 'Failed to order ISP package from provider.';
                return response()->json(['message' => $errorMsg, 'detail' => $evomiResult], 503);
            }

            $packageData = $evomiResult['data'];

            // 2. Transaction & Local Record
            $data = DB::transaction(function () use ($user, $product, $totalCost, $request, $packageData) {
                // Deduct Balance
                $user->balance -= $totalCost;
                $user->save();

                // Log Transaction
                WalletTransaction::create([
                    'user_id'     => $user->id,
                    'type'        => 'debit',
                    'amount'      => $totalCost,
                    'description' => "Order: {$request->quantity}x {$product->name} (Static ISP) for {$request->months} month(s)",
                ]);

                // Create common Order record
                $order = \App\Models\Order::create([
                    'user_id'         => $user->id,
                    'product_id'      => $product->id,
                    'status'          => 'active',
                    'bandwidth_total' => 0, // Static IPs have unlimited bandwidth
                    'bandwidth_used'  => 0,
                    'expires_at'      => now()->addMonths($request->months),
                    'evomi_username'  => $user->evomi_username,
                ]);

                // Create ISP Package record for lifecycle tracking
                $ispPkg = \App\Models\IspPackage::create([
                    'user_id'          => $user->id,
                    'product_id'       => $product->id,
                    'order_id'         => $order->id,
                    'evomi_package_id' => $packageData['id'] ?? null,
                    'status'           => 'active',
                    'expires_at'       => $order->expires_at,
                    'ip_data'          => $packageData,
                ]);

                // Create individual Proxy records
                if (isset($packageData['ips']) && is_array($packageData['ips'])) {
                    foreach ($packageData['ips'] as $ipInfo) {
                        \App\Models\Proxy::create([
                            'order_id' => $order->id,
                            'host'     => $ipInfo['ip'] ?? 'isp.evomi.com',
                            'port'     => $ipInfo['port'] ?? 3000,
                            'username' => $ipInfo['username'] ?? $user->evomi_username,
                            'password' => $ipInfo['password'] ?? '',
                            'country'  => $request->country_code,
                        ]);
                    }
                }

                return $order->load('proxies');
            });

            return response()->json([
                'message' => 'ISP Proxies ordered successfully.',
                'order' => $data,
                'balance' => $user->fresh()->balance
            ]);

        } catch (\Exception $e) {
            Log::error('ProxyController OrderIsp Error: ' . $e->getMessage());
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Claim Free Trial — 20MB Residential.
     * POST /trial/claim
     * Requires auth:sanctum. Email verified check inside.
     */
    public function claimFreeTrial(Request $request)
    {
        $user = $request->user()->fresh();

        // Already claimed?
        if ($user->has_claimed_trial) {
            return response()->json([
                'message' => 'You have already claimed your free trial.',
                'code'    => 'trial_already_claimed',
            ], 409);
        }

        // Email verified?
        if (!$user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'You must verify your email address before claiming the free trial.',
                'code'    => 'email_not_verified',
            ], 403);
        }

        // Find active trial product
        $product = Product::where('is_trial', true)->where('is_active', true)->first();

        if (!$product) {
            return response()->json(['message' => 'Free trial is not available at this time.'], 404);
        }

        $trialMb = 20; // 20 MB

        try {
            // Step 1: Create pending order
            $order = Order::create([
                'user_id'         => $user->id,
                'product_id'      => $product->id,
                'status'          => 'pending',
                'bandwidth_total' => $trialMb,
                'expires_at'      => now()->addDays(7),
            ]);

            // Step 2: Ensure Evomi subuser for this order
            $subuserResult = $this->evomi->ensureOrderSubuser($order);

            if (!$subuserResult['success']) {
                $order->update(['status' => 'failed']);
                return response()->json(['message' => 'Failed to provision trial. Please try again later.'], 503);
            }

            $order    = $order->fresh();
            $userKeys = $order->evomi_keys ?? [];
            $proxyKey = $userKeys['rp'] ?? ($userKeys['residential'] ?? null);

            if (!$proxyKey) {
                $order->update(['status' => 'failed']);
                return response()->json(['message' => 'Failed to get proxy credentials. Please try again.'], 503);
            }

            // Step 3: Allocate 20MB on Evomi (no billing)
            $evomiResult = $this->evomi->allocateBandwidth($order->evomi_username, $trialMb, 'rp');

            if (!$evomiResult) {
                $order->update(['status' => 'failed']);
                return response()->json(['message' => 'Failed to allocate trial bandwidth.'], 503);
            }

            // Step 4: Mark claimed + save proxy (no balance deduction)
            $proxy = DB::transaction(function () use ($user, $proxyKey, $order) {
                $user->has_claimed_trial = true;
                $user->trial_claim_ip    = request()->ip();
                $user->save();

                $order->update(['status' => 'active']);

                return Proxy::create([
                    'order_id' => $order->id,
                    'host'     => 'proxy.upgraderproxy.com',
                    'port'     => 1000,
                    'username' => $order->evomi_username,
                    'password' => "{$proxyKey}_country-US_session-rotating",
                    'country'  => 'US',
                ]);
            });

            // Notify user
            try {
                \Illuminate\Support\Facades\Notification::route('mail', $user->email)
                    ->notify(new \App\Notifications\GenericDynamicNotification('proxy_created_user', [
                        'user'       => ['name' => $user->name],
                        'product'    => ['name' => 'Free Trial — 20MB Residential'],
                        'order'      => ['id' => $order->id],
                        'action_url' => url('/app/my-proxies/rp'),
                        'year'       => date('Y'),
                    ]));
            } catch (\Exception $e) {
                Log::warning('FreeTrial email notification failed: ' . $e->getMessage());
            }

            return response()->json([
                'message'    => 'Free trial claimed! Enjoy your 20MB of residential bandwidth.',
                'trial_mb'   => $trialMb,
                'expires_at' => $order->expires_at,
                'proxy'      => [
                    'host'     => $proxy->host,
                    'port'     => (int) $proxy->port,
                    'username' => $proxy->username,
                    'password' => $proxy->password,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('FreeTrial Claim Error: ' . $e->getMessage());
            return response()->json(['message' => 'An unexpected error occurred.'], 500);
        }
    }
}
