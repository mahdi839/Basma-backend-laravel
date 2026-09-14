<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

require_once __DIR__.'/helpers/ensure_product_variants_matrix.php';

/**
 * Generates one product_variants row per colour x size combination for every
 * existing product.
 *
 * Starting stock rules, chosen so nothing is invented and nothing is lost:
 *  - sizes only (no colours) -> stock copied from product_sizes.stock
 *  - everything else         -> stock 0, because a per-colour split cannot be
 *                               derived from a size-level number. The client
 *                               enters real counts through the Stock In screen.
 *
 * products.colors and product_sizes.stock are left untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 100005 can record as "ran" while leaving the old EAV table in place
        // (it used to return early if product_variants already existed). Repair
        // that here so this backfill never queries a missing variant_key.
        eyara_ensure_product_variants_matrix();

        DB::table('products')->select('id')->orderBy('id')->chunk(100, function ($products) {
            foreach ($products as $product) {
                $this->backfillProduct((int) $product->id);
            }
        });
    }

    private function backfillProduct(int $productId): void
    {
        $colors = DB::table('product_colors')
            ->where('product_id', $productId)
            ->orderBy('position')
            ->get(['id']);

        $sizes = DB::table('product_sizes')
            ->where('product_id', $productId)
            ->get(['size_id', 'price', 'stock']);

        $colorIds = $colors->pluck('id')->all();
        if ($colorIds === []) {
            $colorIds = [null];
        }

        $sizeRows = $sizes->all();
        if ($sizeRows === []) {
            $sizeRows = [null];
        }

        $hasColors = $colors->isNotEmpty();
        $rows = [];
        $queued = [];
        $position = 0;

        foreach ($colorIds as $colorId) {
            foreach ($sizeRows as $sizeRow) {
                $sizeId = $sizeRow->size_id ?? null;
                $variantKey = ((int) ($colorId ?? 0)).'-'.((int) ($sizeId ?? 0));

                if (isset($queued[$variantKey])) {
                    continue;
                }

                $exists = DB::table('product_variants')
                    ->where('product_id', $productId)
                    ->where('variant_key', $variantKey)
                    ->exists();

                if ($exists) {
                    $queued[$variantKey] = true;
                    continue;
                }

                // Only a size-only product can safely inherit its legacy count.
                $stock = (! $hasColors && $sizeRow !== null)
                    ? (int) ($sizeRow->stock ?? 0)
                    : 0;

                $rows[] = [
                    'product_id' => $productId,
                    'product_color_id' => $colorId,
                    'size_id' => $sizeId,
                    'variant_key' => $variantKey,
                    'sku' => null,
                    'price' => isset($sizeRow->price) ? $sizeRow->price : null,
                    'purchase_price' => null,
                    'stock' => $stock,
                    'reserved' => 0,
                    'low_stock_threshold' => null,
                    'allow_preorder' => false,
                    'preorder_limit' => null,
                    'preorder_count' => 0,
                    'is_active' => true,
                    'position' => $position++,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
                $queued[$variantKey] = true;
            }
        }

        if ($rows === []) {
            return;
        }

        DB::table('product_variants')->insert($rows);

        // Seed the ledger so the opening balance is explainable.
        $opening = [];

        foreach ($rows as $row) {
            if ($row['stock'] === 0) {
                continue;
            }

            $variantId = DB::table('product_variants')
                ->where('product_id', $row['product_id'])
                ->where('variant_key', $row['variant_key'])
                ->value('id');

            $opening[] = [
                'product_variant_id' => $variantId,
                'product_id' => $row['product_id'],
                'variant_label' => null,
                'type' => 'initial',
                'quantity' => $row['stock'],
                'stock_after' => $row['stock'],
                'reserved_after' => 0,
                'unit_cost' => null,
                'order_id' => null,
                'order_item_id' => null,
                'user_id' => null,
                'note' => 'Migrated from product_sizes.stock',
                'created_at' => now(),
            ];
        }

        if ($opening !== []) {
            DB::table('stock_movements')->insert($opening);
        }
    }

    public function down(): void
    {
        // Data-only migration. product_variants is dropped by its own migration.
    }
};
