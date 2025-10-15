<?php

namespace App\Http\Controllers\API;
use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;


class ProductController extends Controller
{
  /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $slug = $request->query('slug','');

        $allProducts = Product::with(['images', 'variants', 'faqs', 'category'])
            ->when($slug, function ($q) use ($slug) {
                $q->whereHas('category', function ($query) use ($slug) {
                    $query->where('slug', $slug);
                });
            })
            ->get();

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
            'title'       => 'required',
            'sub_title'   => 'required',
            'video_url'   => 'nullable',
            'description' => 'nullable',
            'discount'    => 'nullable',

            // images
            'image'   => 'required|array',
            'image.*' => 'image|mimes:jpg,jpeg,png',

            // categories
            'categories'                 => 'nullable|array',
            'categories.*.category_id'   => 'required|exists:categories,id',

            // single base price if there are no variant prices
            'price'      => 'required_without:variants|nullable|numeric|min:0',

            // VARIANTS (new)
            'variants'                 => 'nullable|array',
            'variants.*.attribute'     => 'required|string|max:50',
            'variants.*.value'         => 'required|string|max:100',
            'variants.*.price'         => 'nullable|integer|min:0',

            // FAQs
            'question' => 'nullable|array',
            'answer'   => 'nullable|array',
        ]);

        // create product
        $product = Product::create([
            'title'       => $validated['title'],
            'sub_title'   => $validated['sub_title'],
            'price'       => $validated['price'] ?? null,   // your existing single price column
            'description' => $validated['description'] ?? null,
            'video_url'   => $validated['video_url'] ?? null,
            'discount'    => $validated['discount'] ?? null,
        ]);

        // images
        foreach ($validated['image'] as $image) {
            $imageName   = $image->hashName();
            $destination = public_path('uploads/product_photos');
            $image->move($destination, $imageName);
            $product->images()->create(['image' => 'uploads/product_photos/'.$imageName]);
        }

        // categories
        if (!empty($validated['categories'])) {
            $product->category()->attach(
                collect($validated['categories'])->pluck('category_id')->all()
            );
        }

        // VARIANTS (create many)
        if (!empty($validated['variants'])) {
            $rows = collect($validated['variants'])
                ->map(fn($v) => [
                    'attribute'  => $v['attribute'],
                    'value'      => $v['value'],
                    'price'      => $v['price'] ?? null,
                ])->all();

            $product->variants()->createMany($rows);
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

        return response()->json([
            'message' => 'product created successfully',
            'data'    => $product->load('images', 'variants', 'faqs', 'category'),
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $product = Product::with(['images', 'variants', 'faqs', 'category'])->findOrFail($id);

        return response()->json([
            'message' => 'success',
            'data'    => $product,
        ], 200);
    }

    /**
     * Update the specified resource in storage.
     *
     * Strategy: replace variants if 'variants' is provided.
     * (You also have standalone ProductVariant CRUD; both paths remain valid.)
     */
    public function update(Request $request, string $id)
    {
        
        $validated = $request->validate([
            'title'       => 'required',
            'sub_title'   => 'required',
            'video_url'   => 'nullable',
            'description' => 'nullable',
            'discount'    => 'nullable',

            // images
            'image'   => 'nullable|array',
            'image.*' => 'image|mimes:jpg,jpeg,png',

            // images to delete
            'deleted_images'   => 'nullable|array',
            'deleted_images.*' => 'exists:product_images,id',

            // categories
            'categories'               => 'nullable|array',
            'categories.*.category_id' => 'required|exists:categories,id',

            // base price (still allowed)
            'price' => 'nullable|numeric|min:0',

            // VARIANTS (replace-on-update if provided)
            'variants'                 => 'nullable|array',
            'variants.*.attribute'     => 'required|string|max:50',
            'variants.*.value'         => 'required|string|max:100',
            'variants.*.price'         => 'nullable|integer|min:0',

            // FAQs
            'faqs'            => 'nullable|array',
            'faqs.*.question' => 'required',
            'faqs.*.answer'   => 'required',
        ]);

        $product = Product::with(['images', 'variants', 'faqs'])->findOrFail($id);

        // update core fields
        $product->update([
            'title'       => $validated['title'],
            'sub_title'   => $validated['sub_title'],
            'video_url'   => $validated['video_url'] ?? null,
            'description' => $validated['description'] ?? null,
            'discount'    => $validated['discount'] ?? null,
            'price'       => $request->price ?? null,
        ]);
        
        // image deletions
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

        // new images
        if ($request->hasFile('image')) {
            foreach ($request->file('image') as $image) {
                $imageName   = $image->hashName();
                $destination = public_path('uploads/product_photos');
                $image->move($destination, $imageName);
                $product->images()->create(['image' => 'uploads/product_photos/'.$imageName]);
            }
        }

        // CATEGORIES
        if (!empty($validated['categories'])) {
            $categoryIds = collect($validated['categories'])->pluck('category_id')->all();
            $product->category()->sync($categoryIds);
        }

        // VARIANTS — replace all if provided
        if ($request->has('variants')) {
            $product->variants()->delete();
            if (!empty($validated['variants'])) {
                $rows = collect($validated['variants'])
                    ->map(fn($v) => [
                        'attribute'  => $v['attribute'],
                        'value'      => $v['value'],
                        'price'      => $v['price'] ?? null,
                    ])->all();
                $product->variants()->createMany($rows);
            }
        }

        // FAQs
        $product->faqs()->delete();
        if (!empty($validated['faqs'])) {
            foreach ($validated['faqs'] as $faq) {
                $product->faqs()->create([
                    'question' => $faq['question'],
                    'answer'   => $faq['answer'],
                ]);
            }
        }

        return response()->json([
            'message' => 'Product updated successfully',
            'data'    => $product->fresh()->load('images', 'variants', 'faqs', 'category'),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $product = Product::with(['images', 'variants', 'faqs', 'category'])->findOrFail($id);

        // delete image files + rows
        foreach ($product->images as $image) {
            $path = public_path($image->image);
            if (file_exists($path)) {
                @unlink($path);
            }
            $image->delete();
        }

        // detach/delete relations
        $product->faqs()->delete();
        $product->variants()->delete();
        $product->category()->detach();

        // delete product
        $product->delete();

        return response()->json([
            'message' => 'Product deleted successfully',
        ], 200);
    }
}