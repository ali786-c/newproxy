<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index()
    {
        try {
            $products = Product::where('is_active', true)->get()->map(function($product) {
                return [
                    'id'          => (string) $product->id,
                    'name'        => $product->name,
                    'price'       => (float) $product->price,
                    'price_cents' => (int) ($product->price * 100),
                    'type'        => $product->type,
                    'tagline'     => $product->tagline,
                    'features'         => $product->features ?? [],
                    'volume_discounts' => $product->volume_discounts ?? [],
                    'unit'        => $product->unit,
                    'base_cost'   => (float) $product->base_cost,
                    'markup'      => (float) $product->markup,
                ];
            });
            return response()->json($products);
        } catch (\Exception $e) {
            // FIX P3: Log the detailed error but return a generic message to unauthenticated users.
            \Illuminate\Support\Facades\Log::error('Product Index Error: ' . $e->getMessage());
            return response()->json(['error' => 'An internal error occurred. Please try again later.'], 500);
        }
    }

    public function adminIndex(Request $request)
    {
        return response()->json(Product::latest()->paginate(15));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'             => 'required|string|max:255',
            'type'             => 'required|string|in:rp,dc,mp,isp,dc_ipv6,dc_unmetered',
            'unit'             => 'nullable|string|in:GB,IP,Month',
            // FIX P6: Add max price to prevent accidental extreme values.
            'price'            => 'required|numeric|min:0|max:999999',
            'base_cost'        => 'nullable|numeric|min:0',
            'markup'           => 'nullable|numeric|min:0',
            'evomi_product_id' => 'required|string|max:255|unique:products,evomi_product_id',
            'is_active'        => 'boolean',
            'tagline'          => 'nullable|string|max:255',
            // FIX P4: Limit number of features and length of each.
            'features'         => 'nullable|array|max:50',
            'features.*'       => 'string|max:255',
            // FIX P5: Limit number of discount tiers.
            'volume_discounts' => 'nullable|array|max:20',
            'volume_discounts.*.min_qty' => 'required|integer|min:1',
            'volume_discounts.*.price'   => 'required|numeric|min:0',
        ]);

        $product = Product::create($validated);

        \App\Models\AdminLog::log(
            'create_product',
            "Created product: {$product->name} ({$product->type})",
            null,
            $validated
        );

        return response()->json($product, 201);
    }

    public function update(Request $request, $id)
    {
        $product = Product::findOrFail($id);

        $validated = $request->validate([
            'name'             => 'sometimes|required|string|max:255',
            'type'             => 'sometimes|required|string|in:rp,dc,mp,isp,dc_ipv6,dc_unmetered',
            'unit'             => 'nullable|string|in:GB,IP,Month',
            // FIX P6: Add max price to prevent accidental extreme values.
            'price'            => 'sometimes|required|numeric|min:0|max:999999',
            'base_cost'        => 'nullable|numeric|min:0',
            'markup'           => 'nullable|numeric|min:0',
            'evomi_product_id' => 'sometimes|required|string|max:255|unique:products,evomi_product_id,' . $id,
            'is_active'        => 'sometimes|boolean',
            'tagline'          => 'nullable|string|max:255',
            // FIX P4: Limit number of features and length of each.
            'features'         => 'nullable|array|max:50',
            'features.*'       => 'string|max:255',
            // FIX P5: Limit number of discount tiers.
            'volume_discounts' => 'nullable|array|max:20',
            'volume_discounts.*.min_qty' => 'required|integer|min:1',
            'volume_discounts.*.price'   => 'required|numeric|min:0',
        ]);

        $product->update($validated);

        \App\Models\AdminLog::log(
            'update_product',
            "Updated product: {$product->name}",
            null,
            $validated
        );

        return response()->json($product);
    }

    public function destroy($id)
    {
        $product = Product::findOrFail($id);

        // FIX P1: Prevent deletion if there are active orders referencing this product.
        $activeOrders = \App\Models\Order::where('product_id', $id)
            ->whereIn('status', ['active', 'pending'])
            ->count();

        if ($activeOrders > 0) {
            return response()->json([
                'error' => "Cannot delete product. There are {$activeOrders} active or pending orders using this product. Deactivate it instead."
            ], 422);
        }

        $productName = $product->name;
        $product->delete();

        \App\Models\AdminLog::log(
            'delete_product',
            "Deleted product: {$productName}",
            null,
            ['id' => $id]
        );

        return response()->json(['message' => 'Product deleted']);
    }
}
