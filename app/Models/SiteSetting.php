<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SiteSetting extends Model
{
    public const DEFAULT_PRIMARY_COLOR = '#7d0ba7';

    public const DEFAULT_LOW_STOCK_THRESHOLD = 3;

    protected $fillable = [
        'primary_color',
        'inventory_enforcement_enabled',
        'low_stock_threshold',
    ];

    protected $casts = [
        'inventory_enforcement_enabled' => 'boolean',
        'low_stock_threshold' => 'integer',
    ];

    public static function current(): ?self
    {
        return self::query()->first();
    }

    /**
     * Global kill switch. While false, shortfalls are logged but a sale is never
     * blocked.
     */
    public static function inventoryEnforced(): bool
    {
        return (bool) (self::current()?->inventory_enforcement_enabled ?? false);
    }

    public static function lowStockThreshold(): int
    {
        return (int) (self::current()?->low_stock_threshold ?? self::DEFAULT_LOW_STOCK_THRESHOLD);
    }
}
