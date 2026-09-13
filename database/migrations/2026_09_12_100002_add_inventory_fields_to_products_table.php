<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the per-product inventory switch and pre-order settings.
 *
 * track_inventory defaults to false so every existing product keeps behaving
 * exactly as it does today until it is explicitly switched on.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'track_inventory')) {
                $table->boolean('track_inventory')->default(false)->after('status');
            }

            if (! Schema::hasColumn('products', 'preorder_mode')) {
                $table->enum('preorder_mode', ['off', 'always', 'when_out_of_stock'])
                    ->default('off')
                    ->after('track_inventory');
            }

            if (! Schema::hasColumn('products', 'preorder_eta_days')) {
                $table->unsignedSmallInteger('preorder_eta_days')->nullable()->after('preorder_mode');
            }

            if (! Schema::hasColumn('products', 'preorder_note')) {
                $table->string('preorder_note')->nullable()->after('preorder_eta_days');
            }

            if (! Schema::hasColumn('products', 'low_stock_threshold')) {
                $table->unsignedInteger('low_stock_threshold')->nullable()->after('preorder_note');
            }
        });

        if (! $this->hasIndex('products', 'products_track_inventory_status_index')) {
            Schema::table('products', function (Blueprint $table) {
                $table->index(['track_inventory', 'status'], 'products_track_inventory_status_index');
            });
        }
    }

    private function hasIndex(string $table, string $index): bool
    {
        return DB::select(
            'SELECT 1 FROM information_schema.STATISTICS
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?
              LIMIT 1',
            [$table, $index]
        ) !== [];
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('products_track_inventory_status_index');
            $table->dropColumn([
                'track_inventory',
                'preorder_mode',
                'preorder_eta_days',
                'preorder_note',
                'low_stock_threshold',
            ]);
        });
    }
};
