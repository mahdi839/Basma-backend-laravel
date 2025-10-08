<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
class ProductVariant extends Model
{
       use HasFactory;

    protected $fillable = [
        'product_id', 'attribute', 'value', 'price',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    // Optional: effective_price accessor that falls back to product base_price
    public function getEffectivePriceAttribute(): ?int
    {
        if (! is_null($this->price)) {
            return $this->price;
        }
        // If your products table has base_price column:
        return optional($this->product)->base_price;
    }

    public function stocks (){
        return $this->hasMany(ProductStock::class,'product_variant_id');
    }
}
