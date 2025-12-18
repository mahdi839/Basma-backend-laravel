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
        $categories = Category::with('parent')
            ->orderBy('priority')
            ->paginate(20);

        return response()->json($categories);
    }

    public function frontEndIndex()
    {
        // Get all root categories with ALL nested children recursively
        $categories = Category::whereNull('parent_id')
            ->with('allChildren') // This will recursively load all nested children
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
            'home_category' => 'nullable|boolean',
        ]);

        $category = Category::create([
            'name'      => $request->name,
            'slug'      => Str::slug($request->name),
            'parent_id' => $request->parent_id,
            'priority'  => $request->priority ?? 0,
            'home_category' => $request->home_category ?? false,
        ]);
        
        return response()->json($category, 201);
    }

    public function show($id)
    {
        $category = Category::with('allChildren')->findOrFail($id);
        return response()->json($category);
    }

    public function update(Request $request, $id)
    {
        $category = Category::findOrFail($id);

        $request->validate([
            'name'      => 'required|string|max:255',
            'parent_id' => [
                'nullable',
                'exists:categories,id',
                'not_in:' . $id,
                function ($attribute, $value, $fail) use ($id) {
                    // Prevent circular reference
                    if ($value && $this->isDescendant($id, $value)) {
                        $fail('Cannot set a descendant as parent.');
                    }
                },
            ],
            'priority'  => 'nullable|integer|min:0',
            'home_category' => 'nullable|boolean',
        ]);

        $category->update([
            'name'      => $request->name,
            'slug'      => Str::slug($request->name),
            'parent_id' => $request->parent_id,
            'priority'  => $request->priority ?? 0,
            'home_category' => $request->home_category ?? $category->home_category,
        ]);
        
        return response()->json($category);
    }

    public function destroy($id)
    {
        $category = Category::findOrFail($id);
        
        // Check if category has children
        if ($category->children()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete category with subcategories. Please delete or reassign subcategories first.'
            ], 422);
        }
        
        $category->delete();
        return response()->json(['message' => 'Category deleted successfully']);
    }

    /**
     * Check if a category is a descendant of another
     */
    private function isDescendant($categoryId, $potentialAncestorId)
    {
        $category = Category::find($potentialAncestorId);
        
        while ($category && $category->parent_id) {
            if ($category->parent_id == $categoryId) {
                return true;
            }
            $category = $category->parent;
        }
        
        return false;
    }
}