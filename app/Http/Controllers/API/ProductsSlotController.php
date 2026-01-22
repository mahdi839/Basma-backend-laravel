<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductsSlot;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class ProductsSlotController extends Controller
{
    public function frontEndIndex(Request $request)
    {
        $perPage = 4;
        $page = (int) $request->input('page', 1);

        // Create cache key
        $cacheKey = "page_{$page}";

        // Cache with tags - now you can flush all pages at once
        $result = Cache::tags(['home_categories'])->remember($cacheKey, 3600, function () use ($page, $perPage) {
            // Calculate offset
            $offset = ($page - 1) * $perPage;

            // Get total count efficiently (without loading relationships)
            $total = Category::where('home_category', 1)
                ->whereHas('products', function ($q) {
                    $q->whereIn('status', ['in-stock', 'prebook']);
                })
                ->count();

            // Optimized main query with minimal joins
            $categories = Category::where('home_category', 1)
                ->whereHas('products', function ($q) {
                    $q->whereIn('status', ['in-stock', 'prebook']);
                })
                ->with([
                    // Eager load only first 15 products per category
                    'products' => function ($q) {
                        $q->whereIn('status', ['in-stock', 'prebook'])
                            ->select([
                                'products.id',
                                'products.title',
                                'products.price',
                                'products.colors',
                                'products.discount',
                                'products.status',
                            ])
                            ->orderBy('products.id', 'desc')
                            ->limit(15); // Limit at database level
                    },
                    // Only load first image per product
                    'products.images' => function ($q) {
                        $q->select('id', 'product_id', 'image')
                            ->orderBy('id')
                            ->limit(1); // Only first image
                    },
                    // Banner with images
                    'banner' => function ($q) {
                        $q->select('id', 'category_id', 'link', 'type');
                    },
                    'banner.banner_images' => function ($q) {
                        $q->select('id', 'banner_id', 'path')
                            ->limit(1);
                    }
                ])
                ->select('id', 'name', 'slug', 'priority')
                ->orderBy('priority')
                ->skip($offset)
                ->take($perPage)
                ->get();
            return [
                'data' => $categories,
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'has_more' => ($page * $perPage) < $total
            ];
        });

        return response()->json($result);
    }

    public function index()
    {
        $home_category_products = Category::with('products')->where('home_category', 1)->paginate(10);
        return response()->json($home_category_products);
    }

    public function create()
    {
        $products = Product::select(['id', 'title'])->get();
        return response()->json([
            'data' => [
                'products' => $products,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $request->validate(
            [
                'slot_name' => 'required',
                'priority' => 'required',
                'product_id' => 'required',
            ],
            [
                'slot_name.required' => 'Please provide a slot name.',
                'priority.required' => 'Priority is required.',
                'product_id.required' => 'Product name is required ',
            ]
        );

        $slot = ProductsSlot::create([
            'slot_name' => $request->slot_name,
            'priority' => $request->priority,
        ]);

        if ($request->filled('product_id')) {
            foreach ($request->product_id as $productId) {
                $slot->slotDetails()->create([
                    'product_id' => $productId,
                ]);
            }
        }

        $slot->load('slotDetails');

        // ✅ Return JSON response
        return response()->json([
            'message' => 'Slot created successfully',
            'data' => $slot,
        ], 201);
    }

    public function edit($id)
    {
        $product_slot = ProductsSlot::with('slotDetails.product')->findOrFail($id);

        return response()->json([
            'data' => $product_slot,
        ]);
    }

    public function update(Request $request, $id)
    {

        $request->validate(
            [
                'slot_name' => 'required',
                'priority' => 'required',
                'product_id' => 'required',
            ],
            [
                'slot_name.required' => 'Please provide a slot name.',
                'priority.required' => 'Priority is required.',
                'product_id.required' => 'Product name is required.',
            ]
        );
        $product_slot = ProductsSlot::with('slotDetails')->findOrFail($id);
        DB::transaction(function () use ($product_slot, $request) {

            $product_slot->update([
                'slot_name' => $request->slot_name,
                'priority' => $request->priority,
            ]);

            $product_slot->slotDetails()->delete();
            if ($request->filled('product_id')) {
                foreach ($request->product_id as $productId) {
                    $product_slot->slotDetails()->create([
                        'product_id' => $productId,
                    ]);
                }
            }
        });
        // Reload the updated relationship
        $product_slot->load('slotDetails');

        return response()->json([
            'message' => 'Updated Successfully!',
            'data' => $product_slot,
        ]);
    }

    public function destroy($id)
    {
        $product_slot = ProductsSlot::findOrFail($id);
        $product_slot->slotDetails()->delete();
        $product_slot->delete();

        return response()->json([
            'message' => 'Product Slot Deleted Successfully!',
        ], 200);
    }
}
