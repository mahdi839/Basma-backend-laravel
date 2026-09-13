<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A category flagged as a "stock category" turns inventory tracking on for its
 * products and unlocks the colour/size availability filters on the storefront.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            if (! Schema::hasColumn('categories', 'track_inventory')) {
                $table->boolean('track_inventory')->default(false)->after('size_guide_type');
                $table->index('track_inventory', 'categories_track_inventory_index');
            }
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropIndex('categories_track_inventory_index');
            $table->dropColumn('track_inventory');
        });
    }
};
