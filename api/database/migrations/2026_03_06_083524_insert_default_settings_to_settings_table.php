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
            'site_name' => 'Evomi Proxy',
            'support_email' => 'support@upgraderproxy.com',
            'min_balance_threshold' => 5,
            'default_topup_amount' => 10,
            
            // Payment Gateway Defaults
            'stripe_vat_percentage' => 0,
            'cryptomus_vat_percentage' => 0,
            'manual_vat_percentage' => 0,
            'nowpayments_vat_percentage' => 0,
            
            // Referral System
            'referral_enabled' => true,
            'referral_global_rate' => 10,
            'referral_hold_days' => 14,
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
