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
        $page = request()->get('page',1);
        $cachKey = "categories_page_$page";

        $categories = cache()->remember($cachKey,now()->addMinutes(10),function(){
             return Category::paginate(20);
        });
        return response()->json($categories);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $category = Category::create([
            'name' => $request->name,
            'slug' => Str::slug($request->name),
        ]);

        cache()->forget("categories_page_1");

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
        ]);

        $category->update([
            'name' => $request->name,
            'slug' => Str::slug($request->name),
        ]);

        cache()->forget("categories_page_1");

        return response()->json($category);
    }

    public function destroy($id)
    {
        $category = Category::findOrFail($id);
        $category->delete();
        cache()->forget("categories_page_1");
        return response()->json(['message' => 'Category deleted']);
    }
}
