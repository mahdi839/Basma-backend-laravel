<?php

namespace App\Http\Controllers;

use App\Models\ProductStock;
use Illuminate\Http\Request;

class ProductStockController extends Controller
{
    public function index()
    {
        $stocks = ProductStock::with('product:id,title')->get();
        return response()->json($stocks);
    }
    // Add purchase stock
    public function store(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'product_variant_id' => 'sometimes|nullable|exists:product_variants,id',
            'purchase_price' => 'required|numeric',
            'stock' => 'required|integer|min:1',
        ]);

        $stock = ProductStock::updateOrCreate(
        ['product_id' => $request->product_id],
        [
            'product_variant_id' => $request->product_variant_id,
            'purchase_price' => $request->purchase_price,
            'stock' => $request->stock,
        ]
    );

        return response()->json($stock);
    }

    // Get stock for a product
    public function show($productId)
    {
        $stock = ProductStock::where('product_id', $productId)->first();
        return response()->json($stock);
    }

      public function update(Request $request, $id)
    {
        $stock = ProductStock::find($id);
        if (!$stock) {
            return response()->json(['error' => 'Stock not found'], 404);
        }

        // Validate only the fields that are present
        $validated = $request->validate([
            // If you allow changing the product_id, keep it unique among product_stocks
            'product_id'      => 'sometimes|exists:products,id|unique:product_stocks,product_id,' . $id,
            'product_variant_id' => 'sometimes|nullable|exists:product_variants,id',
            'purchase_price'  => 'sometimes|numeric',
            'stock'           => 'sometimes|integer|min:0',
        ]);

        $stock->fill($validated)->save();

        // Return with related product title for convenience
        return response()->json($stock->load('product:id,title'));
    }

    // Decrement stock when order completed
    public function decrementStock($productId, $quantity, $sellPrice)
    {
        $stock = ProductStock::where('product_id', $productId)->first();

        if (!$stock || $stock->stock < $quantity) {
            return response()->json(['error' => 'Insufficient stock'], 400);
        }

        $stock->decrement('stock', $quantity);

        // Calculate profit
        $profit = ($sellPrice - $stock->purchase_price) * $quantity;

        return response()->json([
            'message' => 'Stock decremented',
            'current_stock' => $stock->stock,
            'profit' => $profit
        ]);
    }

    public function destroy($id)
    {
        $stock = ProductStock::find($id);
        if (!$stock) {
            return response()->json(['error' => 'Stock not found'], 404);
        }

        $stock->delete();
        return response()->json(['message' => 'Stock deleted']);
    }
}
