<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    public function index()
    {
        
        $categories = Category::paginate(20); // no caching

        return response()->json($categories);
    }

    public function frontEndIndex()
    {
        $categories = Category::with('banner.banner_images')->get();
        $simplyfiedData = $categories->map(function($category){
           return [
             'id' => $category->id,
             'name'=> $category->name,
             'slug' => $category->slug,
             'home_category' => $category->home_category,
            'priority' => $category->priority,
            'created_at' => $category->created_at,
            'updated_at' => $category->updated_at,
            'banner_image'=> $category->banner && $category->banner->banner_images->isNotEmpty()?
             $category->banner->banner_images->first()->path:null
           ];
        });
        return response()->json($simplyfiedData);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'home_category' => 'nullable|boolean',
            'priority'      => 'nullable|integer|min:0',
        ]);

        $category = Category::create([
            'name' => $request->name,
            'slug' => Str::slug($request->name),
            'home_category' => $request->home_category ?? false,
            'priority'      => $request->priority ?? 0,
        ]);
        return response()->json($category, 201);
    }

    public function show($id)
    {
        $category = Category::findOrFail($id);
        return response()->json($category);
    }

    public function update(Request $request, $id)
    {
        $category = Category::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255',
            'home_category' => 'nullable|boolean',
            'priority'      => 'nullable|integer|min:0',
        ]);

        $category->update([
            'name' => $request->name,
            'slug' => Str::slug($request->name),
            'home_category' => $request->home_category ?? false,
            'priority'      => $request->priority ?? 0,
        ]);
        return response()->json($category);
    }

    public function destroy($id)
    {
        $category = Category::findOrFail($id);
        $category->delete();
        return response()->json(['message' => 'Category deleted']);
    }
}
