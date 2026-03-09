<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Create isp_packages table
        Schema::create('isp_packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->constrained();
            $table->string('order_id')->nullable();
            $table->string('evomi_package_id')->nullable();
            $table->string('status')->default('active'); // active, expired, pending
            $table->dateTime('expires_at');
            $table->json('ip_data')->nullable(); // Stores host, port, credentials, isp, locations
            $table->timestamps();
        });

        // 2. Insert Static ISP Products with 30% Profit Markup + EUR Pricing
        DB::table('products')->insert([
            [
                'name' => 'Static Residential (Shared)',
                'type' => 'isp_shared',
                'tagline' => 'Cost-effective static IPs shared with max 3 users',
                'unit' => 'IP',
                'price' => 1.29, // Based on $1.00 + 30% = $1.30 (~€1.29)
                'is_active' => true,
                'evomi_product_id' => 'static_isp_shared_1',
                'features' => json_encode([
                    'Unlimited Bandwidth',
                    'Shared with max 3 users',
                    'Persistent IP for 30 days',
                    'SOCKS5 & HTTP/S Supported',
                    'ISP-Grade Reputation'
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Static Residential (Dedicated)',
                'type' => 'isp_private',
                'tagline' => 'Private static IPs dedicated only to you',
                'unit' => 'IP',
                'price' => 2.99, // Based on $2.50 + 30% = $3.25 (~€2.99)
                'is_active' => true,
                'evomi_product_id' => 'static_isp_dedicated_2',
                'features' => json_encode([
                    'Unlimited Bandwidth',
                    'Dedicated Private IP',
                    'Persistent IP for 30 days',
                    'Zero Contention - 100% Yours',
                    'Perfect for Social Media & Business'
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Static Residential (Virgin)',
                'type' => 'isp_virgin',
                'tagline' => 'Never-before-used IPs with 0 fraud score',
                'unit' => 'IP',
                'price' => 5.49, // Based on $4.50 + 30% = $5.85 (~€5.49)
                'is_active' => true,
                'evomi_product_id' => 'static_isp_virgin_3',
                'features' => json_encode([
                    'Unlimited Bandwidth',
                    'Guaranteed 0 Fraud Score',
                    'Virgin IPs - Clean Reputation',
                    'Dedicated only to you',
                    'Ideal for high-security targets'
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('isp_packages');
        DB::table('products')->whereIn('type', ['isp_shared', 'isp_private', 'isp_virgin'])->delete();
    }
};
