<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FacebookSetting extends Model
{
     protected $fillable = [
        'pixel_id',
        'access_token',
        'test_event_code',
        'is_active',
        'is_test_mode',
    ];

     protected $casts = [
        'is_active' => 'boolean',
        'is_test_mode' => 'boolean',
    ];

     // Get active settings
    public static function getActive()
    {
        return self::first(); // For single site, get first record
    }
}
