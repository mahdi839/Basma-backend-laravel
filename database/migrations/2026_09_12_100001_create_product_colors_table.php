<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Normalises products.colors (JSON) into a real table so inventory variants can
 * hold a foreign key to a colour.
 *
 * The products.colors JSON column is intentionally left in place and keeps being
 * written by ProductController. This table is an additional, FK-safe mirror.
 * Nothing reads less data than before.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('product_colors')) {
            Schema::create('product_colors', function (Blueprint $table) {
                $table->id();
                $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
                $table->string('name')->nullable();
                $table->string('code', 32)->nullable();
                $table->string('image')->nullable();
                $table->unsignedInteger('position')->default(0);

                // Mirrors products.colors[].id so existing storefront payloads,
                // and order_items rows that only stored an image path, can be matched.
                $table->unsignedInteger('legacy_json_id')->nullable();

                $table->timestamps();

                $table->index(['product_id', 'position']);
                $table->index(['product_id', 'legacy_json_id']);
            });
        }

        $this->backfillFromJson();
    }

    /**
     * Copy every existing colour out of the JSON column. Idempotent: a colour is
     * only inserted when the product has no row with the same legacy id.
     */
    private function backfillFromJson(): void
    {
        DB::table('products')
            ->select('id', 'colors')
            ->whereNotNull('colors')
            ->orderBy('id')
            ->chunk(200, function ($products) {
                $rows = [];

                foreach ($products as $product) {
                    $colors = json_decode((string) $product->colors, true);

                    if (! is_array($colors)) {
                        continue;
                    }

                    $existing = DB::table('product_colors')
                        ->where('product_id', $product->id)
                        ->pluck('legacy_json_id')
                        ->filter()
                        ->all();

                    foreach (array_values($colors) as $index => $color) {
                        if (! is_array($color)) {
                            continue;
                        }

                        $legacyId = isset($color['id']) ? (int) $color['id'] : $index + 1;

                        if (in_array($legacyId, $existing, true)) {
                            continue;
                        }

                        $rows[] = [
                            'product_id' => $product->id,
                            'name' => $color['name'] ?? null,
                            'code' => $color['code'] ?? null,
                            'image' => $color['image'] ?? null,
                            'position' => $index,
                            'legacy_json_id' => $legacyId,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }
                }

                if ($rows !== []) {
                    foreach (array_chunk($rows, 200) as $batch) {
                        DB::table('product_colors')->insert($batch);
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_colors');
    }
};
