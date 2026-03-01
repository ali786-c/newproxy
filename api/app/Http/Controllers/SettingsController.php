<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    /**
     * List of settings allowed to be managed via general settings.
     */
    protected $allowedKeys = [
        'site_name',
        'support_email',
        'admin_notification_email',
        'maintenance_mode',
        'smtp_host',
        'smtp_port',
        'smtp_user',
        'smtp_pass',
        'admin_2fa_required',
        'rate_limiting_enabled',
        'stripe_publishable_key',
        'stripe_secret_key',
        'stripe_webhook_secret',
        'paypal_client_id',
        'paypal_client_secret',
        'paypal_mode',
        'crypto_wallet_address',
        'crypto_provider',
        'crypto_api_key',
        'gateway_stripe_enabled',
        'gateway_paypal_enabled',
        'gateway_crypto_enabled',
        'cryptomus_merchant_id',
        'cryptomus_api_key',
        'cryptomus_webhook_secret',
        'binance_pay_id',
        'binance_pay_instructions',
        'auto_topup_enabled',
        'min_balance_threshold',
        'default_topup_amount',
        'max_monthly_topup',
        'topup_source_primary',
        'topup_source_fallback',
        'retry_attempts',
        'retry_interval',
        'notify_client_success',
        'notify_admin_failure',
        'referral_system_enabled',
        'referral_commission_percentage',
        'referral_hold_days',
    ];

    /**
     * GET /admin/settings - Get all settings
     */
    public function index()
    {
        return response()->json(
            Setting::whereIn('key', $this->allowedKeys)
                ->get()
                ->pluck('value', 'key')
        );
    }

    /**
     * POST /admin/settings - Update bulk settings
     */
    public function update(Request $request)
    {
        $settings = $request->only($this->allowedKeys);

        foreach ($settings as $key => $value) {
            Setting::updateOrCreate(
                ['key' => $key],
                ['value' => is_array($value) ? json_encode($value) : $value]
            );

            // Sync critical keys to .env
            $this->syncToEnv($key, $value);
        }

        return response()->json(['message' => 'General settings updated successfully']);
    }

    /**
     * Sync database setting to .env file for backend components that rely on config().
     */
    protected function syncToEnv($key, $value)
    {
        $envMap = [
            'stripe_publishable_key' => 'STRIPE_KEY',
            'stripe_secret_key'      => 'STRIPE_SECRET',
            'stripe_webhook_secret'  => 'STRIPE_WEBHOOK_SECRET',
            'cryptomus_merchant_id'  => 'CRYPTOMUS_MERCHANT_ID',
            'cryptomus_api_key'      => 'CRYPTOMUS_API_KEY',
            'cryptomus_webhook_secret' => 'CRYPTOMUS_WEBHOOK_SECRET',
        ];

        if (!isset($envMap[$key])) return;

        $envKey = $envMap[$key];
        $path = base_path('.env');

        if (file_exists($path)) {
            $content = file_get_contents($path);
            
            // If key exists, replace it. If not, append it.
            if (strpos($content, "{$envKey}=") !== false) {
                $content = preg_replace("/^{$envKey}=.*/m", "{$envKey}=\"{$value}\"", $content);
            } else {
                $content .= "\n{$envKey}=\"{$value}\"";
            }

            file_put_contents($path, $content);
        }
    }
}
