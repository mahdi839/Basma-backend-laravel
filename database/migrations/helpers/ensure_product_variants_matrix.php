<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Makes sure `product_variants` is the colour×size matrix, not the old
 * attribute/value table.
 *
 * Safe to call more than once. The old table is renamed, never dropped.
 */
function eyara_ensure_product_variants_matrix(): void
{
    if (Schema::hasTable('product_variants') && Schema::hasColumn('product_variants', 'variant_key')) {
        eyara_null_orphaned_variant_ids();
        eyara_restore_inventory_foreign_keys();

        return;
    }

    // Anything pointing at the old table must be released before a rename.
    eyara_drop_foreign_keys_referencing('product_variants');
    eyara_drop_foreign_key_on('order_items', 'product_variant_id');
    eyara_drop_foreign_key_on('product_stocks', 'product_variant_id');
    eyara_drop_foreign_key_on('stock_movements', 'product_variant_id');

    if (Schema::hasTable('product_variants') && ! Schema::hasColumn('product_variants', 'variant_key')) {
        $archive = 'product_variants_legacy';
        if (Schema::hasTable($archive)) {
            $archive = 'product_variants_legacy_'.date('YmdHis');
        }

        Schema::rename('product_variants', $archive);
        eyara_drop_all_foreign_keys($archive);
    }

    if (! Schema::hasTable('product_variants')) {
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('product_color_id')->nullable()
                ->constrained('product_colors')->restrictOnDelete();
            $table->foreignId('size_id')->nullable()
                ->constrained('sizes')->restrictOnDelete();
            $table->string('variant_key', 40);
            $table->string('sku')->nullable();
            $table->unsignedInteger('price')->nullable();
            $table->decimal('purchase_price', 10, 2)->nullable();
            $table->integer('stock')->default(0);
            $table->integer('reserved')->default(0);
            $table->unsignedInteger('low_stock_threshold')->nullable();
            $table->boolean('allow_preorder')->default(false);
            $table->unsignedInteger('preorder_limit')->nullable();
            $table->unsignedInteger('preorder_count')->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['product_id', 'variant_key']);
            $table->unique('sku');
            $table->index(['product_id', 'is_active']);
            $table->index('size_id');
            $table->index('product_color_id');
        });
    }

    eyara_null_orphaned_variant_ids();
    eyara_restore_inventory_foreign_keys();
}

function eyara_null_orphaned_variant_ids(): void
{
    foreach (['stock_movements', 'order_items'] as $table) {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'product_variant_id')) {
            continue;
        }

        DB::statement(
            "UPDATE `{$table}`
                SET product_variant_id = NULL
              WHERE product_variant_id IS NOT NULL
                AND product_variant_id NOT IN (SELECT id FROM product_variants)"
        );
    }
}

function eyara_restore_inventory_foreign_keys(): void
{
    if (Schema::hasTable('order_items') && Schema::hasColumn('order_items', 'product_variant_id')) {
        if (! eyara_has_foreign_key('order_items', 'product_variant_id')) {
            Schema::table('order_items', function (Blueprint $table) {
                $table->foreign('product_variant_id', 'order_items_variant_fk')
                    ->references('id')->on('product_variants')
                    ->nullOnDelete();
            });
        }
    }

    if (Schema::hasTable('stock_movements') && Schema::hasColumn('stock_movements', 'product_variant_id')) {
        if (! eyara_has_foreign_key('stock_movements', 'product_variant_id')) {
            Schema::table('stock_movements', function (Blueprint $table) {
                $table->foreign('product_variant_id', 'stock_movements_variant_fk')
                    ->references('id')->on('product_variants')
                    ->nullOnDelete();
            });
        }
    }
}

function eyara_drop_foreign_keys_referencing(string $referencedTable): void
{
    if (! Schema::hasTable($referencedTable)) {
        return;
    }

    $constraints = DB::select(
        'SELECT TABLE_NAME AS tbl, CONSTRAINT_NAME AS name
           FROM information_schema.KEY_COLUMN_USAGE
          WHERE TABLE_SCHEMA = DATABASE()
            AND REFERENCED_TABLE_NAME = ?
            AND CONSTRAINT_NAME IS NOT NULL',
        [$referencedTable]
    );

    foreach ($constraints as $constraint) {
        DB::statement("ALTER TABLE `{$constraint->tbl}` DROP FOREIGN KEY `{$constraint->name}`");
    }
}

function eyara_drop_foreign_key_on(string $table, string $column): void
{
    if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
        return;
    }

    $constraints = DB::select(
        'SELECT CONSTRAINT_NAME AS name
           FROM information_schema.KEY_COLUMN_USAGE
          WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = ?
            AND COLUMN_NAME = ?
            AND REFERENCED_TABLE_NAME IS NOT NULL',
        [$table, $column]
    );

    foreach ($constraints as $constraint) {
        DB::statement("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$constraint->name}`");
    }
}

function eyara_drop_all_foreign_keys(string $table): void
{
    $constraints = DB::select(
        'SELECT DISTINCT CONSTRAINT_NAME AS name
           FROM information_schema.KEY_COLUMN_USAGE
          WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = ?
            AND REFERENCED_TABLE_NAME IS NOT NULL',
        [$table]
    );

    foreach ($constraints as $constraint) {
        DB::statement("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$constraint->name}`");
    }
}

function eyara_has_foreign_key(string $table, string $column): bool
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
