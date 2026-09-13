<?php

/**
 * Post-migration sanity check. Confirms the new schema landed, nothing legacy
 * was lost, and the reserve -> commit -> restore cycle actually moves numbers.
 *
 *   php scripts/verify-inventory.php
 *
 * The transition test runs inside a transaction that is always rolled back, so
 * it never leaves data behind.
 */

require __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Services\InventoryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$pass = 0;
$fail = 0;

function check(string $label, bool $ok, string $detail = ''): void
{
    global $pass, $fail;

    $ok ? $pass++ : $fail++;
    echo ($ok ? '  OK   ' : '  FAIL ').str_pad($label, 52).$detail."\n";
}

echo "\n== Schema ==\n";

foreach (['product_colors', 'product_variants', 'stock_movements', 'product_variants_legacy'] as $table) {
    check("table {$table}", Schema::hasTable($table));
}

foreach (['track_inventory', 'preorder_mode', 'preorder_eta_days', 'low_stock_threshold'] as $column) {
    check("products.{$column}", Schema::hasColumn('products', $column));
}

check('categories.track_inventory', Schema::hasColumn('categories', 'track_inventory'));
check('site_settings.inventory_enforcement_enabled', Schema::hasColumn('site_settings', 'inventory_enforcement_enabled'));

foreach (['product_color_id', 'is_preorder', 'inventory_state', 'legacy_product_variant_id'] as $column) {
    check("order_items.{$column}", Schema::hasColumn('order_items', $column));
}

echo "\n== Nothing lost ==\n";

check('products.colors still present', Schema::hasColumn('products', 'colors'));
check('product_sizes.stock still present', Schema::hasColumn('product_sizes', 'stock'));
check('product_stocks table still present', Schema::hasTable('product_stocks'));

$orphans = DB::table('order_items')
    ->whereNotNull('product_variant_id')
    ->whereNotIn('product_variant_id', fn ($q) => $q->select('id')->from('product_variants'))
    ->count();
check('no orphaned order_items variants', $orphans === 0, "found {$orphans}");

echo "\n== Guards ==\n";

// nullOnDelete, not cascade: deleting a variant must never delete an order line.
$rule = DB::selectOne(
    "SELECT DELETE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS
      WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'order_items_variant_fk'"
);
check('order_items FK is SET NULL', ($rule->DELETE_RULE ?? '') === 'SET NULL', $rule->DELETE_RULE ?? 'missing');

check(
    'variant_key uniqueness enforced',
    DB::select(
        "SELECT 1 FROM information_schema.STATISTICS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'product_variants'
            AND INDEX_NAME = 'product_variants_product_id_variant_key_unique' LIMIT 1"
    ) !== []
);

check('variant_key generated on save', ProductVariant::makeKey(4, null) === '4-0');

echo "\n== Counters ==\n";

$product = Product::query()->first();

if (! $product) {
    echo "  SKIP  no products in this database\n";
} else {
    DB::beginTransaction();

    try {
        $inventory = app(InventoryService::class);

        $variant = ProductVariant::firstOrCreate(
            ['product_id' => $product->id, 'product_color_id' => null, 'size_id' => null],
            ['stock' => 0, 'reserved' => 0]
        );

        $inventory->stockIn($variant->id, 10, 100.0, 'verify script');
        $variant->refresh();
        check('stockIn adds 10', $variant->stock === 10 && $variant->available === 10, "stock={$variant->stock}");

        $order = Order::query()->first();

        if (! $order) {
            echo "  SKIP  no orders to test the lifecycle with\n";
        } else {
            $item = OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $product->id,
                'product_variant_id' => $variant->id,
                'title' => 'verify script line',
                'colorImage' => '',
                'selected_size' => null,
                'qty' => 3,
                'unitPrice' => 100,
                'totalPrice' => 300,
                'inventory_state' => OrderItem::INV_NONE,
            ]);

            // Placing an order holds stock without deducting it.
            $inventory->reserve($item);
            $variant->refresh();
            check(
                'reserve holds without deducting',
                $variant->stock === 10 && $variant->reserved === 3 && $variant->available === 7,
                "stock={$variant->stock} reserved={$variant->reserved}"
            );

            // This is the moment the user asked for: Order Confirmed deducts.
            $inventory->applyOrderStatus($order->id, 'order_confirmed');
            $variant->refresh();
            check(
                'order_confirmed deducts stock',
                $variant->stock === 7 && $variant->reserved === 0,
                "stock={$variant->stock} reserved={$variant->reserved}"
            );

            // Re-sending the same status must not deduct twice.
            $inventory->applyOrderStatus($order->id, 'order_confirmed');
            $variant->refresh();
            check('re-confirming is idempotent', $variant->stock === 7, "stock={$variant->stock}");

            // Cancelling hands the units back.
            $inventory->applyOrderStatus($order->id, 'cancelled');
            $variant->refresh();
            check(
                'cancelled restores stock',
                $variant->stock === 10 && $variant->reserved === 0,
                "stock={$variant->stock} reserved={$variant->reserved}"
            );

            // And cancelling twice must not inflate it.
            $inventory->applyOrderStatus($order->id, 'cancelled');
            $variant->refresh();
            check('re-cancelling is idempotent', $variant->stock === 10, "stock={$variant->stock}");
        }

        check(
            'ledger recorded the movements',
            StockMovement::where('product_variant_id', $variant->id)->count() >= 1
        );
    } catch (Throwable $e) {
        check('counter cycle', false, get_class($e).': '.$e->getMessage());
    } finally {
        DB::rollBack();
    }
}

echo "\n".str_repeat('-', 68)."\n";
echo "passed: {$pass}   failed: {$fail}\n";

exit($fail === 0 ? 0 : 1);
