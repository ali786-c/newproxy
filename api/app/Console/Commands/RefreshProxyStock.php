<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class RefreshProxyStock extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'proxies:refresh-stock';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Refresh and cache proxy stock/locations from Evomi to ensure instant UI loading.';

    /**
     * Execute the console command.
     */
    public function handle(\App\Services\EvomiService $evomi)
    {
        $this->info('Refreshing Proxy Stock & Locations...');

        // 1. Fetch Master Subuser (any user with evomi_username)
        $user = \App\Models\User::whereNotNull('evomi_username')->first();
        if (!$user) {
            $this->error('No user found with Evomi subuser mapping. Cannot fetch stock.');
            return 1;
        }

        // 2. Fetch Global ISP Stock
        $this->line('Fetching ISP Stock...');
        $ispStock = $evomi->getIspStock($user->evomi_username);
        if ($ispStock && isset($ispStock['data'])) {
            \Illuminate\Support\Facades\Cache::put('evomi_isp_stock_global', $ispStock, 7200); // 2 hours
            $this->info('ISP Stock Cached Successfully.');
        } else {
            $this->error('Failed to fetch ISP Stock.');
        }

        // 3. Fetch General Proxy Settings (Countries/Cities for Residential/DC)
        $this->line('Fetching General Proxy Settings...');
        $settings = $evomi->getProxySettings();
        if ($settings && !isset($settings['error'])) {
            \Illuminate\Support\Facades\Cache::put('evomi_proxy_settings', $settings, 7200);
            $this->info('Proxy Settings Cached Successfully.');
        } else {
            $this->error('Failed to fetch Proxy Settings.');
        }

        $this->info('Stock refresh complete.');
        return 0;
    }
}
