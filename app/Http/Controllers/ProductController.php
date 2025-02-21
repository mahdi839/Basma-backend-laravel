<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Size;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $allProducts =  Product::with(['images','sizes'])->get();

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

            'title'=>'required',
            'sub_title'=>'required',
            'video_url'=>'nullable',
            'description'=>'nullable',
            'discount'=>'nullable',
            'image'=>'required|array',
            'image.*' => 'image|mimes:jpg,jpeg,png',
            'sizes'=>'nullable|array',
            'size_id' => 'required',
            'price'=>'required|numeric',
            'question'=>'nullable|array',
            'answer'=>'nullable|array',

         ]);

         $product = Product::create([
             'title'=>$validated['title'],
             'sub_title'=>$validated['sub_title'],
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


            $product->sizes()->attach($validated['size_id'],['price'=>$validated['price']]);



         foreach ($validated['question'] as $key => $ques) {
            $product->faqs()->create([
                'question' => $ques,
                'answer' => $validated['answer'][$key] ?? null,
            ]);
        }



         return response()->json([
             'message'=> 'product created successfully',
             'data'=>$product->load('images','sizes','faqs')
         ]);

    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $product = Product::with(['images','sizes'])->findOrFail($id);
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
        // $validated = $request->validate([

        //     'title'=>'required',
        //     'sub_title'=>'required',
        //     'video_url'=>'nullable',
        //     'description'=>'nullable',
        //     'discount'=>'nullable',
        //     'image'=>'nullable|array',
        //     'image.*' => 'image|mimes:jpg,jpeg,png',
        //     'sizes'=>'nullable|array',
        //     'size_id' => 'nullable',
        //     'price'=>'required|numeric',
        //     'question'=>'nullable|array',
        //     'answer'=>'nullable|array',

        //  ]);

         $product = Product::with(['images','sizes'])->find($id);
        //  $product->name = $validated['title'];
        //  $product->sub_title = $validated['sub_title'];
        //  $product->video_url = $validated['video_url'];
        //  $product->description = $validated['description'];
        //  $product->discount = $validated['discount'];


            foreach ( $product->images as $image_record){
                if($request->hasFile('image')){

                    if($image_record->image && base_path($image_record->image)){
                        unlink(base_path($image_record->image));
                        $image_record->image->delete();
                    }

                    $imageName = $request->file('image')->hashName();
                    $destination = public_path('uploads/product_photos');
                    $image_record->image->move($destination,$imageName);
            }
            $imageName = $request->file('image')->hashName();
            $product->images()->update([
                 $image_record->image = 'uploads/product_photos/'.$imageName
            ]);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
