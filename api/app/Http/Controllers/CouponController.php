<?php

namespace App\Http\Controllers;

use App\Models\Coupon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CouponController extends Controller
{
    /**
     * Admin: List all coupons.
     */
    public function index()
    {
        return response()->json(Coupon::orderBy('created_at', 'desc')->get());
    }

    /**
     * Admin: Store a new coupon.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'code'       => 'required|string|unique:coupons,code',
            'type'       => ['required', Rule::in(['percentage', 'fixed'])],
            'value'      => 'required|numeric|min:0.01',
            'min_amount' => 'nullable|numeric|min:0',
            'max_uses'   => 'nullable|integer|min:1',
            'expires_at' => 'nullable|date|after:now',
            'is_active'  => 'boolean',
        ]);

        $coupon = Coupon::create($validated);

        \App\Models\AdminLog::log(
            'create_coupon',
            "Created coupon: {$coupon->code}",
            null,
            $validated
        );

        return response()->json($coupon, 201);
    }

    /**
     * Admin: Delete a coupon.
     */
    public function destroy($id)
    {
        $coupon = Coupon::findOrFail($id);
        $couponCode = $coupon->code;
        $coupon->delete();

        \App\Models\AdminLog::log(
            'delete_coupon',
            "Deleted coupon: {$couponCode}",
            null,
            ['id' => $id]
        );

        return response()->json(['message' => 'Coupon deleted successfully']);
    }

    /**
     * Admin: Toggle coupon active status.
     */
    public function toggle($id)
    {
        $coupon = Coupon::findOrFail($id);
        $coupon->is_active = !$coupon->is_active;
        $coupon->save();

        \App\Models\AdminLog::log(
            'toggle_coupon',
            "Toggled active status for coupon: {$coupon->code} (Now: " . ($coupon->is_active ? 'Active' : 'Inactive') . ")",
            null,
            ['id' => $id, 'is_active' => $coupon->is_active]
        );

        return response()->json($coupon);
    }

    /**
     * Client: Validate a coupon code.
     */
    public function validateCoupon(Request $request)
    {
        $request->validate([
            'code'   => 'required|string',
            'amount' => 'required|numeric|min:0',
        ]);

        $coupon = Coupon::where('code', $request->code)->first();

        if (!$coupon) {
            return response()->json(['message' => 'Invalid coupon code.'], 422);
        }

        if (!$coupon->isValid($request->amount)) {
            return response()->json(['message' => 'Coupon is not valid for this order.'], 422);
        }

        return response()->json([
            'valid'    => true,
            'code'     => $coupon->code,
            'type'     => $coupon->type,
            'value'    => $coupon->value,
            'discount' => $coupon->calculateDiscount($request->amount),
        ]);
    }
}
