<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tables', function (Blueprint $table) {
            // Products table indexes
            Schema::table('products', function (Blueprint $table) {
                $table->index('status');
                $table->index('created_at');
                $table->index(['status', 'created_at']);
                $table->fullText('title'); // For search
            });

            // Categories table indexes
            Schema::table('categories', function (Blueprint $table) {
                $table->index('home_category');
                $table->index(['home_category', 'priority']);
            });

            // Product sizes table indexes
            Schema::table('product_sizes', function (Blueprint $table) {
                $table->index('price');
            });

            // Orders table indexes
            Schema::table('orders', function (Blueprint $table) {
                $table->index('phone');
                $table->index('status');
                $table->index('district');
                $table->index('created_at');
                $table->index(['user_id', 'created_at']);
                $table->index(['status', 'created_at']);
            });

            // Order items table indexes
            Schema::table('order_items', function (Blueprint $table) {
                $table->index('product_id');
                $table->index('title');
                $table->index('selected_size');
            });

            // Category product pivot table
            Schema::table('category_product', function (Blueprint $table) {
                $table->index('product_id');
            });
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['created_at']);
            $table->dropIndex(['status', 'created_at']);
            $table->dropFullText(['title']);
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropIndex(['home_category']);
            $table->dropIndex(['home_category', 'priority']);
        });

        Schema::table('product_sizes', function (Blueprint $table) {
            $table->dropIndex(['price']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['phone']);
            $table->dropIndex(['status']);
            $table->dropIndex(['district']);
            $table->dropIndex(['created_at']);
            $table->dropIndex(['user_id', 'created_at']);
            $table->dropIndex(['status', 'created_at']);
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropIndex(['product_id']);
            $table->dropIndex(['title']);
            $table->dropIndex(['selected_size']);
        });

        Schema::table('category_product', function (Blueprint $table) {
            $table->dropIndex(['product_id']);
        });
    }
};
