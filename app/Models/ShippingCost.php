<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShippingCost extends Model
{
    protected $fillable = [
        'inside_dhaka',
        'outside_dhaka',
    ];
}
