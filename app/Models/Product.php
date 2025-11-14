<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{

    protected $casts = [
        'colors' => 'array',
    ];

    protected $guarded = [];

    public function images()
    {
        return $this->hasMany(ProductImage::class);
    }

    public function sizes()
    {
        return $this->belongsToMany(Size::class, 'product_sizes')
            ->withPivot('price')
            ->withTimestamps();
    }

    public function faqs()
    {
        return $this->hasMany(productFaq::class);
    }

    public function category()
    {
        return $this->belongsToMany(Category::class);
    }

    public function variants()
    {
        return $this->hasMany(ProductVariant::class);
    }
}
