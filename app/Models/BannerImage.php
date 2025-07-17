<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BannerImage extends Model
{
    protected $fillable = ['url'];

    public function banner(){
        return $this->belongsTo(Banner::class);
    }
}
