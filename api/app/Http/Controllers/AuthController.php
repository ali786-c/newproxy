<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Order;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Cache;
use App\Services\EvomiService;
use PragmaRX\Google2FALaravel\Facade as Google2FA;

class AuthController extends Controller
{
    /**
     * Register a new user.
     * POST /auth/signup
     */
    public function register(Request $request)
    {
        $request->validate([
            'name'          => 'required|string|max:255',
            'email'         => 'required|string|email|max:255|unique:users',
            'password'      => 'required|string|min:8',
            'referral_code' => 'nullable|string|exists:users,referral_code',
        ]);

        $user = User::create([
            'name'          => $request->name,
            'email'         => $request->email,
            'password'      => Hash::make($request->password),
            'referral_code' => 'UP-' . strtoupper(Str::random(8)),
            'role'          => 'client',
            'balance'       => 0,
            'signup_ip'     => $request->ip(),
        ]);

        // Link referral if provided
        if ($request->referral_code) {
            // Check if referral system is enabled globally
            if (Setting::getValue('referral_system_enabled', '1') === '1') {
                $referrer = User::where('referral_code', $request->referral_code)->first();
                
                if ($referrer) {
                    // Fraud Check: Prevent self-referral via same IP
                    if ($referrer->signup_ip === $request->ip()) {
                        \Log::warning("Self-referral blocked for IP: " . $request->ip() . " attempting to use code: " . $request->referral_code);
                    } else {
                        \App\Models\Referral::create([
                            'referrer_id' => $referrer->id,
                            'referred_id' => $user->id,
                            'ip_address'  => $request->ip(),
                        ]);
                    }
                }
            }
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        // --- NEW: Trigger Dynamic Emails ---
        try {
            $user->notify(new \App\Notifications\WelcomeNotification([
                'user' => ['name' => $user->name],
                'app' => ['name' => \App\Models\Setting::getValue('app_name', 'UpgradedProxy')],
                'action_url' => url('/login'),
                'year' => date('Y')
            ]));

            // Admin Alert
            \Illuminate\Support\Facades\Notification::route('mail', \App\Models\Setting::getValue('admin_notification_email', 'aliyantarar4@gmail.com'))
                ->notify(new \App\Notifications\GenericDynamicNotification('admin_new_user', [
                    'user' => [
                        'name' => $user->name,
                        'email' => $user->email,
                        'signup_ip' => $user->signup_ip
                    ],
                    'year' => date('Y')
                ]));
        } catch (\Exception $e) {
            \Log::error("Registration Email Error: " . $e->getMessage());
        }
        // ------------------------------------

        return response()->json([
            'user'  => $this->formatUser($user),
            'token' => $token,
        ]);
    }

    /**
     * Login user.
     * POST /auth/login
     */
    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        if (!Auth::attempt($request->only('email', 'password'))) {
            $this->recordLoginAttempt($request, null, false);
            throw ValidationException::withMessages([
                'email' => ['Invalid email or password.'],
            ]);
        }

        $user  = User::where('email', $request->email)->firstOrFail();
        
        if ($user->role === 'banned') {
            $this->recordLoginAttempt($request, $user, false);
            throw ValidationException::withMessages([
                'email' => ['Your account has been suspended.'],
            ]);
        }

        $this->recordLoginAttempt($request, $user, true);

        // Check if 2FA is enabled
        if ($user->hasTwoFactorEnabled()) {
            $challengeToken = Str::random(60);
            Cache::put("2fa_challenge_{$challengeToken}", $user->id, 300); // 5 minutes

            return response()->json([
                'requires_2fa' => true,
                'challenge_token' => $challengeToken,
            ]);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user'  => $this->formatUser($user),
            'token' => $token,
        ]);
    }

    /**
     * Verify 2FA code during login.
     * POST /auth/2fa/verify
     */
    public function verify2fa(Request $request)
    {
        $request->validate([
            'challenge_token' => 'required|string',
            'code'           => 'required|string|min:6|max:15',
        ]);

        $userId = Cache::get("2fa_challenge_{$request->challenge_token}");

        if (!$userId) {
            return response()->json(['message' => 'Challenge expired or invalid.'], 422);
        }

        $user = User::findOrFail($userId);

        $isRecoveryCode = str_contains($request->code, '-');
        $valid = false;

        if ($isRecoveryCode) {
            $codes = $user->two_factor_recovery_codes ?? [];
            if (in_array($request->code, $codes)) {
                $valid = true;
                // Consume the code
                $user->two_factor_recovery_codes = array_values(array_diff($codes, [$request->code]));
                $user->save();
            }
        } else {
            $valid = app(\PragmaRX\Google2FALaravel\Google2FA::class)->verifyKey($user->two_factor_secret, $request->code);
        }

        if (!$valid) {
            return response()->json(['message' => 'Invalid verification code.'], 422);
        }

        Cache::forget("2fa_challenge_{$request->challenge_token}");
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user'  => $this->formatUser($user),
            'token' => $token,
        ]);
    }

    private function recordLoginAttempt(Request $request, ?User $user, bool $success)
    {
        try {
            if (!$user && $request->has('email')) {
                 $user = User::where('email', $request->email)->first();
            }

            if ($user) {
                \App\Models\LoginHistory::create([
                    'user_id'    => $user->id,
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'success'    => $success,
                    // geo fields can be populated later with a service
                ]);
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Login logging failed: ' . $e->getMessage());
        }
    }

    /**
     * Get current authenticated user.
     * GET /auth/me
     */
    public function me(Request $request)
    {
        return response()->json($this->formatUser($request->user()));
    }

    /**
     * Logout user.
     * POST /auth/logout
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out successfully.']);
    }

    /**
     * Get user profile with balance.
     * GET /profile
     */
    public function profile(Request $request)
    {
        $user = $request->user();
        return response()->json([
            'id'            => (string) $user->id,
            'name'          => $user->name,
            'email'         => $user->email,
            'role'          => $user->role,
            'balance'       => (float) $user->balance,
            'referral_code' => $user->referral_code,
            'created_at'    => $user->created_at,
        ]);
    }

    /**
     * Get user dashboard stats.
     * GET /stats
     */
    public function stats(Request $request)
    {
        $user = $request->user();
        $cacheKey = "user_stats_{$user->id}";

        return Cache::remember($cacheKey, 300, function () use ($user) {
            $activeOrders = Order::where('user_id', $user->id)
                ->where('status', 'active')
                ->count();

            $totalOrders = Order::where('user_id', $user->id)->count();

            $totalSpent = \App\Models\WalletTransaction::where('user_id', $user->id)
                ->where('type', 'debit')
                ->sum('amount');

            // --- Bandwidth Stats from Evomi ---
            $bandwidthTotal = 0;
            $bandwidthUsed  = 0;

            if ($user->evomi_username) {
                $evomi = app(EvomiService::class);
                $data  = $evomi->getSubuserData($user->evomi_username);
                
                if ($data && isset($data['data']['products'])) {
                    // Evomi returns products as key => data object
                    foreach ($data['data']['products'] as $type => $product) {
                        $bandwidthTotal += (float) ($product['balance'] ?? 0);
                        $bandwidthUsed  += (float) ($product['usage'] ?? 0);
                    }
                }
            }

            return [
                'balance'          => (float) $user->balance,
                'active_proxies'   => $activeOrders,
                'total_orders'     => $totalOrders,
                'total_spent'      => (float) $totalSpent,
                'bandwidth_total'  => $bandwidthTotal,
                'bandwidth_used'   => $bandwidthUsed,
                'bandwidth_unit'   => 'MB',
            ];
        });
    }

    /**
     * Update user profile (name/password).
     * POST /profile
     */
    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'name'     => 'nullable|string|max:255',
            'password' => 'nullable|string|min:8|confirmed',
        ]);

        if ($request->name) {
            $user->name = $request->name;
        }

        if ($request->password) {
            $user->password = Hash::make($request->password);
        }

        $user->save();

        return response()->json([
            'message' => 'Profile updated successfully.',
            'user'    => $this->formatUser($user),
        ]);
    }

    /**
     * Format user for API response — ensures id is string for frontend Zod schema.
     */
    private function formatUser(User $user): array
    {
        return [
            'id'            => (string) $user->id,
            'name'          => $user->name,
            'email'         => $user->email,
            'role'          => $user->role,
            'balance'       => (float) $user->balance,
            'referral_code' => $user->referral_code,
            'is_2fa_enabled'=> $user->hasTwoFactorEnabled(),
        ];
    }

    /**
     * GET /me/usage - Bandwidth usage over time (stub)
     */
    public function usage(Request $request)
    {
        return response()->json([
            'data'               => [],
            'total_bandwidth_mb' => 0,
            'total_requests'     => 0,
            'avg_success_rate'   => 0,
        ]);
    }

    /**
     * GET /me/events - Recent activity events (stub)
     */
    public function events(Request $request)
    {
        return response()->json([]);
    }

    /**
     * GET /me/subscription - Current subscription info (stub)
     */
    public function subscription(Request $request)
    {
        return response()->json([
            'plan'         => 'pay_as_you_go',
            'included_gb'  => 0,
            'used_gb'      => 0,
            'renewal_date' => now()->addMonth()->toDateString(),
            'price_cents'  => 0,
            'status'       => 'active',
        ]);
    }

    /**
     * GET /me/topup-settings - Get user top-up settings merged with global defaults
     */
    public function getTopUpSettings(Request $request)
    {
        $user = $request->user();
        $userSettings = $user->auto_topup_settings ?? [];
        
        $defaults = [
            'enabled'     => Setting::getValue('auto_topup_enabled') === '1',
            'threshold'   => (float) Setting::getValue('min_balance_threshold', 5),
            'amount'      => (float) Setting::getValue('default_topup_amount', 50),
            'max_monthly' => (float) Setting::getValue('max_monthly_topup', 500),
        ];

        return response()->json([
            'enabled'            => (bool) ($userSettings['enabled'] ?? false),
            'threshold'          => (float) ($userSettings['threshold'] ?? $defaults['threshold']),
            'amount'             => (float) ($userSettings['amount'] ?? $defaults['amount']),
            'max_monthly'        => (float) ($userSettings['max_monthly'] ?? $defaults['max_monthly']),
            'has_payment_method' => !empty($user->default_payment_method),
            'global_enabled'     => $defaults['enabled'],
        ]);
    }

    /**
     * POST /me/topup-settings - Update user top-up preferences
     */
    public function updateTopUpSettings(Request $request)
    {
        $request->validate([
            'enabled'     => 'required|boolean',
            'threshold'   => 'required|numeric|min:0',
            'amount'      => 'required|numeric|min:1',
            'max_monthly' => 'required|numeric|min:1',
        ]);

        $user = $request->user();
        $user->auto_topup_settings = [
            'enabled'     => $request->enabled,
            'threshold'   => $request->threshold,
            'amount'      => $request->amount,
            'max_monthly' => $request->max_monthly,
        ];
        $user->save();

        return response()->json(['message' => 'Top-up settings updated successfully.']);
    }
}
