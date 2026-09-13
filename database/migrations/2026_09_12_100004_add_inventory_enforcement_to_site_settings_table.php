<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Global kill switch. While this is false, shortfalls are logged but a sale is
 * never blocked — the safe way to run the first week in production.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('site_settings', 'inventory_enforcement_enabled')) {
                $table->boolean('inventory_enforcement_enabled')->default(false)->after('primary_color');
            }

            if (! Schema::hasColumn('site_settings', 'low_stock_threshold')) {
                $table->unsignedInteger('low_stock_threshold')->default(3)->after('inventory_enforcement_enabled');
            }
        });
    }

    public function down(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->dropColumn(['inventory_enforcement_enabled', 'low_stock_threshold']);
        });
    }
};
