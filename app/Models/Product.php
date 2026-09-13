<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Product extends Model
{

    protected $casts = [
        'colors' => 'array',
        'track_inventory' => 'boolean',
        'preorder_eta_days' => 'integer',
        'low_stock_threshold' => 'integer',
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
        return $this->hasMany(ProductVariant::class)->orderBy('position');
    }

    public function activeVariants()
    {
        return $this->hasMany(ProductVariant::class)->where('is_active', true)->orderBy('position');
    }

    public function productColors()
    {
        return $this->hasMany(ProductColor::class)->orderBy('position');
    }

    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class);
    }

    /**
     * True when this product's stock numbers are enforced. A product inherits the
     * flag from any category marked as a stock category, so switching a category
     * on is enough for the client.
     */
    public function tracksInventory(): bool
    {
        if ($this->track_inventory) {
            return true;
        }

        return $this->relationLoaded('category')
            ? $this->category->contains(fn ($category) => (bool) $category->track_inventory)
            : $this->category()->where('track_inventory', true)->exists();
    }

    public function allowsPreorder(): bool
    {
        return in_array($this->preorder_mode, ['always', 'when_out_of_stock'], true);
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function specifications()
    {
        return $this->hasMany(ProductSpecification::class)->orderBy('order');
    }

    public function firstImage()
    {
        return $this->hasOne(ProductImage::class)->orderBy('position');
    }
}
