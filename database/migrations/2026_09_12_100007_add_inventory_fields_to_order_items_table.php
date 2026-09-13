<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Links an order line to the stock row it consumed, and gives it a per-line
 * state machine.
 *
 * inventory_state is what makes the status endpoint idempotent: a release only
 * runs when the line is still `reserved`, so clicking "Cancelled" twice cannot
 * hand back the same units twice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            if (! Schema::hasColumn('order_items', 'product_color_id')) {
                $table->unsignedBigInteger('product_color_id')->nullable()->after('product_variant_id');
                $table->index('product_color_id');
            }

            if (! Schema::hasColumn('order_items', 'is_preorder')) {
                $table->boolean('is_preorder')->default(false)->after('qty');
            }

            if (! Schema::hasColumn('order_items', 'inventory_state')) {
                $table->enum('inventory_state', ['none', 'reserved', 'committed', 'released', 'returned'])
                    ->default('none')
                    ->after('is_preorder');
                $table->index('inventory_state');
            }

            // product_variant_id used to reference the old attribute/value table.
            // Those ids mean something completely different now, so the original
            // value is archived here rather than thrown away.
            if (! Schema::hasColumn('order_items', 'legacy_product_variant_id')) {
                $table->unsignedBigInteger('legacy_product_variant_id')->nullable()->after('product_color_id');
            }
        });

        DB::statement(
            'UPDATE order_items
                SET legacy_product_variant_id = product_variant_id
              WHERE product_variant_id IS NOT NULL
                AND legacy_product_variant_id IS NULL'
        );

        // Any id that does not exist in the rebuilt table has to be cleared, or
        // adding the constraint below fails with errno 1452.
        DB::statement(
            'UPDATE order_items
                SET product_variant_id = NULL
              WHERE product_variant_id IS NOT NULL
                AND product_variant_id NOT IN (SELECT id FROM product_variants)'
        );

        // Re-point the column at the rebuilt table. nullOnDelete, not cascade:
        // removing a variant must never delete a paid order line.
        if (! $this->hasForeignKey('order_items', 'product_variant_id')) {
            Schema::table('order_items', function (Blueprint $table) {
                $table->foreign('product_variant_id', 'order_items_variant_fk')
                    ->references('id')->on('product_variants')
                    ->nullOnDelete();
            });
        }
    }

    private function hasForeignKey(string $table, string $column): bool
    {
        return DB::select(
            'SELECT 1
               FROM information_schema.KEY_COLUMN_USAGE
              WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = ?
                AND COLUMN_NAME = ?
                AND REFERENCED_TABLE_NAME IS NOT NULL
              LIMIT 1',
            [$table, $column]
        ) !== [];
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropForeign('order_items_variant_fk');
            $table->dropIndex(['product_color_id']);
            $table->dropIndex(['inventory_state']);
            $table->dropColumn([
                'product_color_id',
                'is_preorder',
                'inventory_state',
                'legacy_product_variant_id',
            ]);
        });
    }
};
