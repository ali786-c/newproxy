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
        Schema::table('admin_logs', function (Blueprint $table) {
            $table->string('ip_address', 45)->nullable()->after('target_user_id');
            $table->text('user_agent')->nullable()->after('ip_address');
            $table->string('geo_country')->nullable()->after('user_agent');
            $table->string('geo_city')->nullable()->after('geo_country');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('admin_logs', function (Blueprint $table) {
            $table->dropColumn(['ip_address', 'user_agent', 'geo_country', 'geo_city']);
        });
    }
};
