<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FraudCheckerSetting extends Model
{
    protected $fillable = [
        'api_key',
        'is_active',
        'cache_minutes',
    ];

    protected $hidden = [
        'api_key',
    ];

    protected $casts = [
        'api_key' => 'encrypted',
        'is_active' => 'boolean',
        'cache_minutes' => 'integer',
    ];

    public static function current(): ?self
    {
        return self::query()->first();
    }
}
