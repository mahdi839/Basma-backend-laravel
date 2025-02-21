<?php

namespace App\Http\Controllers;

use App\Models\Size; // Make sure to import the Size model
use Illuminate\Http\Request;

class SizeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // Retrieve all sizes from the database
        $sizes = Size::all();
        return response()->json($sizes, 200); // Return the sizes with a 200 OK response
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // Validate the incoming request data
        $request->validate([
            'size' => 'required|string|max:10', // Adjust validation rules as needed
        ]);

        // Create a new size record in the database
        $size = Size::create($request->only('size'));

        return response()->json($size, 201); // Return the created size with a 201 Created response
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        // Find the size by its ID
        $size = Size::find($id);

        if (!$size) {
            return response()->json(['message' => 'Size not found'], 404); // Return 404 if not found
        }

        return response()->json($size, 200); // Return the size with a 200 OK response
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        // Validate the incoming request data
        $request->validate([
            'size' => 'required|string|max:10', // Adjust validation rules as needed
        ]);

        // Find the size by its ID
        $size = Size::find($id);

        if (!$size) {
            return response()->json(['message' => 'Size not found'], 404); // Return 404 if not found
        }

        // Update the size record in the database
        $size->update($request->only('size'));

        return response()->json($size, 200); // Return the updated size with a 200 OK response
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        // Find the size by its ID
        $size = Size::find($id);

        if (!$size) {
            return response()->json(['message' => 'Size not found'], 404); // Return 404 if not found
        }

        // Delete the size record from the database
        $size->delete();

        return response()->json(['message' => 'Size deleted successfully'], 200); // Return success message with a 200 OK response
    }
}
