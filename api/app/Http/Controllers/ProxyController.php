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
            // ── Step 1: Ensure subuser is initialized ──────────────────────
            $subuserResult = $this->evomi->ensureSubuser($user);

            if (!$subuserResult['success']) {
                return response()->json(['message' => $subuserResult['error']], 503);
            }

            $user    = $user->fresh(); // Refresh after potential update
            $userKeys = $user->evomi_keys ?? [];

            // ── Step 2: Get the proxy key for this product type ────────────
            $proxyKey = $userKeys[$product->type] ?? ($userKeys['residential'] ?? null);

            if (!$proxyKey) {
                Log::error('ProxyController: no proxy key found', [
                    'user_id'      => $user->id,
                    'product_type' => $product->type,
                    'available_keys' => array_keys($userKeys),
                ]);
                return response()->json(['message' => "No proxy key found for type '{$product->type}'. Keys available: " . implode(', ', array_keys($userKeys))], 400);
            }

            // ── Step 3: Allocate bandwidth on Evomi side ───────────────────
            $evomiResult = $this->evomi->allocateBandwidth($user->evomi_username, $request->quantity * 1024, $product->type);

            if (!$evomiResult) {
                return response()->json(['message' => 'Failed to allocate bandwidth on provider side. Check logs.'], 503);
            }

            // ── Step 4: Deduct balance + create order + save proxies ───────
            return DB::transaction(function () use ($user, $product, $totalCost, $request, $proxyKey) {

                $user->balance -= $totalCost;
                $user->save();

                WalletTransaction::create([
                    'user_id'     => $user->id,
                    'type'        => 'debit',
                    'amount'      => $totalCost,
                    'description' => "Purchase: {$request->quantity}x {$product->name}",
                ]);

                $order = Order::create([
                    'user_id'    => $user->id,
                    'product_id' => $product->id,
                    'status'     => 'active',
                    'expires_at' => now()->addDays(30),
                ]);

                $portMap = [
                    'rp'  => 1000,
                    'mp'  => 3000,
                    'isp' => 3000,
                    'dc'  => 2000
                ];
                $hostMap = [
                    'rp'  => 'rp.evomi.com',
                    'mp'  => 'mp.evomi.com',
                    'dc'  => 'dcp.evomi.com',
                    'isp' => 'isp.evomi.com'
                ];

                $port    = $portMap[$product->type] ?? 1000;
                $host    = $hostMap[$product->type] ?? 'gate.evomi.com';

                $country     = $request->country      ?? 'US';
                $sessionType = $request->session_type ?? 'rotating';

                $proxies = [];
                for ($i = 0; $i < $request->quantity; $i++) {
                    $password = "{$proxyKey}_country-{$country}_session-{$sessionType}";
                    $proxy    = Proxy::create([
                        'order_id' => $order->id,
                        'host'     => $host,
                        'port'     => $port,
                        'username' => $user->evomi_username,
                        'password' => $password,
                        'country'  => $country,
                    ]);
                    $proxies[] = $proxy;
                }

                // Trigger auto top-up check if enabled
                app(\App\Http\Controllers\BillingController::class)->checkAndTriggerAutoTopUp($user);

                // --- NEW: Trigger Proxy Created Email ---
                try {
                    $user->notify(new \App\Notifications\ProxyCreatedNotification([
                        'user' => ['name' => $user->name],
                        'product' => ['name' => $product->name],
                        'order' => ['id' => $order->id],
                        'action_url' => url('/app/proxies'),
                        'year' => date('Y')
                    ]));
                } catch (\Exception $e) {
                    \Log::error("Proxy Created Email Error: " . $e->getMessage());
                }
                // ----------------------------------------

                return response()->json([
                    'message' => 'Proxies generated successfully.',
                    'proxies' => collect($proxies)->map(fn($p) => [
                        'host'     => $p->host,
                        'port'     => (int) $p->port,
                        'username' => $p->username,
                        'password' => $p->password,
                    ]),
                    'expires_at' => $order->expires_at,
                    'balance' => $user->balance,
                ]);
            });

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

        return response()->json($orders);
    }

    /**
     * Get dynamic proxy settings (countries, cities, etc.)
     */
    public function settings()
    {
        $cacheKey = 'evomi_proxy_settings';

        $settings = \Illuminate\Support\Facades\Cache::remember($cacheKey, 3600, function () {
            return $this->evomi->getProxySettings();
        });

        if (!$settings || isset($settings['error'])) {
            return response()->json(['message' => 'Could not fetch settings from provider.', 'detail' => $settings], 502);
        }

        return response()->json($settings);
    }
}
