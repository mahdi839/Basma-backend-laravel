<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Size extends Model
{
    //
      protected $guarded = [];
      protected $hidden = ['created_at', 'updated_at']; // <- add this
    public function products()
    {
        return $this->belongsToMany(Product::class, 'product_sizes')
                    ->withPivot('price','stock')
                    ->withTimestamps();
    }
}
