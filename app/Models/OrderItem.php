<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    protected $guarded = [];
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
}
