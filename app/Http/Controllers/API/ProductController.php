<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\Size;
use App\Traits\ClearsHomeCache;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ProductController extends Controller
{

    use ClearsHomeCache;
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $slug = $request->query('slug', '');
        $search = $request->search;
        $status = $request->query('status', '');
        $allProducts = Product::with(['images', 'sizes', 'faqs', 'category', 'specifications'])
            ->when($slug, function ($q) use ($slug) {
                $q->whereHas('category', function ($query) use ($slug) {
                    $query->where('slug', $slug);
                });
            })
            ->when($search && strlen($search) >= 3, function ($q) use ($search) {
                $q->where(function ($query) use ($search) {
                    $query->where('title', 'LIKE', "%{$search}%")
                        ->orWhere('sku', 'LIKE', "%{$search}%");
                });
            })

            ->when($status, function ($q) use ($status) {
                $q->where('status', $status);
            })
            ->latest()
            ->paginate(20);

        return response()->json([
            'message' => 'success',
            'data'    => $allProducts,
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title'             => 'required',
            'short_description' => 'required',
            'video_url'         => 'nullable',
            'description'       => 'nullable',
            'discount'          => 'nullable',
            'status'            => 'required|in:in-stock,sold,prebook',

            // images
            'image'   => 'required|array',
            'image.*' => 'image|max:3072',

            // categories
            'categories'               => 'nullable|array',
            'categories.*.category_id' => 'required|exists:categories,id',

            // base price (optional if sizes have prices)
            'price' => 'nullable|integer|min:0',

            // COLORS with color codes and images
            'colors'              => 'nullable|array',
            'colors.*.code'       => 'nullable|string',  // hex code like #FF5733
            'colors.*.image' => 'required|image|max:3072',
            'colors.*.name' => 'nullable|string|max:50',
            // SIZES with prices and stock
            'sizes'              => 'nullable|array',
            'sizes.*.size_id'    => 'required|exists:sizes,id',
            'sizes.*.price'      => 'nullable|integer|min:0',
            'sizes.*.stock'      => 'nullable|integer|min:0',

            // FAQs
            'question' => 'nullable|array',
            'answer'   => 'nullable|array',

            // SPECIFICATIONS
            'specifications'         => 'nullable|array',
            'specifications.*.key'   => 'required|string',
            'specifications.*.value' => 'required|string',
        ]);

        // Handle colors with images
        $colorsData = [];
        if (!empty($validated['colors'])) {
            foreach ($validated['colors'] as $index => $color) {
                $colorItem = [
                    'id' => $index + 1,
                    'code' => $color['code'],
                    'name' => $color['name'] ?? null,
                ];

                // Handle color image if provided
                if (isset($color['image'])) {
                    $imageName   = $color['image']->hashName();
                    $destination = public_path('uploads/color_images');

                    // Create directory if not exists
                    if (!file_exists($destination)) {
                        mkdir($destination, 0755, true);
                    }

                    $color['image']->move($destination, $imageName);
                    $colorItem['image'] = 'uploads/color_images/' . $imageName;
                }

                $colorsData[] = $colorItem;
            }
        }

        // Create product
        $product = Product::create([
            'title'             => $validated['title'],
            'short_description' => $validated['short_description'],
            'price'             => $validated['price'] ?? null,
            'description'       => $validated['description'] ?? null,
            'video_url'         => $validated['video_url'] ?? null,
            'discount'          => $validated['discount'] ?? null,
            'status'            => $validated['status'],
            'colors'            => !empty($colorsData) ? $colorsData : null,
        ]);

        // Product images
        foreach ($validated['image'] as $index=> $image) {
            $imageName   = $image->hashName();
            $destination = public_path('uploads/product_photos');
            $image->move($destination, $imageName);
            $product->images()->create([
                'image' => 'uploads/product_photos/' . $imageName,
                'position'=>$index
                ]);
        }

        // Categories
        if (!empty($validated['categories'])) {
            $product->category()->attach(
                collect($validated['categories'])->pluck('category_id')->all()
            );
        }

        // SIZES with pivot data (price, stock)
        if (!empty($validated['sizes'])) {
            foreach ($validated['sizes'] as $size) {
                $product->sizes()->attach($size['size_id'], [
                    'price' => $size['price'],
                    'stock' => $size['stock'],
                ]);
            }
        }

        // FAQs
        if (isset($validated['question'])) {
            foreach ($validated['question'] as $key => $ques) {
                $product->faqs()->create([
                    'question' => $ques,
                    'answer'   => $validated['answer'][$key] ?? null,
                ]);
            }
        }

        // SPECIFICATIONS
        if (!empty($validated['specifications'])) {
            foreach ($validated['specifications'] as $index => $spec) {
                $product->specifications()->create([
                    'key'   => $spec['key'],
                    'value' => $spec['value'],
                    'order' => $index,
                ]);
            }
        }

        $this->clearHomeCategoryCach();
        return response()->json([
            'message' => 'product created successfully',
            'data'    => $product->load('images', 'sizes', 'faqs', 'category', 'specifications'),
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $cacheKey = "product:{$id}";

        $product = Cache::remember($cacheKey, now()->addMinutes(30), function () use ($id) {
            return Product::with(['images', 'sizes', 'faqs', 'category', 'specifications'])
                ->findOrFail($id);
        });

        return response()->json([
            'message' => 'success',
            'data'    => $product,
        ], 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $validated = $request->validate([
            'title'             => 'required',
            'short_description' => 'required',
            'video_url'         => 'nullable',
            'description'       => 'nullable',
            'discount'          => 'nullable',
            'status'            => 'required|in:in-stock,sold,prebook',

            // images
            'image'   => 'nullable|array',
            'image.*' => 'image|max:3072',

            // images to delete
            'deleted_images'   => 'nullable|array',
            'deleted_images.*' => 'exists:product_images,id',

            // categories
            'categories'               => 'nullable|array',
            'categories.*.category_id' => 'required|exists:categories,id',

            // base price
            'price' => 'nullable|integer|min:0',

            // COLORS - image is optional if existing_image is provided
            'colors'              => 'nullable|array',
            'colors.*.id'         => 'nullable|integer',
            'colors.*.code'       => 'nullable|string',
            'colors.*.name'       => 'nullable|string|max:50',
            'colors.*.image'      => 'nullable|image|max:3072',
            'colors.*.existing_image' => 'nullable|string',
            
            // SIZES
            'sizes'              => 'nullable|array',
            'sizes.*.size_id'    => 'required|exists:sizes,id',
            'sizes.*.price'      => 'nullable|integer|min:0',
            'sizes.*.stock'      => 'nullable|integer|min:0',

            // FAQs
            'faqs'            => 'nullable|array',
            'faqs.*.question' => 'required',
            'faqs.*.answer'   => 'required',

            // SPECIFICATIONS
            'specifications'         => 'nullable|array',
            'specifications.*.key'   => 'required|string',
            'specifications.*.value' => 'required|string',
        ]);

        $product = Product::with(['images', 'sizes', 'faqs', 'specifications'])->findOrFail($id);

        // Handle colors with images
        $colorsData = [];

        if ($request->has('colors')) {

            // get max existing id to continue increment
            $existingMaxId = collect($product->colors ?? [])->max('id') ?? 0;

            foreach ($validated['colors'] as $index => $color) {

                // If color has existing ID → keep it
                if (isset($color['id'])) {
                    $newId = $color['id'];
                } else {
                    // NEW color → assign new incremental ID
                    $existingMaxId++;
                    $newId = $existingMaxId;
                }

                $colorItem = [
                    'id'   => $newId,
                    'code' => $color['code'] ?? '#000000',
                    'name' => $color['name'] ?? null,
                ];

                // New image uploaded
                if (!empty($color['image'])) {
                    $imageName = $color['image']->hashName();
                    $destination = public_path('uploads/color_images');
                    if (!file_exists($destination)) mkdir($destination, 0755, true);

                    $color['image']->move($destination, $imageName);
                    $colorItem['image'] = 'uploads/color_images/' . $imageName;
                    
                    // Delete old color image if exists
                    if (!empty($color['existing_image'])) {
                        $oldPath = public_path($color['existing_image']);
                        if (file_exists($oldPath)) {
                            @unlink($oldPath);
                        }
                    }
                } elseif (!empty($color['existing_image'])) {
                    // Keep existing image
                    $colorItem['image'] = $color['existing_image'];
                }

                $colorsData[] = $colorItem;
            }
        }

        // Update core fields
        $product->update([
            'title'             => $validated['title'],
            'short_description' => $validated['short_description'],
            'video_url'         => $validated['video_url'] ?? null,
            'description'       => $validated['description'] ?? null,
            'discount'          => $validated['discount'] ?? null,
            'status'            => $validated['status'],
            'price'             => $validated['price'] ?? null,
            'colors'            => !empty($colorsData) ? $colorsData : null,
        ]);

        // Image deletions
        if (!empty($validated['deleted_images'])) {
            foreach ($validated['deleted_images'] as $imageId) {
                $image = $product->images()->find($imageId);
                if ($image) {
                    $path = public_path($image->image);
                    if (file_exists($path)) {
                        @unlink($path);
                    }
                    $image->delete();
                }
            }
        }

        // New images
        $maxPosition = $product->images()->max('position')??-1;
        if ($request->hasFile('image')) {
            foreach ($request->file('image') as $image) {
                $maxPosition++;
                $imageName   = $image->hashName();
                $destination = public_path('uploads/product_photos');
                $image->move($destination, $imageName);
                $product->images()->create([
                    'image' => 'uploads/product_photos/' . $imageName,
                    'position'=>$maxPosition
                    ]);
            }
        }

        // CATEGORIES
        if (!empty($validated['categories'])) {
            $categoryIds = collect($validated['categories'])->pluck('category_id')->all();
            $product->category()->sync($categoryIds);
        }

        // Always sync sizes, even if array is empty (to allow deletion of all sizes)
        $sizesData = [];
        
        if (!empty($validated['sizes'])) {
            foreach ($validated['sizes'] as $size) {
                // Only add if size_id is valid (not empty string)
                if (!empty($size['size_id'])) {
                    $sizesData[$size['size_id']] = [
                        'price' => $size['price'] ?? null,
                        'stock' => $size['stock'] ?? 0,
                    ];
                }
            }
        }
        
        // This will sync the sizes - if $sizesData is empty, it will remove all sizes
        $product->sizes()->sync($sizesData);

        // FAQs
        if ($request->has('faqs')) {
            $product->faqs()->delete();
            if (!empty($validated['faqs'])) {
                foreach ($validated['faqs'] as $faq) {
                    $product->faqs()->create([
                        'question' => $faq['question'],
                        'answer'   => $faq['answer'],
                    ]);
                }
            }
        } else {
            $product->faqs()->delete();
        }

        // SPECIFICATIONS
        if ($request->has('specifications')) {
            $product->specifications()->delete();
            if (!empty($validated['specifications'])) {
                foreach ($validated['specifications'] as $index => $spec) {
                    $product->specifications()->create([
                        'key'   => $spec['key'],
                        'value' => $spec['value'],
                        'order' => $index,
                    ]);
                }
            }
        }

        $this->clearHomeCategoryCach();
        $this->clearRelatedCache($id);
        return response()->json([
            'message' => 'Product updated successfully',
            'data'    => $product->fresh()->load('images', 'sizes', 'faqs', 'category', 'specifications'),
        ]);
    }

    /**
     * Clear related products cache when product is updated/deleted
     */
    protected function clearRelatedCache($productId)
    {
        Cache::forget("product:{$productId}");

        // Clear all pages of related products cache
        $product = Product::with('category:id')->find($productId);

        if ($product) {
            $categoryIds = $product->category->pluck('id');

            // Get all products in same categories
            $relatedProductIds = Product::whereHas('category', function ($q) use ($categoryIds) {
                $q->whereIn('categories.id', $categoryIds);
            })
                ->pluck('id');

            // Clear cache for each product and all their pages
            foreach ($relatedProductIds as $relatedId) {
                // Clear up to 10 pages of cache (adjust as needed)
                for ($page = 1; $page <= 10; $page++) {
                    Cache::forget("related_products:{$relatedId}:page:{$page}");
                }
            }
        }
    }
    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $product = Product::with(['images', 'sizes', 'faqs', 'category', 'specifications'])->findOrFail($id);

        // Delete image files + rows
        foreach ($product->images as $image) {
            $path = public_path($image->image);
            if (file_exists($path)) {
                @unlink($path);
            }
            $image->delete();
        }

        // Delete color images if any
        if ($product->colors) {
            foreach ($product->colors as $color) {
                if (isset($color['image'])) {
                    $path = public_path($color['image']);
                    if (file_exists($path)) {
                        @unlink($path);
                    }
                }
            }
        }

        // Detach/delete relations
        $product->faqs()->delete();
        $product->specifications()->delete();
        $product->sizes()->detach();
        $product->category()->detach();

        // Delete product
        $product->delete();
        $this->clearHomeCategoryCach();
        $this->clearRelatedCache($id);
        return response()->json([
            'message' => 'Product deleted successfully',
        ], 200);
    }

    public function category_products(Request $request, $id)
    {
        $page = $request->query('page', 1);
        $perPage = 20; // Products per page

        // Cache key includes page number
        $cacheKey = "related_products:{$id}:page:{$page}";

        $result = Cache::remember($cacheKey, now()->addHours(2), function () use ($id, $page, $perPage) {
            $product = Product::select('id')->with('category:id')->findOrFail($id);

            $categoryIds = $product->category->pluck('id');

            $query = Product::select([
                'id',
                'title',
                'colors',
                'short_description',
                'price',
                'discount',
                'status',
                'created_at'
            ])
                ->withCount(['sizes'])
                ->whereHas('category', function ($q) use ($categoryIds) {
                    $q->whereIn('categories.id', $categoryIds);
                })
                ->with([
                    'images' => function ($q) {
                        $q->select('id', 'product_id', 'image')
                            ->orderBy('id')
                            ->limit(1);
                    }
                ])
                ->where('id', '!=', $product->id)
                ->whereIn('status', ['in-stock', 'prebook'])
                ->orderBy('created_at', 'desc');

            // Paginate
            $paginated = $query->paginate($perPage, ['*'], 'page', $page);

            // Transform data
            $products = $products = $paginated->getCollection()->map(function ($item) {
                return [
                    'id' => $item->id,
                    'title' => $item->title,
                    'short_description' => $item->short_description,
                    'price' => $item->price,
                    'discount' => $item->discount,
                    'status' => $item->status,
                    'sizes_count' => $item->sizes_count,
                    'colors' => $item->colors,
                    'image' => $item->images->first()?->image,
                    'created_at' => $item->created_at,
                ];
            });


            return [
                'data' => $products,
                'pagination' => [
                    'current_page' => $paginated->currentPage(),
                    'last_page' => $paginated->lastPage(),
                    'per_page' => $paginated->perPage(),
                    'total' => $paginated->total(),
                    'has_more' => $paginated->hasMorePages(),
                ]
            ];
        });

        return response()->json($result);
    }

    public function categorySlugProducts(Request $request, $slug)
    {
        $page = $request->query('page', 1);
        $perPage = 20;

        // Cache key includes slug and page number
        $cacheKey = "category_slug_products:{$slug}:page:{$page}";

        $result = Cache::remember($cacheKey, now()->addHours(2), function () use ($slug, $page, $perPage) {
            // Find category by slug
            $category = Category::where('slug', $slug)->firstOrFail();
            $query = Product::select([
                'id',
                'title',
                'colors',
                'short_description',
                'price',
                'discount',
                'status',
                'created_at'
            ])
                ->withCount(['sizes'])
                ->whereHas('category', function ($q) use ($category) {
                    $q->where('categories.id', $category->id);
                })
                ->with([
                    'images' => function ($q) {
                        $q->select('id', 'product_id', 'image')
                            ->orderBy('id')
                            ->limit(1);
                    }
                ])
                ->whereIn('status', ['in-stock', 'prebook'])
                ->orderBy('created_at', 'desc');

            // Paginate
            $paginated = $query->paginate($perPage, ['*'], 'page', $page);

            // Transform data
            $products = $paginated->getCollection()->map(function ($item) {
                return [
                    'id' => $item->id,
                    'title' => $item->title,
                    'short_description' => $item->short_description,
                    'price' => $item->price,
                    'discount' => $item->discount,
                    'status' => $item->status,
                    'sizes_count' => $item->sizes_count,
                    'colors' => $item->colors,
                    'image' => $item->images->first()?->image,
                    'created_at' => $item->created_at,
                ];
            });
            return [
                'data' => $products,
                'pagination' => [
                    'current_page' => $paginated->currentPage(),
                    'last_page' => $paginated->lastPage(),
                    'per_page' => $paginated->perPage(),
                    'total' => $paginated->total(),
                    'has_more' => $paginated->hasMorePages(),
                ]
            ];
        });

        return response()->json($result);
    }


    public function searchProducts(Request $request)
    {
        $search = $request->query('q', '');

        if (strlen($search) < 3) {
            return response()->json([
                'message' => 'Type at least 3 characters',
                'data' => []
            ], 200);
        }

        $products = Product::with(['images', 'sizes'])
            ->where('title', 'LIKE', "%{$search}%")
            ->limit(10)
            ->get();

        return response()->json([
            'message' => 'success',
            'data' => $products
        ], 200);
    }

    public function shopProducts(Request $request)
    {
        $page = $request->query('page', 1);
        $perPage = 9;
        $categories = $request->query('categories', []);
        $sizes = $request->query('sizes', []);
        $minPrice = $request->query('min_price');
        $maxPrice = $request->query('max_price');
        $search = $request->query('search', '');
        $status = $request->query('status', '');

        // Convert categories and sizes to arrays if they're strings
        if (is_string($categories)) {
            $categories = explode(',', $categories);
        }
        if (is_string($sizes)) {
            $sizes = explode(',', $sizes);
        }

        $query = Product::with(['images', 'sizes', 'category'])
            ->when(!empty($categories), function ($q) use ($categories) {
                $q->whereHas('category', function ($query) use ($categories) {
                    $query->whereIn('categories.id', $categories);
                });
            })
            ->when(!empty($sizes), function ($q) use ($sizes) {
                $q->whereHas('sizes', function ($query) use ($sizes) {
                    $query->whereIn('sizes.id', $sizes);
                });
            })
            ->when($minPrice !== null, function ($q) use ($minPrice) {
                $q->where(function ($query) use ($minPrice) {
                    $query->where('price', '>=', $minPrice)
                        ->orWhereHas('sizes', function ($q) use ($minPrice) {
                            $q->where('product_sizes.price', '>=', $minPrice);
                        });
                });
            })
            ->when($maxPrice !== null, function ($q) use ($maxPrice) {
                $q->where(function ($query) use ($maxPrice) {
                    $query->where('price', '<=', $maxPrice)
                        ->orWhereHas('sizes', function ($q) use ($maxPrice) {
                            $q->where('product_sizes.price', '<=', $maxPrice);
                        });
                });
            })
            ->when($search && strlen($search) >= 3, function ($q) use ($search) {
                $q->where('title', 'LIKE', "%{$search}%");
            })
            ->when($status, function ($q) use ($status) {
                $q->where('status', $status);
            })
            ->whereIn('status', ['in-stock', 'prebook']) // Only show in-stock products in shop
            ->orderBy('created_at', 'desc');

        $products = $query->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'message' => 'success',
            'data' => $products->items(),
            'pagination' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
                'has_more' => $products->hasMorePages(),
            ]
        ], 200);
    }

    /**
     * Get filter options for shop
     */
    public function shopFilters()
    {
        $categories = Category::select('id', 'name', 'slug')->get();
        $sizes = Size::select('id', 'size')->get();

        $priceRange = Product::selectRaw('MIN(COALESCE(product_sizes.price, products.price)) as min_price, MAX(COALESCE(product_sizes.price, products.price)) as max_price')
            ->leftJoin('product_sizes', 'products.id', '=', 'product_sizes.product_id')
            ->whereIn('status', ['in-stock', 'prebook'])
            ->first();

        return response()->json([
            'message' => 'success',
            'data' => [
                'categories' => $categories,
                'sizes' => $sizes,
                'price_range' => [
                    'min' => (int)($priceRange->min_price ?? 0),
                    'max' => (int)($priceRange->max_price ?? 1000),
                ]
            ]
        ], 200);
    }
}