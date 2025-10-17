<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AbandonedCheckout extends Model
{
    protected $fillable = ['session_id','name','phone','address','cart_items'];
}
