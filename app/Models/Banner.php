<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Banner extends Model
{
    protected $fillable = [
        'link',
        'type',
        'category_id',
        'products_slots_id'
    ];
   
    public function banner_images(){
        return $this->hasMany(BannerImage::class);
    }

    public function category(){
        return  $this->belongsTo(Category::class);
    }
    public function slot(){
        return  $this->belongsTo(ProductsSlot::class,'products_slots_id');
    }
}
