<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Banner extends Model
{
    protected $fillable = [
        'path',
        'type',
        'category_id'
    ];
   
    public function images(){
        return $this->hasMany(BannerImage::class);
    }

    public function category(){
        return  $this->blongsTo(Category::class);
    }
}
