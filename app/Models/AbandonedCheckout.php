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
        'is_recovered'
    ];
    protected $casts = [
        'cart_items' => 'array', // Automatically handles JSON encoding/decoding
        'is_converted' => 'boolean',
    ];
}
