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
   
    public function banner_mages(){
        return $this->hasMany(BannerImage::class);
    }

    public function category(){
        return  $this->belongsTo(Category::class);
    }
}
