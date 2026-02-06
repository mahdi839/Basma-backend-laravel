<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Product extends Model
{

    protected $casts = [
        'colors' => 'array',
    ];

    protected $guarded = [];

   

    public function images()
    {
        return $this->hasMany(ProductImage::class)->orderBy('position');;
    }

    public function thumbnail()
    {
        $this->hasOne(ProductImage::class)->orderBy('position');
    }

    public function sizes()
    {
        return $this->belongsToMany(Size::class, 'product_sizes')
            ->withPivot('price', 'stock')
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

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function specifications()
    {
        return $this->hasMany(ProductSpecification::class)->orderBy('order');
    }
}
