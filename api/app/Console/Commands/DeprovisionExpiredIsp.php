<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class DeprovisionExpiredIsp extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'isp:deprovision-expired';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Find and deprovision expired Static ISP packages.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $expiredPackages = \App\Models\IspPackage::where('status', 'active')
            ->where('expires_at', '<', now())
            ->get();

        if ($expiredPackages->isEmpty()) {
            $this->info('No expired ISP packages found.');
            return 0;
        }

        foreach ($expiredPackages as $package) {
            $this->info("Processing expired package #{$package->id} for User #{$package->user_id}");

            // 1. Mark as expired
            $package->update(['status' => 'expired']);

            // 2. Mark associated Order and Proxies as expired
            if ($package->order_id) {
                $order = \App\Models\Order::find($package->order_id);
                if ($order && $order->status === 'active') {
                    $order->update(['status' => 'expired']);
                    // Delete actual proxy credentials from UI
                    $count = \App\Models\Proxy::where('order_id', $order->id)->delete();
                    $this->line("Deleted {$count} proxies for Order #{$order->id}");
                }
            } else {
                // Fallback for older records or if link missed
                $orders = \App\Models\Order::where('user_id', $package->user_id)
                    ->where('product_id', $package->product_id)
                    ->where('status', 'active')
                    ->where('expires_at', '<=', $package->expires_at)
                    ->get();

                foreach ($orders as $order) {
                    $order->update(['status' => 'expired']);
                    \App\Models\Proxy::where('order_id', $order->id)->delete();
                }
            }

            // Optional: Send notification to user
            // $package->user->notify(new \App\Notifications\IspPackageExpired($package));
        }

        $this->info('Deprovisioning complete.');
        return 0;
    }
}
