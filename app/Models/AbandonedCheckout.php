<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AbandonedCheckout extends Model
{
    protected $fillable = [
        'session_id',
        'name',
        'phone',
        'address',
        'cart_items',
        'user_id',
        'is_recovered',
        'status',
        'converted_order_id',
        'converted_at',
    ];
    protected $casts = [
        'cart_items' => 'array', // Automatically handles JSON encoding/decoding
        'is_recovered' => 'boolean',
        'converted_at' => 'datetime',
    ];
}
