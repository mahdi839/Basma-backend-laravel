<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductsSlot extends Model
{
    protected $fillable = ['slot_name','priority'];
    
    public function slotDetails (){
        return $this->hasMany(SlotDetail::class,'slot_id');
    }
}
