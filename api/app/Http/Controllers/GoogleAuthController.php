<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class GoogleAuthController extends Controller
{
    /**
     * Redirect the user to the Google authentication page.
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function redirectToGoogle()
    {
        try {
            Log::info('Google Login: Hit redirect route');
            $url = Socialite::driver('google')->stateless()->redirect()->getTargetUrl();
            Log::info('Google Login: Redirecting to ' . $url);
            return response()->json(['url' => $url]);
        } catch (\Exception $e) {
            Log::error('Google Login Redirect Error: ' . $e->getMessage(), [
                'stack' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => 'Server error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Obtain the user information from Google.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function handleGoogleCallback(Request $request)
    {
        try {
            Log::info('Google Auth Callback: Start', [
                'code' => $request->query('code') ? 'present' : 'missing',
                'ip' => $request->ip()
            ]);
            
            // Frontend passes the 'code' obtained from Google
            $googleUser = Socialite::driver('google')->stateless()->user();
            
            Log::info('Google Auth Callback: User retrieved', ['email' => $googleUser->email]);
            
            $user = User::where('google_id', $googleUser->id)
                        ->orWhere('email', $googleUser->email)
                        ->first();

            if (!$user) {
                // Determine referral_id if passed
                $referral_code = $request->query('referral_code');
                $referrer = null;
                if ($referral_code) {
                    $referrer = User::where('referral_code', $referral_code)->first();
                }

                $user = User::create([
                    'name'              => $googleUser->name,
                    'email'             => $googleUser->email,
                    'google_id'         => $googleUser->id,
                    'avatar'            => $googleUser->avatar,
                    'password'          => bcrypt(Str::random(24)),
                    'role'              => 'client',
                    'referral_code'     => 'UP-' . strtoupper(Str::random(8)),
                    'signup_ip'         => $request->ip(),
                    'email_verified_at' => now(), // Google users are pre-verified
                ]);

                // Link referral if referrer found
                if ($referrer) {
                    if ($referrer->signup_ip !== $request->ip()) {
                        \App\Models\Referral::create([
                            'referrer_id' => $referrer->id,
                            'referred_id' => $user->id,
                            'ip_address'  => $request->ip(),
                        ]);
                    }
                }

                // Trigger Welcome Email
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
                    Log::error("Google Registration Email Error: " . $e->getMessage());
                }
            } else {
                // Check if user is banned
                if ($user->role === 'banned') {
                    $this->recordLoginAttempt($request, $user, false);
                    return response()->json([
                        'error' => 'Your account has been suspended.'
                    ], 403);
                }

                if (!$user->google_id) {
                    $user->update([
                        'google_id' => $googleUser->id,
                        'avatar'    => $googleUser->avatar,
                        'email_verified_at' => $user->email_verified_at ?? now(),
                    ]);
                }
            }

            // Record Login
            $this->recordLoginAttempt($request, $user, true);

            // Check if 2FA is enabled
            if ($user->hasTwoFactorEnabled()) {
                $challengeToken = Str::random(60);
                \Illuminate\Support\Facades\Cache::put("2fa_challenge_{$challengeToken}", $user->id, 300);

                return response()->json([
                    'requires_2fa'    => true,
                    'challenge_token' => $challengeToken,
                ]);
            }

            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'user'  => $this->formatUser($user),
                'token' => $token,
            ]);

        } catch (\Exception $e) {
            Log::error('Google Auth Error: ' . $e->getMessage());
            return response()->json([
                'error'   => 'Failed to authenticate with Google.',
                'details' => $e->getMessage()
            ], 401);
        }
    }

    private function recordLoginAttempt(Request $request, User $user, bool $success)
    {
        try {
            \App\Models\LoginHistory::create([
                'user_id'    => $user->id,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'success'    => $success,
            ]);
        } catch (\Exception $e) {
            Log::error('Google Login logging failed: ' . $e->getMessage());
        }
    }

    private function formatUser(User $user): array
    {
        return [
            'id'             => (string) $user->id,
            'name'           => $user->name,
            'email'          => $user->email,
            'role'           => $user->role,
            'balance'        => (float) $user->balance,
            'referral_code'  => $user->referral_code,
            'avatar'         => $user->avatar,
            'is_2fa_enabled' => $user->hasTwoFactorEnabled(),
        ];
    }
}
