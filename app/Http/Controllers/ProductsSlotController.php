<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductsSlot;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductsSlotController extends Controller
{
   public function frontEndIndex (){
     $all_products = [];
     $product_slot = ProductsSlot::with([
    'slotDetails',
    'slotDetails.product.images',
    'slotDetails.product.sizes',
   ])->get();
   

  return $all_products;
     return response()->json($product_slot);
   }

   public function index (){

       $product_slot = ProductsSlot::with(['slotDetails',
       'slotDetails.product'=>function($q){
         $q->select('id','title');
       }
      ])->paginate(10);
      return response()->json($product_slot);
   } 

   public function create(){
      $products = Product::select(['id','title'])->get();
    
      return response()->json([
         'data' => [
            'products' => $products,
          ]
      ]);
   }

    public function store(Request $request){
       $request->validate([
          'slot_name' => 'required',
          'priority' => 'required',
          'product_id'=> 'required',
       ],
       [
           'slot_name.required' => 'Please provide a slot name.',
           'priority.required' => 'Priority is required.',
           'product_id.required' => 'Product name is required ',
       ]);

       $slot = ProductsSlot::create([
          'slot_name' => $request->slot_name,
          'priority' => $request->priority,
       ]);

       if($request->filled('product_id')){
         foreach($request->product_id as $productId){
            $slot->slotDetails()->create([
                'product_id'=>$productId,
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
       $product_slot = ProductsSlot::with('slotDetails.product')->findOrFail($id);
       return response()->json([
          'data'=> $product_slot
       ]);
    } 

    public function update(Request $request,$id){

        $request->validate([
            'slot_name' => 'required',
            'priority' => 'required',
            'product_id'=> 'required',
        ],
      [
           'slot_name.required' => 'Please provide a slot name.',
           'priority.required' => 'Priority is required.',
           'product_id.required' => 'Product name is required.',
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
