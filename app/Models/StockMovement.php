<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Immutable ledger row. Written only by InventoryService.
 */
class StockMovement extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    public const TYPE_INITIAL = 'initial';
    public const TYPE_PURCHASE = 'purchase';
    public const TYPE_ADJUSTMENT = 'adjustment';
    public const TYPE_RESERVE = 'reserve';
    public const TYPE_RELEASE = 'release';
    public const TYPE_SALE = 'sale';
    public const TYPE_RETURN = 'return';
    public const TYPE_DAMAGE = 'damage';

    protected $fillable = [
        'product_variant_id',
        'product_id',
        'variant_label',
        'type',
        'quantity',
        'stock_after',
        'reserved_after',
        'unit_cost',
        'order_id',
        'order_item_id',
        'user_id',
        'note',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'stock_after' => 'integer',
        'reserved_after' => 'integer',
        'unit_cost' => 'decimal:2',
        'created_at' => 'datetime',
    ];

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
