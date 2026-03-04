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
        'crypto_wallet_address',
        'crypto_provider',
        'crypto_api_key',
        'gateway_stripe_enabled',
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
        $settings = Setting::whereIn('key', $this->allowedKeys)
            ->get()
            ->pluck('value', 'key');

        $envMap = [
            'stripe_publishable_key' => 'services.stripe.key',
            'stripe_secret_key'      => 'services.stripe.secret',
            'stripe_webhook_secret'  => 'services.stripe.webhook_secret',
            'cryptomus_merchant_id'  => 'services.cryptomus.merchant_id',
            'cryptomus_api_key'      => 'services.cryptomus.api_key',
            'cryptomus_webhook_secret' => 'services.cryptomus.webhook_secret',
            'smtp_host'              => 'mail.mailers.smtp.host',
            'smtp_port'              => 'mail.mailers.smtp.port',
            'smtp_user'              => 'mail.mailers.smtp.username',
            'smtp_pass'              => 'mail.mailers.smtp.password',
            'support_email'          => 'mail.from.address',
        ];

        foreach ($envMap as $dbKey => $configKey) {
            $configValue = config($configKey);
            if ($configValue) {
                $settings[$dbKey] = $configValue;
            }
        }

        return response()->json($settings);
    }

    /**
     * POST /admin/settings - Update bulk settings
     */
    public function update(Request $request)
    {
        $settings = $request->only($this->allowedKeys);

        foreach ($settings as $key => $value) {
            // Explicitly cast boolean values to "1" or "0" for the database
            if (is_bool($value)) {
                $value = $value ? '1' : '0';
            }

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
            'smtp_host'              => 'MAIL_HOST',
            'smtp_port'              => 'MAIL_PORT',
            'smtp_user'              => 'MAIL_USERNAME',
            'smtp_pass'              => 'MAIL_PASSWORD',
            'support_email'          => 'MAIL_FROM_ADDRESS',
        ];

        if (!isset($envMap[$key])) return;

        $envKey = $envMap[$key];
        $path = base_path('.env');

        if (file_exists($path)) {
            $content = file_get_contents($path);
            
            // Clean value for .env (wrap in quotes if contains spaces or special chars)
            $escapedValue = (str_contains($value, ' ') || str_contains($value, '$')) ? "\"{$value}\"" : $value;
            
            if (preg_match("/^{$envKey}=/m", $content)) {
                $content = preg_replace("/^{$envKey}=.*/m", "{$envKey}={$escapedValue}", $content);
            } else {
                $content = rtrim($content) . "\n{$envKey}={$escapedValue}\n";
            }

            file_put_contents($path, $content);
        }
    }
}
