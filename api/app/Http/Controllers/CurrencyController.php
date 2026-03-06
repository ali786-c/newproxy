<?php

namespace App\Http\Controllers;

use App\Models\SupportedCurrency;
use Illuminate\Http\Request;

class CurrencyController extends Controller
{
    public function index()
    {
        return response()->json(SupportedCurrency::where('is_active', true)->get());
    }

    public function adminIndex()
    {
        return response()->json(SupportedCurrency::orderBy('code', 'asc')->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code'          => 'required|string|unique:supported_currencies,code|max:10',
            'name'          => 'required|string|max:100',
            'symbol'        => 'required|string|max:10',
            'exchange_rate' => 'required|numeric|min:0',
            'is_active'     => 'boolean',
        ]);

        $currency = SupportedCurrency::create($validated);

        \App\Models\AdminLog::log(
            'create_currency',
            "Added supported currency: {$currency->code} ({$currency->name})",
            null,
            $validated
        );

        return response()->json($currency, 201);
    }

    public function update(Request $request, $id)
    {
        $currency = SupportedCurrency::findOrFail($id);
        $validated = $request->validate([
            'code'          => 'sometimes|required|string|max:10|unique:supported_currencies,code,' . $id,
            'name'          => 'sometimes|required|string|max:100',
            'symbol'        => 'sometimes|required|string|max:10',
            'exchange_rate' => 'sometimes|required|numeric|min:0',
            'is_active'     => 'sometimes|boolean',
        ]);

        $currency->update($validated);

        \App\Models\AdminLog::log(
            'update_currency',
            "Updated currency: {$currency->code}",
            null,
            $validated
        );

        return response()->json($currency);
    }

    public function toggle($id)
    {
        $currency = SupportedCurrency::findOrFail($id);
        $currency->is_active = !$currency->is_active;
        $currency->save();

        \App\Models\AdminLog::log(
            'toggle_currency',
            "Toggled active status for currency: {$currency->code} (Now: " . ($currency->is_active ? 'Active' : 'Inactive') . ")",
            null,
            ['id' => $id, 'is_active' => $currency->is_active]
        );

        return response()->json($currency);
    }
}
