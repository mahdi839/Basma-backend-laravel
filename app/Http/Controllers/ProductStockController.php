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
            'purchase_price' => 'required|numeric',
            'stock' => 'required|integer|min:1',
        ]);

        $stock = ProductStock::updateOrCreate(
            ['product_id' => $request->product_id],
            ['purchase_price' => $request->purchase_price, 'stock' => $request->stock]
        );

        return response()->json($stock);
    }

    // Get stock for a product
    public function show($productId)
    {
        $stock = ProductStock::where('product_id', $productId)->first();
        return response()->json($stock);
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
}
