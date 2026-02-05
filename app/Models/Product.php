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

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($product) {
            if (empty($product->sku)) {
                $product->sku = self::generateSku();
            }
        });
    }

    /**
     * Generate a unique SKU
     */

    public static function generateSku()
    {
        do {
            $sku = "PRD-" . strtoupper(Str::random(6));
        } while (self::where('sku', $sku)->exists());
        return $sku;
    }

    public function images()
    {
        return $this->hasMany(ProductImage::class);
    }

    public function thumbnail(){
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
