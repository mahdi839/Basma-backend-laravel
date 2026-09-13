<?php

namespace App\Services;

use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductColor;
use App\Models\ProductVariant;
use App\Models\SiteSetting;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * The only place that writes product_variants.stock / .reserved.
 *
 * Model used, matching how the shop actually runs:
 *   order placed          -> reserve   (units held, physical stock untouched)
 *   status order_confirmed -> commit   (stock reduced for real)
 *   status cancelled       -> release reservation, or restock if already committed
 *   status returned        -> restock
 *
 * Every mutation locks its variant rows and appends a stock_movements row, and
 * every order-driven mutation is gated on order_items.inventory_state so that
 * repeating a status change is a no-op.
 */
class InventoryService
{
    /** Statuses that mean the order is still only holding stock. */
    public const RESERVE_STATUSES = [
        'pending',
        'placed',
        'processing',
        'first_call',
        'second_call',
        'third_call',
    ];

    /** Statuses that mean the goods are committed and stock must drop. */
    public const COMMIT_STATUSES = [
        'order_confirmed',
        'completed',
        'delivered',
        'shipped_to_you',
    ];

    /** Statuses that hand units back. */
    public const RESTORE_STATUSES = [
        'cancel',
        'cancelled',
        'returned',
    ];

    /* ------------------------------------------------------------------ */
    /* Resolution                                                          */
    /* ------------------------------------------------------------------ */

    /**
     * Turn a cart/admin line into the variant row it consumes.
     *
     * Accepts whatever the caller happens to know: an explicit variant id, a
     * colour id, a legacy JSON colour id, a colour image path, or a colour name.
     * Older storefront builds only send an image path, so that path must keep
     * working.
     */
    public function resolveVariant(int $productId, array $hints = []): ?ProductVariant
    {
        if (! empty($hints['variant_id'])) {
            $variant = ProductVariant::where('product_id', $productId)
                ->whereKey($hints['variant_id'])
                ->first();

            if ($variant) {
                return $variant;
            }
        }

        $colorId = $this->resolveColorId($productId, $hints);
        $sizeId = ! empty($hints['size_id']) ? (int) $hints['size_id'] : null;

        $variant = ProductVariant::where('product_id', $productId)
            ->where('variant_key', ProductVariant::makeKey($colorId, $sizeId))
            ->first();

        if ($variant) {
            return $variant;
        }

        // A product with a single variant (no colours, no sizes) may be ordered
        // without either hint.
        if ($colorId === null && $sizeId === null) {
            return ProductVariant::where('product_id', $productId)->first();
        }

        return null;
    }

    /**
     * Same as resolveVariant but creates the row when it is genuinely missing —
     * for example a colour added to a product before this feature existed.
     */
    public function ensureVariant(int $productId, ?int $colorId, ?int $sizeId): ProductVariant
    {
        $key = ProductVariant::makeKey($colorId, $sizeId);

        $variant = ProductVariant::where('product_id', $productId)
            ->where('variant_key', $key)
            ->first();

        if ($variant) {
            return $variant;
        }

        return ProductVariant::create([
            'product_id' => $productId,
            'product_color_id' => $colorId,
            'size_id' => $sizeId,
            'stock' => 0,
            'reserved' => 0,
            'is_active' => true,
        ]);
    }

    private function resolveColorId(int $productId, array $hints): ?int
    {
        if (! empty($hints['product_color_id'])) {
            return (int) $hints['product_color_id'];
        }

        $colors = ProductColor::where('product_id', $productId)->get();

        if ($colors->isEmpty()) {
            return null;
        }

        if (! empty($hints['color_id'])) {
            $byLegacy = $colors->firstWhere('legacy_json_id', (int) $hints['color_id']);
            if ($byLegacy) {
                return (int) $byLegacy->id;
            }
        }

        // Cart lines store an absolute URL; compare on file name only.
        if (! empty($hints['color_image'])) {
            $needle = basename(parse_url((string) $hints['color_image'], PHP_URL_PATH) ?: '');

            if ($needle !== '') {
                $byImage = $colors->first(
                    fn (ProductColor $color) => $color->image && basename($color->image) === $needle
                );

                if ($byImage) {
                    return (int) $byImage->id;
                }
            }
        }

        if (! empty($hints['color_name'])) {
            $byName = $colors->first(
                fn (ProductColor $color) => $color->name
                    && mb_strtolower(trim($color->name)) === mb_strtolower(trim((string) $hints['color_name']))
            );

            if ($byName) {
                return (int) $byName->id;
            }
        }

        return null;
    }

    /* ------------------------------------------------------------------ */
    /* Availability                                                        */
    /* ------------------------------------------------------------------ */

    /**
     * @return array{ok: bool, available: int, preorder: bool, reason: ?string}
     */
    public function checkAvailability(ProductVariant $variant, int $qty): array
    {
        $available = $variant->available;

        if (! $variant->is_active) {
            return ['ok' => false, 'available' => 0, 'preorder' => false, 'reason' => 'unavailable'];
        }

        if ($available >= $qty) {
            return ['ok' => true, 'available' => $available, 'preorder' => false, 'reason' => null];
        }

        $remaining = $variant->preorderRemaining();

        if ($variant->allow_preorder && ($remaining === null || $remaining >= $qty)) {
            return ['ok' => true, 'available' => $available, 'preorder' => true, 'reason' => null];
        }

        return [
            'ok' => false,
            'available' => max(0, $available),
            'preorder' => false,
            'reason' => $available <= 0 ? 'out_of_stock' : 'insufficient',
        ];
    }

    /* ------------------------------------------------------------------ */
    /* Order-driven transitions                                            */
    /* ------------------------------------------------------------------ */

    /**
     * Hold units for a freshly created line. Safe to call twice.
     */
    public function reserve(OrderItem $item, ?int $userId = null): void
    {
        if ($item->inventory_state !== OrderItem::INV_NONE || ! $item->product_variant_id) {
            return;
        }

        $variant = $this->lock($item->product_variant_id);
        if (! $variant) {
            return;
        }

        $qty = (int) $item->qty;

        $variant->reserved += $qty;
        if ($item->is_preorder) {
            $variant->preorder_count += $qty;
        }
        $variant->save();

        $this->record($variant, StockMovement::TYPE_RESERVE, $qty, [
            'order_id' => $item->order_id,
            'order_item_id' => $item->id,
            'user_id' => $userId,
            'note' => 'Held for order placement',
        ]);

        $item->forceFill(['inventory_state' => OrderItem::INV_RESERVED])->save();
    }

    /**
     * Turn a hold into a real deduction. Runs when the order is confirmed.
     */
    public function commit(OrderItem $item, ?int $userId = null): void
    {
        if (! $item->product_variant_id || $item->inventory_state === OrderItem::INV_COMMITTED) {
            return;
        }

        // Nothing to commit once the line has been given back.
        if (in_array($item->inventory_state, [OrderItem::INV_RELEASED, OrderItem::INV_RETURNED], true)) {
            return;
        }

        $variant = $this->lock($item->product_variant_id);
        if (! $variant) {
            return;
        }

        $qty = (int) $item->qty;

        if ($item->inventory_state === OrderItem::INV_RESERVED) {
            $variant->reserved = max(0, $variant->reserved - $qty);
        }

        $variant->stock -= $qty;
        $variant->save();

        $this->record($variant, StockMovement::TYPE_SALE, -$qty, [
            'order_id' => $item->order_id,
            'order_item_id' => $item->id,
            'user_id' => $userId,
            'note' => 'Order confirmed',
        ]);

        $item->forceFill(['inventory_state' => OrderItem::INV_COMMITTED])->save();
    }

    /**
     * Give units back. Releases the hold when still reserved, restocks when the
     * deduction already happened.
     */
    public function restore(OrderItem $item, string $reason, ?int $userId = null): void
    {
        if (! $item->product_variant_id) {
            return;
        }

        if (in_array($item->inventory_state, [OrderItem::INV_NONE, OrderItem::INV_RELEASED, OrderItem::INV_RETURNED], true)) {
            return;
        }

        $variant = $this->lock($item->product_variant_id);
        if (! $variant) {
            return;
        }

        $qty = (int) $item->qty;
        $wasCommitted = $item->inventory_state === OrderItem::INV_COMMITTED;

        if ($wasCommitted) {
            $variant->stock += $qty;
        } else {
            $variant->reserved = max(0, $variant->reserved - $qty);
        }

        if ($item->is_preorder) {
            $variant->preorder_count = max(0, $variant->preorder_count - $qty);
        }

        $variant->save();

        $this->record(
            $variant,
            $reason === 'returned' ? StockMovement::TYPE_RETURN : StockMovement::TYPE_RELEASE,
            $wasCommitted ? $qty : 0,
            [
                'order_id' => $item->order_id,
                'order_item_id' => $item->id,
                'user_id' => $userId,
                'note' => $wasCommitted
                    ? 'Restocked after '.$reason
                    : 'Reservation released after '.$reason,
            ]
        );

        $item->forceFill([
            'inventory_state' => $reason === 'returned'
                ? OrderItem::INV_RETURNED
                : OrderItem::INV_RELEASED,
        ])->save();
    }

    /**
     * Drive every line of an order to the state its new status implies.
     * Idempotent, so re-selecting the same status changes nothing.
     */
    public function applyOrderStatus(int $orderId, string $status, ?int $userId = null): void
    {
        DB::transaction(function () use ($orderId, $status, $userId) {
            $items = OrderItem::where('order_id', $orderId)
                ->whereNotNull('product_variant_id')
                ->get();

            foreach ($items as $item) {
                if (in_array($status, self::COMMIT_STATUSES, true)) {
                    $this->commit($item, $userId);
                    continue;
                }

                if (in_array($status, self::RESTORE_STATUSES, true)) {
                    $this->restore($item, $status === 'returned' ? 'returned' : 'cancellation', $userId);
                    continue;
                }

                if (in_array($status, self::RESERVE_STATUSES, true)) {
                    // Re-hold a line that had been cancelled and is now revived.
                    if (in_array($item->inventory_state, [OrderItem::INV_RELEASED, OrderItem::INV_RETURNED], true)) {
                        $item->forceFill(['inventory_state' => OrderItem::INV_NONE])->save();
                        $this->reserve($item, $userId);
                        continue;
                    }

                    $this->reserve($item, $userId);
                }
            }
        });
    }

    /**
     * Undo whatever a line is currently holding. Used when an admin edits or
     * deletes a line from an existing order.
     */
    public function releaseForEdit(OrderItem $item, ?int $userId = null): void
    {
        $this->restore($item, 'order edit', $userId);
    }

    /* ------------------------------------------------------------------ */
    /* Manual stock management                                             */
    /* ------------------------------------------------------------------ */

    /** Receive a shipment. */
    public function stockIn(int $variantId, int $qty, ?float $unitCost = null, ?string $note = null, ?int $userId = null): ProductVariant
    {
        if ($qty <= 0) {
            throw new RuntimeException('Received quantity must be greater than zero.');
        }

        return DB::transaction(function () use ($variantId, $qty, $unitCost, $note, $userId) {
            $variant = $this->lock($variantId);

            if (! $variant) {
                throw new RuntimeException('Variant not found.');
            }

            $variant->stock += $qty;

            if ($unitCost !== null) {
                $variant->purchase_price = $unitCost;
            }

            $variant->save();

            $this->record($variant, StockMovement::TYPE_PURCHASE, $qty, [
                'user_id' => $userId,
                'unit_cost' => $unitCost,
                'note' => $note ?: 'Stock received',
            ]);

            return $variant;
        });
    }

    /**
     * Set an absolute on-hand figure, e.g. after a physical recount.
     */
    public function adjust(int $variantId, int $newStock, string $type = StockMovement::TYPE_ADJUSTMENT, ?string $note = null, ?int $userId = null): ProductVariant
    {
        return DB::transaction(function () use ($variantId, $newStock, $type, $note, $userId) {
            $variant = $this->lock($variantId);

            if (! $variant) {
                throw new RuntimeException('Variant not found.');
            }

            $delta = $newStock - (int) $variant->stock;

            if ($delta === 0) {
                return $variant;
            }

            $variant->stock = $newStock;
            $variant->save();

            $this->record($variant, $type, $delta, [
                'user_id' => $userId,
                'note' => $note ?: 'Manual adjustment',
            ]);

            return $variant;
        });
    }

    /* ------------------------------------------------------------------ */
    /* Internals                                                           */
    /* ------------------------------------------------------------------ */

    private function lock(int $variantId): ?ProductVariant
    {
        return ProductVariant::whereKey($variantId)->lockForUpdate()->first();
    }

    private function record(ProductVariant $variant, string $type, int $quantity, array $attributes = []): void
    {
        $variant->loadMissing(['color', 'size']);

        StockMovement::create(array_merge([
            'product_variant_id' => $variant->id,
            'product_id' => $variant->product_id,
            'variant_label' => $variant->label(),
            'type' => $type,
            'quantity' => $quantity,
            'stock_after' => (int) $variant->stock,
            'reserved_after' => (int) $variant->reserved,
        ], $attributes));

        if ($variant->stock < 0) {
            Log::warning('Inventory went negative, which points at an unguarded write.', [
                'product_variant_id' => $variant->id,
                'stock' => $variant->stock,
            ]);
        }
    }

    /* ------------------------------------------------------------------ */
    /* Product structure sync                                              */
    /* ------------------------------------------------------------------ */

    /**
     * Mirror products.colors (JSON) into product_colors so variants have a real
     * foreign key to point at. The JSON column stays authoritative for display,
     * so no existing storefront read changes.
     *
     * @param  array<int, array>  $colorsJson  the array just written to products.colors
     */
    public function syncProductColors(Product $product, array $colorsJson): void
    {
        $existing = ProductColor::where('product_id', $product->id)->get();
        $keptIds = [];

        foreach (array_values($colorsJson) as $position => $color) {
            if (! is_array($color)) {
                continue;
            }

            $legacyId = isset($color['id']) ? (int) $color['id'] : $position + 1;

            $row = $existing->firstWhere('legacy_json_id', $legacyId);

            if (! $row) {
                // Fall back to matching on the image, which is what identifies a
                // colour on order lines written before this table existed.
                $row = $existing->first(
                    fn (ProductColor $candidate) => $candidate->image
                        && ! empty($color['image'])
                        && basename($candidate->image) === basename($color['image'])
                );
            }

            $attributes = [
                'product_id' => $product->id,
                'name' => $color['name'] ?? null,
                'code' => $color['code'] ?? null,
                'image' => $color['image'] ?? null,
                'position' => $position,
                'legacy_json_id' => $legacyId,
            ];

            if ($row) {
                $row->update($attributes);
                $keptIds[] = $row->id;

                continue;
            }

            $keptIds[] = ProductColor::create($attributes)->id;
        }

        $this->retireRemovedColors($product, $existing, $keptIds);
    }

    /**
     * A colour the admin removed is deleted only when nothing depends on it.
     * Otherwise its variants are deactivated so stock history survives — the
     * colour FK is restrictOnDelete precisely to prevent silent data loss.
     */
    private function retireRemovedColors(Product $product, $existing, array $keptIds): void
    {
        foreach ($existing as $row) {
            if (in_array($row->id, $keptIds, true)) {
                continue;
            }

            $variants = ProductVariant::where('product_color_id', $row->id)->get();

            $hasHistory = $variants->contains(
                fn (ProductVariant $v) => $v->stock != 0
                    || $v->reserved != 0
                    || OrderItem::where('product_variant_id', $v->id)->exists()
            );

            if ($hasHistory) {
                ProductVariant::whereIn('id', $variants->pluck('id'))->update(['is_active' => false]);

                continue;
            }

            ProductVariant::whereIn('id', $variants->pluck('id'))->delete();
            $row->delete();
        }
    }

    /**
     * Make sure a variant row exists for every colour x size combination, and
     * deactivate rows whose combination no longer exists on the product.
     */
    public function syncProductVariants(Product $product): void
    {
        $colorIds = ProductColor::where('product_id', $product->id)
            ->orderBy('position')
            ->pluck('id')
            ->all();

        $sizeRows = DB::table('product_sizes')
            ->where('product_id', $product->id)
            ->get(['size_id', 'price']);

        $sizeIds = $sizeRows->pluck('size_id')->all();
        $priceBySize = $sizeRows->pluck('price', 'size_id');

        $colorIds = $colorIds === [] ? [null] : $colorIds;
        $sizeIds = $sizeIds === [] ? [null] : $sizeIds;

        $validKeys = [];
        $position = 0;

        foreach ($colorIds as $colorId) {
            foreach ($sizeIds as $sizeId) {
                $variant = $this->ensureVariant((int) $product->id, $colorId, $sizeId);
                $validKeys[] = $variant->variant_key;

                $updates = ['position' => $position++, 'is_active' => true];

                // Seed the price from the size pivot the first time only, so a
                // per-variant override is never clobbered.
                if ($variant->price === null && $sizeId !== null && isset($priceBySize[$sizeId])) {
                    $updates['price'] = $priceBySize[$sizeId];
                }

                $variant->update($updates);
            }
        }

        ProductVariant::where('product_id', $product->id)
            ->whereNotIn('variant_key', $validKeys)
            ->update(['is_active' => false]);
    }

    /* ------------------------------------------------------------------ */
    /* Read helpers used by the storefront                                 */
    /* ------------------------------------------------------------------ */

    /**
     * Availability map for a product page: which colours and sizes can still be
     * bought, plus per-combination numbers.
     */
    public function availabilityFor(Product $product): array
    {
        $threshold = SiteSetting::lowStockThreshold();
        $tracks = $product->tracksInventory();

        $variants = $product->variants()->with(['color', 'size'])->get();

        $combinations = $variants->map(function (ProductVariant $variant) use ($tracks, $threshold) {
            $available = $variant->available;

            return [
                'variant_id' => $variant->id,
                'product_color_id' => $variant->product_color_id,
                'color_id' => $variant->color?->legacy_json_id,
                'color_name' => $variant->color?->name,
                'size_id' => $variant->size_id,
                'size' => $variant->size?->size,
                'price' => $variant->price,
                'sku' => $variant->sku,
                'available' => $tracks ? max(0, $available) : null,
                'in_stock' => $tracks ? ($available > 0) : true,
                'allow_preorder' => (bool) $variant->allow_preorder,
                'low_stock' => $tracks && $variant->isLowStock($threshold),
                'is_active' => (bool) $variant->is_active,
            ];
        })->values();

        return [
            'track_inventory' => $tracks,
            'preorder_mode' => $product->preorder_mode ?? 'off',
            'preorder_eta_days' => $product->preorder_eta_days,
            'preorder_note' => $product->preorder_note,
            'total_available' => $tracks ? (int) $variants->sum(fn ($v) => max(0, $v->available)) : null,
            'combinations' => $combinations,
        ];
    }
}
