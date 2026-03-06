<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use PragmaRX\Google2FALaravel\Facade as Google2FA;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\Hash;

class TwoFactorAuthController extends Controller
{
    /**
     * Get 2FA Setup data (Secret & QR Code)
     * GET /auth/2fa/setup
     */
    public function setup(Request $request)
    {
        $user = $request->user();

        if ($user->two_factor_confirmed_at) {
            return response()->json(['message' => '2FA is already enabled'], 400);
        }

        // Generate secret if not exists or regeneratging
        if (!$user->two_factor_secret) {
            $user->two_factor_secret = Google2FA::generateSecretKey();
            $user->save();
        }

        $qrCodeUrl = Google2FA::getQRCodeUrl(
            config('app.name', 'UpgradedProxy'),
            $user->email,
            $user->two_factor_secret
        );

        // Generate SVG QR Code using BaconQrCode
        $renderer = new ImageRenderer(
            new RendererStyle(200, 1), // Margin of 1
            new SvgImageBackEnd()
        );
        $writer = new Writer($renderer);
        $qrCodeSvg = $writer->writeString($qrCodeUrl);

        // Strip XML declaration if present to ensure clean rendering in HTML
        $qrCodeSvg = trim(preg_replace('/^<\?xml[^>]*\?>/i', '', $qrCodeSvg));

        return response()->json([
            'secret' => $user->two_factor_secret,
            'qr_code_svg' => $qrCodeSvg,
        ]);
    }

    /**
     * Confirm 2FA setup.
     * POST /auth/2fa/confirm
     */
    public function confirm(Request $request)
    {
        $request->validate([
            'code' => 'required|string|size:6',
        ]);

        $user = $request->user();

        if ($user->two_factor_confirmed_at) {
            return response()->json(['message' => '2FA is already enabled'], 400);
        }

        $valid = Google2FA::verifyKey($user->two_factor_secret, $request->code);

        if ($valid) {
            $user->two_factor_confirmed_at = now();
            
            // Generate recovery codes
            $recoveryCodes = collect(range(1, 8))->map(function () {
                return str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT) . '-' . str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            })->toArray();
            
            $user->two_factor_recovery_codes = $recoveryCodes;
            $user->save();

            // Audit Log
            \App\Models\AdminLog::log(
                '2fa_enabled',
                "User #{$user->id} ({$user->email}) enabled Two-Factor Authentication.",
                $user->id
            );

            return response()->json([
                'message' => '2FA enabled successfully',
                'recovery_codes' => $recoveryCodes
            ]);
        }

        return response()->json(['message' => 'Invalid verification code'], 422);
    }

    /**
     * Disable 2FA.
     * POST /auth/2fa/disable
     */
    public function disable(Request $request)
    {
        $request->validate([
            'password' => 'required',
            'code' => 'required|string', // Strictly required now
        ]);

        $user = $request->user();

        if (!Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Invalid password'], 422);
        }

        // Handle both TOTP code and Recovery Code
        $isRecoveryCode = str_contains($request->code, '-');
        $valid = false;

        if ($isRecoveryCode) {
            $codes = $user->two_factor_recovery_codes ?? [];
            if (in_array($request->code, $codes)) {
                $valid = true;
            }
        } else {
            $valid = Google2FA::verifyKey($user->two_factor_secret, $request->code);
        }

        if (!$valid) {
            return response()->json(['message' => 'Invalid verification code'], 422);
        }

        $user->two_factor_secret = null;
        $user->two_factor_confirmed_at = null;
        $user->two_factor_recovery_codes = null;
        $user->save();

        // Audit Log
        \App\Models\AdminLog::log(
            '2fa_disabled',
            "User #{$user->id} ({$user->email}) disabled Two-Factor Authentication.",
            $user->id
        );

        return response()->json(['message' => '2FA disabled successfully']);
    }

    /**
     * Get recovery codes.
     * GET /auth/2fa/recovery-codes
     */
    public function getRecoveryCodes(Request $request)
    {
        $user = $request->user();
        
        if (!$user->two_factor_confirmed_at) {
            return response()->json(['message' => '2FA is not enabled'], 400);
        }

        return response()->json([
            'recovery_codes' => $user->two_factor_recovery_codes
        ]);
    }
}
