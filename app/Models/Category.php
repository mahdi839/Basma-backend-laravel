<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $fillable = ['name', 'slug','home_category','priority'];

    public function products (){
        return $this->belongsToMany(Product::class);
    }

    public function banner (){
        return $this->hasOne(Banner::class);
    }
}
