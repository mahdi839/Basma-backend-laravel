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

        $categories =  Category::with('parent')
            ->orderBy('priority')
            ->paginate(20);

        return response()->json($categories);
    }

    public function frontEndIndex()
    {
        $categories =  $categories = Category::whereNull('parent_id')
            ->with(['children' => function ($q) {
                $q->orderBy('priority');
            }])
            ->orderBy('priority')
            ->get();
        return response()->json($categories);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'      => 'required|string|max:255',
            'parent_id' => 'nullable|exists:categories,id',
            'priority'  => 'nullable|integer|min:0',
        ]);

        $category = Category::create([
            'name'      => $request->name,
            'slug'      => Str::slug($request->name),
            'parent_id' => $request->parent_id,
            'priority'  => $request->priority ?? 0,
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
            'name'      => 'required|string|max:255',
            'parent_id' => 'nullable|exists:categories,id|not_in:' . $id,
            'priority'  => 'nullable|integer|min:0',
        ]);

        $category->update([
            'name'      => $request->name,
            'slug'      => Str::slug($request->name),
            'parent_id' => $request->parent_id,
            'priority'  => $request->priority ?? 0,
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
