<?php

namespace App\Http\Controllers\web\DashboardController;

use App\Models\Size;
use Illuminate\Http\Request;

class SizeController
{
    public function index (){
        $sizes = Size::all();
        return view('dashboard.size.index',compact('sizes'));
    }
    public function create(){
        return view('dashboard.size.create');
    }

     public function store(Request $request)
    {
        $request->validate([
            'size' => 'required|string|max:10',
        ]);

        Size::create($request->only('size'));

        return redirect()->route('admin.index.size')->with('success', 'Size created successfully!');
    }
       public function edit($id)
    {
        $size = Size::findOrFail($id); // Will throw 404 if not found
        return view('dashboard.size.edit', compact('size')); // Pass size to edit view
    }

    /**
     * Update the specified size.
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'size' => 'required|string|max:10',
        ]);

        $size = Size::findOrFail($id);
        $size->update($request->only('size'));

        return redirect()->route('admin.index.size')->with('success', 'Size updated successfully!');
    }

    /**
     * Delete the specified size.
     */
    public function destroy($id)
    {
        $size = Size::findOrFail($id);
        $size->delete();

        return redirect()->route('admin.index.size')->with('success', 'Size deleted successfully!');
    }
}
