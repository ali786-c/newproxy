<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PasswordResetController extends Controller
{
    public function sendResetLink(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $user = User::where('email', $request->email)->first();
        if (!$user) {
            return response()->json(['message' => 'If an account exists, a reset link has been sent.']);
        }

        $token = Str::random(60);
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $user->email],
            ['token' => Hash::make($token), 'created_at' => now()]
        );

        \Illuminate\Support\Facades\Log::info("Password reset link for {$user->email}: " . url("/reset-password?token={$token}&email=" . urlencode($user->email)));

        // --- NEW: Trigger Dynamic Reset Email ---
        try {
            $user->notify(new \App\Notifications\ResetPasswordNotification([
                'user' => ['name' => $user->name],
                'reset_url' => url('/reset-password?token=' . $token . '&email=' . $user->email),
                'year' => date('Y')
            ]));
        } catch (\Exception $e) {
            \Log::error("Reset Password Email Error: " . $e->getMessage());
        }
        // ----------------------------------------

        return response()->json(['message' => 'Password reset link sent successfully.']);
    }

    public function reset(Request $request)
    {
        $request->validate([
            'token'    => 'required',
            'email'    => 'required|email',
            'password' => 'required|min:8|confirmed',
        ]);

        $reset = DB::table('password_reset_tokens')->where('email', $request->email)->first();

        if (!$reset || !Hash::check($request->token, $reset->token)) {
            return response()->json(['message' => 'Invalid token or email.'], 400);
        }

        $user = User::where('email', $request->email)->first();
        if (!$user) return response()->json(['message' => 'User not found.'], 404);

        $user->password = Hash::make($request->password);
        $user->save();

        // --- NEW: Trigger Password Changed Email ---
        try {
            $user->notify(new \App\Notifications\GenericDynamicNotification('password_changed_user', [
                'user' => ['name' => $user->name],
                'action_url' => url('/login'),
                'year' => date('Y')
            ]));
        } catch (\Exception $e) {
            \Log::error("Password Changed Email Error: " . $e->getMessage());
        }
        // -------------------------------------------

        DB::table('password_reset_tokens')->where('email', $request->email)->delete();

        return response()->json(['message' => 'Password has been reset successfully.']);
    }
}
