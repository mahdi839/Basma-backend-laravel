<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One row per colour x size combination. This is the single source of truth for
 * stock; product_sizes.stock and the product_stocks table are legacy and no
 * longer read by the order flow.
 */
class ProductVariant extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'product_color_id',
        'size_id',
        'variant_key',
        'sku',
        'price',
        'purchase_price',
        'stock',
        'reserved',
        'low_stock_threshold',
        'allow_preorder',
        'preorder_limit',
        'preorder_count',
        'is_active',
        'position',
    ];

    protected $casts = [
        'price' => 'integer',
        'purchase_price' => 'decimal:2',
        'stock' => 'integer',
        'reserved' => 'integer',
        'low_stock_threshold' => 'integer',
        'allow_preorder' => 'boolean',
        'preorder_limit' => 'integer',
        'preorder_count' => 'integer',
        'is_active' => 'boolean',
        'position' => 'integer',
    ];

    protected $appends = ['available'];

    protected static function booted(): void
    {
        // variant_key is the real uniqueness guard, so it must never be stale.
        static::saving(function (self $variant) {
            $variant->variant_key = self::makeKey($variant->product_color_id, $variant->size_id);
        });
    }

    /**
     * MySQL treats each NULL as distinct, so a composite unique index over
     * nullable colour/size columns would not stop duplicates. Zero stands in for
     * "no colour" and "no size".
     */
    public static function makeKey(?int $colorId, ?int $sizeId): string
    {
        return ((int) $colorId).'-'.((int) $sizeId);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function color()
    {
        return $this->belongsTo(ProductColor::class, 'product_color_id');
    }

    public function size()
    {
        return $this->belongsTo(Size::class);
    }

    public function movements()
    {
        return $this->hasMany(StockMovement::class)->latest('created_at');
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    /** Units a new customer can still buy from physical stock. */
    public function getAvailableAttribute(): int
    {
        return (int) $this->stock - (int) $this->reserved;
    }

    /** Remaining pre-order allowance; null means unlimited. */
    public function preorderRemaining(): ?int
    {
        if (! $this->allow_preorder) {
            return 0;
        }

        if ($this->preorder_limit === null) {
            return null;
        }

        return max(0, (int) $this->preorder_limit - (int) $this->preorder_count);
    }

    public function canFulfil(int $qty): bool
    {
        if ($this->available >= $qty) {
            return true;
        }

        $remaining = $this->preorderRemaining();

        return $remaining === null || $remaining >= $qty;
    }

    public function isLowStock(int $fallbackThreshold = 3): bool
    {
        $threshold = $this->low_stock_threshold
            ?? $this->product?->low_stock_threshold
            ?? $fallbackThreshold;

        return $this->available > 0 && $this->available <= $threshold;
    }

    /** Human label used in the ledger and admin tables, e.g. "Maroon / M". */
    public function label(): string
    {
        $parts = array_filter([
            $this->color?->name,
            $this->size?->size,
        ]);

        return $parts === [] ? 'Default' : implode(' / ', $parts);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeInStock(Builder $query): Builder
    {
        return $query->whereRaw('(stock - reserved) > 0');
    }

    public function scopeOutOfStock(Builder $query): Builder
    {
        return $query->whereRaw('(stock - reserved) <= 0');
    }

    public function scopeLowStock(Builder $query, int $fallbackThreshold = 3): Builder
    {
        return $query
            ->whereRaw('(stock - reserved) > 0')
            ->whereRaw('(stock - reserved) <= COALESCE(low_stock_threshold, ?)', [$fallbackThreshold]);
    }
}
