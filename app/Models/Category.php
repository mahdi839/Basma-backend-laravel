<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $fillable = ['name', 'slug', 'parent_id', 'home_category', 'priority'];

    public function products()
    {
        return $this->belongsToMany(Product::class);
    }

    public function banner()
    {
        return $this->hasOne(Banner::class);
    }

    // Parent category
    public function parent()
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    // Direct children only
    public function children()
    {
        return $this->hasMany(Category::class, 'parent_id')->orderBy('priority');
    }

    /**
     * Get all descendants recursively (children, grandchildren, etc.)
     * This is the FIXED version that properly loads nested children
     */
    public function allChildren()
    {
        return $this->hasMany(Category::class, 'parent_id')
            ->with('allChildren')
            ->orderBy('priority');
    }

    /**
     * Get all ancestors (parent, grandparent, etc.)
     */
    public function ancestors()
    {
        $ancestors = collect([]);
        $parent = $this->parent;

        while ($parent) {
            $ancestors->push($parent);
            $parent = $parent->parent;
        }

        return $ancestors;
    }

    /**
     * Get the depth level of this category (0 for root)
     */
    public function getDepthAttribute()
    {
        return $this->ancestors()->count();
    }

    /**
     * Check if this category is a root category
     */
    public function isRoot()
    {
        return is_null($this->parent_id);
    }

    /**
     * Check if this category has children
     */
    public function hasChildren()
    {
        return $this->children()->count() > 0;
    }
}