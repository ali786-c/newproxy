<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FreeTrialProductSeeder extends Seeder
{
    public function run(): void
    {
        // Only insert if it doesn't already exist
        $exists = DB::table('products')->where('is_trial', true)->exists();

        if (!$exists) {
            DB::table('products')->insert([
                'name'             => 'Free Trial — 20MB Residential',
                'type'             => 'rp',
                'unit'             => 'GB',
                'price'            => 0.00,
                'base_cost'        => 0.00,
                'markup'           => 0.00,
                'evomi_product_id' => 'free_trial_rp',
                'is_active'        => true,
                'is_trial'         => true,
                'tagline'          => 'Try before you buy — 20MB free residential bandwidth',
                'features'         => json_encode([
                    '20MB Residential Bandwidth',
                    'All countries supported',
                    'Rotating & Sticky sessions',
                    'One-time per account',
                    'No credit card required',
                ]),
                'volume_discounts' => null,
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);
        }
    }
}
