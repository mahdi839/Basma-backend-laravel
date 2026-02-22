<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('banners', function (Blueprint $table) {

            // make type nullable + default hero
            $table->enum('type', ['hero','slot','category'])
                  ->nullable()
                  ->default('hero')
                  ->change();

            // already nullable but ensure safe behavior
            $table->foreignId('category_id')
                  ->nullable()
                  ->change();

            $table->foreignId('products_slots_id')
                  ->nullable()
                  ->change();
        });
    }

    public function down(): void
    {
        Schema::table('banners', function (Blueprint $table) {

            $table->enum('type', ['hero','slot','category'])
                  ->nullable(false)
                  ->default(null)
                  ->change();
        });
    }
};