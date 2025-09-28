<?php

namespace App\Http\Controllers\API;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Size;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $slug = $request->query('slug','');
        $allProducts =  Product::with(['images','sizes','faqs','category'])
        ->when($slug,function($q)use ($slug){
            $q->whereHas('category',function($query) use ($slug){
                $query->where('slug',$slug);
            });
        })
        ->get();
       
        return response()->json([
            'message'=> 'success',
            'data'=>$allProducts
        ],200);
    }

    /**
     * Store a newly created resource in storage.
     */ 
    public function store(Request $request)
    {

        $validated = $request->validate([
            'title' => 'required',
            'sub_title' => 'required',
            'video_url' => 'nullable',
            'description' => 'nullable',
            'discount' => 'nullable',
            'image' => 'required|array', 
            'image.*' => 'image|mimes:jpg,jpeg,png',
            'sizes' => 'nullable|array',
            'categories'=> 'nullable|array',
            'categories.*.category_id'=>'required|exists:categories,id', 
            'price'=> 'required_without:sizes',
            'sizes.*.size_id' => 'required|exists:sizes,id', 
            'sizes.*.price' => 'required|numeric', 
            'question' => 'nullable|array',
            'answer' => 'nullable|array',
        ]);

         $product = Product::create([
             'title'=>$validated['title'],
             'sub_title'=>$validated['sub_title'],
             'price' => $validated['price'] ?? null,
             'description'=>$validated['description']??null,
             'video_url'=>$validated['video_url']??null,
             'discount'=>$validated['discount']??null,
         ]);

         foreach($validated['image'] as $image){
           $imageName = $image->hashName();
           $destination = public_path('uploads/product_photos');
           $image->move($destination,$imageName);
            $product->images()->create(['image'=>'uploads/product_photos/'.$imageName]);
         }

                // Handle size-based pricing if exists
            if (!empty($validated['sizes'])) {
                foreach ($validated['sizes'] as $size) {
                    $product->sizes()->attach($size['size_id'], [
                        'price' => $size['price']
                    ]);
                }
            }

            // insert categories
            if(!empty($validated['categories'])){
                foreach($validated['categories'] as $category){
                    $product->category()->attach($category['category_id']);
                }
            }


            if (isset($validated['question'])) {
                foreach ($validated['question'] as $key => $ques) {
                    $product->faqs()->create([
                        'question' => $ques,
                        'answer' => $validated['answer'][$key] ?? null,
                    ]);
                }
            }



         return response()->json([
             'message'=> 'product created successfully',
             'data'=>$product->load('images','sizes','faqs','category')
         ]);

    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $product = Product::with(['images','sizes','faqs','category'])->findOrFail($id);
        return response()->json([
            'message' => 'success',
            'data'=>$product
        ],200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $validated = $request->validate([
            'title' => 'required',
            'sub_title' => 'required',
            'video_url' => 'nullable',
            'description' => 'nullable',
            'discount' => 'nullable',
            'image' => 'nullable|array',
            'image.*' => 'image|mimes:jpg,jpeg,png',
            'deleted_images' => 'nullable|array', 
            'deleted_images.*' => 'exists:images,id', 
            'categories'=> 'nullable|array',
            'categories.*.category_id'=>'required|exists:categories,id', 
            'sizes' => 'nullable|array',
            'sizes.*.size_id' => 'required|exists:sizes,id',
            'sizes.*.price' => 'required|numeric',
            'faqs' => 'nullable|array',
            'faqs.*.question' => 'required',
            'faqs.*.answer' => 'required',
        ]);
    
        $product = Product::with(['images', 'sizes', 'faqs'])->findOrFail($id);
    
        // Update basic product info
        $product->update([
            'title' => $validated['title'],
            'sub_title' => $validated['sub_title'],
            'video_url' => $validated['video_url'],
            'description' => $validated['description'],
            'discount' => $validated['discount'],
            'price' => $request->price ?? null, // Handle single price if applicable
        ]);
    
         // Process image deletions using deleted_images
        if (!empty($validated['deleted_images'])) {
            foreach ($validated['deleted_images'] as $imageId) {
                $image = $product->images()->find($imageId);
                if ($image) {
                    if (file_exists(public_path($image->image))) {
                        unlink(public_path($image->image));
                    }
                    $image->delete();
                }
            }
        }
    
        // Process new image uploads
        if ($request->hasFile('image')) {
            foreach ($request->file('image') as $image) {
                $imageName = $image->hashName();
                $destination = public_path('uploads/product_photos');
                $image->move($destination, $imageName);
                $product->images()->create(['image' => 'uploads/product_photos/'.$imageName]);
            }
        }
    
        // Update sizes
        $product->sizes()->detach(); // Remove existing sizes
        if (!empty($validated['sizes'])) {
            foreach ($validated['sizes'] as $size) {
                $product->sizes()->attach($size['size_id'], ['price' => $size['price']]);
            }
        }

         // update categories
         if (!empty($validated['categories'])) {
            $categoryIds = array_column($validated['categories'], 'category_id');
            $product->category()->sync($categoryIds);
        }
    
        // Update FAQs
        $product->faqs()->delete(); // Remove existing FAQs
        if (!empty($validated['faqs'])) {
            foreach ($validated['faqs'] as $faq) {
                $product->faqs()->create([
                    'question' => $faq['question'],
                    'answer' => $faq['answer'],
                ]);
            }
        }
    
        return response()->json([
            'message' => 'Product updated successfully',
            'data' => $product->fresh()->load('images', 'sizes', 'faqs')
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $product = Product::findOrFail($id);
    
    // Delete associated images files and database records
    foreach ($product->images as $image) {
        if (file_exists(public_path($image->image))) {
            unlink(public_path($image->image));
        }
        $image->delete();
    
    
    $product->faqs()->delete();
    $product->sizes()->detach();
    $product->category()->detach();
    // Delete the product itself
    $product->delete();
    
    return response()->json([
        'message' => 'Product deleted successfully'
    ], 200);

    }
}
}