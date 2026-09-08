<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;

class SeoController extends Controller
{
    public function sitemap()
    {
        $products = Product::query()
            ->whereIn('status', ['in-stock', 'prebook'])
            ->select('id', 'updated_at')
            ->orderBy('id')
            ->get();

        $categories = Category::query()
            ->select('slug', 'name', 'updated_at')
            ->whereNotNull('slug')
            ->where('slug', '!=', '')
            ->get();

        return response()->json([
            'products' => $products,
            'categories' => $categories,
        ]);
    }

    public function categoryBySlug(string $slug)
    {
        $category = Category::query()
            ->select('id', 'name', 'slug', 'updated_at')
            ->where('slug', $slug)
            ->first();

        if (!$category) {
            return response()->json(['message' => 'Category not found'], 404);
        }

        return response()->json($category);
    }
}
