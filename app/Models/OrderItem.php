<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    public const INV_NONE = 'none';
    public const INV_RESERVED = 'reserved';
    public const INV_COMMITTED = 'committed';
    public const INV_RELEASED = 'released';
    public const INV_RETURNED = 'returned';

    protected $guarded = [];

    protected $casts = [
        'is_preorder' => 'boolean',
        'qty' => 'integer',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function size()
    {
        return $this->belongsTo(Size::class, 'selected_size');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function color()
    {
        return $this->belongsTo(ProductColor::class, 'product_color_id');
    }
}
