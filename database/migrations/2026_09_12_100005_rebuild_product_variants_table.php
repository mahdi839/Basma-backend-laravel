<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rebuilds product_variants as the colour x size stock matrix.
 *
 * The previous table was attribute/value shaped (EAV) and cannot express a
 * combination. It is RENAMED to product_variants_legacy rather than dropped, so
 * no row is ever lost on a production database.
 */
return new class extends Migration
{
    public function up(): void
    {
        // order_items.product_variant_id points at the old table. Release the
        // constraint before renaming, then re-point it in a later migration.
        $this->dropForeignKeyIfExists('order_items', 'product_variant_id');
        $this->dropForeignKeyIfExists('product_stocks', 'product_variant_id');

        if (Schema::hasTable('product_variants') && ! Schema::hasTable('product_variants_legacy')) {
            Schema::rename('product_variants', 'product_variants_legacy');

            // InnoDB foreign key names are unique per DATABASE, not per table, so
            // the archived table still owns `product_variants_product_id_foreign`.
            // Leaving it would make the CREATE below fail with errno 121.
            $this->dropAllForeignKeys('product_variants_legacy');
        }

        if (Schema::hasTable('product_variants')) {
            return;
        }

        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();

            // Nullable: a product may have no colours and/or no sizes.
            $table->foreignId('product_color_id')->nullable()
                ->constrained('product_colors')->restrictOnDelete();
            $table->foreignId('size_id')->nullable()
                ->constrained('sizes')->restrictOnDelete();

            // MySQL treats every NULL as distinct, so a composite unique index on
            // (product_id, product_color_id, size_id) would silently allow
            // duplicates for one-size / no-colour products. This computed key is
            // the real guard: "{colour id or 0}-{size id or 0}".
            $table->string('variant_key', 40);

            $table->string('sku')->nullable();

            $table->unsignedInteger('price')->nullable();
            $table->decimal('purchase_price', 10, 2)->nullable();

            // Signed on purpose: a negative value surfaces an oversell bug
            // instead of throwing mid-transaction.
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

    /**
     * Drops a foreign key by column without needing doctrine/dbal, and without
     * failing when the constraint (or the table) is not there.
     */
    private function dropForeignKeyIfExists(string $table, string $column): void
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

    /** Releases every foreign key a table owns. Data is untouched. */
    private function dropAllForeignKeys(string $table): void
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

    public function down(): void
    {
        Schema::dropIfExists('product_variants');

        if (Schema::hasTable('product_variants_legacy')) {
            Schema::rename('product_variants_legacy', 'product_variants');
        }
    }
};
