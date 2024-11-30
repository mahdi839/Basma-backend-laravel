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
        //
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
            $product->images()->create(['image'=>$image]);
         }
         
          $allSizes =  Size::all();
         foreach ($allSizes as $size){
            $product->sizes()->attach($size->id,['price'=>$validated['price']]);
         }


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
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
