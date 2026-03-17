<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Product;

class ProductSyncSeeder extends Seeder
{
    public function run(): void
    {
        $products = [
            [
                'name' => 'Shared Datacenter (GB)',
                'type' => 'sdc',
                'price' => 1.00,
                'evomi_product_id' => 'reseller_sdc_plan',
                'is_active' => true,
            ],
            [
                'name' => 'Static ISP Proxy (Monthly)',
                'type' => 'isp_private',
                'price' => 2.50,
                'evomi_product_id' => 'reseller_isp_plan',
                'is_active' => true,
            ],
            [
                'name' => 'Datacenter IPv6 (GB)',
                'type' => 'dc_ipv6',
                'price' => 0.50,
                'evomi_product_id' => 'reseller_dc_ipv6_plan',
                'is_active' => true,
            ],
            [
                'name' => 'Datacenter Unmetered (GB)',
                'type' => 'dc_unmetered',
                'price' => 3.00,
                'evomi_product_id' => 'reseller_dc_unmetered_plan',
                'is_active' => true,
            ],
        ];

        foreach ($products as $p) {
            Product::updateOrCreate(['type' => $p['type']], $p);
        }
    }
}
