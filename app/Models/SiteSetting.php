<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SiteSetting extends Model
{
    public const DEFAULT_PRIMARY_COLOR = '#7d0ba7';

    protected $fillable = [
        'primary_color',
    ];

    public static function current(): ?self
    {
        return self::query()->first();
    }
}
