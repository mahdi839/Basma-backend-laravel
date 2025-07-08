<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductsSlot;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductsSlotController extends Controller
{
   
   public function index (){

       $product_slot = ProductsSlot::with(['slotDetails',
       'slotDetails.product'=>function($q){
         $q->select('id','title');
       },
       'slotDetails.category'=>function($q){
         $q->select('id','name');
       }
      
      ])->paginate(10);
      return response()->json($product_slot);
   } 

   public function create(){
      $products = Product::select(['id','title'])->get();
      $categories = Category::select(['id','name'])->get();
      return response()->json([
         'data' => [
            'products' => $products,
            'categories' => $categories
          ]
      ]);
   }

    public function store(Request $request){
       $request->validate([
          'slot_name' => 'required',
          'priority' => 'required',
          'product_id'=> 'nullable|required_without:category_id',
           'categories' => 'nullable|array',
         'categories.*.id' => 'required_with:categories|integer',
         'categories.*.limit' => 'required_with:categories|integer|min:1',
       ],
       [
           'slot_name.required' => 'Please provide a slot name.',
           'priority.required' => 'Priority is required.',
           'product_id.required_without' => 'Product name is required if no category is selected.',
           'category_id.required_without' => 'Category  is required if no product is selected.',
           'limit.required_if' => 'Limit is required when a category is selected.'
       ]);

       $slot = ProductsSlot::create([
          'slot_name' => $request->slot_name,
          'priority' => $request->priority,
       ]);

       if($request->filled('product_id')){
         foreach($request->product_id as $productId){
            $slot->slotDetails()->create([
                'product_id'=>$productId,
                'category_id'=>null,
                'limit'=>null
            ]);
         }
       }

      if($request->filled('categories')){
         foreach($request->categories as $cat){
            $slot->slotDetails()->create([
               'category_id' => $cat['id'],
               'product_id' => null,
               'limit' => $cat['limit'],
            ]);
         }
         }

        
        $slot->load('slotDetails');

        // ✅ Return JSON response
        return response()->json([
            'message' => 'Slot created successfully',
            'data'    => $slot
        ], 201);

    }

    public function edit($id){
       $product_slot = ProductsSlot::with(['slotDetails.product','slotDetails.category'=>function($q){
         $q->select('id','name');
       }])->findOrFail($id);

       return response()->json([
          'data'=> $product_slot
       ]);
    } 

    public function update(Request $request,$id){

        $request->validate([
            'slot_name' => 'required',
            'priority' => 'required',
            'product_id'=> 'nullable|required_without:category_id',
             'categories' => 'nullable|array',
            'categories.*.id' => 'required_with:categories|integer',
            'categories.*.limit' => 'required_with:categories|integer|min:1',
        ],
      [
           'slot_name.required' => 'Please provide a slot name.',
           'priority.required' => 'Priority is required.',
           'product_id.required_without' => 'Product name is required if no category is selected.',
           'category_id.required_without' => 'Category  is required if no product is selected.',
           'limit.required_if' => 'Limit is required when a category is selected.'
       ]);
         $product_slot = ProductsSlot::with('slotDetails')->findOrFail($id);
         DB::transaction(function()use($product_slot,$request){
       
         $product_slot->update([
            'slot_name' => $request->slot_name,
            'priority' => $request->priority,
         ]);

         $product_slot->slotDetails()->delete();
         if($request->filled('product_id')){
            foreach($request->product_id as $productId){
               $product_slot->slotDetails()->create([
                   'product_id'=>$productId,
                   'category_id'=>null,
                   'limit'=>null
               ]);
            }
          }
   
        if($request->filled('categories')){
         foreach($request->categories as $cat){
            $product_slot->slotDetails()->create([
               'category_id' => $cat['id'],
               'product_id' => null,
               'limit' => $cat['limit'],
            ]);
         }
         }

         });
          // Reload the updated relationship
         $product_slot->load('slotDetails');

         return response()->json([
            'message'=> 'Updated Successfully!',
            'data'=> $product_slot
         ]);
   
    }

    public function destroy ($id){
        $product_slot = ProductsSlot::findOrFail($id);
        $product_slot->slotDetails()->delete();
        $product_slot->delete();

        return response()->json([
            'message'=> 'Product Slot Deleted Successfully!'
        ],200);
    }
}
