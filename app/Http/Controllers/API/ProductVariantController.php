<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ProductVariant;
use Illuminate\Validation\Rule;
class ProductVariantController extends Controller
{
 /**
     * GET /api/product-variants
     * Optional filters: product_id, attribute, value
     * Supports pagination: ?page=1&per_page=20
     */
    public function index(Request $request)
    {
        $query = ProductVariant::query()->with('product:id,title');

        if ($request->filled('product_id')) {
            $query->where('product_id', $request->integer('product_id'));
        }
        if ($request->filled('attribute')) {
            $query->where('attribute', $request->get('attribute'));
        }
        if ($request->filled('value')) {
            $query->where('value', $request->get('value'));
        }

        $perPage = (int) $request->get('per_page', 20);
        $variants = $query->orderBy('id', 'desc')->paginate($perPage);

        return response()->json([
            'data' => $variants->items(),
            'meta' => [
                'current_page' => $variants->currentPage(),
                'per_page'     => $variants->perPage(),
                'total'        => $variants->total(),
                'last_page'    => $variants->lastPage(),
            ],
        ]);
    }

    /**
     * POST /api/product-variants
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'attribute'  => ['required', 'string', 'max:50'],
            'value'      => ['required', 'string', 'max:100'],
            'price'      => ['nullable', 'integer', 'min:0'],
        ]);

        // Enforce unique(product_id, attribute, value)
        $exists = ProductVariant::where('product_id', $validated['product_id'])
            ->where('attribute', $validated['attribute'])
            ->where('value', $validated['value'])
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'Variant already exists for this product with the same attribute and value.'
            ], 422);
        }

        $variant = ProductVariant::create($validated);

        return response()->json([
            'data' => $variant->load('product:id,title'),
            'message' => 'Variant created successfully.',
        ], 201);
    }

    /**
     * GET /api/product-variants/{id}
     */
    public function show(ProductVariant $productVariant)
    {
        return response()->json([
            'data' => $productVariant->load('product:id,title'),
        ]);
    }

    /**
     * PUT/PATCH /api/product-variants/{id}
     */
    public function update(Request $request, ProductVariant $productVariant)
    {
        $validated = $request->validate([
            'product_id' => ['sometimes', 'integer', 'exists:products,id'],
            'attribute'  => ['sometimes', 'string', 'max:50'],
            'value'      => ['sometimes', 'string', 'max:100'],
            'price'      => ['nullable', 'integer', 'min:0'],
        ]);

        // If any of (product_id, attribute, value) change, re-check uniqueness
        $newProductId = $validated['product_id'] ?? $productVariant->product_id;
        $newAttribute = $validated['attribute'] ?? $productVariant->attribute;
        $newValue     = $validated['value'] ?? $productVariant->value;

        $exists = ProductVariant::where('product_id', $newProductId)
            ->where('attribute', $newAttribute)
            ->where('value', $newValue)
            ->where('id', '!=', $productVariant->id)
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'Variant already exists for this product with the same attribute and value.'
            ], 422);
        }

        $productVariant->update($validated);

        return response()->json([
            'data' => $productVariant->fresh()->load('product:id,title'),
            'message' => 'Variant updated successfully.',
        ]);
    }

    /**
     * DELETE /api/product-variants/{id}
     */
    public function destroy(ProductVariant $productVariant)
    {
        $productVariant->delete();

        return response()->json([
            'message' => 'Variant deleted successfully.',
        ], 204);
    }
}
