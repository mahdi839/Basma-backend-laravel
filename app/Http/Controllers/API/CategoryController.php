<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Traits\ClearsHomeCache;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    use ClearsHomeCache;
    public function index()
    {
        $categories = Category::with('parent')
            ->orderBy('priority')
            ->paginate(30);

        return response()->json($categories);
    }

     public function product_add_category()
    {
        return $categories = Category::get(['id','name']);
   
        return response()->json($categories);
    }

    public function frontEndIndex()
    {
        $cacheKey = "categoryHomePage";
        // Get all root categories with ALL nested children recursively
        $categories = Cache::remember($cacheKey,86400,function(){
            return Category::whereNull('parent_id')
            ->with('allChildren') // This will recursively load all nested children
            ->orderBy('priority')
            ->get();
        });
            
        return response()->json($categories);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'      => 'required|string|max:255',
            'parent_id' => 'nullable|exists:categories,id',
            'priority'  => 'nullable|integer|min:0',
            'home_category' => 'nullable|boolean',
            'size_guide_type' => 'nullable|in:shoe,dress',
        ]);

        $category = Category::create([
            'name'      => $request->name,
            'slug'      => Str::slug($request->name),
            'parent_id' => $request->parent_id,
            'priority'  => $request->priority ?? 0,
            'home_category' => $request->home_category ?? false,
            'size_guide_type' => $request->size_guide_type,
        ]);
        $this->clearHomeCategoryCach();
          // Simple cache forget
        Cache::forget('categoryHomePage');
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
            'size_guide_type' => 'nullable|in:shoe,dress',
        ]);

        $category->update([
            'name'      => $request->name,
            'slug'      => Str::slug($request->name),
            'parent_id' => $request->parent_id,
            'priority'  => $request->priority ?? 0,
            'home_category' => $request->home_category ?? $category->home_category,
            'size_guide_type' => $request->size_guide_type ?? $category->size_guide_type,
        ]);
        $this->clearHomeCategoryCach();
          // Simple cache forget
        Cache::forget('categoryHomePage');
        return response()->json($category);
    }

    public function productSizeGuideType ($id){
      $product = Product::with('category:id,name,size_guide_type')->where('id',$id)->first('id');
      return response()->json($product->category[0]);
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
        $this->clearHomeCategoryCach();
          // Simple cache forget
        Cache::forget('categoryHomePage');
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