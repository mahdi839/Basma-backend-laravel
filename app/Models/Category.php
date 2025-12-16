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

    // Sub categories
    public function children()
    {
        return $this->hasMany(Category::class, 'parent_id');
    }
}
