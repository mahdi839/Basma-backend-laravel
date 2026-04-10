<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
         // Get all order items that have a colorImage but missing color_name
        $items = DB::table('order_items')
            ->whereNotNull('colorImage')
            ->where('colorImage', '!=', '')
            ->where(function ($q) {
                $q->whereNull('color_name')
                  ->orWhere('color_name', '');
            })
            ->whereNotNull('product_id')
            ->select('id', 'product_id', 'colorImage')
            ->get();

        foreach ($items as $item) {
            $product = DB::table('products')
                ->where('id', $item->product_id)
                ->value('colors');

            if (!$product) continue;

            $colors = json_decode($product, true);
            if (!is_array($colors)) continue;

            // Normalize the stored colorImage URL for comparison
            // colorImage may be stored as full URL (https://...) or partial path
            $storedImage = $item->colorImage;

            $matchedName = null;

            foreach ($colors as $color) {
                $colorImagePath = $color['image'] ?? '';

                // Try multiple matching strategies:
                // 1. Exact match
                // 2. stored URL ends with the color image path
                // 3. color image path is contained in the stored URL
                if (
                    $storedImage === $colorImagePath ||
                    str_ends_with($storedImage, $colorImagePath) ||
                    str_ends_with($colorImagePath, basename($storedImage))
                ) {
                    $matchedName = $color['name'];
                    break;
                }
            }

            if ($matchedName) {
                DB::table('order_items')
                    ->where('id', $item->id)
                    ->update(['color_name' => $matchedName]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
