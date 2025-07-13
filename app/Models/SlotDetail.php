<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SlotDetail extends Model
{
    protected $fillable = ['slot_id','product_id','category_id','limit'];

    public function product(){
        return $this->belongsTo(Product::class);
    }

   
}
