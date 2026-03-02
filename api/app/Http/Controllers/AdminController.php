<?php

namespace App\Http\Controllers;

use App\Models\AdminLog;
use App\Models\User;
use App\Models\Order;
use App\Models\WalletTransaction;
use App\Models\UsageLog;
use App\Models\UptimeRecord;
use App\Models\FulfillmentLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AdminController extends Controller
{
    public function users(Request $request)
    {
        $query = User::latest();

        if ($request->search) {
            $s = $request->search;
            $query->where(function($q) use ($s) {
                $q->where('name', 'like', "%$s%")
                  ->orWhere('email', 'like', "%$s%")
                  ->orWhere('referral_code', 'like', "%$s%");
            });
        }

        return response()->json($query->get());
    }

    public function updateBalance(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'amount' => 'required|numeric',
            'reason' => 'required|string',
        ]);

        $admin = $request->user();
        $target = User::findOrFail($request->user_id);

        DB::transaction(function () use ($admin, $target, $request) {
            $oldBalance = $target->balance;
            $target->balance += $request->amount;
            $target->save();

            AdminLog::log(
                'update_balance',
                "Balance changed from {$oldBalance} to {$target->balance}. Reason: {$request->reason}",
                $target->id
            );
        });

        return response()->json(['message' => 'Balance updated successfully', 'new_balance' => $target->balance]);
    }

    public function banUser(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'reason' => 'required|string',
        ]);

        $admin = $request->user();
        $target = User::findOrFail($request->user_id);

        $isBanning = $target->role !== 'banned';
        $target->role = $isBanning ? 'banned' : 'client';
        $target->save();

        AdminLog::log(
            $isBanning ? 'ban_user' : 'unban_user',
            ($isBanning ? "User banned. " : "User unbanned. ") . "Reason: {$request->reason}",
            $target->id
        );

        return response()->json(['message' => 'User status updated successfully', 'new_role' => $target->role]);
    }

    /**
     * POST /admin/users/{id}/role - Change user role
     */
    public function updateRole(Request $request, $id)
    {
        $request->validate(['role' => 'required|in:admin,client']);
        
        $user = User::findOrFail($id);
        $oldRole = $user->role;
        $user->role = $request->role;
        $user->save();

        AdminLog::log(
            'change_role',
            "Role changed from {$oldRole} to {$request->role}",
            $user->id
        );

        return response()->json(['message' => "Role updated to {$request->role}"]);
    }

    /**
     * POST /admin/users/{id}/password - Set new user password
     */
    public function updatePassword(Request $request, $id)
    {
        $request->validate([
            'password' => 'required|string|min:8|confirmed'
        ]);

        $user = User::findOrFail($id);
        $user->password = Hash::make($request->password);
        $user->save();

        AdminLog::log(
            'reset_password',
            "Password manually reset by admin",
            $user->id
        );

        return response()->json(['message' => 'Password updated successfully']);
    }

    /**
     * GET /admin/users/{id}/orders - List a specific user's orders/proxies
     */
    public function getUserOrders($id)
    {
        $orders = Order::with('product', 'proxies')
            ->where('user_id', $id)
            ->latest()
            ->get();
            
        return response()->json($orders);
    }

    /**
     * DELETE /admin/orders/{orderId} - Delete a specific order
     */
    public function deleteOrder($orderId)
    {
        $order = Order::findOrFail($orderId);
        $userId = $order->user_id;

        DB::transaction(function() use ($order, $userId) {
            // Remove associated proxies
            $order->proxies()->delete();
            $order->delete();

            AdminLog::log(
                'delete_order',
                "Order #{$order->id} and associated proxies deleted by admin.",
                $userId
            );
        });

        return response()->json(['message' => 'Order deleted successfully']);
    }

    /**
     * POST /admin/users/{id}/orders - Manually add an order/proxy to a user
     */
    public function addUserOrder(Request $request, $id)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1|max:100',
            'days' => 'required|integer|min:1|max:365',
        ]);

        $user = User::findOrFail($id);
        $product = \App\Models\Product::findOrFail($request->product_id);

        $order = DB::transaction(function() use ($user, $product, $request) {
            $order = Order::create([
                'user_id' => $user->id,
                'product_id' => $product->id,
                'status' => 'active',
                'expires_at' => now()->addDays($request->days),
                'description' => "Manually assigned by admin"
            ]);

            // Create placeholder proxies (Host/Port/User/Pass based on product type)
            // Note: Admin might need real keys, for now use standard pattern.
            $portMap = ['rp' => 1000, 'mp' => 3000, 'isp' => 3000, 'dc' => 2000];
            $hostMap = ['rp'  => 'rp.evomi.com', 'mp' => 'mp.evomi.com', 'dc' => 'dcp.evomi.com', 'isp' => 'isp.evomi.com'];
            
            $port = $portMap[$product->type] ?? 1000;
            $host = $hostMap[$product->type] ?? 'gate.evomi.com';

            for ($i = 0; $i < $request->quantity; $i++) {
                \App\Models\Proxy::create([
                    'order_id' => $order->id,
                    'host' => $host,
                    'port' => $port,
                    'username' => $user->evomi_username ?: $user->email,
                    'password' => 'admin_assigned_' . strtolower(\Illuminate\Support\Str::random(8)),
                    'country' => 'US',
                ]);
            }

            AdminLog::log(
                'add_order_manual',
                "Manually assigned order #{$order->id} ({$product->name}) for {$request->days} days.",
                $user->id
            );

            return $order;
        });

        return response()->json(['message' => 'Order assigned successfully', 'order' => $order]);
    }

    public function stats()
    {
        $errorThreshold = (float) \App\Models\Setting::getValue('alert_error_spike_pct', 5.0);
        $banThreshold   = (float) \App\Models\Setting::getValue('alert_ban_spike_pct', 8.0);
        
        // 1. Calculate Error Rate (last 24h)
        $totalChecks = UptimeRecord::where('checked_at', '>=', now()->subDay())->count();
        $errorChecks = UptimeRecord::where('checked_at', '>=', now()->subDay())
            ->where('status', '!=', 'up')
            ->count();
        $currentErrorRate = $totalChecks > 0 ? round(($errorChecks / $totalChecks) * 100, 2) : 0.0;

        // 2. Calculate Ban Rate (last 24h)
        $totalUsers = User::count();
        $bannedRecently = AdminLog::where('action', 'ban_user')
            ->where('created_at', '>=', now()->subDay())
            ->count();
        $currentBanRate = $totalUsers > 0 ? round(($bannedRecently / $totalUsers) * 100, 2) : 0.0;

        $alerts = [];
        if ($currentErrorRate > $errorThreshold) {
            $alerts[] = [
                'id' => 'err_' . time(),
                'type' => 'error',
                'message' => "Error rate spike: {$currentErrorRate}% exceeds threshold of {$errorThreshold}%",
                'icon' => 'Zap'
            ];
        }

        if ($currentBanRate > $banThreshold) {
            $alerts[] = [
                'id' => 'ban_' . time(),
                'type' => 'warning',
                'message' => "Ban rate spike: {$currentBanRate}% exceeds threshold of {$banThreshold}%",
                'icon' => 'ShieldAlert'
            ];
        }

        // 3. Pending Support Tickets
        $openTickets = \App\Models\SupportTicket::where('status', '!=', 'closed')->count();
        if ($openTickets > 0) {
            $alerts[] = [
                'id' => 'support_' . time(),
                'type' => 'info',
                'message' => "{$openTickets} pending support tickets require attention.",
                'icon' => 'Ticket'
            ];
        }

        // 4. Pending SLA Credits
        $pendingCredits = \App\Models\SLACredit::where('status', 'pending')->count();
        if ($pendingCredits > 0) {
            $alerts[] = [
                'id' => 'sla_' . time(),
                'type' => 'warning',
                'message' => "{$pendingCredits} SLA compensation credits pending approval.",
                'icon' => 'DollarSign'
            ];
        }

        if (empty($alerts)) {
            $alerts[] = [
                'id' => 'ok_' . time(),
                'type' => 'info',
                'message' => 'System is running normally. No critical issues detected.',
                'icon' => 'Activity'
            ];
        }

        // Merge Recent Sales (Top-ups and Purchases)
        $topups = WalletTransaction::with('user:id,name,email')
            ->where('type', 'credit')
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn($t) => [
                'user'   => $t->user ? ($t->user->name ?? $t->user->email) : 'Unknown',
                'amount' => (float) $t->amount,
                'time'   => $t->created_at->diffForHumans(),
                'plan'   => 'Wallet Top-up',
                'created_at' => $t->created_at
            ]);

        $orders = Order::with(['user:id,name,email', 'product:id,name,price'])
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn($o) => [
                'user'   => $o->user ? ($o->user->name ?? $o->user->email) : 'Unknown',
                'amount' => (float) ($o->product->price ?? 0),
                'time'   => $o->created_at->diffForHumans(),
                'plan'   => $o->product->name ?? 'Proxy Order',
                'created_at' => $o->created_at
            ]);

        $recentSales = $topups->concat($orders)
            ->sortByDesc('created_at')
            ->values()
            ->take(5)
            ->map(function($item) {
                unset($item['created_at']);
                return $item;
            });

        return response()->json([
            'total_users'          => (int) User::count(),
            'total_active_proxies' => (int) Order::where('status', 'active')->count(),
            'total_revenue'        => (float) WalletTransaction::where('type', 'credit')->sum('amount'),
            'system_total_balance' => (float) User::sum('balance'),
            'recent_registrations' => (int) User::where('created_at', '>=', now()->subDays(7))->count(),
            'revenue_last_24h'     => (float) WalletTransaction::where('type', 'credit')
                ->where('created_at', '>=', now()->subDay())
                ->sum('amount'),
            
            'bandwidth_30d_gb'     => (float) UsageLog::where('date', '>=', now()->subDays(30)->toDateString())->sum('gb_used'),
            'uptime'               => 99.99,
            'error_rate'           => $currentErrorRate,
            
            'recent_sales'         => $recentSales,
            'alerts'               => $alerts
        ]);
    }

    public function getAlertConfig()
    {
        return response()->json([
            'error_spike_pct'       => (float) \App\Models\Setting::getValue('alert_error_spike_pct', 5.0),
            'ban_spike_pct'         => (float) \App\Models\Setting::getValue('alert_ban_spike_pct', 8.0),
            'spend_cap_usd'         => (float) \App\Models\Setting::getValue('alert_spend_cap_eur', 5000),
            'unusual_geo_threshold' => (int) \App\Models\Setting::getValue('alert_unusual_geo_threshold', 3),
            'notify_email'          => (bool) \App\Models\Setting::getValue('alert_notify_email', true),
            'notify_webhook'        => (bool) \App\Models\Setting::getValue('alert_notify_webhook', false),
            'webhook_url'           => \App\Models\Setting::getValue('alert_webhook_url', ''),
        ]);
    }

    public function updateAlertConfig(Request $request)
    {
        $defaults = [
            'error_spike_pct'       => 'alert_error_spike_pct',
            'ban_spike_pct'         => 'alert_ban_spike_pct',
            'spend_cap_usd'         => 'alert_spend_cap_eur',
            'unusual_geo_threshold' => 'alert_unusual_geo_threshold',
            'notify_email'          => 'alert_notify_email',
            'notify_webhook'        => 'alert_notify_webhook',
            'webhook_url'           => 'alert_webhook_url',
        ];

        foreach ($defaults as $inputKey => $settingKey) {
            if ($request->has($inputKey)) {
                \App\Models\Setting::updateOrCreate(
                    ['key' => $settingKey],
                    ['value' => $request->input($inputKey), 'type' => 'string']
                );
            }
        }

        return response()->json(['message' => 'Alert configuration updated']);
    }

    public function logs(Request $request)
    {
        $query = AdminLog::with(['admin', 'targetUser']);
        
        if ($request->has('action') && $request->action !== 'all') {
            $query->where('action', $request->action);
        }
        
        return response()->json($query->latest()->limit(100)->get());
    }

    /**
     * GET /admin/users/{id}/stats - Detailed stats for a single user
     */
    public function userStats($id)
    {
        $user = User::findOrFail($id);
        
        return response()->json([
            'total_spent'   => (float) \App\Models\WalletTransaction::where('user_id', $id)->where('type', 'debit')->sum('amount'),
            'total_orders'  => (int) \App\Models\Order::where('user_id', $id)->count(),
            'active_orders' => (int) \App\Models\Order::where('user_id', $id)->where('status', 'active')->count(),
            'total_keys'    => (int) \App\Models\ApiKey::where('user_id', $id)->count(),
            'active_keys'   => (int) \App\Models\ApiKey::where('user_id', $id)->count(),
            'total_tickets' => (int) \App\Models\SupportTicket::where('user_id', $id)->count(),
            'open_tickets'  => (int) \App\Models\SupportTicket::where('user_id', $id)->where('status', 'open')->count(),
        ]);
    }

    public function listResellers()
    {
        return response()->json(\App\Models\ResellerProfile::with('user')->latest()->get());
    }

    public function storeReseller(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'company_name' => 'required|string|max:255',
            'commission_rate' => 'required|numeric|min:0|max:100',
            'brand_primary_color' => 'nullable|string',
            'brand_secondary_color' => 'nullable|string',
            'custom_domain' => 'nullable|string|unique:reseller_profiles,custom_domain',
        ]);

        $reseller = \App\Models\ResellerProfile::create($validated);
        return response()->json($reseller, 201);
    }

    public function updateReseller(Request $request, $id)
    {
        $reseller = \App\Models\ResellerProfile::findOrFail($id);
        
        $validated = $request->validate([
            'company_name' => 'sometimes|required|string|max:255',
            'commission_rate' => 'sometimes|required|numeric|min:0|max:100',
            'brand_primary_color' => 'nullable|string',
            'brand_secondary_color' => 'nullable|string',
            'custom_domain' => 'nullable|string|unique:reseller_profiles,custom_domain,' . $id,
            'is_active' => 'sometimes|boolean',
        ]);

        $reseller->update($validated);
        return response()->json($reseller);
    }

    public function referralStats()
    {
        return response()->json([
            'total_referrals' => \App\Models\Referral::count(),
            'total_pending'   => (float) \App\Models\ReferralEarning::where('status', 'pending')->sum('amount'),
            'total_completed' => (float) \App\Models\ReferralEarning::where('status', 'completed')->sum('amount'),
            'top_referrers'   => User::whereHas('referrals')
                ->withCount('referrals')
                ->orderBy('referrals_count', 'desc')
                ->limit(5)
                ->get(['id', 'name', 'email', 'referrals_count'])
        ]);
    }

    public function listReferralEarnings(Request $request)
    {
        $query = \App\Models\ReferralEarning::with(['referrer', 'referred']);
        
        if ($request->status) {
            $query->where('status', $request->status);
        }

        if ($request->user_id) {
            $query->where('referrer_id', $request->user_id);
        }

        if ($request->search) {
            $s = $request->search;
            $query->whereHas('referrer', function($q) use ($s) {
                $q->where('name', 'like', "%$s%")->orWhere('email', 'like', "%$s%");
            });
        }

        return response()->json($query->latest()->paginate(20));
    }

    public function updateInfluencerRate(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'custom_rate' => 'nullable|numeric|min:0|max:100',
        ]);

        $user = User::findOrFail($request->user_id);
        $user->custom_referral_rate = $request->custom_rate;
        $user->save();

        AdminLog::log(
            'update_influencer_rate',
            "Custom referral rate set to " . ($request->custom_rate ?? 'null') . "%",
            $user->id
        );

        return response()->json(['message' => 'Influencer rate updated successfully']);
    }

    public function updateReferralEarningStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:pending,completed,void',
        ]);

        $earning = \App\Models\ReferralEarning::findOrFail($id);
        $oldStatus = $earning->status;
        $earning->status = $request->status;
        $earning->save();

        AdminLog::log(
            'update_referral_earning_status',
            "Referral earning #{$id} status changed from {$oldStatus} to {$request->status}"
        );

        return response()->json(['message' => 'Earning status updated successfully']);
    }

    /**
     * GET /admin/fulfillment-logs - List granular fulfillment logs for debugging.
     */
    public function fulfillmentLogs(Request $request)
    {
        $logs = FulfillmentLog::with('user:id,name,email')
            ->latest()
            ->paginate(50);

        return response()->json($logs);
    }
}
