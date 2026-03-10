<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email_verification_code', 6)->nullable()->after('email');
            $table->timestamp('email_verification_expires_at')->nullable()->after('email_verification_code');
            $table->boolean('has_claimed_trial')->default(false)->after('balance');
            $table->string('trial_claim_ip')->nullable()->after('has_claimed_trial');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'email_verification_code',
                'email_verification_expires_at',
                'has_claimed_trial',
                'trial_claim_ip'
            ]);
        });
    }
};
