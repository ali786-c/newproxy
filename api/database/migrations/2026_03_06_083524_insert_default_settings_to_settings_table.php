<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Setting;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $defaults = [
            'gateway_stripe_enabled' => '1',
            'stripe_publishable_key' => 'pk_live_...',
            'stripe_secret_key' => 'sk_live_...',
            'stripe_webhook_secret' => 'whsec_...',
            'admin_notification_email' => 'admin@example.com',
            'smtp_host' => 'server382.web-hosting.com',
            'smtp_port' => '465',
            'smtp_user' => 'no-reply@upgraderproxy.com',
            'smtp_pass' => 'ALiyan78675',
            'support_email' => 'support@upgraderproxy.com',
            'gateway_crypto_enabled' => '1',
            'binance_pay_id' => '12345678',
            'binance_pay_instructions' => 'Please send USDT via Binance Pay to this ID.',
            'stripe_vat_percentage' => '0',
            'site_name' => 'UpgradedProxy',
            'cryptomus_api_key' => '...',
            'auto_blog_enabled' => '1',
            'gateway_nowpayments_enabled' => '1',
            'nowpayments_api_key' => '...',
            'nowpayments_ipn_secret' => '...',
            'gemini_api_key' => '...',
            'gemini_model' => 'gemini-2.5-flash',
            'referral_system_enabled' => '1',
            'referral_commission_percentage' => '10',
            'referral_hold_days' => '14',
            'nowpayments_vat_percentage' => '0',
            'cryptomus_vat_percentage' => '0',
            'manual_vat_percentage' => '0',
        ];

        // Insert only if the key doesn't exist to prevent overwriting user-configured settings
        foreach ($defaults as $key => $value) {
            Setting::firstOrCreate(
                ['key' => $key],
                ['value' => $value]
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Not dropping these defaults on rollback to prevent accidental data loss.
    }
};
