<?php

namespace App\Http\Controllers;

use App\Models\SLAConfig;
use App\Models\UptimeRecord;
use App\Models\SLACredit;
use Illuminate\Http\Request;

class SLAController extends Controller
{
    public function index()
    {
        $records = UptimeRecord::orderBy('checked_at', 'desc')->limit(100)->get();
        $configs = SLAConfig::all();
        
        return response()->json([
            'records' => $records,
            'configs' => $configs,
        ]);
    }

    public function getConfigs()
    {
        return response()->json(SLAConfig::all());
    }

    public function storeConfig(Request $request)
    {
        $validated = $request->validate([
            'proxy_type' => 'required|string',
            'guaranteed_uptime' => 'required|numeric',
            'credit_per_percent' => 'required|numeric',
            'measurement_window' => 'string',
            'is_active' => 'boolean',
        ]);

        $config = SLAConfig::updateOrCreate(
            ['proxy_type' => $validated['proxy_type']],
            $validated
        );

        \App\Models\AdminLog::log(
            'update_sla_config',
            "Updated SLA configuration for {$config->proxy_type}",
            null,
            $validated
        );

        return response()->json($config);
    }

    public function getCredits()
    {
        return response()->json(SLACredit::with('user')->orderBy('created_at', 'desc')->get());
    }

    public function approveCredit($id)
    {
        $credit = SLACredit::findOrFail($id);
        $credit->update(['status' => 'approved']);

        \App\Models\AdminLog::log(
            'approve_sla_credit',
            "Approved SLA credit #[{$id}] for user #{$credit->user_id}",
            $credit->user_id,
            ['id' => $id, 'amount' => $credit->amount]
        );

        return response()->json($credit);
    }
}
