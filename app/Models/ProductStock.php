<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductStock extends Model
{
    use HasFactory;

    protected $fillable = ['product_id', 'purchase_price','product_variant_id', 'stock'];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function variant (){
        return $this->belongsTo(ProductVariant::class,'product_variant_id');
    }
}
