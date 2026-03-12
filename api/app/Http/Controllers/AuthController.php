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
use Kreait\Firebase\Factory;
use Kreait\Firebase\Auth as FirebaseAuth;
use Kreait\Firebase\Exception\Auth\RevokedIdToken;
use Kreait\Firebase\Exception\Auth\InvalidToken;

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

        // --- Trigger Dynamic Emails (Welcome + Admin Alert) ---
        try {
            // 1. Generate Firebase Verification Link
            $credentialsPath = config('services.firebase.credentials');
            $factory = (new Factory)->withServiceAccount($credentialsPath);
            $auth    = $factory->createAuth();
            
            // We ensure the user exists in Firebase first (or just attempt to generate)
            // If they just signed up, they should exist in Firebase from frontend.
            // But we can also create them here if needed for robustness.
            $firebaseLink = $auth->getEmailVerificationLink($user->email);

            // 2. Send Verification Email via Laravel/Brevo
            $user->notify(new \App\Notifications\FirebaseVerificationNotification($firebaseLink, $user->name));

            // 3. Welcome Notification
            $user->notify(new \App\Notifications\WelcomeNotification([
                'user' => ['name' => $user->name],
                'app' => ['name' => \App\Models\Setting::getValue('app_name', 'UpgradedProxy')],
                'action_url' => url('/login'),
                'year' => date('Y')
            ]));

            // 4. Admin Alert
            $rootUrl = str_replace('/api', '', rtrim(config('app.url'), '/'));
            \Illuminate\Support\Facades\Notification::route('mail', \App\Models\Setting::getValue('admin_notification_email'))
                ->notify(new \App\Notifications\GenericDynamicNotification('admin_new_user', [
                    'user' => [
                        'name' => $user->name,
                        'email' => $user->email,
                        'ip' => $user->signup_ip
                    ],
                    'admin_url' => $rootUrl . '/admin/users/' . $user->id,
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
            try {
                $valid = app(\PragmaRX\Google2FALaravel\Google2FA::class)->verifyKey($user->two_factor_secret, $request->code);
            } catch (\PragmaRX\Google2FA\Exceptions\IncompatibleWithGoogleAuthenticatorException $e) {
                $valid = false;
                \Illuminate\Support\Facades\Log::warning("Corrupted 2FA secret for user {$user->id}. Exception: " . $e->getMessage());
            } catch (\Exception $e) {
                $valid = false;
                \Illuminate\Support\Facades\Log::error("2FA Verification Error for user {$user->id}: " . $e->getMessage());
            }
        }

        if (!$valid) {
            return response()->json(['message' => 'Invalid verification code or corrupted 2FA setup.'], 422);
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
        $user = $request->user();
        return response()->json([
            'id'                 => (string) $user->id,
            'name'               => $user->name,
            'email'              => $user->email,
            'role'               => $user->role,
            'balance'            => (float) $user->balance,
            'referral_code'      => $user->referral_code,
            'avatar'             => $user->avatar,
            'email_verified_at'  => $user->email_verified_at,
            'has_claimed_trial'  => (bool) $user->has_claimed_trial,
            'created_at'         => $user->created_at,
        ]);
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
            'avatar'        => $user->avatar,
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

        return Cache::remember($cacheKey, 60, function () use ($user) {
            $orders = Order::where('user_id', $user->id)
                ->where('status', 'active')
                ->with('product')
                ->get();

            $activeOrders = $orders->count();
            $totalOrders = Order::where('user_id', $user->id)->count();

            $totalSpent = \App\Models\WalletTransaction::where('user_id', $user->id)
                ->where('type', 'debit')
                ->sum('amount');

            // --- Bandwidth Stats from Evomi (Aggregated from Orders) ---
            $bandwidthTotal = $orders->sum('bandwidth_total');
            $bandwidthUsed  = 0;

            $evomi = app(EvomiService::class);
            $allBalances = $evomi->getSubuserBalances();
            
            $typeMap = [
                'rp'  => 'residential',
                'mp'  => 'mobile',
                'dc'  => 'dataCenter',
                'isp' => 'static',
            ];

            foreach ($orders as $order) {
                $username = $order->evomi_username;
                if ($username && isset($allBalances[$username])) {
                    $balances = $allBalances[$username];
                    $typeCode = $order->product->type;
                    $evomiType = $typeMap[$typeCode] ?? $typeCode;
                    $currentBalance = (float) ($balances[$evomiType] ?? ($balances[$typeCode] ?? 0));
                    
                    $orderUsed = max(0, (float) $order->bandwidth_total - $currentBalance);
                    $bandwidthUsed += $orderUsed;
                }
            }

            return [
                'balance'          => (float) $user->balance,
                'active_proxies'   => $activeOrders,
                'total_orders'     => $totalOrders,
                'total_spent'      => (float) $totalSpent,
                'bandwidth_total'  => (float) $bandwidthTotal,
                'bandwidth_used'   => (float) $bandwidthUsed,
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
            'name'             => 'nullable|string|max:255',
            'current_password' => 'required_with:password|string',
            'password'         => 'nullable|string|min:8|confirmed',
        ]);

        if ($request->name) {
            $user->name = $request->name;
        }

        if ($request->password) {
            // Verify current password
            if (!Hash::check($request->current_password, $user->password)) {
                return response()->json(['message' => 'The provided current password does not match our records.'], 422);
            }

            $user->password = Hash::make($request->password);

            // Audit Log for password change
            \App\Models\AdminLog::log(
                'password_changed',
                "User #{$user->id} ({$user->email}) changed their password.",
                $user->id
            );
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
            'id'                => (string) $user->id,
            'name'              => $user->name,
            'email'             => $user->email,
            'role'              => $user->role,
            'balance'           => (float) $user->balance,
            'referral_code'     => $user->referral_code,
            'avatar'            => $user->avatar,
            'is_2fa_enabled'    => $user->hasTwoFactorEnabled(),
            'email_verified_at' => $user->email_verified_at ? $user->email_verified_at->toIso8601String() : null,
            'has_claimed_trial' => (bool) $user->has_claimed_trial,
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

    /**
     * Sync Firebase email verification to SaaS database.
     * POST /auth/firebase-sync
     * 
     * Called by the frontend after Firebase confirms emailVerified = true.
     * Verifies the Firebase ID token, checks emailVerified flag,
     * then marks email_verified_at in the local DB.
     */
    public function firebaseSync(Request $request)
    {
        $request->validate([
            'firebase_id_token' => 'required|string',
        ]);

        $user = $request->user();

        // Already verified — no work needed
        if ($user->email_verified_at) {
            return response()->json([
                'message' => 'Already verified.',
                'user'    => $this->formatUser($user),
            ]);
        }

        try {
            $credentialsPath = config('services.firebase.credentials');

            $factory = (new Factory)->withServiceAccount($credentialsPath);
            $auth    = $factory->createAuth();

            // Verify the token (throws on invalid/expired)
            $verifiedToken = $auth->verifyIdToken($request->firebase_id_token);

            // Check Firebase emailVerified claim
            $claims = $verifiedToken->claims();
            $emailVerified = $claims->get('email_verified', false);

            if (!$emailVerified) {
                return response()->json([
                    'message' => 'Firebase email not yet verified. Please click the link in your email first.',
                ], 422);
            }

            // Double-check the Firebase email matches the SaaS user email
            $firebaseEmail = $claims->get('email', '');
            if (strtolower($firebaseEmail) !== strtolower($user->email)) {
                \Log::warning("Firebase sync email mismatch for user #{$user->id}: Firebase={$firebaseEmail}, SaaS={$user->email}");
                return response()->json([
                    'message' => 'Token email does not match your account email.',
                ], 422);
            }

            // Mark as verified in SaaS DB
            $user->markEmailAsVerified();
            event(new \Illuminate\Auth\Events\Verified($user));

            \Log::info("Firebase verification synced for user #{$user->id} ({$user->email})");

            return response()->json([
                'message' => 'Email verified successfully.',
                'user'    => $this->formatUser($user->fresh()),
            ]);

        } catch (InvalidToken | RevokedIdToken $e) {
            \Log::warning("Firebase sync invalid token for user #{$user->id}: " . $e->getMessage());
            return response()->json([
                'message' => 'Invalid or expired Firebase token. Please try again.',
            ], 401);
        } catch (\Exception $e) {
            \Log::error("Firebase sync error for user #{$user->id}: " . $e->getMessage());
            return response()->json([
                'message' => 'Verification sync failed. Please try again.',
            ], 500);
        }
    }
    /**
     * Resend Firebase verification email via Laravel.
     * POST /auth/resend-verification
     */
    public function resendFirebaseVerification(Request $request)
    {
        $user = $request->user();

        if ($user->email_verified_at) {
            return response()->json(['message' => 'Email already verified.']);
        }

        try {
            $credentialsPath = config('services.firebase.credentials');
            $factory = (new Factory)->withServiceAccount($credentialsPath);
            $auth    = $factory->createAuth();

            $firebaseLink = $auth->getEmailVerificationLink($user->email);
            
            $user->notify(new \App\Notifications\FirebaseVerificationNotification($firebaseLink, $user->name));

            return response()->json(['message' => 'Verification email resent successfully.']);
        } catch (\Exception $e) {
            \Log::error("Resend Verification Error for user #{$user->id}: " . $e->getMessage());
            return response()->json(['message' => 'Failed to send verification email.'], 500);
        }
    }
}
