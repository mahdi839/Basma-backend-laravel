<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    protected $guarded = [];
    public function order(){
        return $this->belongsTo(Order::class);
    }

  public function selectedVariant()
{
    return $this->belongsTo(ProductVariant::class, 'product_variant_id');
}


}
