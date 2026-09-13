<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only ledger. Every change to stock or reserved writes exactly one row,
 * which is what makes "why is this 3 and not 5" answerable.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('stock_movements')) {
            return;
        }

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();

            // nullOnDelete so the audit trail outlives the variant it describes.
            $table->foreignId('product_variant_id')->nullable()
                ->constrained('product_variants')->nullOnDelete();
            $table->foreignId('product_id')->nullable()
                ->constrained('products')->nullOnDelete();

            // Snapshot such as "Maroon / M", kept so history stays readable.
            $table->string('variant_label')->nullable();

            $table->enum('type', [
                'initial',
                'purchase',
                'adjustment',
                'reserve',
                'release',
                'sale',
                'return',
                'damage',
            ]);

            $table->integer('quantity');        // signed: + in, - out
            $table->integer('stock_after');
            $table->integer('reserved_after');
            $table->decimal('unit_cost', 10, 2)->nullable();

            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignId('order_item_id')->nullable()->constrained('order_items')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('note')->nullable();

            // Rows are immutable, so there is no updated_at.
            $table->timestamp('created_at')->useCurrent();

            $table->index(['product_variant_id', 'created_at']);
            $table->index('order_id');
            $table->index(['type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
