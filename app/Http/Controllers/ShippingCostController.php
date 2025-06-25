<?php

namespace App\Http\Controllers;

use App\Models\ShippingCost;
use Illuminate\Http\Request;

class ShippingCostController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $shipping_cost = ShippingCost::all();
        return response()->json($shipping_cost);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
        
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'inside_dhaka' => 'nullable|numeric',
            'outside_dhaka' => 'nullable|numeric',
            'one_shipping_cost' => 'nullable|numeric',
        ]);

        $data =  ShippingCost::create($request->all());
        return response()->json($data,201);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $shippingCost = ShippingCost::findOrFail($id);
        return response()->json($shippingCost, 200);
    }
    public function latest()
    {
        $shippingCost = ShippingCost::latest()->first();
        return response()->json($shippingCost, 200);
    }
    

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(ShippingCost $shippingCost)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'inside_dhaka' => 'nullable|numeric',
            'outside_dhaka' => 'nullable|numeric',
            'one_shipping_cost' => 'nullable|numeric',
        ]);
       $shippingCost = ShippingCost::findOrFail($id);
        $shippingCost->update($request->all());

        return response()->json(['message' => 'Updated successfully']);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $shippingCost = ShippingCost::findOrFail($id);
        $shippingCost->delete();
        return response()->json(['message' => 'Deleted successfully']);
    }
}
