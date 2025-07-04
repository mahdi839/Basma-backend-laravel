<?php

namespace App\Http\Controllers;

use App\Models\ProductsSlot;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductsSlotController extends Controller
{
    public function store(Request $request){
       $request->validate([
          'slot_name' => 'required',
          'priority' => 'required',
          'product_id'=> 'nullable|prohibits:category_id|required_without:category_id',
          'category_id' =>'nullable|prohibits:product_id|required_without:product_id',
          'limit' => 'nullable|required_if:category_id,!' 
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

       if($request->filled('category_id')){
        foreach($request->category_id as $categoryId){
           $slot->slotDetails()->create([
               'category_id'=>$categoryId,
               'product_id'=>null,
               'limit'=> $request->limit
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
            'product_id'=> 'nullable|prohibits:category_id|required_without:category_id',
            'category_id' =>'nullable|prohibits:product_id|required_without:product_id',
            'limit' => 'nullable|required_if:category_id,!' 
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
   
          if($request->filled('category_id')){
           foreach($request->category_id as $categoryId){
              $product_slot->slotDetails()->create([
                  'category_id'=>$categoryId,
                  'product_id'=>null,
                  'limit'=> $request->limit
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
