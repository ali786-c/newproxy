<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Order;
use App\Models\Product;
use App\Models\Proxy;
use App\Models\WalletTransaction;
use App\Services\EvomiService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Carbon;

class ProxyApiController extends ApiBaseController
{
    protected $evomi;

    public function __construct(EvomiService $evomi)
    {
        $this->evomi = $evomi;
    }

    /**
     * Get the authenticated user's balance.
     */
    public function balance(Request $request): JsonResponse
    {
        if (!$request->attributes->get('api_key')->hasAbility('balance:read')) {
            return $this->sendError('Insufficient scopes.', 'insufficient_scopes', 403);
        }

        return $this->sendSuccess([
            'balance'  => (float) $request->user()->balance,
            'currency' => 'EUR',
        ]);
    }

    /**
     * List available products with pagination and filtering.
     */
    public function products(Request $request): JsonResponse
    {
        if (!$request->attributes->get('api_key')->hasAbility('products:read')) {
            return $this->sendError('Insufficient scopes.', 'insufficient_scopes', 403);
        }

        $query = Product::query()->where('is_active', true);

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        if ($request->has('country')) {
            $query->where('country', $request->country);
        }

        $perPage = $request->integer('per_page', 15);
        $products = $query->paginate($perPage);

        return $this->sendSuccess($products->items(), 'Products retrieved successfully.', 200, [
            'pagination' => [
                'total'        => $products->total(),
                'per_page'     => $products->perPage(),
                'current_page' => $products->currentPage(),
                'last_page'    => $products->lastPage(),
            ]
        ]);
    }

    /**
     * Generate proxies (Purchase).
     * Includes Idempotency-Key support to prevent duplicate charges.
     */
    public function generate(Request $request): JsonResponse
    {
        $idempotencyKey = $request->header('Idempotency-Key');

        // 1. Check Idempotency
        if ($idempotencyKey) {
            $cacheKey = 'idem:' . sha1($idempotencyKey);
            $cached   = \Illuminate\Support\Facades\Cache::get($cacheKey);

            if ($cached) {
                return response()->json($cached['data'], $cached['status']);
            }
        }

        // 2. Validate Request
        $request->validate([
            'product_id'   => 'required|exists:products,id',
            'quantity'     => 'required|integer|min:1|max:500',
            'country'      => 'nullable|string|max:10',
            'session_type' => 'nullable|string|in:sticky,rotating',
        ]);

        // check ability
        if (!$request->attributes->get('api_key')->hasAbility('proxies:generate')) {
            return $this->sendError('This API key does not have permission to generate proxies.', 'insufficient_scopes', 403);
        }

        try {
            // 3. Atomic Balance Deduction & Order Creation (Pending)
            $preStep = DB::transaction(function () use ($request) {
                // Lock the user record for balance deduction
                $user = \App\Models\User::where('id', $request->user()->id)->lockForUpdate()->first();
                $product = Product::findOrFail($request->product_id);

                // Pricing Logic
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
                    throw new \Exception('insufficient_balance');
                }

                // Deduct Balance
                $user->balance -= $totalCost;
                $user->save();

                // Create Transaction record
                WalletTransaction::create([
                    'user_id'     => $user->id,
                    'type'        => 'debit',
                    'amount'      => $totalCost,
                    'description' => "API Purchase (Pending): {$request->quantity}x {$product->name}",
                ]);

                // Create Order (Pending)
                $order = Order::create([
                    'user_id'         => $user->id,
                    'product_id'      => $product->id,
                    'status'          => 'pending',
                    'bandwidth_total' => $request->quantity * 1024, // MB
                    'expires_at'      => now()->addDays(30),
                ]);

                return [
                    'user'       => $user,
                    'product'    => $product,
                    'order'      => $order,
                    'totalCost'  => $totalCost,
                ];
            });

            // 4. Provisioning (Evomi Logic) - Outside main transaction to avoid long locks
            $user     = $preStep['user'];
            $product  = $preStep['product'];
            $order    = $preStep['order'];
            $totalCost = $preStep['totalCost'];

            $subuserResult = $this->evomi->ensureOrderSubuser($order);
            if (!$subuserResult['success']) {
                $this->handleProvisioningFailure($order, $user, $totalCost, 'Subuser setup failed: ' . ($subuserResult['error'] ?? 'Unknown Error'));
                return $this->sendError($subuserResult['error'] ?? 'Provider setup failed', 'provider_setup_failed', 503);
            }

            $order    = $order->fresh();
            $userKeys = $order->evomi_keys ?? [];
            $proxyKey = $userKeys[$product->type] ?? ($userKeys['residential'] ?? null);

            if (!$proxyKey) {
                $this->handleProvisioningFailure($order, $user, $totalCost, "Missing proxy configuration for type: {$product->type}");
                return $this->sendError("Missing proxy configuration for type: {$product->type}", 'missing_config', 400);
            }

            // Allocate Bandwidth
            $evomiResult = $this->evomi->allocateBandwidth($order->evomi_username, $order->bandwidth_total, $product->type);
            if (!$evomiResult) {
                $this->handleProvisioningFailure($order, $user, $totalCost, 'Bandwidth allocation failed on provider.');
                return $this->sendError('Failed to allocate bandwidth on provider.', 'provider_allocation_failed', 503);
            }

            // 5. Finalize Order & Proxies
            $result = DB::transaction(function () use ($user, $product, $order, $request, $proxyKey) {
                $order->status = 'active';
                $order->save();

                $portMap = ['rp' => 1000, 'mp' => 3000, 'isp' => 3000, 'dc' => 2000];
                $hostMap = [
                    'rp'  => 'proxy.upgradedproxy.com',
                    'mp'  => 'proxy.upgradedproxy.com',
                    'dc'  => 'proxy.upgradedproxy.com',
                    'dc_ipv6' => 'proxy.upgradedproxy.com',
                    'dc_unmetered' => 'proxy.upgradedproxy.com',
                    'isp' => 'proxy.upgradedproxy.com',
                    'isp_shared' => 'proxy.upgradedproxy.com',
                    'isp_private' => 'proxy.upgradedproxy.com',
                    'isp_virgin' => 'proxy.upgradedproxy.com',
                ];

                $port        = $portMap[$product->type] ?? 1000;
                $host        = $hostMap[$product->type] ?? 'proxy.upgradedproxy.com';
                $country     = $request->country ?? 'US';
                $sessionType = $request->session_type ?? 'rotating';

                $proxies = [];
                for ($i = 0; $i < $request->quantity; $i++) {
                    $proxy = Proxy::create([
                        'order_id' => $order->id,
                        'host'     => $host,
                        'port'     => $port,
                        'username' => $order->evomi_username,
                        'password' => "{$proxyKey}_country-{$country}_session-{$sessionType}",
                        'country'  => $country,
                    ]);
                    $proxies[] = [
                        'host'     => $proxy->host,
                        'port'     => (int) $proxy->port,
                        'username' => $proxy->username,
                        'password' => $proxy->password,
                    ];
                }

                return [
                    'order_id'          => $order->id,
                    'proxies'           => $proxies,
                    'expires_at'        => $order->expires_at,
                    'balance_remaining' => (float) $user->balance,
                ];
            });

            $response = $this->sendSuccess($result, 'Proxies generated successfully.');

            // 6. Store Idempotency
            if ($idempotencyKey) {
                $cacheKey = 'idem:' . sha1($idempotencyKey);
                \Illuminate\Support\Facades\Cache::put($cacheKey, [
                    'data'   => $response->getData(true),
                    'status' => $response->getStatusCode(),
                ], now()->addHours(24));
            }

            return $response;

        } catch (\Exception $e) {
            if ($e->getMessage() === 'insufficient_balance') {
                return $this->sendError('Insufficient balance.', 'insufficient_balance', 402);
            }
            
            Log::error('API Generation Error: ' . $e->getMessage());
            return $this->sendError('An internal error occurred during generation.', 'internal_error', 500);
        }
    }

    /**
     * Handle refund and order failure state.
     */
    private function handleProvisioningFailure(Order $order, \App\Models\User $user, float $amount, string $reason): void
    {
        try {
            DB::transaction(function () use ($order, $user, $amount, $reason) {
                // Update Order status
                $order->status = 'failed';
                $order->save();

                // Refund Balance
                $user->balance += $amount;
                $user->save();

                // Create Transaction record (Credit)
                WalletTransaction::create([
                    'user_id'     => $user->id,
                    'type'        => 'credit',
                    'amount'      => $amount,
                    'description' => "API Refund (Failure): Order #{$order->id} - {$reason}",
                ]);

                Log::warning("API Provisioning Failed & Refunded: Order #{$order->id}. Reason: {$reason}");
            });
        } catch (\Exception $e) {
            Log::critical("FATAL: Failed to refund user after provisioning failure! Order #{$order->id}. Error: " . $e->getMessage());
        }
    }


    /**
     * List user orders/purchases.
     */
    /**
     * List user orders/purchases.
     */
    public function orders(Request $request): JsonResponse
    {
        if (!$request->attributes->get('api_key')->hasAbility('proxies:read')) {
            return $this->sendError('Insufficient scopes.', 'insufficient_scopes', 403);
        }

        $perPage = $request->integer('per_page', 15);
        $orders = Order::with('product')
            ->where('user_id', $request->user()->id)
            ->latest()
            ->paginate($perPage);

        // Enrich with usage stats using optimized bulk lookup
        $allBalances = $this->evomi->getSubuserBalances();
        $typeMap = [
            'rp'  => 'residential',
            'mp'  => 'mobile',
            'dc'  => 'dataCenter',
            'isp' => 'static',
        ];

        foreach ($orders->items() as $order) {
            $username = $order->evomi_username;
            if ($username && isset($allBalances[$username])) {
                $balances = $allBalances[$username];
                $typeCode = $order->product->type;
                $evomiType = $typeMap[$typeCode] ?? $typeCode;
                $currentBalance = (float) ($balances[$evomiType] ?? ($balances[$typeCode] ?? 0));
                $order->bandwidth_used = max(0, (float) $order->bandwidth_total - $currentBalance);
            } else {
                $order->bandwidth_used = 0;
            }
        }

        return $this->sendSuccess($orders->items(), 'Orders retrieved successfully.', 200, [
            'pagination' => [
                'total'        => $orders->total(),
                'per_page'     => $orders->perPage(),
                'current_page' => $orders->currentPage(),
                'last_page'    => $orders->lastPage(),
            ]
        ]);
    }

    /**
     * Get proxies for a specific order.
     */
    public function proxyDetail(Request $request, $id): JsonResponse
    {
        if (!$request->attributes->get('api_key')->hasAbility('proxies:read')) {
            return $this->sendError('Insufficient scopes.', 'insufficient_scopes', 403);
        }

        $order = Order::where('user_id', $request->user()->id)->findOrFail($id);
        $proxies = Proxy::where('order_id', $order->id)->get();

        return $this->sendSuccess([
            'order_id'   => $order->id,
            'status'     => $order->status,
            'expires_at' => $order->expires_at,
            'proxies'    => $proxies->map(fn($p) => [
                'host'     => $p->host,
                'port'     => (int) $p->port,
                'username' => $p->username,
                'password' => $p->password,
                'country'  => $p->country,
            ])
        ]);
    }

    /**
     * List API activity logs for the authenticated user.
     */
    public function logs(Request $request): JsonResponse
    {
        // Require audit:read or similar? Let's use proxies:read for now or *
        if (!$request->attributes->get('api_key')->hasAbility('*')) {
            return $this->sendError('Insufficient scopes to view logs.', 'insufficient_scopes', 403);
        }

        $perPage = $request->integer('per_page', 25);
        $logs = DB::table('api_logs')
            ->where('user_id', $request->user()->id)
            ->latest()
            ->paginate($perPage);

        return $this->sendSuccess($logs->items(), 'API logs retrieved successfully.', 200, [
            'pagination' => [
                'total'        => $logs->total(),
                'per_page'     => $logs->perPage(),
                'current_page' => $logs->currentPage(),
                'last_page'    => $logs->lastPage(),
            ]
        ]);
    }
}
