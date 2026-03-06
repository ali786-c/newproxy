<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    /**
     * List of settings that should be masked in API responses.
     */
    protected $sensitiveKeys = [
        'stripe_secret_key',
        'stripe_webhook_secret',
        'cryptomus_api_key',
        'cryptomus_webhook_secret',
        'nowpayments_api_key',
        'nowpayments_ipn_secret',
        'smtp_pass',
    ];

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
        'gateway_nowpayments_enabled',
        'nowpayments_api_key',
        'nowpayments_ipn_secret',
        'nowpayments_vat_percentage',
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
        'stripe_vat_percentage',
        'cryptomus_vat_percentage',
        'manual_vat_percentage',
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
            'stripe_vat_percentage'  => 'services.stripe.vat_percentage',
            'cryptomus_vat_percentage' => 'services.cryptomus.vat_percentage',
            'manual_vat_percentage'  => 'services.manual.vat_percentage',
            'nowpayments_api_key'    => 'services.nowpayments.api_key',
            'nowpayments_ipn_secret' => 'services.nowpayments.ipn_secret',
            'nowpayments_vat_percentage' => 'services.nowpayments.vat_percentage',
        ];

        foreach ($envMap as $dbKey => $configKey) {
            // Only use config value if DB value is missing/empty
            if (!isset($settings[$dbKey]) || $settings[$dbKey] === '') {
                $configValue = config($configKey);
                if ($configValue !== null && $configValue !== '') {
                    $settings[$dbKey] = (string) $configValue;
                }
            }
        }

        $settings = $settings->map(function ($value, $key) {
            // Decrypt if sensitive (cached in DB as encrypted)
            if (Setting::isSensitive($key) && !empty($value)) {
                try {
                    $value = \Illuminate\Support\Facades\Crypt::decryptString($value);
                } catch (\Exception $e) { /* ignore */ }
            }
            return $this->maskValue($key, (string) $value);
        });

        return response()->json($settings);
    }

    /**
     * POST /admin/settings - Update bulk settings
     */
    public function update(Request $request)
    {
        // Task G2: Validation
        $request->validate([
            'site_name' => 'sometimes|string|max:255',
            'support_email' => 'sometimes|email',
            'admin_notification_email' => 'sometimes|email',
            'stripe_vat_percentage' => 'sometimes|numeric|min:0|max:100',
            'cryptomus_vat_percentage' => 'sometimes|numeric|min:0|max:100',
            'manual_vat_percentage' => 'sometimes|numeric|min:0|max:100',
            'min_balance_threshold' => 'sometimes|numeric|min:0',
            'default_topup_amount' => 'sometimes|numeric|min:1',
        ]);

        $settings = $request->only($this->allowedKeys);
        $updatedKeys = [];

        foreach ($settings as $key => $value) {
            // Task G4: Intelligent Redaction-Aware Update
            if ($this->isMaskedPlaceholder($key, $value)) {
                continue;
            }

            // Task Anti-Hack: CRITICAL - Strip all newlines to prevent .env injection
            if (is_string($value)) {
                $value = str_replace(["\r", "\n"], '', $value);
            }

            // Explicitly cast boolean values to "1" or "0" for the database
            if (is_bool($value)) {
                $value = $value ? '1' : '0';
            }

            $currentValue = Setting::getValue($key); // Decrypts if sensitive
            
            if ($currentValue != $value) {
                // If sensitive, encrypt before saving to DB
                $dbValue = $value;
                if (Setting::isSensitive($key) && !empty($value)) {
                    $dbValue = \Illuminate\Support\Facades\Crypt::encryptString($value);
                }

                Setting::updateOrCreate(
                    ['key' => $key],
                    ['value' => is_array($dbValue) ? json_encode($dbValue) : $dbValue]
                );

                // Task G3: Audit Logging
                \App\Models\AdminLog::log(
                    'update_setting',
                    "Setting '{$key}' updated.",
                    null,
                    [
                        'key' => $key,
                        'old' => $this->maskValue($key, $currentValue),
                        'new' => $this->maskValue($key, $value)
                    ]
                );

                // Task G5: Sync critical keys to .env
                $this->syncToEnv($key, $value);
                $updatedKeys[] = $key;
            }
        }

        if (count($updatedKeys) > 0) {
            \Illuminate\Support\Facades\Cache::forget('admin_gateway_status');
        }

        return response()->json([
            'message' => count($updatedKeys) > 0 ? 'Settings updated successfully' : 'No changes detected',
            'updated' => $updatedKeys
        ]);
    }

    /**
     * Mask sensitive values for API responses.
     */
    protected function maskValue($key, $value)
    {
        if (empty($value) || !in_array($key, $this->sensitiveKeys)) {
            return $value;
        }

        // Masking pattern: First 4 ... Last 4
        if (strlen($value) <= 10) {
            return '********';
        }

        return substr($value, 0, 4) . '...' . substr($value, -4);
    }

    /**
     * Check if the value is a masked placeholder (redacted).
     */
    protected function isMaskedPlaceholder($key, $value)
    {
        if (!in_array($key, $this->sensitiveKeys)) {
            return false;
        }

        if ($value === '********') {
            return true;
        }

        // Matches pattern "xxxx...yyyy"
        return preg_match('/^.{4}\.\.\..{4}$/', (string)$value);
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
            'stripe_vat_percentage'  => 'STRIPE_VAT_PERCENTAGE',
            'cryptomus_vat_percentage' => 'CRYPTOMUS_VAT_PERCENTAGE',
            'manual_vat_percentage'  => 'MANUAL_VAT_PERCENTAGE',
            'nowpayments_api_key'    => 'NOWPAYMENTS_API_KEY',
            'nowpayments_ipn_secret' => 'NOWPAYMENTS_IPN_SECRET',
            'nowpayments_vat_percentage' => 'NOWPAYMENTS_VAT_PERCENTAGE',
        ];

        if (!isset($envMap[$key])) return;

        $envKey = $envMap[$key];
        $path = base_path('.env');

        if (file_exists($path)) {
            $content = file_get_contents($path);
            
            // Task G5: Safe Value Escaping
            // Use single quotes if value contains special chars like $ or spaces
            if (preg_match('/[ \$\&\!]/', $value)) {
                $escapedValue = "'" . str_replace("'", "'\\''", $value) . "'";
            } else {
                $escapedValue = $value;
            }
            
            if (preg_match("/^{$envKey}=/m", $content)) {
                $content = preg_replace("/^{$envKey}=.*/m", "{$envKey}={$escapedValue}", $content);
            } else {
                $content = rtrim($content) . "\n{$envKey}={$escapedValue}\n";
            }

            file_put_contents($path, $content);

            // Force clear config cache so the new .env values are picked up immediately
            try {
                \Illuminate\Support\Facades\Artisan::call('config:clear');
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error("Failed to clear config cache after .env sync: " . $e->getMessage());
            }
        }
    }
}
