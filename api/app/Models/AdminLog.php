<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminLog extends Model
{
    protected $fillable = [
        'admin_id',
        'action',
        'target_user_id',
        'details',
        'ip_address',
        'user_agent',
        'geo_country',
        'geo_city',
    ];

    /**
     * Centralized logging helper.
     */
    public static function log($action, $details = null, $targetUserId = null)
    {
        $adminId = auth()->id();
        $request = request();
        
        $ip = $request->ip();
        $userAgent = $request->userAgent();
        
        $log = new self([
            'admin_id' => $adminId,
            'action' => $action,
            'target_user_id' => $targetUserId,
            'details' => $details,
            'ip_address' => $ip,
            'user_agent' => $userAgent,
        ]);

        // Simple geolocation lookup for public IPs
        if ($ip && !in_array($ip, ['127.0.0.1', '::1'])) {
            try {
                $response = @file_get_contents("http://ip-api.com/json/{$ip}?fields=status,message,country,city");
                if ($response) {
                    $geo = json_decode($response, true);
                    if (isset($geo['status']) && $geo['status'] === 'success') {
                        $log->geo_country = $geo['country'] ?? null;
                        $log->geo_city = $geo['city'] ?? null;
                    }
                }
            } catch (\Exception $e) {
                // Silently fail geo-lookup
            }
        }

        $log->save();
        return $log;
    }

    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function targetUser()
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }
}
